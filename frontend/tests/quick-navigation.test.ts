import { describe, expect, it, vi } from 'vitest'
import { ministryNavigation } from '../src/children/navigation'
import { visibleQuickActions } from '../src/navigation/quickActions'
import { visibleNavigation } from '../src/navigation/workspace'

describe('quick actions and ministry destinations', () => {
  it('requires every permission needed by a creation workflow', () => {
    const readOnly = new Set([
      'people.read',
      'children.read',
    ])
    expect(
      visibleQuickActions(permission => readOnly.has(permission)),
    ).toEqual([])
    const manager = new Set(['people.read', 'people.manage'])
    expect(
      visibleQuickActions(permission => manager.has(permission)).map(
        action => action.title,
      ),
    ).toEqual(['Add person', 'Add household'])
    expect(
      visibleQuickActions(
        permission => permission === 'children.station',
      ).map(action => action.to),
    ).toEqual(['/children-station'])
  })
  it('omits empty ministry sections without losing incident-only or station-only access', () => {
    expect(ministryNavigation(() => false)).toEqual([])
    const incidents = ministryNavigation(
      permission => permission === 'children.incidents.record',
    )
    expect(incidents.map(group => group.title)).toEqual(['Safeguarding'])
    expect(
      incidents.flatMap(group => group.items).map(item => item.key),
    ).toEqual(['incidents'])
    const station = ministryNavigation(
      permission => permission === 'children.station',
    )
    expect(
      station.flatMap(group => group.items).map(item => item.key),
    ).toEqual(['station'])
  })
  it('keeps every existing destination reachable exactly once', () => {
    const items = ministryNavigation(() => true).flatMap(group => group.items)
    expect(items).toHaveLength(12)
    expect(new Set(items.map(item => item.to)).size).toBe(12)
    expect(items.map(item => item.key)).toContain('delivery')
  })
})

it('keeps Java serving destinations reachable with their own permissions', () => {
  const reader = visibleNavigation(permission => permission === 'volunteering.read').flatMap(group => group.items)
  expect(reader.map(item => item.to)).toContain('/volunteering')
  expect(reader.map(item => item.to)).toContain('/volunteering/schedule')
  const volunteer = visibleNavigation(permission => permission === 'volunteering.serve').flatMap(group => group.items)
  expect(volunteer.map(item => item.to)).toContain('/serve')
})

it('separates ministry destinations from volunteering and keeps every related page reachable', () => {
  const groups = visibleNavigation(() => true)
  const children = groups.find(group => group.title === 'Children Ministry')!
  const volunteering = groups.find(group => group.title === 'Volunteering')!
  expect(children.items.map(item => item.to)).toEqual(ministryNavigation(() => true).flatMap(group => group.items.map(item => item.to)))
  expect(volunteering.items.map(item => item.to)).toEqual(['/volunteering', '/volunteering/teams', '/volunteering/schedule', '/volunteering/deliveries', '/serve'])
  expect(groups.some(group => group.title === 'Serving & children')).toBe(false)
})

it('offers Giving creation only to Giving managers', () => {
  expect(visibleQuickActions(permission => permission === 'giving.manage').map(action => action.to)).toContain('/giving?action=create')
  expect(visibleQuickActions(permission => permission === 'giving.read').map(action => action.to)).not.toContain('/giving?action=create')
  expect(visibleNavigation(permission => permission === 'giving.read').flatMap(group => group.items).map(item => item.to)).toContain('/giving')
})

it('keeps Giving pledges, statements and reconciliation reachable to finance readers', () => {
  const routes = visibleNavigation(permission => permission === 'giving.read').flatMap(group => group.items).map(item => item.to)
  expect(routes).toContain('/giving/pledges')
  expect(routes).toContain('/giving/reports')
  expect(routes).toContain('/giving/reconciliations')
})
