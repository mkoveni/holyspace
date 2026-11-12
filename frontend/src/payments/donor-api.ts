import type { Checkout, Provider } from './api'
import { useApi } from '@/composables/useApi'
export interface OnlineFund {
  id: string
  name: string
  description: string
  active: boolean
  enabled: boolean
  version: number
}
export interface DonorOrder {
  id: string
  fundId: string
  amountMinor: number
  provider: string
  mode: string
  recurring: boolean
  status: string
  subscriptionStatus: string
  version: number
  createdAt: string
}
export interface OwnGift {
  id: string
  amountMinor: number
  currency: string
  receivedOn: string
  method: string
  reversed: boolean
  refundedMinor: number
}
export interface MyGiving {
  donor: { id: string, name: string } | null
  funds: OnlineFund[]
  providers: Provider[]
  payments: { items: DonorOrder[], total: number }
  gifts: OwnGift[]
  giftTotal: number
}
export interface DonorDetail {
  order: DonorOrder
  charges: {
    id: string
    amountMinor: number
    refundedMinor: number
    receivedOn: string
    givingStatus: string
    confirmationStatus: string
    captured: boolean
    confirmation?: { subject: string, html: string } | null
  }[]
  refunds: { amountMinor: number, refundedOn: string, applied: boolean }[]
}
export interface DonorLink {
  accountId: string
  accountName: string
  personId: string
  personName: string
  active: boolean
  version: number
}
export const donorApi = {
  overview: async (paymentOffset = 0, giftOffset = 0) =>
    (
      await useApi().get<MyGiving>('/api/my-giving', {
        params: { limit: 25, paymentOffset, giftOffset },
      })
    ).data,
  detail: async (id: string) =>
    (await useApi().get<DonorDetail>(`/api/my-giving/payments/${id}`)).data,
  start: async (body: object) =>
    (await useApi().post<{ id: string }>('/api/my-giving/payments', body)).data,
  checkout: async (id: string) =>
    (await useApi().post<Checkout>(`/api/my-giving/payments/${id}/checkout`))
      .data,
  subscription: async (id: string, expectedVersion: number, cancel: boolean) =>
    useApi().post(`/api/my-giving/payments/${id}/subscription`, {
      expectedVersion,
      cancel,
    }),
  access: async (offset = 0) =>
    (
      await useApi().get<{
        links: DonorLink[]
        funds: OnlineFund[]
        hasMore: boolean
      }>('/api/giving/donor-access', { params: { offset, limit: 25 } })
    ).data,
  link: async (account: string, body: object) =>
    useApi().put(`/api/giving/donor-access/${account}`, body),
  /** The online-giving catalogue alone: needs giving.manage only (the donor-access list also needs identity.manage). */
  onlineFunds: async () =>
    (
      await useApi().get<{ funds: OnlineFund[], truncated: boolean }>(
        '/api/giving/online-funds',
      )
    ).data,
  fund: async (fund: OnlineFund) =>
    useApi().put(`/api/giving/online-funds/${fund.id}`, {
      enabled: !fund.enabled,
      expectedVersion: fund.version,
    }),
}
