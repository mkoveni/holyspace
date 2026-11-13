import { routeMatches, visibleNavigation } from './workspace'

export interface AreaItem {
  title: string
  to: string
}

export interface NavigationArea {
  key: string
  title: string
  icon: string
  /** Where the sidebar link goes: the first page the person may open. */
  to: string
  /** Sibling pages, shown as tabs at the top of every page in the area. */
  items: AreaItem[]
}

const areaDefinitions = [
  { key: 'people', title: 'People', icon: 'mdi-account-group-outline', groups: ['People & households', 'Groups & ministries'] },
  { key: 'events', title: 'Events & serving', icon: 'mdi-calendar-star', groups: ['Events', 'Volunteering'] },
  { key: 'children', title: 'Children', icon: 'mdi-sprout-outline', groups: ['Children Ministry'] },
  { key: 'giving', title: 'Giving', icon: 'mdi-gift-outline', groups: ['Giving'] },
  { key: 'communications', title: 'Communications', icon: 'mdi-email-outline', groups: ['Engagement & reports'] },
  { key: 'admin', title: 'Admin & settings', icon: 'mdi-shield-account-outline', groups: ['Accounts & access', 'My account'] },
] as const

/** Collapse the detailed navigation into a handful of top-level areas. */
export function visibleAreas (
  hasPermission: (permission: string) => boolean,
): NavigationArea[] {
  const groups = visibleNavigation(hasPermission)
  return areaDefinitions.flatMap(definition => {
    const items = groups
      .filter(group => (definition.groups as readonly string[]).includes(group.title))
      .flatMap(group => group.items.map(item => ({ title: item.title, to: item.to })))
    const first = items[0]
    return first
      ? [{ key: definition.key, title: definition.title, icon: definition.icon, to: first.to, items }]
      : []
  })
}

/** The best-matching page in an area for a path (longest destination wins). */
export function activeAreaItem (area: NavigationArea, path: string): string {
  return area.items
    .filter(item => routeMatches(path, item.to))
    .filter(item =>
      item.to !== '/children'
      || path === '/children'
      || /^\/children\/(?:programmes|participants)\//.test(path))
    .reduce((best, item) => (item.to.length > best.length ? item.to : best), '')
}
