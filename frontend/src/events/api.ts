import { extractErrorMessage } from '@/composables/apiErrors'
import { useApi } from '@/composables/useApi'
export interface Event {
  id: string
  title: string
  type: string
  description: string
  active: boolean
  version: number
}
export interface Venue {
  id: string
  name: string
  address: string
  exclusive: boolean
  active: boolean
  version: number
}
export interface Series {
  id: string
  timeZone: string
  frequency: string
  interval: number
  count: number
  firstDate: string
  startsAt: string
  endsAt: string
  endDayOffset: number
  venueId: string
  version: number
}
export interface Occurrence {
  id: string
  eventId: string
  seriesId: string
  originDate: string
  timeZone: string
  startsAt: string
  endsAt: string
  venueId: string
  venueName: string
  venueAddress: string
  status: string
  overridden: boolean
  version: number
  scheduleRevision: number
}
export interface Counts {
  confirmed: number
  waitlisted: number
  cancelled: number
}
export interface CalendarItem {
  occurrence: Occurrence
  title: string
  type: string
  registrations: Counts
}
export interface Policy {
  occurrenceId: string
  open: boolean
  capacity: number | null
  waitlistEnabled: boolean
  version: number
}
export interface Details {
  occurrence: Occurrence
  title: string
  type: string
  series: Series
  registrationPolicy: Policy
  registrations: Counts
}
export interface Registration {
  id: string
  personId: string
  status: string
  position: number
  version: number
}
export interface History {
  id: string
  action: string
  reason: string
  occurredAt: string
  venueName?: string
  startsAt?: string
  endsAt?: string
}
export interface Schedule {
  venueId: string | null
  timeZone: string
  frequency: string
  interval: number
  count: number
  firstDate: string
  startsAt: string
  endsAt: string
  endDayOffset: number
}
export interface SchedulePreview {
  hash: string
  venue: Venue
  dates: { originDate: string, startsAt: string, endsAt: string }[]
}
export interface Change {
  occurrenceId: string
  expectedVersion: number
  action: string
  scope: string
  date: string
  startsAt: string
  endsAt: string
  endDayOffset: number
  venueId: string
}
export interface ChangePreview {
  hash: string
  changes: { before: Occurrence, after: Occurrence, registrations: Counts }[]
  skipped: string[]
  confirmedAffected: number
  waitlistedAffected: number
  attendedAffected: number
}
const root = '/api/events'
async function get<T> (path: string, params: object = {}) {
  return (await useApi().get<T>(root + path, { params })).data
}
async function post<T = void> (path: string, body: object) {
  return (await useApi().post<T>(root + path, body)).data
}
async function put (path: string, body: object) {
  await useApi().put(root + path, body)
}
export const eventApi = {
  memberOffer: (id: string) =>
    get<{ enabled: boolean, version: number }>(`/${id}/member-offer`),
  publishToMembers: (id: string, enabled: boolean, expectedVersion: number) =>
    put(`/${id}/member-offer`, { enabled, expectedVersion }),
  scanMember: (id: string, requestId: string, credential: string) =>
    post<{ id: string }>(`/occurrences/${id}/scan-member`, {
      requestId,
      credential,
    }),
  readiness: (id: string) =>
    get<EventReadiness>(`/occurrences/${id}/readiness`),
  closeout: (id: string, body: object) =>
    post(`/occurrences/${id}/closeout`, body),
  reopenCloseout: (id: string, body: object) =>
    post(`/occurrences/${id}/closeout/reopen`, body),
  closeoutHistory: (id: string, offset = 0) =>
    get<CloseoutHistory[]>(`/occurrences/${id}/closeout/history`, {
      limit: 25,
      offset,
    }),

  events: (search = '', offset = 0, includeArchived = false) =>
    get<{ events: Event[], total: number }>('', {
      search,
      offset,
      limit: 25,
      includeArchived,
    }),
  event: (id: string, offset = 0) =>
    get<{ event: Event, series: Series[], totalSeries: number }>(`/${id}`, {
      limit: 25,
      offset,
    }),
  create: (body: object) => post<{ id: string }>('', body),
  update: (id: string, body: object) => put(`/${id}`, body),
  addSeries: (id: string, body: object) =>
    post<{ id: string }>(`/${id}/series`, body),
  venues: (search = '', offset = 0, includeArchived = false, limit = 25) =>
    get<{ venues: Venue[], total: number }>('/venues', {
      search,
      offset,
      includeArchived,
      limit,
    }),
  createVenue: (body: object) => post<{ id: string }>('/venues', body),
  updateVenue: (id: string, body: object) => put(`/venues/${id}`, body),
  preview: (schedule: Schedule) =>
    post<SchedulePreview>('/schedule/preview', { draft: schedule }),
  calendar: (params: object) =>
    get<{ occurrences: CalendarItem[], total: number }>('/calendar', {
      limit: 25,
      ...params,
    }),
  occurrence: (id: string) => get<Details>(`/occurrences/${id}`),
  previewChange: (draft: Change) =>
    post<ChangePreview>('/occurrences/preview', { draft }),
  change: (action: string, body: object) =>
    post(`/occurrences/${action.toLowerCase()}`, body),
  policy: (id: string, body: object) =>
    put(`/occurrences/${id}/registration`, body),
  registrations: (id: string, status?: string, offset = 0) =>
    get<{
      registrations: { registration: Registration, name: string }[]
      total: number
      counts: Counts
    }>(`/occurrences/${id}/registrations`, { status, offset, limit: 25 }),
  register: (id: string, body: object) =>
    post<{ id: string }>(`/occurrences/${id}/registrations`, body),
  cancelRegistration: (id: string, body: object) =>
    post(`/registrations/${id}/cancel`, body),
  attendance: (id: string, search = '', status?: string, offset = 0) =>
    get<AttendancePage>(`/occurrences/${id}/attendance`, {
      search,
      status,
      offset,
      limit: 25,
    }),
  checkIn: (id: string, body: object) =>
    post<{ id: string }>(`/registrations/${id}/check-in`, body),
  walkIn: (id: string, body: object) =>
    post<{ registrationId: string, admitted: boolean, status: string }>(
      `/occurrences/${id}/walk-ins`,
      body,
    ),
  visitLink: (id: string) =>
    get<VisitLink>(`/occurrences/${id}/visit-link`),
  configureVisitLink: (id: string, action: VisitLinkAction) =>
    post<VisitLink>(`/occurrences/${id}/visit-link`, { action }),
  visitors: (id: string, status?: string, offset = 0) =>
    get<VisitorPage>(`/occurrences/${id}/visitors`, {
      status,
      offset,
      limit: 25,
    }),
  handOverVisitorChild: (id: string, decision: 'HANDED_OVER' | 'DISMISSED') =>
    post(`/visitor-children/${id}/handover`, { decision }),
  reviewVisitor: (id: string, body: object) =>
    post(`/registrations/${id}/visitor-review`, body),
  convertVisitor: (id: string, inviteToApp: boolean) =>
    post(`/registrations/${id}/visitor-convert`, { inviteToApp }),
  correctAttendance: (id: string, body: object) =>
    post(`/registrations/${id}/attendance-correction`, body),
  attendanceHistory: (id: string, offset = 0) =>
    get<AttendanceHistory[]>(`/registrations/${id}/attendance-history`, {
      offset,
      limit: 25,
    }),
  report: (params: object) =>
    get<EventReport>('/attendance-report', { limit: 25, ...params }),
  exportReport: async (params: object) => {
    try {
      return (
        await useApi().get<Blob>(root + '/attendance-report.csv', {
          params,
          responseType: 'blob',
        })
      ).data
    } catch (error) {
      const data = (error as { response?: { data?: unknown } }).response?.data
      if (data instanceof Blob) {
        let message = 'Unable to export this report.'
        try {
          message = JSON.parse(await data.text()).message || message
        } catch {
          message = 'Unable to export this report.'
        }
        throw new Error(message, { cause: error })
      }
      throw error
    }
  },
  messageContacts: (id: string) =>
    get<{ id: string, value: string }[]>(
      `/registrations/${id}/message-contacts`,
    ),
  reviewNotice: (id: string, body: object) =>
    post<NoticeReview>(`/registrations/${id}/notification-review`, body),
  approveNotice: (id: string, body: object) =>
    post<{ id: string }>(`/registrations/${id}/notifications`, body),
  history: (kind: 'occurrences' | 'registrations', id: string, offset = 0) =>
    get<History[]>(`/${kind}/${id}/history`, { limit: 25, offset }),
}
export const eventTypes = [
  'SERVICE',
  'MEETING',
  'CONFERENCE',
  'OUTREACH',
  'CLASS',
  'OTHER',
]
export function required (value: unknown) {
  return Boolean(value) || 'This field is required.'
}
export function dateLabel (value: string, zone?: string) {
  return new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: zone,
  })
}
export function errorMessage (error: unknown) {
  return extractErrorMessage(error, 'Unable to complete this request.')
}
export function newSchedule (): Schedule {
  const tomorrow = new Date(Date.now() + 86_400_000)
  return {
    venueId: null,
    timeZone: 'Africa/Johannesburg',
    frequency: 'ONCE',
    interval: 1,
    count: 1,
    firstDate: tomorrow.toISOString().slice(0, 10),
    startsAt: '09:00',
    endsAt: '10:30',
    endDayOffset: 0,
  }
}

