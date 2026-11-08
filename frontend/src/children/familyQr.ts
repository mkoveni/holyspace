export type FamilyQr = { childId: string } | { pickupCode: string }
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
export function parseFamilyQr (
  raw: string,
  kind: 'arrival' | 'collection',
  resourceId: string,
): FamilyQr {
  const parts = raw.trim().split(':')
  if (
    parts.length !== 5
    || parts[0] !== 'divinecommunityspace'
    || parts[1] !== `child-${kind}`
    || parts[2] !== 'v1'
    || !uuid.test(parts[3] || '')
    || parts[3] !== resourceId
  ) {
    throw new Error(
      'This is not a family QR for the selected session or attendance. Open the correct record first.',
    )
  }
  const value = parts[4] || ''
  if (kind === 'arrival') {
    if (!uuid.test(value)) {
      throw new Error('The check-in reference is invalid.')
    }
    return { childId: value }
  }
  if (!/^[\w-]{22}$/.test(value)) {
    throw new Error('The collection label is invalid.')
  }
  return { pickupCode: value }
}
