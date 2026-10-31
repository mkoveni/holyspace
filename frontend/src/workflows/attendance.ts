import type { WorkflowDefinition } from '@/types/workflow'
export const attendanceDefinition: WorkflowDefinition = {
  context: 'attendance', title: 'Attendance', subtitle: 'Record participation, count visitors, and review attendance history',
  filters: [
    { key: 'occurrenceId', label: 'Occurrence', type: 'select', options: 'occurrences' },
    { key: 'personId', label: 'Person', type: 'select', options: 'people' },
  ],
  sections: [
    { key: 'entries', title: 'Attendance records', createPath: '/api/attendance/entries', editPath: record => `/api/attendance/entries/${record.id}`, fields: [
      { key: 'occurrenceId', label: 'Occurrence', type: 'select', options: 'occurrences', required: true, readonlyOnEdit: true },
      { key: 'personId', label: 'Person', type: 'select', options: 'people', required: true, readonlyOnEdit: true },
      { key: 'status', label: 'Status', type: 'select', options: ['present', 'absent', 'excused'].map(value => ({ title: value, value })), default: 'present' },
      { key: 'reason', label: 'Reason (required for corrections)', type: 'textarea', requiredOnEdit: true },
    ] },
    { key: 'children', title: 'Children’s attendance', fields: [], columns: [{ title: 'Child', key: 'personId' }, { title: 'Occurrence', key: 'occurrenceId' }, { title: 'Status', key: 'status' }, { title: 'Source', key: 'source' }] },
    { key: 'visitors', title: 'Visitor counts', createPath: '/api/attendance/visitors', editPath: record => `/api/attendance/visitors/${record.id}`, fields: [
      { key: 'occurrenceId', label: 'Occurrence', type: 'select', options: 'occurrences', required: true, readonlyOnEdit: true },
      { key: 'count', label: 'Anonymous visitors (exclude named attendees)', type: 'number', default: 0, required: true },
      { key: 'reason', label: 'Reason (required for corrections)', type: 'textarea', requiredOnEdit: true },
    ] },
    { key: 'totals', title: 'Attendance totals', fields: [], columns: [{ title: 'Occurrence', key: 'occurrenceId' }, { title: 'Present', key: 'present' }, { title: 'Absent', key: 'absent' }, { title: 'Excused', key: 'excused' }, { title: 'Visitors', key: 'visitors' }, { title: 'Total present', key: 'totalPresent' }] },
  ],
}
