/** Shared Vuetify validation rules, so every form words its errors the same way. */
export function required (value: unknown) {
  return (Array.isArray(value) ? value.length > 0 : value !== null && value !== undefined && String(value).trim() !== '')
    || 'This field is required.'
}

export function email (value: string) {
  return !value || /^[^\s@]+@[^\s@][^\s.@]*\.[^\s@]+$/.test(value) || 'Enter a valid email address.'
}

export function minLength (length: number) {
  return (value: string) =>
    !value || value.length >= length || `Use at least ${length} characters.`
}

export function maxLength (length: number) {
  return (value: string) =>
    !value || value.length <= length || `Use at most ${length} characters.`
}
