import axios from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { childrenApi } from '@/children/api'
import {
  dayAfter,
  documentHash,
  instant,
  lastDay,
  safeLink,
  sessionRange,
} from '@/children/presentation'
import { useTask } from '@/directory/useTask'
const client = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn() }))
vi.mock('@/composables/useApi', () => ({
  useApi: () => client,
  extractErrorMessage: (error: Error) => error.message,
}))
beforeEach(() => {
  vi.clearAllMocks()
  client.get.mockResolvedValue({ data: [] })
  client.post.mockResolvedValue({ data: {} })
  client.put.mockResolvedValue({ data: {} })
})
it('discovers station sessions without calling directory-wide session endpoints', async () => {
  await childrenApi.sessions(
    '2026-10-26T00:00:00Z',
    '2026-10-27T00:00:00Z',
    25,
    true,
  )
  expect(client.get).toHaveBeenCalledWith('/api/children/station/sessions', {
    params: {
      from: '2026-10-26T00:00:00Z',
      until: '2026-10-27T00:00:00Z',
      limit: 25,
      offset: 25,
    },
    signal: undefined,
  })
  await childrenApi.session('session', true)
  expect(client.get).toHaveBeenLastCalledWith(
    '/api/children/station/sessions/session',
    { params: undefined, signal: undefined },
  )
})
it('flattens named station attendance without requiring People directory access', async () => {
  client.get.mockResolvedValueOnce({
    data: [
      {
        attendance: { id: 'attendance', childId: 'child', version: 4 },
        childName: 'Lindiwe Mabasa',
      },
    ],
  })
  expect(await childrenApi.roster('session', 100)).toEqual([
    {
      id: 'attendance',
      childId: 'child',
      version: 4,
      childName: 'Lindiwe Mabasa',
    },
  ])
  expect(client.get).toHaveBeenCalledWith(
    '/api/children/station/sessions/session/attendance',
    { params: { limit: 100, offset: 100, openOnly: true }, signal: undefined },
  )
})
it('uses caller request identities and exact optimistic versions for attendance writes', async () => {
  await childrenApi.checkIn({
    sessionId: 'session',
    childId: 'child',
    requestId: 'arrival-request',
  })
  await childrenApi.checkout({
    attendanceId: 'attendance',
    expectedVersion: 7,
    requestId: 'checkout-request',
    collectorId: 'adult',
    identityVerified: true,
    pickupCode: 'private-code',
  })
  expect(client.post).toHaveBeenNthCalledWith(1, '/api/children/attendance', {
    sessionId: 'session',
    childId: 'child',
    requestId: 'arrival-request',
  })
  expect(client.post).toHaveBeenNthCalledWith(
    2,
    '/api/children/attendance/checkout',
    {
      attendanceId: 'attendance',
      expectedVersion: 7,
      requestId: 'checkout-request',
      collectorId: 'adult',
      identityVerified: true,
      pickupCode: 'private-code',
    },
  )
})
it('accesses care as an audited command scoped to the selected session', async () => {
  await childrenApi.support('child', 'session')
  expect(client.post).toHaveBeenCalledWith('/api/children/support/access', {
    childId: 'child',
    sessionId: 'session',
  })
  expect(client.get).not.toHaveBeenCalled()
})
it('converts inclusive form dates without shifting leap days or year boundaries', () => {
  expect(dayAfter('2024-02-28')).toBe('2024-02-29')
  expect(lastDay('2024-03-01')).toBe('2024-02-29')
  expect(dayAfter('2026-12-31')).toBe('2027-01-01')
  const range = sessionRange('2026-10-26')
  expect(new Date(range.from).getDate()).toBe(26)
  expect(new Date(range.until).getDate()).toBe(27)
  expect(() => instant('')).toThrow('Choose a valid date and time.')
})
it('only makes http and https evidence references clickable', () => {
  expect(safeLink('javascript:alert(1)')).toBeUndefined()
  expect(safeLink('/private/reference')).toBeUndefined()
  expect(safeLink('https://church.org/consent')).toBe(
    'https://church.org/consent',
  )
})
it('fingerprints the actual policy document instead of accepting a fabricated hash', async () => {
  expect(await documentHash(new File(['abc'], 'consent.txt'))).toBe(
    'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
  )
})
it('keeps ministry validation reasons visible while preventing blind conflict retries', async () => {
  const task = useTask({ preserveConflictMessage: true })
  const failure = new axios.AxiosError(
    'Current guardian consent is required.',
    '409',
    undefined,
    undefined,
    { status: 409 } as never,
  )
  const operation = vi.fn().mockRejectedValue(failure)
  await task.run(operation)
  await task.run(operation)
  expect(task.error.value).toBe('Current guardian consent is required.')
  expect(task.conflict.value).toBe(true)
  expect(operation).toHaveBeenCalledTimes(1)
})
