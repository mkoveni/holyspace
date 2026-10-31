// Java Children Ministry transport contracts. Sensitive pickup codes stay in component memory.
export interface Programme {
  id: string
  name: string
  currentPolicyId: string | null
  minStaff: number
  childrenPerStaff: number
  version: number
  createdAt: string
}

export interface ConsentPolicy {
  id: string
  programmeId: string
  label: string
  documentReference: string
  contentHash: string
  createdAt: string
}

export interface MinistryClass {
  id: string
  programmeId: string
  name: string
  minAgeMonths: number
  maxAgeMonths: number
}

export interface Enrolment {
  id: string
  programmeId: string
  childId: string
  validFrom: string
  validUntil: string
  endedAt: string | null
  version: number
  createdAt: string
}

export interface ClassAssignment {
  id: string
  programmeId: string
  classId: string
  childId: string
  enrolmentId: string
  validFrom: string
  validUntil: string
  endedAt: string | null
  version: number
}

export interface GuardianAuthority {
  id: string
  childId: string
  guardianId: string
  validFrom: string
  validUntil: string
  evidenceReference: string
  verifiedBy: string
  revokedAt: string | null
  revocationReason: string | null
  version: number
}

export interface Consent {
  id: string
  programmeId: string
  policyId: string
  childId: string
  authorityId: string
  validFrom: string
  validUntil: string
  acceptedAt: string
  recordedBy: string
  evidenceReference: string
  revokedAt: string | null
  revocationReason: string | null
  version: number
}

export interface PickupAuthorization {
  id: string
  programmeId: string
  childId: string
  collectorId: string
  authorityId: string
  validFrom: string
  validUntil: string
  evidenceReference: string
  recordedBy: string
  revokedAt: string | null
  revocationReason: string | null
  version: number
}

export interface Location {
  id: string
  name: string
  timeZone: string
}

export interface Room {
  id: string
  locationId: string
  name: string
  capacity: number
}

export interface Worker {
  personId: string
  accountId: string
  verifiedFrom: string
  screenedUntil: string
  trainedUntil: string
  evidenceReference: string
  verifiedBy: string
  revokedAt: string | null
  version: number
}

export interface MinistrySession {
  id: string
  classId: string
  roomId: string
  startsAt: string
  endsAt: string
  seatLimit: number
  status: 'PLANNED' | 'OPEN' | 'NEEDS_REVIEW' | 'CLOSED' | 'CANCELLED'
  version: number
}

export interface SessionView {
  session: MinistrySession
  className: string
  roomName: string
  timeZone: string
  effectiveCapacity: number
  occupancy: number
  staffingNeedsReview: boolean
  staff: Staff[]
}

export interface Staff {
  personId: string
  name: string
  role: 'LEADER' | 'ASSISTANT'
  present: boolean
  issues: string[]
}

export interface AttendanceView {
  id: string
  childId: string
  sessionId: string
  arrivedAt: string
  checkedOutAt: string | null
  collectorId: string | null
  authorizationId: string | null
  identityVerified: boolean
  exceptionUsed: boolean
  labelVersion: number
  labelExpiresAt: string | null
  labelIssued: boolean
  version: number
}

export interface LabelIssue {
  attendanceId: string
  labelVersion: number
  pickupCode: string
  expiresAt: string
  attendanceVersion: number
}

export interface SafeguardingView {
  authorities: GuardianAuthority[]
  consents: Consent[]
  pickupAuthorizations: PickupAuthorization[]
}

export interface SupportRecord {
  childId: string
  allergies: string
  careInstructions: string
  restrictedNotes: string
  reviewOn: string
  retainUntil: string
  version: number
  updatedAt: string
}

export interface ExpectedChild {
  childId: string
  name: string
  enrolmentId: string
  assignmentId: string
}

export interface PickupCollector {
  authorizationId: string | null
  collectorId: string | null
  name: string
  validUntil: string
}

export interface AttendanceEvent {
  id: string
  attendanceId: string
  actorId: string
  type: string
  reason: string
  occurredAt: string
}

export interface AttendanceSegment {
  id: string
  attendanceId: string
  sessionId: string
  enrolmentId: string
  assignmentId: string
  consentId: string
  arrivedAt: string
  leftAt: string
}

