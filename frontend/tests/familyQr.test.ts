import { describe, expect, it } from 'vitest'
import { parseFamilyQr } from '../src/children/familyQr'
const session = '11111111-1111-4111-8111-111111111111',
  child = '22222222-2222-4222-8222-222222222222',
  code = 'a'.repeat(22)
describe('Family QR references', () => {
  it('scopes arrivals to their selected session', () =>
    expect(
      parseFamilyQr(
        `holyspace:child-arrival:v1:${session}:${child}`,
        'arrival',
        session,
      ),
    ).toEqual({ childId: child }))
  it('reads a collection label only for the selected attendance', () =>
    expect(
      parseFamilyQr(
        `holyspace:child-collection:v1:${session}:${code}`,
        'collection',
        session,
      ),
    ).toEqual({ pickupCode: code }))
  it('rejects event, wrong attendance, malformed and mixed-purpose codes', () => {
    for (const raw of [
      `holyspace:event:v1:${code}`,
      `holyspace:child-collection:v1:${child}:${code}`,
      `holyspace:child-arrival:v1:${session}:${child}`,
      `holyspace:child-collection:v1:${session}:invalid`,
    ]) {
      expect(() => parseFamilyQr(raw, 'collection', session)).toThrow()
    }
  })
})
