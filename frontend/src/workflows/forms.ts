import type { WorkflowField } from '@/types/workflow'
import { toRaw } from 'vue'
import { amountMinor } from './money'

export function localInput (value: string): string {
  if (!value) {
    return ''
  }
  const date = new Date(value)
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
}
export function fieldPayload (fields: WorkflowField[], values: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(fields.map(field => {
    let value = values[field.key]
    switch (field.type) {
      case 'datetime': {
        value = value ? new Date(String(value)).toISOString() : null
        break
      }
      case 'intervals': {
        value = (value as { startsAt: string, endsAt: string }[]).map(window => ({ startsAt: new Date(window.startsAt).toISOString(), endsAt: new Date(window.endsAt).toISOString() }))
        break
      }
      case 'money': {
        value = amountMinor(value, String(values.currency))
        break
      }
      case 'allocations': {
        value = (value as { fundId: string, amount: string }[]).map(allocation => ({ fundId: allocation.fundId, amountMinor: amountMinor(allocation.amount, String(values.currency)) }))
        break
      }
      case 'number': {
        value = Number(value)
        break
      }
      default: { if (value === '' && field.nullable) {
        value = null
      }
      }
    }
    return [field.key, value]
  }))
}

export function copyFormValue<T> (value: T): T {
  return structuredClone(toRaw(value))
}