export interface ChildrenReviewWorkerRequest {
  personId: string
  expectedVersion: number
  screenedUntil: string
  trainedUntil: string
  evidenceReference: string
  revoked: boolean
}

export interface ChildrenSaveChildSupportRequest {
  childId: string
  expectedVersion: number
  allergies: string
  careInstructions: string
  restrictedNotes: string
  reviewOn: string
  retainUntil: string
}

export interface ChildrenSetSessionStatusRequest {
  acknowledgeIncidents?: boolean
  sessionId: string
  expectedVersion: number
  status: 'PLANNED' | 'OPEN' | 'NEEDS_REVIEW' | 'CLOSED' | 'CANCELLED'
  reason: string
}

export interface ChildrenSetSessionStaffRequest {
  sessionId: string
  expectedVersion: number
  workerId: string
  role: 'LEADER' | 'ASSISTANT'
  present: boolean
}

export interface ChildrenRegisterWorkerRequest {
  personId: string
  accountId: string
  screenedUntil: string
  trainedUntil: string
  evidenceReference: string
}

export interface ChildrenPurgeExpiredSupportRequest {
  limit: number
}

export interface ChildrenAccessChildSupportRequest {
  childId: string
  sessionId: string
}

export interface ChildrenCreateSessionRequest {
  classId: string
  roomId: string
  startsAt: string
  endsAt: string
  seatLimit: number
}

export interface ChildrenCreateRoomRequest {
  locationId: string
  name: string
  capacity: number
}

export interface ChildrenCreateProgrammeRequest {
  name: string
  minStaff: number
  childrenPerStaff: number
}

export interface ChildrenAuthorizePickupRequest {
  programmeId: string
  childId: string
  collectorId: string
  authorityId: string
  validFrom: string
  validUntil: string
  evidenceReference: string
}

export interface ChildrenRevokePickupAuthorizationRequest {
  id: string
  expectedVersion: number
  reason: string
}

export interface ChildrenCreateLocationRequest {
  name: string
  timeZone: string
}

export interface ChildrenVerifyGuardianRequest {
  childId: string
  guardianId: string
  validFrom: string
  validUntil: string
  evidenceReference: string
}

export interface ChildrenRevokeGuardianRequest {
  id: string
  expectedVersion: number
  reason: string
}

export interface ChildrenEnrolChildRequest {
  programmeId: string
  childId: string
  validFrom: string
  validUntil: string
}

export interface ChildrenEndEnrolmentRequest {
  enrolmentId: string
  expectedVersion: number
}

export interface ChildrenRecordConsentRequest {
  childId: string
  programmeId: string
  policyId: string
  authorityId: string
  validFrom: string
  validUntil: string
  acceptedAt: string
  evidenceReference: string
}

export interface ChildrenRevokeConsentRequest {
  id: string
  expectedVersion: number
  reason: string
}

export interface ChildrenPublishConsentPolicyRequest {
  programmeId: string
  expectedVersion: number
  label: string
  documentReference: string
  contentHash: string
}

export interface ChildrenCreateClassRequest {
  programmeId: string
  name: string
  minAgeMonths: number
  maxAgeMonths: number
}

export interface ChildrenAssignClassRequest {
  enrolmentId: string
  classId: string
  validFrom: string
  validUntil: string
}

export interface ChildrenEndClassAssignmentRequest {
  assignmentId: string
  expectedVersion: number
}

export interface ChildrenCheckInChildRequest {
  sessionId: string
  childId: string
  requestId: string
}

export interface ChildrenTransferChildRequest {
  attendanceId: string
  expectedVersion: number
  targetSessionId: string
  reason: string
}

export interface ChildrenSupervisorCheckoutRequest {
  attendanceId: string
  expectedVersion: number
  requestId: string
  collectorId: string
  identityVerified: boolean
  reason: string
}

export interface ChildrenIssuePickupLabelRequest {
  attendanceId: string
  expectedVersion: number
  replacementReason: string
}

export interface ChildrenCheckOutChildRequest {
  attendanceId: string
  expectedVersion: number
  requestId: string
  collectorId: string
  identityVerified: boolean
  pickupCode: string
}

