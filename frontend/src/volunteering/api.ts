import { extractErrorMessage } from '@/composables/apiErrors'
import { useApi } from '@/composables/useApi'
export interface Volunteer {
  id: string
  personId: string
  accountId: string | null
  status: string
  version: number
  availabilityRevision: number
}
export interface Person {
  volunteer: Volunteer
  name: string
}
export interface Team {
  id: string
  name: string
  active: boolean
  version: number
}
export interface Role {
  id: string
  teamId: string
  name: string
  childrenRole: string
  active: boolean
  requiredCredentials: string[]
}
export interface Membership {
  id: string
  volunteerId: string
  teamId: string
  validFrom: string
  validUntil: string
  revokedAt: string | null
}
export interface Approval {
  id: string
  volunteerId: string
  roleId: string
  validFrom: string
  validUntil: string
  revokedAt: string | null
}
export interface CredentialType {
  id: string
  code: string
  name: string
}
export interface Credential {
  id: string
  typeId: string
  type: string
  validFrom: string
  validUntil: string
  revokedAt: string | null
}
export interface Weekly {
  dayOfWeek: number
  startsAt: string
  endsAt: string
  timeZone: string
  validFrom: string
  validUntil: string
}
export interface ExceptionWindow {
  startsAt: string
  endsAt: string
  available: boolean
}
export interface Profile {
  person: Person
  credentials: Credential[]
  weekly: Weekly[]
  exceptions: ExceptionWindow[]
}
export interface TeamDetails {
  team: Team
  roles: Role[]
  memberships: Membership[]
  approvals: Approval[]
}
export interface Readiness {
  ready: boolean
  issues: string[]
}
export interface Shift {
  id: string
  teamId: string
  roleId: string
  sourceKind: string
  sourceId: string | null
  startsAt: string
  endsAt: string
  positions: number
  status: string
  version: number
  scheduleRevision: number
}
export interface Assignment {
  id: string
  shiftId: string
  volunteerId: string
  status: string
  version: number
}
export interface InvitationView {
  invitation: {
    id: string
    assignmentId: string
    version: number
    status: string
    expiresAt: string
  }
  effectiveStatus: string
  team: string
  role: string
  startsAt: string
  endsAt: string
  readiness: Readiness
}
export interface Booking {
  assignment: Assignment
  name: string
  readiness: Readiness
  invitations: InvitationView[]
}
export interface ShiftView {
  shift: Shift
  team: string
  role: string
  assignments: Booking[]
  reserved: number
  confirmed: number
  ready: number
  vacancies: number
}
const root = '/api/volunteering'
async function get<T> (path: string, params?: object): Promise<T> {
  return (await useApi().get<T>(root + path, { params })).data
}
async function post<T = void> (path: string, body: object): Promise<T> {
  return (await useApi().post<T>(root + path, body)).data
}
async function put (path: string, body: object) {
  await useApi().put(root + path, body)
}
export const servingApi = {
  volunteers: (search = '', offset = 0) =>
    get<{ volunteers: Person[], total: number }>('/volunteers', {
      search,
      offset,
      limit: 25,
    }),
  profile: (id: string) => get<Profile>(`/volunteers/${id}`),
  register: (body: object) => post<{ id: string }>('/volunteers', body),
  status: (id: string, body: object) => put(`/volunteers/${id}/status`, body),
  types: () => get<CredentialType[]>('/credential-types'),
  createType: (body: object) => post('/credential-types', body),
  credential: (id: string, body: object) =>
    post(`/volunteers/${id}/credentials`, body),
  revokeCredential: (id: string, body: object) =>
    post(`/volunteers/${id}/credentials/revoke`, body),
  availability: (id: string, body: object) =>
    put(`/volunteers/${id}/availability`, body),
  teams: (offset = 0) =>
    get<{ teams: Team[], total: number }>('/teams', { limit: 25, offset }),
  team: (id: string) => get<TeamDetails>(`/teams/${id}`),
  createTeam: (body: object) => post<{ id: string }>('/teams', body),
  updateTeam: (id: string, body: object) => put(`/teams/${id}`, body),
  role: (id: string, body: object) => post(`/teams/${id}/roles`, body),
  join: (id: string, body: object) => post(`/teams/${id}/memberships`, body),
  approve: (id: string, body: object) => post(`/teams/${id}/approvals`, body),
  revoke: (id: string, body: object) => post(`/teams/${id}/revoke`, body),
  shifts: (from: string, until: string, teamId?: string, offset = 0) =>
    get<{ shifts: ShiftView[], total: number }>('/shifts', {
      from,
      until,
      teamId: teamId || undefined,
      offset,
      limit: 25,
    }),
  shift: (id: string) => get<ShiftView>(`/shifts/${id}`),
  createShift: (body: object) => post<{ id: string }>('/shifts', body),
  bulkShifts: (body: object[]) => post<string[]>('/shifts/bulk', body),
  updateShift: (id: string, body: object) => put(`/shifts/${id}`, body),
  assign: (body: object) => post<{ id: string }>('/assignments', body),
  assignment: (id: string, body: object) =>
    post(`/assignments/${id}/status`, body),
  readiness: (volunteerId: string, shiftId: string) =>
    get<Readiness>('/readiness', { volunteerId, shiftId }),
  invite: (id: string, body: object) =>
    post(`/assignments/${id}/invitations`, body),
  respond: (id: string, body: object) =>
    post(`/invitations/${id}/respond`, body),
  own: (offset = 0) =>
    get<InvitationView[]>('/me/invitations', { offset, limit: 25 }),
  expire: () => post<number>('/invitations/expire', { limit: 100 }),
  history: (id: string, offset = 0) =>
    get<{ id: string, action: string, reason: string, occurredAt: string }[]>(
      `/assignments/${id}/events`,
      { offset, limit: 25 },
    ),
  activities: (
    from: string,
    until: string,
    kind = 'CHILDREN_SESSION',
    offset = 0,
  ) =>
    get<
      {
        id: string
        label: string
        startsAt: string
        endsAt: string
        venueLabel?: string
      }[]
    >('/activities', { kind, from, until, limit: 100, offset }),
}
export function servingError (error: unknown) {
  return extractErrorMessage(error, 'Unable to complete this request. Please try again.')
}
export function required (value: unknown) {
  return Boolean(value) || 'This field is required.'
}
export function timeLabel (value: string) {
  return new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}
