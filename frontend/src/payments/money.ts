/** Convert an ordinary ZAR decimal to cents without floating-point rounding. */
export function zarMinorAmount (value: string, minimum = 500): number | null {
  if (!/^\d{1,7}(?:\.\d{1,2})?$/.test(value.trim())) {
    return null
  }
  const [whole, fraction = ''] = value.trim().split('.')
  const result = Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
  return result >= minimum && result <= 100_000_000 ? result : null
}
