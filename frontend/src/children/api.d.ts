import type * as T from './types';
export declare const childrenApi: {
    ministryOverview: () => Promise<T.MinistryOverview>;
    emailReview: (id: string) => Promise<T.EmailReview>;
    verifyEmail: (id: string, body: object) => Promise<{
        id: string;
    }>;
    revokeEmail: (id: string, reason: string) => Promise<void>;
    queueEmail: (id: string, body: object) => Promise<{
        id: string;
    }>;
    emailDeliveries: (status?: string, offset?: number) => Promise<T.EmailDeliveryPage>;
    emailDelivery: (id: string, offset?: number) => Promise<T.EmailDeliveryDetail>;
    cancelDelivery: (id: string, body: object) => Promise<void>;
    retryDelivery: (id: string, body: object) => Promise<void>;
    incidents: (filter: {
        sessionId?: string;
        childId?: string;
        status: string;
        overdueOnly: boolean;
    }, offset?: number) => Promise<T.IncidentPage>;
    incidentVisits: (sessionId: string, search?: string, offset?: number) => Promise<{
        visits: T.IncidentVisit[];
        total: number;
    }>;
    reportIncident: (body: object) => Promise<{
        id: string;
    }>;
    accessIncident: (id: string, offset?: number) => Promise<T.IncidentPrivateView>;
    followUpIncident: (id: string, body: object) => Promise<void>;
    incidentOwners: (search?: string) => Promise<{
        id: string;
        name: string;
    }[]>;
    incidentGuardians: (id: string) => Promise<{
        authorityId: string;
        guardianId: string;
        name: string;
    }[]>;
    purgeIncidents: () => Promise<{
        purged: number;
    }>;
    communications: (childId: string | undefined, status: string, offset?: number) => Promise<T.CommunicationPage>;
    prepareCommunication: (body: object) => Promise<{
        id: string;
    }>;
    communication: (id: string) => Promise<T.GuardianCommunication>;
    recordContact: (id: string, body: object) => Promise<void>;
    teamPlanning: (filter: {
        from: string;
        until: string;
        programmeId?: string;
        locationId?: string;
        search?: string;
        state?: string;
    }, offset?: number) => Promise<T.TeamPlanningPage>;
    planningWorkers: (search?: string) => Promise<{
        workers: T.PlanningWorker[];
        total: number;
    }>;
    previewTeamPlan: (draft: T.TeamPlanDraft) => Promise<T.TeamPlanPreview>;
    applyTeamPlan: (draft: T.TeamPlanDraft, reason: string) => Promise<void>;
    safeguardingFollowUp: (filter: {
        programmeId?: string;
        search?: string;
        kind?: string;
        state?: string;
        days: number;
    }, offset?: number) => Promise<T.FollowUpPage>;
    previewSeries: (draft: T.SessionSeriesDraft) => Promise<T.SessionSeriesPreview>;
    createSeries: (requestId: string, draft: T.SessionSeriesDraft) => Promise<{
        id: string;
    }>;
    sessionSeries: (offset?: number) => Promise<T.SessionSeriesPage>;
    sessionSeriesDetail: (id: string) => Promise<T.SessionSeriesDetail>;
    cancelSeries: (id: string, expectedVersion: number, reason: string) => Promise<void>;
    attendanceReport: (filter: T.AttendanceReportFilter, offset?: number) => Promise<T.AttendanceReport>;
    attendanceTimeline: (id: string, offset?: number) => Promise<T.AttendanceReport>;
    exportAttendance: (filter: T.AttendanceReportFilter) => Promise<string>;
    programmes: (search?: string, offset?: number, signal?: AbortSignal) => Promise<T.Programme[]>;
    programme: (id: string) => Promise<T.Programme>;
    readiness: (programmeId: string, childId: string) => Promise<T.ChildReadinessView>;
    programmeChildren: (programmeId: string, search?: string, status?: string, offset?: number) => Promise<T.ProgrammeChildrenPage>;
    classes: (programmeId: string) => Promise<T.MinistryClass[]>;
    policy: (id: string) => Promise<T.ConsentPolicy>;
    enrolments: (childId: string, offset?: number) => Promise<T.Enrolment[]>;
    assignments: (childId: string, offset?: number) => Promise<T.ClassAssignment[]>;
    safeguarding: (childId: string) => Promise<T.SafeguardingView>;
    locations: (offset?: number) => Promise<T.Location[]>;
    rooms: (locationId: string) => Promise<T.Room[]>;
    sessions: (from: string, until: string, offset?: number, station?: boolean, signal?: AbortSignal) => Promise<T.SessionView[]>;
    closeout: (id: string) => Promise<T.SessionCloseout>;
    sessionDay: (id: string, view?: string, search?: string, offset?: number) => Promise<T.SessionDayPage>;
    session: (id: string, station?: boolean) => Promise<T.SessionView>;
    roster: (id: string, offset?: number, openOnly?: boolean) => Promise<{
        childName: string;
        id: string;
        childId: string;
        sessionId: string;
        arrivedAt: string;
        checkedOutAt: string | null;
        collectorId: string | null;
        authorizationId: string | null;
        identityVerified: boolean;
        exceptionUsed: boolean;
        labelVersion: number;
        labelExpiresAt: string | null;
        labelIssued: boolean;
        version: number;
    }[]>;
    expected: (id: string, offset?: number) => Promise<T.ExpectedChild[]>;
    attendance: (id: string) => Promise<T.AttendanceView>;
    collectors: (id: string) => Promise<T.PickupCollector[]>;
    history: (childId: string, offset?: number) => Promise<T.AttendanceView[]>;
    events: (id: string) => Promise<T.AttendanceEvent[]>;
    segments: (id: string) => Promise<T.AttendanceSegment[]>;
    worker: (id: string) => Promise<T.Worker>;
    createProgramme: (body: T.ChildrenCreateProgrammeRequest) => Promise<{
        id: string;
    }>;
    publishPolicy: (body: T.ChildrenPublishConsentPolicyRequest) => Promise<{
        id: string;
    }>;
    createClass: (body: T.ChildrenCreateClassRequest) => Promise<{
        id: string;
    }>;
    enrol: (body: T.ChildrenEnrolChildRequest) => Promise<{
        id: string;
    }>;
    assignClass: (body: T.ChildrenAssignClassRequest) => Promise<{
        id: string;
    }>;
    endEnrolment: (body: T.ChildrenEndEnrolmentRequest) => Promise<void>;
    endAssignment: (body: T.ChildrenEndClassAssignmentRequest) => Promise<void>;
    verifyGuardian: (body: T.ChildrenVerifyGuardianRequest) => Promise<{
        id: string;
    }>;
    recordConsent: (body: T.ChildrenRecordConsentRequest) => Promise<{
        id: string;
    }>;
    authorizePickup: (body: T.ChildrenAuthorizePickupRequest) => Promise<{
        id: string;
    }>;
    revokeGuardian: (body: T.ChildrenRevokeGuardianRequest) => Promise<void>;
    revokeConsent: (body: T.ChildrenRevokeConsentRequest) => Promise<void>;
    revokePickup: (body: T.ChildrenRevokePickupAuthorizationRequest) => Promise<void>;
    createLocation: (body: T.ChildrenCreateLocationRequest) => Promise<{
        id: string;
    }>;
    createRoom: (body: T.ChildrenCreateRoomRequest) => Promise<{
        id: string;
    }>;
    registerWorker: (body: T.ChildrenRegisterWorkerRequest) => Promise<void>;
    reviewWorker: (body: T.ChildrenReviewWorkerRequest) => Promise<void>;
    createSession: (body: T.ChildrenCreateSessionRequest) => Promise<{
        id: string;
    }>;
    staff: (body: T.ChildrenSetSessionStaffRequest) => Promise<void>;
    status: (body: T.ChildrenSetSessionStatusRequest) => Promise<void>;
    checkIn: (body: T.ChildrenCheckInChildRequest) => Promise<T.AttendanceView>;
    issueLabel: (body: T.ChildrenIssuePickupLabelRequest) => Promise<T.LabelIssue>;
    checkout: (body: T.ChildrenCheckOutChildRequest) => Promise<T.AttendanceView>;
    supervisorCheckout: (body: T.ChildrenSupervisorCheckoutRequest) => Promise<T.AttendanceView>;
    transfer: (body: T.ChildrenTransferChildRequest) => Promise<T.AttendanceView>;
    support: (childId: string, sessionId: string | null) => Promise<T.SupportRecord>;
    saveSupport: (body: T.ChildrenSaveChildSupportRequest) => Promise<void>;
    purgeSupport: (limit?: number) => Promise<number>;
};
