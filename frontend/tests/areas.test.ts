import { describe, expect, it } from 'vitest'
import { activeAreaItem, visibleAreas } from '../src/navigation/areas'

describe('navigation areas', () => {
  it('collapses everything into a few areas and loses no destination', () => {
    const areas = visibleAreas(() => true)
    expect(areas.length).toBeLessThanOrEqual(6)
    expect(areas.map(area => area.key)).toEqual(['people', 'events', 'children', 'giving', 'communications', 'admin'])
    const paths = areas.flatMap(area => area.items.map(item => item.to))
    expect(paths).toContain('/giving/reconciliations')
    expect(paths).toContain('/children-station')
    expect(new Set(paths).size).toBe(paths.length)
  })
  it('hides areas the person cannot use and links to the first permitted page', () => {
    expect(visibleAreas(() => false).map(area => area.key)).toEqual(['admin'])
    const giving = visibleAreas(permission => permission === 'giving.read').find(area => area.key === 'giving')!
    expect(giving.to).toBe('/giving')
  })
  it('finds the active tab for nested routes', () => {
    const giving = visibleAreas(() => true).find(area => area.key === 'giving')!
    expect(activeAreaItem(giving, '/giving/pledges')).toBe('/giving/pledges')
    expect(activeAreaItem(giving, '/giving')).toBe('/giving')
  })
})
