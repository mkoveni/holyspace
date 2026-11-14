import type { TicketFees } from './fees'
import { apiUrl } from '@/composables/useApi'
import { hostedCheckout } from '@/payments/checkout'

export { totalFor } from './fees'
export type { TicketFees } from './fees'
export { formatRand } from '@/payments/giving-amount'

export interface PublicEventSummary {
  occurrenceId: string
  title: string
  startsAt: string
  endsAt: string
  timeZone: string
  venueName: string
  coverImageUrl: string | null
  bannerImageUrl: string | null
  fromPriceMinor: number | null
  available: boolean
}
export interface PublicTicketType {
  id: string
  name: string
  description: string
  priceMinor: number
  available: boolean
}
export interface PublicEventDetail extends PublicEventSummary {
  description: string
  venueAddress: string
  maxPerOrder: number
  ticketTypes: PublicTicketType[]
  fees: TicketFees
}
export interface StartedOrder {
  id: string
  accessToken: string | null
  status: string
  totalMinor: number
  checkout: { id: string, url: string } | null
}
export type OrderState = 'AWAITING_PAYMENT' | 'PAID' | 'EXPIRED' | 'TEST_PAYMENT' | 'REFUNDED' | 'IN_REVIEW'
export interface OrderStatus {
  id: string
  state: OrderState
  totalMinor: number
  seats: number
  walletToken: string | null
}
export interface WalletTicket {
  id: string
  type: string
  holder: string
  status: 'ISSUED' | 'CHECKED_IN' | 'VOID'
  code: string | null
}
export interface Wallet {
  orderId: string
  state: OrderState
  event: { title: string, startsAt: string, endsAt: string, timeZone: string, venueName: string, venueAddress: string }
  subtotalMinor: number
  feeMinor: number
  totalMinor: number
  tickets: WalletTicket[]
}

export const tokenKey = (orderId: string) => `public-tickets:${orderId}`

async function send<T> (path: string, init: RequestInit, fallback: string): Promise<T> {
  let response: Response
  try {
    response = await fetch(apiUrl(path), { cache: 'no-store', credentials: 'omit', ...init })
  } catch {
    throw new Error('Unable to reach the church right now. Please try again.')
  }
  if (!response.ok) {
    const body = await response.json().catch(() => ({})) as { message?: string }
    throw new Error(body.message ?? fallback)
  }
  return await response.json() as T
}

function json (body: unknown): RequestInit {
  return { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
}

export const publicTickets = {
  events: async () => (await send<{ events: PublicEventSummary[] }>('/api/public/events', {}, 'Tickets are unavailable right now.')).events,
  event: (id: string) => send<PublicEventDetail>(`/api/public/events/${id}`, {}, 'This event is not on sale.'),
  order: (id: string, body: {
    requestId: string
    quantities: Record<string, number>
    name: string
    email: string
    humanToken: string | null
    website: string
  }) => send<StartedOrder>(`/api/public/events/${id}/orders`, json(body), 'We could not start your order. Please try again.'),
  status: (token: string) => send<OrderStatus>('/api/public/ticket-orders/status', json({ token }), 'We could not check your order.'),
  wallet: (token: string) => send<Wallet>('/api/public/ticket-orders/wallet', json({ token }), 'We could not find these tickets.'),
}

/** Sends the buyer to the provider's hosted page; card details are only ever entered there. */
export function goToCheckout (checkout: { id: string, url: string }): void {
  window.location.assign(hostedCheckout({ ...checkout, fields: {} }).url)
}

export function formatWhen (iso: string, timeZone: string): string {
  return new Date(iso).toLocaleString('en-ZA', { timeZone, dateStyle: 'full', timeStyle: 'short' })
}
