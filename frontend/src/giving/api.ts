import { useApi } from '@/composables/useApi'
export interface Fund {
  id: string
  name: string
  description: string
  active: boolean
  version: number
}
export interface GiftSummary {
  id: string
  donorId: string | null
  donorName: string
  amountMinor: number
  currency: string
  receivedOn: string
  method: string
  externalReference: string | null
  reversed: boolean
  reconciled: boolean
  refundedMinor?: number
}
export interface GiftDetail {
  refundedMinor: number
  gift: {
    id: string
    donorId: string | null
    money: { amountMinor: number, currency: string }
    receivedOn: string
    method: string
    externalReference: string | null
    allocations: { fundId: string, amountMinor: number }[]
    createdBy: string
    createdAt: string
  }
  donorName: string
  fundNames: Record<string, string>
  reversal: {
    id: string
    reversedOn: string
    reason: string
    createdBy: string
    createdAt: string
  } | null
  history: {
    action: string
    reason: string
    actorId: string
    occurredAt: string
  }[]
}
export interface Total {
  currency: string
  grossMinor: string
  reversedMinor: string
  netMinor: string
}
export interface Filter {
  from: string
  to: string
  currency?: string
  fundId?: string
  donorId?: string
  limit?: number
  offset?: number
}
export interface Pledge {
  id: string
  donorId: string
  fundId: string
  target: { amountMinor: number, currency: string }
  startsOn: string
  endsOn: string
  status: string
  version: number
  cancellationReason: string | null
}
export interface PledgeSummary {
  pledge: Pledge
  donorName: string
  fundName: string
  creditedMinor: number
  remainingMinor: number
  fulfilled: boolean
}
export interface Assignment {
  assignment: {
    id: string
    giftId: string
    amountMinor: number
    status: string
    version: number
    cancellationReason: string | null
  }
  receivedOn: string
  giftReversed: boolean
}
export interface PledgeDetail {
  summary: PledgeSummary
  assignments: Assignment[]
  history: GiftDetail['history']
}
export interface PledgeCandidate {
  giftId: string
  receivedOn: string
  availableMinor: number
  reference: string | null
  method: string
}
export interface Acknowledgement {
  id: string
  giftId: string
  number: string
  donorName: string
  issuedOn: string
  receivedOn: string
  amountMinor: number
  currency: string
  method: string
  reference: string | null
  voided: boolean
  reversedOn: string | null
  reversalReason: string | null
  lines: { fundId: string, fundName: string, amountMinor: number }[]
}
export interface LedgerLine {
  giftId: string
  kind: string
  date: string
  donorId: string | null
  donorName: string
  fundId: string
  fundName: string
  currency: string
  amountMinor: number
  method: string
  reference: string | null
  reason: string
}
export interface GivingReport {
  donorId: string | null
  donorName: string
  from: string
  to: string
  generatedAt: string
  lines: LedgerLine[]
  totals: Total[]
}
export interface ReconciliationReview {
  id: string
  version: number
  outcome: string
  note: string
  reversedMinor: number
  createdBy: string
  createdAt: string
}
export interface ReconciliationSummary {
  batch: {
    id: string
    reference: string
    expectedMinor: number
    actualMinor: number
    currency: string
    reconciledOn: string
    reason: string
    giftIds: string[]
  }
  reversedMinor: number
  currentNetMinor: number
  version: number
  review: ReconciliationReview | null
  needsReview: boolean
}
export interface ReconciliationDetail {
  summary: ReconciliationSummary
  items: {
    giftId: string
    donorName: string
    receivedOn: string
    amountMinor: number
    reversed: boolean
  }[]
  reviews: ReconciliationReview[]
}
const root = '/api/giving'
export const givingApi = {
  async funds (params: object) {
    return (
      await useApi().get<{ items: Fund[], total: number }>(root + '/funds', {
        params,
      })
    ).data
  },
  async createFund (body: object) {
    return (await useApi().post<{ id: string }>(root + '/funds', body)).data
  },
  async updateFund (id: string, body: object) {
    await useApi().put(root + '/funds/' + id, body)
  },
  async gifts (params: Filter) {
    return (
      await useApi().get<{ items: GiftSummary[], total: number }>(
        root + '/gifts',
        { params },
      )
    ).data
  },
  async totals (params: Filter) {
    return (await useApi().get<Total[]>(root + '/totals', { params })).data
  },
  async gift (id: string) {
    return (await useApi().get<GiftDetail>(root + '/gifts/' + id)).data
  },
  async record (body: object) {
    return (await useApi().post<{ id: string }>(root + '/gifts', body)).data
  },
  async reverse (id: string, body: object) {
    return (
      await useApi().post<{ id: string }>(
        root + '/gifts/' + id + '/reversals',
        body,
      )
    ).data
  },

  async pledges (params: object) {
    return (
      await useApi().get<{ items: PledgeSummary[], total: number }>(
        root + '/pledges',
        { params },
      )
    ).data
  },
  async pledge (id: string, params: object = {}) {
    return (
      await useApi().get<PledgeDetail>(root + '/pledges/' + id, { params })
    ).data
  },
  async candidates (id: string, params: object = {}) {
    return (
      await useApi().get<PledgeCandidate[]>(
        root + '/pledges/' + id + '/candidates',
        { params },
      )
    ).data
  },
  async createPledge (body: object) {
    return (await useApi().post<{ id: string }>(root + '/pledges', body)).data
  },
  async cancelPledge (id: string, body: object) {
    await useApi().post(root + '/pledges/' + id + '/cancel', body)
  },
  async assign (id: string, body: object) {
    return (
      await useApi().post<{ id: string }>(
        root + '/pledges/' + id + '/assignments',
        body,
      )
    ).data
  },
  async cancelAssignment (id: string, body: object) {
    await useApi().post(root + '/pledge-assignments/' + id + '/cancel', body)
  },
  async statement (id: string, from: string, to: string) {
    return (
      await useApi().get<GivingReport>(root + '/statements/' + id, {
        params: { from, to },
      })
    ).data
  },
  async ledgerCsv (params: Filter) {
    try {
      return (
        await useApi().get<Blob>(root + '/ledger.csv', {
          params,
          responseType: 'blob',
        })
      ).data
    } catch (error) {
      const failure = error as { response?: { data?: unknown } }
      if (failure.response?.data instanceof Blob) {
        try {
          failure.response.data = JSON.parse(await failure.response.data.text())
        } catch {
          /* Preserve the original response if it is not JSON. */
        }
      }
      throw error
    }
  },
  async acknowledgement (id: string) {
    return (
      await useApi().get<Acknowledgement>(root + '/acknowledgements/' + id)
    ).data
  },
  async giftAcknowledgement (id: string) {
    return (
      await useApi().get<{ acknowledgement: Acknowledgement | null }>(
        root + '/gifts/' + id + '/acknowledgement',
      )
    ).data.acknowledgement
  },
  async issueAcknowledgement (id: string, body: object) {
    return (
      await useApi().post<{ id: string }>(
        root + '/gifts/' + id + '/acknowledgements',
        body,
      )
    ).data
  },
  async reconciliations (params: object) {
    return (
      await useApi().get<{ items: ReconciliationSummary[], total: number }>(
        root + '/reconciliations',
        { params },
      )
    ).data
  },
  async reconciliation (id: string) {
    return (
      await useApi().get<ReconciliationDetail>(root + '/reconciliations/' + id)
    ).data
  },
  async reconcile (body: object) {
    return (
      await useApi().post<{ id: string }>(root + '/reconciliations', body)
    ).data
  },
  async reviewReconciliation (id: string, body: object) {
    return (
      await useApi().post<{ id: string }>(
        root + '/reconciliations/' + id + '/reviews',
        body,
      )
    ).data
  },
}
