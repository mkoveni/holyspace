import type { PersonProfile } from './types'
import { directoryApi } from './api'
/** Bound parallel requests while resolving relationship/member names from the API. */
export async function personReferences (ids: string[], signal: AbortSignal) {
  const unique = [...new Set(ids)]
  const result: Record<string, PersonProfile> = {}
  let cursor = 0
  await Promise.all(
    Array.from({ length: Math.min(4, unique.length) }, async () => {
      while (cursor < unique.length && !signal.aborted) {
        const id = unique[cursor++]
        if (id) {
          result[id] = await directoryApi.person(id, signal)
        }
      }
    }),
  )
  return result
}
