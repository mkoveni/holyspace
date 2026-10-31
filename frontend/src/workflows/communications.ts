import type { WorkflowDefinition } from '@/types/workflow'
export const communicationsDefinition: WorkflowDefinition = {
  context: 'communications', title: 'Communications', subtitle: 'Prepare messages, record channel consent, and track campaign delivery',
  sections: [
    { key: 'templates', title: 'Templates', createPath: '/api/communications/templates', fields: [
      { key: 'name', label: 'Template name', required: true },
      { key: 'channel', label: 'Channel', type: 'select', default: 'email', options: [{ title: 'Email', value: 'email' }, { title: 'SMS', value: 'sms' }] },
      { key: 'subject', label: 'Subject (required for email)', default: '' },
      { key: 'body', label: 'Message (use {{name}} for the recipient’s name)', type: 'textarea', required: true },
    ] },
    { key: 'preferences', title: 'Contact consent', createPath: '/api/communications/preferences', fields: [
      { key: 'personId', label: 'Person', type: 'select', options: 'people', required: true },
      { key: 'channel', label: 'Channel', type: 'select', default: 'email', options: [{ title: 'Email', value: 'email' }, { title: 'SMS', value: 'sms' }] },
      { key: 'consent', label: 'Person has given consent', type: 'boolean' },
      { key: 'consentNote', label: 'Consent evidence or note', type: 'textarea', default: '' },
    ], actions: [{ label: 'Withdraw consent', path: record => `/api/communications/preferences/${record.id}/withdraw`, payload: () => ({}), available: record => Boolean(record.consent) }] },
    { key: 'campaigns', title: 'Campaigns', createPath: '/api/communications/campaigns', fields: [
      { key: 'name', label: 'Campaign name', required: true },
      { key: 'templateId', label: 'Template', type: 'select', options: 'communications.templates', required: true },
      { key: 'recipientIds', label: 'Audience', type: 'multi', options: 'people', required: true },
    ], columns: [{ title: 'Campaign', key: 'name' }, { title: 'Channel', key: 'channel' }, { title: 'Subject', key: 'subject' }, { title: 'Status', key: 'status' }], actions: [
      { label: 'Queue for delivery', path: record => `/api/communications/campaigns/${record.id}/queue`, payload: () => ({}), available: record => record.status === 'draft' },
      { label: 'Cancel', path: record => `/api/communications/campaigns/${record.id}/cancel`, payload: () => ({}), available: record => record.status !== 'cancelled' },
    ] },
    { key: 'deliveries', title: 'Delivery history', editPath: record => `/api/communications/deliveries/${record.id}/resolve`, fields: [{ key: 'status', label: 'Outcome verified with provider', type: 'select', required: true, options: [{ title: 'Accepted by provider', value: 'delivered' }, { title: 'Failed', value: 'failed' }] }, { key: 'note', label: 'Evidence from provider reconciliation (no credentials)', type: 'textarea', required: true }], columns: [{ title: 'Recipient', key: 'personId' }, { title: 'Channel', key: 'channel' }, { title: 'Subject', key: 'subject' }, { title: 'Status', key: 'status' }, { title: 'Delivered at', key: 'deliveredAt' }, { title: 'Details', key: 'error' }] },
    { key: 'providers', title: 'Provider status', fields: [], columns: [{ title: 'Channel', key: 'channel' }, { title: 'Configured', key: 'configured' }] },
  ],
}
