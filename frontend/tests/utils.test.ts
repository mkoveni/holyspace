import { describe, expect, it } from 'vitest'
import { filterEntries } from '../src/navigation/commandPalette'
import { toCsv } from '../src/utils/csv'
import { formatDate, initialsOf } from '../src/utils/format'
import { email, minLength, required } from '../src/utils/rules'

describe('csv export', () => {
  it('quotes commas and quotes, and neutralises spreadsheet formulas', () => {
    const csv = toCsv([{ name: 'Smith, Ann', note: '=SUM(A1)' }, { name: 'Say "hi"', note: null }], [
      { header: 'Name', value: row => row.name },
      { header: 'Note', value: row => row.note },
    ])
    expect(csv).toBe('Name,Note\r\n"Smith, Ann",\'=SUM(A1)\r\n"Say ""hi""",')
  })
})

describe('format and rules', () => {
  it('formats dates safely and builds initials', () => {
    expect(formatDate(null)).toBe('—')
    expect(formatDate('not a date')).toBe('—')
    expect(initialsOf('ada lovelace byron')).toBe('AL')
    expect(initialsOf('', 'X')).toBe('X')
  })
  it('validates required, email and length', () => {
    expect(required('  ')).toBe('This field is required.')
    expect(required(['a'])).toBe(true)
    expect(email('nope')).toBe('Enter a valid email address.')
    expect(email('a@b.co')).toBe(true)
    expect(minLength(12)('short')).toBe('Use at least 12 characters.')
  })
})

describe('command palette ranking', () => {
  const entries = ['Giving', 'Online giving', 'People'].map(title => ({ id: title, title, subtitle: 'x', icon: '', to: '/', kind: 'Page' as const }))
  it('puts prefix matches before substring matches', () => {
    expect(filterEntries(entries, 'giv').map(entry => entry.title)).toEqual(['Giving', 'Online giving'])
    expect(filterEntries(entries, 'zzz')).toEqual([])
  })
})
