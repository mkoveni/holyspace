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
}

export { priceToMinor } from './fees'
