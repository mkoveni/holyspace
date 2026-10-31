import { describe, expect, it } from 'vitest'
import { routeMatches, visibleNavigation } from '../src/navigation/workspace'

describe('permission-aware workspace navigation', () => {
  it('omits empty groups and all restricted items for an account without staff access', () => {
    const navigation = visibleNavigation(() => false)
    expect(navigation.map(group => group.title)).toEqual(['My account'])
    expect(navigation.flatMap(group => group.items).every(item => !item.permission)).toBe(true)
  })
  it('shows only permitted items in a partially accessible group', () => {
    const navigation = visibleNavigation(permission => permission === 'children.station')
    expect(navigation.find(group => group.title === 'Children Ministry')?.items.map(item => item.to)).toEqual(['/children-station'])
    expect(navigation.some(group => group.title === 'Accounts & access')).toBe(false)
  })
  it('matches child routes without matching unrelated prefixes', () => {
    expect(routeMatches('/people/123', '/people')).toBe(true)
    expect(routeMatches('/people-extra', '/people')).toBe(false)
    expect(routeMatches('/events', '/')).toBe(false)
    expect(routeMatches('/', '/')).toBe(true)
  })
})