export interface ProgrammeChildrenPage {
  children: {
    enrolmentId: string
    childId: string
    name: string
    validFrom: string
    validUntil: string
    status: 'ACTIVE' | 'UPCOMING' | 'EXPIRED' | 'ENDED'
    classes: { id: string, name: string }[]
    readiness: { ready: boolean, completed: number, total: number } | null
  }[]
  total: number
}

export type ReadinessStepCode
  = | 'IDENTITY'
    | 'ENROLMENT'
    | 'CLASS'
    | 'POLICY'
    | 'GUARDIAN'
    | 'CONSENT'
    | 'COLLECTOR'
export interface ChildReadinessView {
  ready: boolean
  completed: number
  total: number
  on: string
  checkedAt: string
  timeZone: string
  steps: { code: ReadinessStepCode, complete: boolean }[]
  enrolment: Enrolment | null
}

export interface SessionDayPage {
  children: {
    childId: string
    name: string
    attendance: AttendanceView | null
    ready: boolean
    issues: string[]
  }[]
  total: number
  checkedAt: string
}

export interface SessionCloseout {
  incidents?: { unresolved: number, overdue: number, unassigned: number } | null
  visits: number
  children: number
  awaitingCollection: number
  collected: number
  transferredOut: number
  labelsNeedingAttention: number
  failedHandoverAttempts: number
  supervisorCollections: number
  checkedAt: string
}

export interface AttendanceReportFilter {
  from: string
  until: string
  programmeId?: string
  locationId?: string
  childId?: string
  search?: string
  outcome?: string
}
export interface AttendanceVisit {
  id: string
  attendanceId: string
  childId: string
  childName: string
  sessionId: string
  programmeId: string
  programme: string
  ministryClass: string
  room: string
  location: string
  timeZone: string
  arrivedAt: string
  leftAt: string | null
  outcome: string
  supervisorCollection: boolean
}
export interface AttendanceReport {
  visits: AttendanceVisit[]
  summary: {
    visits: number
    children: number
    attending: number
    collected: number
    transferred: number
    supervisorCollections: number
  }
  timeZone: string
  checkedAt: string
}

export interface SessionSeriesDraft {
  name: string
  sourceSessionId: string
  sourceVersion: number
  recurrence: {
    firstDate: string
    startsAt: string
    endsAt: string
    intervalWeeks: number
    occurrences: number
    excludedDates: string[]
  }
}
export interface SessionSeriesPreview {
  className: string
  roomName: string
  timeZone: string
  seatLimit: number
  team: { workerId: string, name: string, role: string }[]
  occurrences: {
    slot: { date: string, startsAt: string, endsAt: string, excluded: boolean }
    conflicts: string[]
    staffingWarnings: string[]
  }[]
  checkedAt: string
}
export interface SessionSeriesSummary {
  id: string
  name: string
  sourceSessionId: string
  className: string
  roomName: string
  timeZone: string
  firstDate: string
  intervalWeeks: number
  occurrences: number
  totalSessions: number
  plannedSessions: number
  cancelledSessions: number
  stopped: boolean
  version: number
}
export interface SessionSeriesPage {
  series: SessionSeriesSummary[]
  total: number
}
export interface SessionSeriesDetail {
  series: SessionSeriesSummary
  sessions: {
    id: string
    date: string
    startsAt: string
    endsAt: string
    status: MinistrySession['status']
  }[]
}

