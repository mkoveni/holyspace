import type { WorkflowDefinition } from '@/types/workflow'
export const volunteeringDefinition: WorkflowDefinition = {
  occurrencePlanning: true,
  context: 'volunteering', title: 'Volunteering', subtitle: 'Approve volunteers, plan shifts, and fill your rota',
  sections: [
    { key: 'roles', title: 'Roles', createPath: '/api/volunteering/roles', fields: [
      { key: 'name', label: 'Role name', required: true },
      { key: 'requiresScreening', label: 'Requires current screening', type: 'boolean' },
      { key: 'requiredQualifications', label: 'Required qualifications', type: 'tags' },
    ] },
    { key: 'profiles', title: 'Volunteer profiles', createPath: '/api/volunteering/profiles', editPath: record => `/api/volunteering/profiles/${record.id}`, fields: [
      { key: 'personId', label: 'Person', type: 'select', options: 'people', required: true, readonlyOnEdit: true },
      { key: 'approvedRoleIds', label: 'Approved roles', type: 'multi', options: 'volunteering.roles', required: true },
      { key: 'availability', label: 'Availability windows', type: 'intervals' },
      { key: 'qualifications', label: 'Qualifications', type: 'tags' },
      { key: 'qualificationsValidUntil', nullable: true, label: 'Qualifications valid until', type: 'date' },
      { key: 'screeningValidUntil', nullable: true, label: 'Screening valid until', type: 'date' },
      { key: 'active', label: 'Active volunteer', type: 'boolean', default: true },
    ] },
    { key: 'shifts', title: 'Shifts', createPath: '/api/volunteering/shifts', editPath: record => `/api/volunteering/shifts/${record.id}`, fields: [
      { key: 'occurrenceId', label: 'Event occurrence', type: 'select', options: 'occurrences', required: true, readonlyOnEdit: true },
      { key: 'roleId', label: 'Role', type: 'select', options: 'volunteering.roles', required: true, readonlyOnEdit: true },
      { key: 'startsAt', label: 'Starts', type: 'datetime', required: true },
      { key: 'endsAt', label: 'Ends', type: 'datetime', required: true },
      { key: 'positions', label: 'Positions', type: 'number', default: 1, required: true },
      { key: 'status', label: 'Status', type: 'select', default: 'open', options: ['open', 'needs_review', 'cancelled'].map(value => ({ title: value, value })) },
    ], columns: [{ title: 'Occurrence', key: 'occurrenceId' }, { title: 'Role', key: 'roleId' }, { title: 'Starts', key: 'startsAt' }, { title: 'Status', key: 'status' }, { title: 'Positions', key: 'positions' }, { title: 'Vacancies', key: 'vacancies' }] },
    { key: 'assignments', title: 'Assignments', createPath: '/api/volunteering/assignments', fields: [
      { key: 'shiftId', label: 'Shift', type: 'select', options: 'volunteering.shifts', required: true },
      { key: 'personId', label: 'Volunteer', type: 'select', options: 'people', required: true },
    ], columns: [{ title: 'Volunteer', key: 'personId' }, { title: 'Shift', key: 'shiftId' }, { title: 'Status', key: 'status' }], actions: ['assigned', 'declined', 'cancelled'].map(status => ({ label: status === 'assigned' ? 'Confirm' : (status === 'declined' ? 'Decline' : 'Cancel'), path: record => `/api/volunteering/assignments/${record.id}/status`, payload: () => ({ status }), available: record => record.status !== status })) },
  ],
}
