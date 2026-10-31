import type { WorkflowDefinition } from '@/types/workflow'
import { localInput } from './forms'
export const ministriesDefinition: WorkflowDefinition = {
  context: 'ministries', title: 'Ministries', subtitle: 'Organise ministry teams, small groups, and leadership',
  sections: [
    { key: 'ministries', title: 'Ministries and groups', createPath: '/api/ministries/ministries', fields: [
      { key: 'name', label: 'Name', required: true },
      { key: 'type', label: 'Type', type: 'select', default: 'ministry', options: [{ title: 'Ministry', value: 'ministry' }, { title: 'Small group', value: 'small_group' }] },
      { key: 'description', nullable: true, label: 'Description', type: 'textarea' },
    ], columns: [{ title: 'Name', key: 'name' }, { title: 'Type', key: 'type' }, { title: 'Active', key: 'active' }], actions: [{ label: 'Archive', path: record => `/api/ministries/ministries/${record.id}/archive`, payload: () => ({}), available: record => Boolean(record.active) }] },
    { key: 'memberships', title: 'Memberships', createPath: '/api/ministries/memberships', fields: [
      { key: 'ministryId', label: 'Ministry or group', type: 'select', options: 'ministries.ministries', required: true },
      { key: 'personId', label: 'Person', type: 'select', options: 'people', required: true },
      { key: 'joinedOn', label: 'Joined on', type: 'date', required: true },
      { key: 'role', label: 'Role', type: 'select', default: 'member', options: [{ title: 'Member', value: 'member' }, { title: 'Leader', value: 'leader' }] },
    ], columns: [{ title: 'Ministry', key: 'ministryId' }, { title: 'Person', key: 'personId' }, { title: 'Role', key: 'role' }, { title: 'Joined', key: 'joinedOn' }, { title: 'Active', key: 'active' }, { title: 'Left', key: 'leftOn' }], actions: [
      { label: 'Make leader', path: record => `/api/ministries/memberships/${record.id}/role`, payload: () => ({ role: 'leader' }), available: record => Boolean(record.active) && record.role !== 'leader' },
      { label: 'Make member', path: record => `/api/ministries/memberships/${record.id}/role`, payload: () => ({ role: 'member' }), available: record => Boolean(record.active) && record.role !== 'member' },
      { label: 'End membership', path: record => `/api/ministries/memberships/${record.id}/leave`, payload: () => ({ leftOn: localInput(new Date().toISOString()).slice(0, 10) }), available: record => Boolean(record.active) },
    ] },
  ],
}
