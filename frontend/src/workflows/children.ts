import type { WorkflowDefinition } from '@/types/workflow'
export const childrenDefinition: WorkflowDefinition = {
  context: 'children', title: 'Children’s Ministry', subtitle: 'Manage Sunday school enrolment, staffed sessions, and safe collection',
  sections: [
    { key: 'programmes', title: 'Programmes', createPath: '/api/children/programmes', fields: [{ key: 'name', label: 'Programme name', required: true }] },
    { key: 'classes', title: 'Classes', createPath: '/api/children/classes', fields: [
      { key: 'programmeId', label: 'Programme', type: 'select', options: 'children.programmes', required: true },
      { key: 'name', label: 'Class name', required: true },
      { key: 'minAge', label: 'Minimum age', type: 'number', default: 0, required: true },
      { key: 'maxAge', label: 'Maximum age (under 18)', type: 'number', default: 12, required: true },
      { key: 'capacity', label: 'Capacity', type: 'number', default: 20, required: true },
    ] },
    { key: 'enrolments', title: 'Enrolments', createPath: '/api/children/enrolments', fields: [
      { key: 'classId', label: 'Class', type: 'select', options: 'children.classes', required: true },
      { key: 'childId', label: 'Child', type: 'select', options: 'people', required: true },
      { key: 'enrolledOn', label: 'Enrolled on', type: 'date', required: true },
    ], columns: [{ title: 'Child', key: 'childId' }, { title: 'Class', key: 'classId' }, { title: 'Enrolled on', key: 'enrolledOn' }, { title: 'Active', key: 'active' }], actions: [{ label: 'Deactivate', path: record => `/api/children/enrolments/${record.id}/deactivate`, payload: () => ({}), available: record => Boolean(record.active) }] },
    { key: 'authorisations', title: 'Pickup authorisations', createPath: '/api/children/authorisations', fields: [
      { key: 'childId', label: 'Child', type: 'select', options: 'people', required: true },
      { key: 'collectorId', label: 'Authorised adult', type: 'select', options: 'people', required: true },
      { key: 'validUntil', label: 'Valid until', type: 'date', required: true },
    ], columns: [{ title: 'Child', key: 'childId' }, { title: 'Collector', key: 'collectorId' }, { title: 'Valid until', key: 'validUntil' }, { title: 'Active', key: 'active' }], actions: [{ label: 'Revoke', path: record => `/api/children/authorisations/${record.id}/revoke`, payload: () => ({}), available: record => Boolean(record.active) }] },
    { key: 'sessions', title: 'Sessions', createPath: '/api/children/sessions', editPath: record => `/api/children/sessions/${record.id}`, fields: [
      { key: 'classId', label: 'Class', type: 'select', options: 'children.classes', required: true, readonlyOnEdit: true },
      { key: 'occurrenceId', label: 'Event occurrence', type: 'select', options: 'occurrences', required: true, readonlyOnEdit: true },
      { key: 'staffPersonIds', label: 'Screened staff with confirmed volunteer assignments', type: 'multi', options: 'people', required: true },
    ], columns: [{ title: 'Class', key: 'classId' }, { title: 'Occurrence', key: 'occurrenceId' }, { title: 'Staff', key: 'staffPersonIds' }, { title: 'Status', key: 'status' }], actions: [{ label: 'Cancel session', path: record => `/api/children/sessions/${record.id}/cancel`, payload: () => ({}), available: record => record.status !== 'cancelled' }] },
    { key: 'checkins', title: 'Check-ins', createPath: '/api/children/checkins', fields: [
      { key: 'sessionId', label: 'Session', type: 'select', options: 'children.sessions', required: true },
      { key: 'childId', label: 'Child', type: 'select', options: 'people', required: true },
      { key: 'staffPersonId', label: 'Receiving staff member', type: 'select', options: 'people', required: true },
    ], columns: [{ title: 'Child', key: 'childId' }, { title: 'Session', key: 'sessionId' }, { title: 'Checked in', key: 'checkedInAt' }, { title: 'Checked out', key: 'checkedOutAt' }, { title: 'Collector', key: 'collectorId' }, { title: 'Version', key: 'version' }] },
    { key: 'checkout', title: 'Collection', createPath: '/api/children/checkout', fields: [
      { key: 'checkInId', label: 'Open check-in', type: 'select', options: 'children.checkins', required: true },
      { key: 'collectorId', label: 'Collecting adult (verify identity)', type: 'select', options: 'people', required: true },
      { key: 'staffPersonId', label: 'Staff supervising handover', type: 'select', options: 'people', required: true },
      { key: 'pickupCode', label: 'Pickup label code (required when labels were issued)' },
      { key: 'identityVerified', label: 'I checked the collecting adult’s identity', type: 'boolean' },
      { key: 'expectedVersion', label: 'Current check-in version (required when labels were issued)', type: 'number', nullable: true },

    ] },
  ],
}
