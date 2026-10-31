import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import { useApi } from '@/composables/useApi'
import { useWorkflowsStore } from '@/stores/workflows'
import { copyFormValue, fieldPayload } from '@/workflows/forms'
import { amountMinor } from '@/workflows/money'

vi.mock('@/composables/useApi', () => ({ useApi: vi.fn(), extractErrorMessage: (error: Error) => error.message }))
beforeEach(() => setActivePinia(createPinia()))

it('converts currency amounts and allocations exactly, including currencies with different precision', () => {
  expect(amountMinor('123.45', 'ZAR')).toBe(12_345)
  expect(amountMinor('123', 'JPY')).toBe(123)
  expect(amountMinor('1.234', 'KWD')).toBe(1234)
  expect(() => amountMinor('1.234', 'ZAR')).toThrow()
  expect(() => amountMinor('0', 'ZAR')).toThrow()
  expect(() => amountMinor('1e3', 'ZAR')).toThrow()
  expect(fieldPayload([{ key: 'amountMinor', label: 'Amount', type: 'money' }, { key: 'allocations', label: 'Funds', type: 'allocations' }], { currency: 'ZAR', amountMinor: '10.50', allocations: [{ fundId: 'fund', amount: '10.50' }] })).toEqual({ amountMinor: 1050, allocations: [{ fundId: 'fund', amountMinor: 1050 }] })
})

it('preserves optional text but normalises nullable references and local times', () => {
  expect(fieldPayload([{ key: 'subject', label: 'Subject' }, { key: 'donorId', label: 'Donor', nullable: true }, { key: 'startsAt', label: 'Start', type: 'datetime' }], { subject: '', donorId: '', startsAt: '2030-01-06T09:00:00Z' })).toEqual({ subject: '', donorId: null, startsAt: '2030-01-06T09:00:00.000Z' })
})

it('edits reactive collections without mutating the loaded roster', () => {
  const record = reactive({ qualifications: ['first aid'], availability: [{ startsAt: '2030-01-06T09:00:00Z' }] })
  const value = copyFormValue(record.availability)
  value[0]!.startsAt = 'changed'
  expect(record.availability[0]!.startsAt).toBe('2030-01-06T09:00:00Z')
  expect(copyFormValue(record.qualifications)).toEqual(['first aid'])
})

it('releases saving after rejection, sends updates, and clears private records on logout', async () => {
  const request = vi.fn().mockRejectedValueOnce(new Error('Class is full.')).mockResolvedValue({ data: { id: 'entry' } })
  const get = vi.fn().mockResolvedValue({ data: { entries: [{ id: 'entry' }] } })
  vi.mocked(useApi).mockReturnValue({ request, get } as unknown as ReturnType<typeof useApi>)
  const store = useWorkflowsStore()
  await expect(store.save('/api/children/enrolments', {})).rejects.toThrow('Class is full.')
  expect(store.isSaving).toBe(false)
  await store.save('/api/attendance/entries/entry', { status: 'present', reason: 'Correction' }, 'PUT')
  expect(request).toHaveBeenLastCalledWith({ url: '/api/attendance/entries/entry', method: 'PUT', data: { status: 'present', reason: 'Correction' } })
  await store.load('attendance', { personId: 'person' })
  expect(get).toHaveBeenCalledWith('/api/attendance', { params: { personId: 'person' } })
  store.reset()
  expect(store.data).toEqual({})
})

it('sends the loaded record version with edits and actions', async () => {
  const request = vi.fn().mockResolvedValue({ data: {} })
  vi.mocked(useApi).mockReturnValue({ request } as unknown as ReturnType<typeof useApi>)
  await useWorkflowsStore().save('/api/ministries/memberships/member/role', { role: 'leader' }, 'POST', 2)
  expect(request).toHaveBeenCalledWith({ url: '/api/ministries/memberships/member/role', method: 'POST', data: { role: 'leader' }, headers: { 'If-Match': '"2"' } })
})

it('requests audit history using a bounded entry cursor', async () => {
  const get = vi.fn().mockResolvedValue({ data: { entries: [], nextBefore: null } })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  await useWorkflowsStore().audit('giving', 'gift', 10)
  expect(get).toHaveBeenCalledWith('/api/audit/giving/gift', { params: { limit: 25, before: 10 } })
})

it('loads field history and preserves the cursor', async () => {
  const page = { entries: [{ id: 20, changes: { '/name': { fromPresent: true, from: 'Old', toPresent: true, to: 'New' } } }], nextBefore: 20 }
  const get = vi.fn().mockResolvedValue({ data: page })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  expect(await useWorkflowsStore().audit('giving', 'record', 30)).toEqual(page)
  expect(get).toHaveBeenCalledWith('/api/audit/giving/record', { params: { limit: 25, before: 30 } })
})

it('uses legacy history only when field history is explicitly unsupported', async () => {
  const legacy = { entries: [], nextBefore: null }
  const get = vi.fn().mockRejectedValueOnce({ isAxiosError: true, response: { status: 501 } }).mockResolvedValueOnce({ data: legacy })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  expect(await useWorkflowsStore().audit('giving', 'record')).toEqual(legacy)
  expect(get.mock.calls[1]?.[0]).toBe('/api/workflows/giving/record/audit')
  get.mockReset().mockRejectedValue({ isAxiosError: true, response: { status: 403 } })
  await expect(useWorkflowsStore().audit('giving', 'record')).rejects.toThrow()
  expect(get).toHaveBeenCalledTimes(1)
})
