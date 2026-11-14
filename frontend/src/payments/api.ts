import { useApi } from '@/composables/useApi'
export interface Provider {
  id: string
  name: string
  available: boolean
  mode: string
  recurring: boolean
}
export interface Payment {
  id: string
  donorId: string
  donorName: string
  fundId: string
  amountMinor: number
  provider: string
  mode: string
  recurring: boolean
  status: string
  givingStatus?: string
  subscriptionStatus: string
  reviewReason: string | null
  version: number
  createdAt: string
}
export interface Charge {
  id: string
  reference: string
  amountMinor: number
  receivedOn: string
  giftId: string | null
}
export interface Review {
  version: number
  settlementReference: string
  feeMinor: number
  bankMinor: number
  refundedMinor: number
  note: string
}
export interface ChargeView {
  charge: Charge
  refundedMinor: number
  settlement: Review | null
  needsReview: boolean
  givingStatus?: string
  settlementStatus?: string
}
export interface Refund {
  id: string
  chargeId: string | null
  reference: string
  amountMinor: number
  refundedOn: string
  applied: boolean
  reason: string
}
export interface Detail {
  order: Payment
  charges: ChargeView[]
  refunds: Refund[]
  history: { action: string, note: string, occurredAt: string }[]
}
export interface Checkout {
  id: string
  url: string
  fields: Record<string, string>
}
export interface ReceiptReview {
  contacts: { id: string, value: string }[]
  preview: {
    hash: string
    recipient: string
    subject: string
    html: string
    templateVersion: number
  } | null
  receiptId: string | null
  delivery: {
    id: string
    status: string
    captured: boolean
    version: number
    errorCode: string
  } | null
}
export const paymentsApi = {
  retryPosting: async (id: string, expectedVersion: number) =>
    (
      await useApi().post<{ complete: boolean, message: string }>(
        `/api/payments/${id}/posting-retries`,
        { expectedVersion },
      )
    ).data,
  receipt: async (id: string, chargeId: string, contactId: string | null) =>
    (
      await useApi().get<ReceiptReview>(`/api/payments/${id}/receipts`, {
        params: { chargeId, ...(contactId ? { contactId } : {}) },
      })
    ).data,
  approveReceipt: async (id: string, body: object) =>
    useApi().post(`/api/payments/${id}/receipts`, body),
  retryReceipt: async (id: string, receiptId: string, body: object) =>
    useApi().post(`/api/payments/${id}/receipts/${receiptId}/retries`, body),
  providers: async () =>
    (await useApi().get<Provider[]>('/api/payments/providers')).data,
  list: async (offset: number, reviewOnly: boolean) =>
    (
      await useApi().get<{ items: Payment[], total: number }>('/api/payments', {
        params: { offset, limit: 25, reviewOnly },
      })
    ).data,
  detail: async (id: string) =>
    (await useApi().get<Detail>(`/api/payments/${id}`)).data,
  start: async (body: object) =>
    (await useApi().post<{ id: string }>('/api/payments', body)).data,
  checkout: async (id: string) =>
    (await useApi().post<Checkout>(`/api/payments/${id}/checkout`)).data,
  refund: async (id: string, body: object) =>
    useApi().post(`/api/payments/${id}/refunds`, body),
  review: async (id: string, body: object) =>
    useApi().post(`/api/payments/${id}/reviews`, body),
}
