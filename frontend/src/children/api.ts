import type * as T from './types'
import { useApi } from '@/composables/useApi'
const root = '/api/children'
async function get<T> (
  path: string,
  params?: object,
  signal?: AbortSignal,
): Promise<T> {
  return (await useApi().get<T>(root + path, { params, signal })).data
}
async function post<T> (path: string, body: object): Promise<T> {
  return (await useApi().post<T>(root + path, body)).data
}
async function put (path: string, body: object): Promise<void> {
  await useApi().put(root + path, body)
}
export const childrenApi = {
  ministryOverview: () => get<T.MinistryOverview>('/overview'),
  emailReview: (id: string) =>
    get<T.EmailReview>(`/guardian-communications/${id}/email-review`),
  verifyEmail: (id: string, body: object) =>
    post<{ id: string }>(`/guardian-communications/${id}/verify-email`, body),
  revokeEmail: (id: string, reason: string) =>
    post<void>(`/verified-emails/${id}/revoke`, { reason }),
  queueEmail: (id: string, body: object) =>
    post<{ id: string }>(`/guardian-communications/${id}/queue-email`, body),
  emailDeliveries: (status = 'ALL', offset = 0) =>
    get<T.EmailDeliveryPage>('/email-deliveries', {
      status,
      limit: 25,
      offset,
    }),
  emailDelivery: (id: string, offset = 0) =>
    get<T.EmailDeliveryDetail>(`/email-deliveries/${id}`, {
      limit: 25,
      offset,
    }),
  cancelDelivery: (id: string, body: object) =>
    post<void>(`/email-deliveries/${id}/cancel`, body),
  retryDelivery: (id: string, body: object) =>
    post<void>(`/email-deliveries/${id}/retry`, body),

  incidents: (
    filter: {
      sessionId?: string
      childId?: string
      status: string
      overdueOnly: boolean
    },
    offset = 0,
  ) => get<T.IncidentPage>('/incidents', { ...filter, limit: 25, offset }),
  incidentVisits: (sessionId: string, search = '', offset = 0) =>
    get<{ visits: T.IncidentVisit[], total: number }>('/incidents/attendance', {
      sessionId,
      search,
      limit: 25,
      offset,
    }),
  reportIncident: (body: object) => post<{ id: string }>('/incidents', body),
  accessIncident: (id: string, offset = 0) =>
    post<T.IncidentPrivateView>(`/incidents/${id}/access`, {
      limit: 25,
      offset,
    }),
  followUpIncident: (id: string, body: object) =>
    post<void>(`/incidents/${id}/follow-up`, body),
  incidentOwners: (search = '') =>
    get<{ id: string, name: string }[]>('/incidents/owners', { search }),
  incidentGuardians: (id: string) =>
    get<{ authorityId: string, guardianId: string, name: string }[]>(
      `/incidents/${id}/guardians`,
    ),
  purgeIncidents: () =>
    post<{ purged: number }>('/incidents/purge-expired', { limit: 100 }),
  communications: (childId: string | undefined, status: string, offset = 0) =>
    get<T.CommunicationPage>('/guardian-communications', {
      childId,
      status,
      limit: 25,
      offset,
    }),
  prepareCommunication: (body: object) =>
    post<{ id: string }>('/guardian-communications', body),
  communication: (id: string) =>
    get<T.GuardianCommunication>(`/guardian-communications/${id}`),
  recordContact: (id: string, body: object) =>
    post<void>(`/guardian-communications/${id}/contact`, body),

  teamPlanning: (
    filter: {
      from: string
      until: string
      programmeId?: string
      locationId?: string
      search?: string
      state?: string
    },
    offset = 0,
  ) =>
    get<T.TeamPlanningPage>('/team-planning', { ...filter, limit: 25, offset }),
  planningWorkers: (search = '') =>
    get<{ workers: T.PlanningWorker[], total: number }>(
      '/team-planning/workers',
      { search, limit: 25, offset: 0 },
    ),
  previewTeamPlan: (draft: T.TeamPlanDraft) =>
    post<T.TeamPlanPreview>('/team-planning/preview', draft),
  applyTeamPlan: (draft: T.TeamPlanDraft, reason: string) =>
    post<void>('/team-planning/apply', { draft, reason }),
  safeguardingFollowUp: (
    filter: {
      programmeId?: string
      search?: string
      kind?: string
      state?: string
      days: number
    },
    offset = 0,
  ) =>
    get<T.FollowUpPage>('/safeguarding-follow-up', {
      ...filter,
      limit: 25,
      offset,
    }),
  previewSeries: (draft: T.SessionSeriesDraft) =>
    post<T.SessionSeriesPreview>('/session-series/preview', draft),
  createSeries: (requestId: string, draft: T.SessionSeriesDraft) =>
    post<{ id: string }>('/session-series', { requestId, draft }),
  sessionSeries: (offset = 0) =>
    get<T.SessionSeriesPage>('/session-series', { limit: 25, offset }),
  sessionSeriesDetail: (id: string) =>
    get<T.SessionSeriesDetail>(`/session-series/${id}`),
  cancelSeries: (id: string, expectedVersion: number, reason: string) =>
    post<void>(`/session-series/${id}/cancel`, { expectedVersion, reason }),
  attendanceReport: (filter: T.AttendanceReportFilter, offset = 0) =>
    get<T.AttendanceReport>('/attendance-reports', {
      ...filter,
      limit: 25,
      offset,
    }),
  attendanceTimeline: (id: string, offset = 0) =>
    get<T.AttendanceReport>(`/attendance/${id}/timeline`, {
      limit: 50,
      offset,
    }),
  exportAttendance: async (filter: T.AttendanceReportFilter) => {
    try {
      return (
        await useApi().get<string>(root + '/attendance-reports/export', {
          params: filter,
          responseType: 'text',
          headers: { Accept: 'text/csv' },
        })
      ).data
    } catch (error) {
      const response = (error as { response?: { data?: unknown } }).response
      if (typeof response?.data === 'string') {
        try {
          response.data = JSON.parse(response.data)
        } catch {
          /* Keep the original server error. */
        }
      }
      throw error
    }
  },
  programmes: (search = '', offset = 0, signal?: AbortSignal) =>
    get<T.Programme[]>('/programmes', { search, limit: 25, offset }, signal),
  programme: (id: string) => get<T.Programme>(`/programmes/${id}`),
  readiness: (programmeId: string, childId: string) =>
    get<T.ChildReadinessView>(
      `/programmes/${programmeId}/children/${childId}/readiness`,
    ),
  programmeChildren: (
    programmeId: string,
    search = '',
    status = 'ALL',
    offset = 0,
  ) =>
    get<T.ProgrammeChildrenPage>(`/programmes/${programmeId}/children`, {
      search,
      status,
      limit: 25,
      offset,
    }),
  classes: (programmeId: string) =>
    get<T.MinistryClass[]>('/classes', { programmeId }),
  policy: (id: string) => get<T.ConsentPolicy>(`/consent-policies/${id}`),
  enrolments: (childId: string, offset = 0) =>
    get<T.Enrolment[]>('/enrolments', { childId, limit: 25, offset }),
  assignments: (childId: string, offset = 0) =>
    get<T.ClassAssignment[]>('/class-assignments', {
      childId,
      limit: 25,
      offset,
    }),
  safeguarding: (childId: string) =>
    get<T.SafeguardingView>(`/safeguarding/${childId}`),
  locations: (offset = 0) =>
    get<T.Location[]>('/locations', { limit: 100, offset }),
  rooms: (locationId: string) => get<T.Room[]>('/rooms', { locationId }),
  sessions: (
    from: string,
    until: string,
    offset = 0,
    station = false,
    signal?: AbortSignal,
  ) =>
    get<T.SessionView[]>(
      station ? '/station/sessions' : '/sessions',
      { from, until, limit: 25, offset },
      signal,
    ),
  closeout: (id: string) => get<T.SessionCloseout>(`/sessions/${id}/closeout`),
  sessionDay: (id: string, view = 'EXPECTED', search = '', offset = 0) =>
    get<T.SessionDayPage>(`/station/sessions/${id}/day`, {
      view,
      search,
      limit: 100,
      offset,
    }),
  session: (id: string, station = false) =>
    get<T.SessionView>(station ? `/station/sessions/${id}` : `/sessions/${id}`),
  roster: async (id: string, offset = 0, openOnly = true) =>
    (
      await get<{ attendance: T.AttendanceView, childName: string }[]>(
        `/station/sessions/${id}/attendance`,
        { limit: 100, offset, openOnly },
      )
    ).map(value => ({ ...value.attendance, childName: value.childName })),
  expected: (id: string, offset = 0) =>
    get<T.ExpectedChild[]>(`/sessions/${id}/expected-children`, {
      limit: 100,
      offset,
    }),
  attendance: (id: string) => get<T.AttendanceView>(`/attendance/${id}`),
  collectors: (id: string) =>
    get<T.PickupCollector[]>(`/attendance/${id}/collectors`),
  history: (childId: string, offset = 0) =>
    get<T.AttendanceView[]>(`/attendance/children/${childId}`, {
      limit: 25,
      offset,
    }),
  events: (id: string) =>
    get<T.AttendanceEvent[]>(`/attendance/${id}/events`, {
      limit: 100,
      offset: 0,
    }),
  segments: (id: string) =>
    get<T.AttendanceSegment[]>(`/attendance/${id}/segments`),
  worker: (id: string) => get<T.Worker>(`/workers/${id}`),
  createProgramme: (body: T.ChildrenCreateProgrammeRequest) =>
    post<{ id: string }>('/programmes', body),
  publishPolicy: (body: T.ChildrenPublishConsentPolicyRequest) =>
    post<{ id: string }>('/consent-policies', body),
  createClass: (body: T.ChildrenCreateClassRequest) =>
    post<{ id: string }>('/classes', body),
  enrol: (body: T.ChildrenEnrolChildRequest) =>
    post<{ id: string }>('/enrolments', body),
  assignClass: (body: T.ChildrenAssignClassRequest) =>
    post<{ id: string }>('/class-assignments', body),
  endEnrolment: (body: T.ChildrenEndEnrolmentRequest) =>
    post<void>('/enrolments/end', body),
  endAssignment: (body: T.ChildrenEndClassAssignmentRequest) =>
    post<void>('/class-assignments/end', body),
  verifyGuardian: (body: T.ChildrenVerifyGuardianRequest) =>
    post<{ id: string }>('/guardian-authorities', body),
  recordConsent: (body: T.ChildrenRecordConsentRequest) =>
    post<{ id: string }>('/consents', body),
  authorizePickup: (body: T.ChildrenAuthorizePickupRequest) =>
    post<{ id: string }>('/pickup-authorizations', body),
  revokeGuardian: (body: T.ChildrenRevokeGuardianRequest) =>
    post<void>('/guardian-authorities/revoke', body),
  revokeConsent: (body: T.ChildrenRevokeConsentRequest) =>
    post<void>('/consents/revoke', body),
  revokePickup: (body: T.ChildrenRevokePickupAuthorizationRequest) =>
    post<void>('/pickup-authorizations/revoke', body),
  createLocation: (body: T.ChildrenCreateLocationRequest) =>
    post<{ id: string }>('/locations', body),
  createRoom: (body: T.ChildrenCreateRoomRequest) =>
    post<{ id: string }>('/rooms', body),
  registerWorker: (body: T.ChildrenRegisterWorkerRequest) =>
    post<void>('/workers', body),
  reviewWorker: (body: T.ChildrenReviewWorkerRequest) => put('/workers', body),
  createSession: (body: T.ChildrenCreateSessionRequest) =>
    post<{ id: string }>('/sessions', body),
  staff: (body: T.ChildrenSetSessionStaffRequest) =>
    put('/sessions/staff', body),
  status: (body: T.ChildrenSetSessionStatusRequest) =>
    put('/sessions/status', body),
  checkIn: (body: T.ChildrenCheckInChildRequest) =>
    post<T.AttendanceView>('/attendance', body),
  issueLabel: (body: T.ChildrenIssuePickupLabelRequest) =>
    post<T.LabelIssue>('/attendance/labels', body),
  checkout: (body: T.ChildrenCheckOutChildRequest) =>
    post<T.AttendanceView>('/attendance/checkout', body),
  supervisorCheckout: (body: T.ChildrenSupervisorCheckoutRequest) =>
    post<T.AttendanceView>('/attendance/supervisor-checkout', body),
  transfer: (body: T.ChildrenTransferChildRequest) =>
    post<T.AttendanceView>('/attendance/transfers', body),
  support: (childId: string, sessionId: string | null) =>
    post<T.SupportRecord>('/support/access', { childId, sessionId }),
  saveSupport: (body: T.ChildrenSaveChildSupportRequest) =>
    put('/support', body),
  purgeSupport: (limit = 50) => post<number>('/support/purge', { limit }),
}
