import { describe, expect, it } from 'vitest'
import { planningRecords } from '../src/workflows/volunteerPlanning'

const shifts = [{ id: 's1', occurrenceId: 'event1' }, { id: 's2', occurrenceId: 'event2' }]
const assignments = [{ id: 'a1', shiftId: 's1' }, { id: 'a2', shiftId: 's2' }]

describe('event volunteer planning', () => {
  it('shows only shifts and assignments for the selected occurrence', () => {
    expect(planningRecords('shifts', shifts, shifts, 'event1')).toEqual([shifts[0]])
    expect(planningRecords('assignments', assignments, shifts, 'event1')).toEqual([assignments[0]])
    expect(planningRecords('assignments', assignments, shifts, 'empty')).toEqual([])
  })
  it('keeps profiles global and restores all records when cleared', () => {
    const profiles = [{ id: 'person1' }]
    expect(planningRecords('profiles', profiles, shifts, 'event1')).toEqual(profiles)
    expect(planningRecords('shifts', shifts, shifts, null)).toEqual(shifts)
    expect(planningRecords('assignments', assignments, shifts, null)).toEqual(assignments)
  })
})
