import { useApi } from '@/composables/useApi'

export interface StaffTicketType {
  id: string
  name: string
  description: string
  priceMinor: number
  quantityCap: number | null
  sortOrder: number
  active: boolean
  version: number
}
export interface PublicSales {
  enabled: boolean
  opensAt: string | null
  closesAt: string | null
  maxPerOrder: number
  version: number
  ticketTypes: StaffTicketType[]
}
export interface StaffTicketOrder {
  id: string
  status: string
  seats: number
  totalMinor: number
  createdAt: string
  buyerName: string | null
  buyerEmail: string | null
}

export interface StaffTicket {
  id: string
  type: string
  priceMinor: number
  holder: string
  status: 'ISSUED' | 'CHECKED_IN' | 'VOID'
  credited: boolean
}
export interface StaffOrderDetail {
  id: string
  status: string
  seats: number
  subtotalMinor: number
  feeMinor: number
  totalMinor: number
  refundWaitingMinor: number
  buyerName: string | null
  buyerEmail: string | null
  invoiceNumber: string | null
  creditNotes: { number: string, totalMinor: number }[]
  tickets: StaffTicket[]
}
export interface ScanCounts { issued: number, checkedIn: number, void: number }
export interface ScanOutcome {
  result: 'ADMITTED' | 'ALREADY_USED' | 'REJECTED'
  reason: string | null
  ticketType: string | null
  holder: string | null
  checkedInAt: string | null
  counts: ScanCounts
}
export interface TicketReport {
  types: { name: string, sold: number, checkedIn: number, void: number, grossMinor: number }[]
  orders: number
  paidOrders: number
  ticketsMinor: number
  feesMinor: number
  refundedMinor: number
  checkedIn: number
  awaitingRefundReview: number
  scan: ScanCounts
}
export interface InvoiceSettings {
  id: string
  effectiveFrom: string
  legalName: string
  address: string
  registrationNumber: string
  npoNumber: string
  vatNumber: string
  vatRegistered: boolean
  vatRateBp: number
}

const base = (occurrenceId: string) => `/api/events/occurrences/${occurrenceId}`

export const ticketStaffApi = {
  sales: async (occurrenceId: string) => (await useApi().get<PublicSales>(`${base(occurrenceId)}/public-sales`)).data,
  configure: async (occurrenceId: string, body: { expectedVersion: number, enabled: boolean, opensAt: string | null, closesAt: string | null, maxPerOrder: number }) => {
    await useApi().put(`${base(occurrenceId)}/public-sales`, body)
  },
  saveType: async (occurrenceId: string, type: Omit<StaffTicketType, 'id' | 'version'> & { id?: string, version?: number }) => {
    const body = { ...type, expectedVersion: type.version ?? 0 }
    await (type.id
      ? useApi().put(`${base(occurrenceId)}/ticket-types/${type.id}`, body)
      : useApi().post(`${base(occurrenceId)}/ticket-types`, body))
  },
  orders: async (occurrenceId: string) =>
    (await useApi().get<{ orders: StaffTicketOrder[] }>(`${base(occurrenceId)}/ticket-orders`)).data.orders,
  order: async (occurrenceId: string, orderId: string) =>
    (await useApi().get<StaffOrderDetail>(`${base(occurrenceId)}/ticket-orders/${orderId}`)).data,
  voidTickets: async (occurrenceId: string, orderId: string, ticketIds: string[]) =>
    (await useApi().post<{ matched: boolean, refundWaitingMinor: number }>(`${base(occurrenceId)}/ticket-orders/${orderId}/voids`, { ticketIds })).data,
  resend: async (occurrenceId: string, orderId: string) => {
    await useApi().post(`${base(occurrenceId)}/ticket-orders/${orderId}/resend`)
  },
  scan: async (occurrenceId: string, code: string) =>
    (await useApi().post<ScanOutcome>(`${base(occurrenceId)}/tickets/scan`, { code })).data,
  report: async (occurrenceId: string) => (await useApi().get<TicketReport>(`${base(occurrenceId)}/ticket-report`)).data,
  invoiceSettings: async () => (await useApi().get<{ settings: InvoiceSettings[] }>('/api/invoice-settings')).data.settings,
  changeInvoiceSettings: async (body: Omit<InvoiceSettings, 'id'>) => {
    await useApi().put('/api/invoice-settings', body)
  },
}

export { priceToMinor } from './fees'
