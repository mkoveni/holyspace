import type { QuickAction } from './quickActions'
import { navigationIcon, visibleNavigation } from './workspace'

export interface PaletteEntry {
  id: string
  title: string
  subtitle: string
  icon: string
  to: string
  kind: 'Action' | 'Page' | 'Person'
}

export function pageEntries (
  hasPermission: (permission: string) => boolean,
): PaletteEntry[] {
  return visibleNavigation(hasPermission).flatMap(group =>
    group.items.map(item => ({
      id: `page:${item.to}`,
      title: item.title,
      subtitle: group.title,
      icon: navigationIcon(item.to),
      to: item.to,
      kind: 'Page' as const,
    })),
  )
}

export function actionEntries (actions: QuickAction[]): PaletteEntry[] {
  return actions.map(action => ({
    id: `action:${action.to}`,
    title: action.title,
    subtitle: action.description,
    icon: action.icon,
    to: action.to,
    kind: 'Action' as const,
  }))
}

/** Rank entries: prefix matches first, then substring matches; empty query keeps order. */
export function filterEntries (entries: PaletteEntry[], query: string, limit = 12): PaletteEntry[] {
  const needle = query.trim().toLowerCase()
  if (!needle) {
    return entries.slice(0, limit)
  }
  const scored = entries.flatMap(entry => {
    const title = entry.title.toLowerCase()
    const haystack = `${title} ${entry.subtitle.toLowerCase()}`
    if (title.startsWith(needle)) {
      return [{ entry, score: 0 }]
    }
    if (title.includes(needle)) {
      return [{ entry, score: 1 }]
    }
    if (haystack.includes(needle)) {
      return [{ entry, score: 2 }]
    }
    return []
  })
  // eslint-disable-next-line unicorn/no-array-sort
  return scored.sort((a, b) => a.score - b.score).slice(0, limit).map(item => item.entry)
}
