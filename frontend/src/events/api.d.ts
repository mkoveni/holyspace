export interface Event {
    id: string;
    title: string;
    type: string;
    description: string;
    active: boolean;
    version: number;
}
export interface Venue {
    id: string;
    name: string;
    address: string;
    exclusive: boolean;
    active: boolean;
    version: number;
}
export interface Series {
    id: string;
    timeZone: string;
    frequency: string;
    interval: number;
    count: number;
    firstDate: string;
    startsAt: string;
    endsAt: string;
    endDayOffset: number;
    venueId: string;
    version: number;
}
export interface Occurrence {
    id: string;
    eventId: string;
    seriesId: string;
    originDate: string;
    timeZone: string;
    startsAt: string;
    endsAt: string;
    venueId: string;
    venueName: string;
    venueAddress: string;
    status: string;
    overridden: boolean;
    version: number;
    scheduleRevision: number;
}
export interface Counts {
    confirmed: number;
    waitlisted: number;
    cancelled: number;
}
export interface CalendarItem {
    occurrence: Occurrence;
    title: string;
    type: string;
    registrations: Counts;
}
export interface Policy {
    occurrenceId: string;
    open: boolean;
    capacity: number | null;
    waitlistEnabled: boolean;
    version: number;
}
export interface Details {
    occurrence: Occurrence;
    title: string;
    type: string;
    series: Series;
    registrationPolicy: Policy;
    registrations: Counts;
}
export interface Registration {
    id: string;
    personId: string;
    status: string;
    position: number;
    version: number;
}
export interface History {
    id: string;
    action: string;
    reason: string;
    occurredAt: string;
    venueName?: string;
    startsAt?: string;
    endsAt?: string;
}
export interface Schedule {
    venueId: string | null;
    timeZone: string;
    frequency: string;
    interval: number;
    count: number;
    firstDate: string;
    startsAt: string;
    endsAt: string;
    endDayOffset: number;
}
export interface SchedulePreview {
    hash: string;
    venue: Venue;
    dates: {
        originDate: string;
        startsAt: string;
        endsAt: string;
    }[];
}
export interface Change {
    occurrenceId: string;
    expectedVersion: number;
    action: string;
    scope: string;
    date: string;
    startsAt: string;
    endsAt: string;
    endDayOffset: number;
    venueId: string;
}
export interface ChangePreview {
    hash: string;
    changes: {
        before: Occurrence;
        after: Occurrence;
        registrations: Counts;
    }[];
    skipped: string[];
    confirmedAffected: number;
    waitlistedAffected: number;
    attendedAffected: number;
}
export declare const eventApi: {
    memberOffer: (id: string) => Promise<{
        enabled: boolean;
        version: number;
    }>;
    publishToMembers: (id: string, enabled: boolean, expectedVersion: number) => Promise<void>;
    scanMember: (id: string, requestId: string, credential: string) => Promise<{
        id: string;
    }>;
    readiness: (id: string) => Promise<EventReadiness>;
    closeout: (id: string, body: object) => Promise<void>;
    reopenCloseout: (id: string, body: object) => Promise<void>;
    closeoutHistory: (id: string, offset?: number) => Promise<CloseoutHistory[]>;
    events: (search?: string, offset?: number, includeArchived?: boolean) => Promise<{
        events: Event[];
        total: number;
    }>;
    event: (id: string, offset?: number) => Promise<{
        event: Event;
        series: Series[];
        totalSeries: number;
    }>;
    create: (body: object) => Promise<{
        id: string;
    }>;
    update: (id: string, body: object) => Promise<void>;
    addSeries: (id: string, body: object) => Promise<{
        id: string;
    }>;
    venues: (search?: string, offset?: number, includeArchived?: boolean, limit?: number) => Promise<{
        venues: Venue[];
        total: number;
    }>;
    createVenue: (body: object) => Promise<{
        id: string;
    }>;
    updateVenue: (id: string, body: object) => Promise<void>;
    preview: (schedule: Schedule) => Promise<SchedulePreview>;
    calendar: (params: object) => Promise<{
        occurrences: CalendarItem[];
        total: number;
    }>;
    occurrence: (id: string) => Promise<Details>;
    previewChange: (draft: Change) => Promise<ChangePreview>;
    change: (action: string, body: object) => Promise<void>;
    policy: (id: string, body: object) => Promise<void>;
    registrations: (id: string, status?: string, offset?: number) => Promise<{
        registrations: {
            registration: Registration;
            name: string;
        }[];
        total: number;
        counts: Counts;
    }>;
    register: (id: string, body: object) => Promise<{
        id: string;
    }>;
    cancelRegistration: (id: string, body: object) => Promise<void>;
    attendance: (id: string, search?: string, status?: string, offset?: number) => Promise<AttendancePage>;
    checkIn: (id: string, body: object) => Promise<{
        id: string;
    }>;
    walkIn: (id: string, body: object) => Promise<{
        registrationId: string;
        admitted: boolean;
        status: string;
    }>;
    visitLink: (id: string) => Promise<VisitLink>;
    configureVisitLink: (id: string, action: VisitLinkAction) => Promise<VisitLink>;
    visitors: (id: string, status?: string, offset?: number) => Promise<VisitorPage>;
    handOverVisitorChild: (id: string, decision: "HANDED_OVER" | "DISMISSED") => Promise<void>;
    reviewVisitor: (id: string, body: object) => Promise<void>;
    convertVisitor: (id: string, inviteToApp: boolean) => Promise<void>;
    correctAttendance: (id: string, body: object) => Promise<void>;
    attendanceHistory: (id: string, offset?: number) => Promise<AttendanceHistory[]>;
    report: (params: object) => Promise<EventReport>;
    exportReport: (params: object) => Promise<Blob>;
    messageContacts: (id: string) => Promise<{
        id: string;
        value: string;
    }[]>;
    reviewNotice: (id: string, body: object) => Promise<NoticeReview>;
    approveNotice: (id: string, body: object) => Promise<{
        id: string;
    }>;
    history: (kind: "occurrences" | "registrations", id: string, offset?: number) => Promise<History[]>;
};
export declare const eventTypes: string[];
export declare function required(value: unknown): true | "This field is required.";
export declare function dateLabel(value: string, zone?: string): string;
export declare function errorMessage(error: unknown): string;
export declare function newSchedule(): Schedule;
export interface AttendanceRecord {
    id: string;
    registrationId: string;
    arrivedAt: string;
    status: 'PRESENT' | 'VOIDED';
    version: number;
}
export interface AttendanceEntry {
    registration: Registration;
    attendance: AttendanceRecord | null;
    name: string;
    status: string;
}
export interface AttendanceCounts extends Counts {
    attended: number;
    absent: number;
}
export interface AttendancePage {
    attendees: AttendanceEntry[];
    total: number;
    counts: AttendanceCounts;
    admissionOpen: boolean;
}
export type VisitLinkAction = 'ENABLE' | 'DISABLE' | 'ROTATE' | 'MESSAGES_ON' | 'MESSAGES_OFF';
export interface VisitLink {
    occurrenceId: string;
    enabled: boolean;
    token: string | null;
    messages: boolean;
}
export interface VisitorChild {
    id: string;
    firstName: string;
    birthDate: string;
    handoverStatus: 'PENDING' | 'HANDED_OVER' | 'DISMISSED';
}
export interface Visitor {
    registrationId: string;
    fullName: string;
    mobile: string | null;
    email: string | null;
    howHeard: string | null;
    partyAdults: number | null;
    firstTime: boolean;
    reviewStatus: 'PENDING' | 'CONFIRMED' | 'REMOVED';
    checkedInAt: string;
    attendanceStatus: string;
    attendanceVersion: number | null;
    children: VisitorChild[];
}
export interface VisitorPage {
    total: number;
    pending: number;
    visitors: Visitor[];
}
export interface AttendanceHistory {
    id: string;
    actorId: string;
    action: string;
    reason: string;
    occurredAt: string;
}
export interface ReportRow {
    occurrenceId: string;
    eventId: string;
    title: string;
    startsAt: string;
    endsAt: string;
    timeZone: string;
    venue: string;
    status: string;
    counts: AttendanceCounts;
}
export interface EventReport {
    occurrences: ReportRow[];
    totals: AttendanceCounts & {
        occurrences: number;
    };
}
export interface NoticeReview {
    hash: string;
    recipient: string;
    subject: string;
    html: string;
    purpose: string;
    templateVersion: number;
}
export interface EventReadiness {
    checks: {
        key: string;
        title: string;
        status: string;
        detail: string;
    }[];
    staffing: {
        shifts: number;
        positions: number;
        confirmed: number;
        ready: number;
        proposed: number;
        gaps: number;
        complete: boolean;
    };
    review: {
        occurrence: Occurrence;
        attendance: AttendanceCounts;
        notices: {
            approved: number;
            submitted: number;
            delivered: number;
            pending: number;
            failed: number;
            captured: number;
            cancelled: number;
            remindersApproved: number;
            complete: boolean;
        };
        hash: string;
        canClose: boolean;
    };
    closeout: {
        occurrenceId: string;
        status: string;
        version: number;
        note: string;
        followUp: string;
        updatedAt: string | null;
        updatedBy: string | null;
    };
    changedSinceCloseout: boolean;
    assessedAt: string;
}
export interface CloseoutHistory {
    id: string;
    version: number;
    action: string;
    note: string;
    followUp: string;
    attended: number;
    absent: number;
    waitlisted: number;
    unresolvedNotices: number;
    actorId: string;
    actorName: string;
    occurredAt: string;
}
