import type { Checkout } from './api'
import { apiUrl } from '@/composables/useApi'
import { hostedCheckout } from './checkout'

export interface PublicGivingOptions {
  funds: { id: string, name: string, description: string }[]
  providers: { id: string, name: string }[]
  minimumAmountMinor: number
}
export interface PublicCheckout {
  id: string
  accessToken: string | null
  checkout: Checkout | null
}
export type GuestState = 'AWAITING_PAYMENT' | 'PAID' | 'FAILED' | 'TEST_PAYMENT'
export interface GuestStatus {
  id: string
  state: GuestState
  amountMinor: number
  confirmationQueued: boolean
}

const MINIMUM_MINOR = 500
const MAXIMUM_MINOR = 100_000_000

/** Rands typed by a person ("250", "250.5", "250,50") to exact integer cents; null when it is not a valid amount. */
export function randsToMinor (value: string): number | null {
  const text = value.trim().replace(',', '.')
  if (!/^\d{1,7}(?:\.\d{1,2})?$/.test(text)) {
    return null
  }
  const [whole, fraction = ''] = text.split('.')
  const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
  return minor >= MINIMUM_MINOR && minor <= MAXIMUM_MINOR ? minor : null
}

export function formatRand (minor: number): string {
  return `R${(minor / 100).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

export const tokenKey = (orderId: string) => `public-giving:${orderId}`

async function failure (response: Response, fallback: string): Promise<Error> {
  const body = await response.json().catch(() => ({})) as { message?: string }
  return new Error(body.message ?? fallback)
}

async function send<T> (path: string, init: RequestInit, fallback: string): Promise<T> {
  let response: Response
  try {
    response = await fetch(apiUrl(path), { cache: 'no-store', credentials: 'omit', ...init })
  } catch {
    throw new Error('Unable to reach the church right now. Please try again.')
  }
  if (!response.ok) {
    throw await failure(response, fallback)
  }
  return await response.json() as T
}

function json (body: unknown): RequestInit {
  return {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  }
}

export const publicGiving = {
  options: () => send<PublicGivingOptions>('/api/public/giving', {}, 'Online giving is unavailable right now.'),
  start: (body: {
    requestId: string
    fundId: string
    amountMinor: number
    provider: string
    email: string
    name: string
    humanToken: string | null
    website: string
  }) => send<PublicCheckout>('/api/public/giving/checkouts', json(body), 'We could not start your gift. Please try again.'),
  status: (accessToken: string) =>
    send<GuestStatus>('/api/public/giving/orders/status', json({ accessToken }), 'We could not check your gift.'),
}

/** Sends the giver to the provider's hosted page; card details are only ever entered there. */
export function launchCheckout (checkout: Checkout): void {
  const target = hostedCheckout(checkout)
  if (!target.post) {
    window.location.assign(target.url)
    return
  }
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = target.url
  for (const [name, value] of Object.entries(checkout.fields)) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = value
    form.append(input)
  }
  document.body.append(form)
  form.submit()
  form.remove()
}
