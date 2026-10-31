import { beforeEach, expect, it, vi } from 'vitest'
import axios from 'axios'
import { directoryApi } from '@/directory/api'
import { dateLabel, churchToday } from '@/directory/presentation'
import { useTask } from '@/directory/useTask'
import type { PersonSummary } from '@/directory/types'
const client = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn() }))
vi.mock('@/composables/useApi', () => ({ useApi: () => client, extractErrorMessage: (error: Error) => error.message }))
const person: PersonSummary = { id: 'person', version: 7, archivedAt: null, details: { titleCode: null, firstName: 'Solly', lastName: 'Mabasa', dateOfBirth: null, dateOfDeath: null, gender: 'UNKNOWN', lifeStatus: 'UNKNOWN' }, membershipType: 'VISITOR', ageCategory: 'UNKNOWN', ageAsOf: '2026-10-25' }
beforeEach(() => { vi.clearAllMocks(); client.get.mockResolvedValue({ data: [] }); client.put.mockResolvedValue({}); client.post.mockResolvedValue({ data: { id: 'created' } }) })
it('sends a server-side date/age query and cancellation signal rather than filtering one page locally', async () => {
  const controller = new AbortController()
  await directoryApi.people({ ageCategory: 'CHILD', asOf: '2026-10-25', offset: 25 }, controller.signal)
  expect(client.get).toHaveBeenCalledWith('/api/people', { params: { limit: 25, offset: 25, ageCategory: 'CHILD', asOf: '2026-10-25' }, signal: controller.signal })
})
it('uses nested Java person details and the captured version for profile edits', async () => {
  await directoryApi.savePerson(person.details, 'TRANSFERRED', person)
  expect(client.put).toHaveBeenCalledWith('/api/people/person', { person: person.details, membershipType: 'TRANSFERRED', expectedVersion: 7 })
})
it('uses canonical contact endpoints and the person revision', async () => {
  await directoryApi.addContact(person, { kind: 'EMAIL', value: 'family@example.org', primary: true })
  await directoryApi.endContact(person, 'contact')
  expect(client.post).toHaveBeenNthCalledWith(1, '/api/people/person/contacts', { kind: 'EMAIL', value: 'family@example.org', primary: true, expectedVersion: 7 })
  expect(client.post).toHaveBeenNthCalledWith(2, '/api/people/person/contacts/contact/end', { expectedVersion: 7 })
})
it('sets household contact by membership identity, with the household revision', async () => {
  await directoryApi.setContact({ id: 'household', version: 4, name: 'Home', primaryContactMembershipId: null, createdAt: '', updatedAt: '' }, 'membership')
  expect(client.put).toHaveBeenCalledWith('/api/households/household/primary-contact', { expectedVersion: 4, membershipId: 'membership' })
})
it('blocks repeated submissions after conflict until the caller explicitly clears it', async () => {
  const task = useTask()
  const operation = vi.fn().mockRejectedValue(new axios.AxiosError('Conflict', '409', undefined, undefined, { status: 409 } as never))
  await task.run(operation); await task.run(operation)
  expect(task.conflict.value).toBe(true); expect(operation).toHaveBeenCalledTimes(1)
  task.clear(); await task.run(operation); expect(operation).toHaveBeenCalledTimes(2)
})
it('prevents duplicate submissions while a save is pending', async () => {
  const task = useTask(); let resolve!: () => void
  const operation = vi.fn(() => new Promise<void>(done => { resolve = done }))
  const first = task.run(operation); await task.run(operation)
  expect(operation).toHaveBeenCalledTimes(1); resolve(); await first; expect(task.busy.value).toBe(false)
})
it('formats date-only values without a timezone shift and uses the configured church date', () => {
  expect(dateLabel('2008-10-26')).toContain('26')
  expect(churchToday('Africa/Johannesburg')).toMatch(/^\d{4}-\d{2}-\d{2}$/)
})
