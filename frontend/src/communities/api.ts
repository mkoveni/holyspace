import type { Schedule } from '@/events/api'
import { useApi } from '@/composables/useApi'
export { dateLabel, errorMessage, newSchedule, required } from '@/events/api'
const root = '/api/communities'
async function get<T> (path: string, params?: object) {
  return (await useApi().get<T>(root + path, { params })).data
}
async function post<T = void> (path: string, body: object) {
  return (await useApi().post<T>(root + path, body)).data
}
export interface Community {
  id: string
  kind: string
  name: string
  description: string
  parentId: string | null
  campus: string
  active: boolean
  version: number
}
export interface Membership {
  id: string
  communityId: string
  personId: string
  startedAt: string
  endedAt: string | null
  version: number
}
export interface Member {
  membership: Membership
  name: string
}
export interface Leadership {
  id: string
  membershipId: string
  communityId: string
  accountId: string | null
  role: string
  startedAt: string
  endedAt: string | null
  version: number
}
export interface Counts {
  meetingId: string
  expected: number
  present: number
  absent: number
  excused: number
  unrecorded: number
  guests: number
}
export interface Meeting {
  meeting: {
    id: string
    communityId: string
    occurrenceId: string
    status: string
    rosterFrozen: boolean
    version: number
  }
  date: {
    id: string
    startsAt: string
    endsAt: string
    timeZone: string
    venue: string
    status: string
  }
  counts: Counts
}
export interface Roster {
  id: string
  meetingId: string
  personId: string
  membershipId: string | null
  guest: boolean
  status: string
  version: number
  name: string
}
export interface FollowUp {
  id: string
  communityId: string
  personId: string
  name: string
  note: string
  dueOn: string
  status: string
  version: number
}
export interface History {
  id: string
  targetId: string
  action: string
  reason: string
  actorId: string
  occurredAt: string
}
export interface NoticeReview {
  hash: string
  recipient: string
  subject: string
  html: string
  templateVersion: number
}
export interface MemberDelivery {
  id: string
  status: string
  scheduledAt: string
  attempts: number
  errorCode: string
  captured: boolean
}
export const communityApi = {
  deliveries: (id: string, offset = 0) =>
    get<MemberDelivery[]>(`/${id}/deliveries`, { limit: 25, offset }),
  directory: (params: object) =>
    get<{ communities: Community[], total: number }>('', {
      limit: 25,
      ...params,
    }),
  create: (body: object) => post<{ id: string }>('', body),
  detail: (id: string) =>
    get<{ community: Community, canOperate: boolean }>('/' + id),
  update: (id: string, body: object) => useApi().put(root + '/' + id, body),
  members: (id: string, ended = false, offset = 0) =>
    get<{ members: Member[], total: number }>(`/${id}/members`, {
      ended,
      offset,
      limit: 25,
    }),
  join: (id: string, personId: string, requestId: string) =>
    post(`/${id}/members`, { personId, requestId }),
  leave: (id: string, body: object) => post(`/memberships/${id}/leave`, body),
  leadership: (id: string, offset = 0) =>
    get<{ leadership: Leadership, accountName: string }[]>(
      `/${id}/leadership`,
      { offset, limit: 25 },
    ),
  appoint: (id: string, body: object) =>
    post(`/memberships/${id}/leadership`, body),
  endLeadership: (id: string, body: object) =>
    post(`/leadership/${id}/end`, body),
  meetings: (id: string, offset = 0) =>
    get<{ meetings: Meeting[], total: number }>(`/${id}/meetings`, {
      offset,
      limit: 25,
    }),
  preview: (id: string, schedule: Schedule) =>
    post<{ hash: string, dates: Meeting['date'][] }>(`/${id}/meeting-preview`, {
      schedule,
    }),
  schedule: (id: string, body: object) => post(`/${id}/meetings`, body),
  prepare: (id: string, expectedVersion: number) =>
    post(`/meetings/${id}/prepare`, { expectedVersion }),
  roster: (id: string, offset = 0) =>
    get<{ roster: Roster[], total: number, counts: Counts }>(
      `/meetings/${id}/roster`,
      { offset, limit: 25 },
    ),
  attendance: (id: string, body: object) =>
    useApi().put(root + '/roster/' + id, body),
  guest: (id: string, body: object) => post(`/meetings/${id}/guests`, body),
  finish: (id: string, body: object) => post(`/meetings/${id}/finish`, body),
  followups: (id: string, completed = false, offset = 0) =>
    get<FollowUp[]>(`/${id}/follow-ups`, { completed, offset, limit: 25 }),
  followup: (id: string, body: object) => post(`/${id}/follow-ups`, body),
  complete: (id: string, body: object) =>
    post(`/follow-ups/${id}/complete`, body),
  history: (id: string, offset = 0) =>
    get<History[]>(`/${id}/history`, { offset, limit: 25 }),
  contacts: (id: string) =>
    get<{ id: string, value: string }[]>(`/memberships/${id}/contacts`),
  reviewNotice: (id: string, body: object) =>
    post<NoticeReview>(`/memberships/${id}/notice-review`, body),
  approveNotice: (id: string, body: object) =>
    post<{ id: string }>(`/memberships/${id}/notices`, body),
}