export interface AttendanceRecord {
  id: string
  registrationId: string
  arrivedAt: string
  status: 'PRESENT' | 'VOIDED'
  version: number
}
export interface AttendanceEntry {
  registration: Registration
  attendance: AttendanceRecord | null
  name: string
  status: string
}
export interface AttendanceCounts extends Counts {
  attended: number
  absent: number
}
export interface AttendancePage {
  attendees: AttendanceEntry[]
  total: number
  counts: AttendanceCounts
  admissionOpen: boolean
}
export type VisitLinkAction = 'ENABLE' | 'DISABLE' | 'ROTATE' | 'MESSAGES_ON' | 'MESSAGES_OFF'
export interface VisitLink {
  occurrenceId: string
  enabled: boolean
  token: string | null
  messages: boolean
}
export interface VisitorChild {
  id: string
  firstName: string
  birthDate: string
  handoverStatus: 'PENDING' | 'HANDED_OVER' | 'DISMISSED'
}
export interface Visitor {
  registrationId: string
  fullName: string
  mobile: string | null
  email: string | null
  howHeard: string | null
  partyAdults: number | null
  firstTime: boolean
  reviewStatus: 'PENDING' | 'CONFIRMED' | 'REMOVED'
  checkedInAt: string
  attendanceStatus: string
  attendanceVersion: number | null
  children: VisitorChild[]
}
export interface VisitorPage {
  total: number
  pending: number
  visitors: Visitor[]
}
export interface AttendanceHistory {
  id: string
  actorId: string
  action: string
  reason: string
  occurredAt: string
}
export interface ReportRow {
  occurrenceId: string
  eventId: string
  title: string
  startsAt: string
  endsAt: string
  timeZone: string
  venue: string
  status: string
  counts: AttendanceCounts
}
export interface EventReport {
  occurrences: ReportRow[]
  totals: AttendanceCounts & { occurrences: number }
}
export interface NoticeReview {
  hash: string
  recipient: string
  subject: string
  html: string
  purpose: string
  templateVersion: number
}

export interface EventReadiness {
  checks: { key: string, title: string, status: string, detail: string }[]
  staffing: {
    shifts: number
    positions: number
    confirmed: number
    ready: number
    proposed: number
    gaps: number
    complete: boolean
  }
  review: {
    occurrence: Occurrence
    attendance: AttendanceCounts
    notices: {
      approved: number
      submitted: number
      delivered: number
      pending: number
      failed: number
      captured: number
      cancelled: number
      remindersApproved: number
      complete: boolean
    }
    hash: string
    canClose: boolean
  }
  closeout: {
    occurrenceId: string
    status: string
    version: number
    note: string
    followUp: string
    updatedAt: string | null
    updatedBy: string | null
  }
  changedSinceCloseout: boolean
  assessedAt: string
}
export interface CloseoutHistory {
  id: string
  version: number
  action: string
  note: string
  followUp: string
  attended: number
  absent: number
  waitlisted: number
  unresolvedNotices: number
  actorId: string
  actorName: string
  occurredAt: string
}