export interface PlanningStaff {
  workerId: string
  name: string
  role: string
  present: boolean
  qualified: boolean
  clash: boolean
  screenedUntil: string | null
  trainedUntil: string | null
  issues: string[]
}
export interface PlanningSession {
  session: MinistrySession
  programmeId: string
  programme: string
  ministryClass: string
  room: string
  timeZone: string
  requiredStaff: number
  qualifiedStaff: number
  qualifiedLeader: boolean
  plannedCapacity: number
  ready: boolean
  editable: boolean
  issues: string[]
  team: PlanningStaff[]
}
export interface TeamPlanningPage {
  sessions: PlanningSession[]
  total: number
  summary: {
    sessions: number
    ready: number
    attention: number
    missingLeaders: number
    shortCapacity: number
    clashes: number
  }
  timeZone: string
  checkedAt: string
}
export interface TeamPlanDraft {
  targets: { sessionId: string, version: number }[]
  workerId: string
  replaceWorkerId: string | null
  role: 'LEADER' | 'ASSISTANT'
}
export interface TeamPlanPreview {
  sessions: PlanningSession[]
  blockers: string[]
  checkedAt: string
}
export interface PlanningWorker {
  id: string
  name: string
  screenedUntil: string
  trainedUntil: string
  revoked: boolean
}
export interface FollowUpIssue {
  id: string
  kind: string
  state: string
  childId: string
  childName: string
  programmeId: string | null
  programme: string
  expiresAt: string | null
  reviewOn: string | null
  action: string
  message: string
}
export interface FollowUpPage {
  issues: FollowUpIssue[]
  total: number
  summary: {
    children: number
    missing: number
    expired: number
    expiring: number
    careDue: number
  }
  careIncluded: boolean
  through: string
  timeZone: string
  checkedAt: string
}

export interface Incident {
  id: string
  childId: string
  sessionId: string
  segmentId: string
  category: string
  severity: string
  occurredAt: string
  reportedBy: string
  reportedAt: string
  status: string
  ownerId: string | null
  reviewOn: string
  retainUntil: string
  version: number
}
export interface IncidentPage {
  incidents: { incident: Incident, childName: string }[]
  total: number
  overdue: number
  unassigned: number
}
export interface IncidentPrivateView {
  incident: Incident
  details: { description: string, immediateAction: string }
  events: {
    id: string
    revision: number
    type: string
    actorId: string
    occurredAt: string
    note: string
    guardianId: string | null
  }[]
  totalEvents: number
}
export interface IncidentVisit {
  visit: {
    segmentId: string
    childId: string
    arrivedAt: string
    leftAt: string | null
  }
  childName: string
}
export interface GuardianCommunication {
  id: string
  childId: string
  authorityId: string
  guardianId: string
  programmeId: string
  policyId: string | null
  sessionId: string | null
  type: string
  message: string
  dueOn: string
  status: string
  createdBy: string
  createdAt: string
  updatedBy: string
  updatedAt: string
  contactReference: string
  version: number
}
export interface CommunicationSummary {
  id: string
  childId: string
  guardianId: string
  programmeId: string
  sessionId: string | null
  type: string
  dueOn: string
  status: string
  version: number
}
export interface CommunicationPage {
  communications: {
    delivery?: EmailDeliverySummary | null
    communication: CommunicationSummary
    childName: string
    guardianName: string
  }[]
  total: number
  overdue: number
}

export interface EmailRecipient {
  contactId: string
  email: string
  verificationId: string | null
  validUntil: string | null
  reviewHash: string | null
}
export interface EmailReview {
  subject: string
  html: string
  templateKey: string
  templateVersion: number
  communicationId: string
  version: number
  message: string
  recipients: EmailRecipient[]
  transport: { enabled: boolean, capture: boolean, mode: string }
}
export interface EmailDeliverySummary {
  id: string
  communicationId: string
  status: string
  scheduledAt: string
  attempts: number
  errorCode: string
  captured: boolean
  version: number
}
export interface EmailDeliveryPage {
  deliveries: EmailDeliverySummary[]
  total: number
  failed: number
  waiting: number
}
export interface EmailDeliveryDetail {
  delivery: EmailDeliverySummary & {
    recipient: string
    subject: string
    body: string
    contentType: string
    templateKey: string | null
    templateVersion: number | null
    messageId: string
    approvedAt: string
  }
  events: { id: string, type: string, occurredAt: string, reference: string }[]
}
export interface MinistryOverview {
  checkedAt: string
  timeZone: string
  upcoming: {
    total: number
    sessions: {
      id: string
      programme: string
      ministryClass: string
      room: string
      startsAt: string
      status: string
    }[]
  } | null
  teams: TeamPlanningPage | null
  teamNotice: string
  safeguarding: FollowUpPage | null
  safeguardingNotice: string
  incidents: {
    incidents: Incident[]
    total: number
    overdue: number
    unassigned: number
  } | null
  communications: EmailDeliveryPage | null
}
