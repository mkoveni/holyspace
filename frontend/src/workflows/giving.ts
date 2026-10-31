import type { WorkflowDefinition } from '@/types/workflow'
export const givingDefinition: WorkflowDefinition = {
  context: 'giving', title: 'Giving', subtitle: 'Record donations, acknowledge gifts, reconcile, and report by fund and currency',
  filters: [
    { key: 'from', label: 'Totals from', type: 'date' },
    { key: 'to', label: 'Totals through', type: 'date' },
    { key: 'fundId', label: 'Fund', type: 'select', options: 'giving.funds' },
  ],
  sections: [
    { key: 'funds', title: 'Funds', createPath: '/api/giving/funds', fields: [{ key: 'name', label: 'Fund name', required: true }] },
    { key: 'gifts', title: 'Donations', createPath: '/api/giving/gifts', fields: [
      { key: 'amountMinor', label: 'Amount', type: 'money', required: true },
      { key: 'currency', label: 'Currency code', default: 'ZAR', required: true },
      { key: 'allocations', label: 'Fund allocations', type: 'allocations' },
      { key: 'receivedOn', label: 'Received on', type: 'date', required: true },
      { key: 'method', label: 'Method', type: 'select', options: ['cash', 'bank', 'other'].map(value => ({ title: value, value })), default: 'cash', required: true },
      { key: 'donorId', nullable: true, label: 'Donor (leave blank for anonymous)', type: 'select', options: 'people' },
      { key: 'occurrenceId', nullable: true, label: 'Event occurrence (optional)', type: 'select', options: 'occurrences' },
      { key: 'externalReference', nullable: true, label: 'Bank or source reference (optional)' },
      { key: 'idempotencyKey', label: 'Request identity', type: 'hidden', default: () => crypto.randomUUID() },
    ], columns: [{ title: 'Donor', key: 'donorId' }, { title: 'Amount', key: 'amountMinor' }, { title: 'Currency', key: 'currency' }, { title: 'Received', key: 'receivedOn' }, { title: 'Status', key: 'status' }], actions: [{ label: 'Issue acknowledgement', path: () => '/api/giving/receipts', payload: record => ({ giftId: record.id }), available: record => record.status === 'posted' }] },
    { key: 'receipts', title: 'Acknowledgements', fields: [], columns: [{ title: 'Number', key: 'receiptNumber' }, { title: 'Amount', key: 'amountMinor' }, { title: 'Currency', key: 'currency' }, { title: 'Issued', key: 'issuedOn' }, { title: 'Void', key: 'void' }] },
    { key: 'reversals', title: 'Reversals', createPath: '/api/giving/reversals', fields: [
      { key: 'giftId', label: 'Donation', type: 'select', options: 'giving.gifts', required: true },
      { key: 'reversedOn', label: 'Reversed on', type: 'date', required: true },
      { key: 'reason', label: 'Reason', type: 'textarea', required: true },
    ], columns: [{ title: 'Donation', key: 'giftId' }, { title: 'Amount', key: 'amountMinor' }, { title: 'Currency', key: 'currency' }, { title: 'Reversed', key: 'reversedOn' }, { title: 'Reason', key: 'reason' }] },
    { key: 'pledges', title: 'Pledges', createPath: '/api/giving/pledges', fields: [
      { key: 'donorId', nullable: true, label: 'Donor', type: 'select', options: 'people', required: true },
      { key: 'fundId', label: 'Fund', type: 'select', options: 'giving.funds', required: true },
      { key: 'amountMinor', label: 'Amount', type: 'money', required: true },
      { key: 'currency', label: 'Currency code', default: 'ZAR', required: true },
      { key: 'dueOn', label: 'Due on', type: 'date', required: true },
    ], actions: ['fulfilled', 'cancelled'].map(status => ({ label: status === 'fulfilled' ? 'Mark fulfilled' : 'Cancel', path: record => `/api/giving/pledges/${record.id}/status`, payload: () => ({ status }), available: record => record.status === 'open' })) },
    { key: 'batches', title: 'Reconciliation batches', createPath: '/api/giving/batches', fields: [
      { key: 'reference', label: 'Statement or batch reference', required: true },
      { key: 'giftIds', label: 'Donations', type: 'multi', options: 'giving.gifts', required: true },
      { key: 'expectedAmountMinor', label: 'Expected total', type: 'money', required: true },
      { key: 'currency', label: 'Currency code', default: 'ZAR', required: true },
      { key: 'reconciledOn', label: 'Reconciled on', type: 'date', required: true },
    ] },
    { key: 'totals', title: 'Fund totals', fields: [], columns: [{ title: 'Fund', key: 'fundId' }, { title: 'Currency', key: 'currency' }, { title: 'Gross', key: 'grossAmountMinor' }, { title: 'Reversals', key: 'reversalAmountMinor' }, { title: 'Net', key: 'netAmountMinor' }] },
  ],
}
