import { describe, expect, it } from 'vitest'
import {
  dateTimeLabel,
  localDateTimeValue,
  parseLocalDateTime,
} from '../src/components/common/date-time/value'

describe('local date and time values', () => {
  it('round trips local calendar values without converting to UTC', () => {
    const date = new Date(2028, 1, 29, 0, 7)
    expect(localDateTimeValue(date)).toBe('2028-02-29T00:07')
    expect(parseLocalDateTime('2028-02-29T00:07')?.getTime()).toBe(
      date.getTime(),
    )
  })
  it.each([
    '',
    '2027-02-29T09:00',
    '2028-04-31T09:00',
    '2028-01-01T24:00',
    '2028-01-01T09:60',
    '2028-01-01T09:00Z',
    '2028-01-01',
  ])('rejects invalid or non-local values: %s', value => {
    expect(parseLocalDateTime(value)).toBeNull()
  })
  it('formats valid values and preserves invalid input for validation', () => {
    expect(dateTimeLabel('2028-02-29T14:37')).toBe(
      new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
      }).format(new Date(2028, 1, 29, 14, 37)),
    )
    expect(dateTimeLabel('invalid')).toBe('invalid')
    expect(dateTimeLabel('')).toBe('')
  })
})
