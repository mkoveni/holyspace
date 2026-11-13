export interface Volunteer {
    id: string;
    personId: string;
    accountId: string | null;
    status: string;
    version: number;
    availabilityRevision: number;
}
export interface Person {
    volunteer: Volunteer;
    name: string;
}
export interface Team {
    id: string;
    name: string;
    active: boolean;
    version: number;
}
export interface Role {
    id: string;
    teamId: string;
    name: string;
    childrenRole: string;
    active: boolean;
    requiredCredentials: string[];
}
export interface Membership {
    id: string;
    volunteerId: string;
    teamId: string;
    validFrom: string;
    validUntil: string;
    revokedAt: string | null;
}
export interface Approval {
    id: string;
    volunteerId: string;
    roleId: string;
    validFrom: string;
    validUntil: string;
    revokedAt: string | null;
}
export interface CredentialType {
    id: string;
    code: string;
    name: string;
}
export interface Credential {
    id: string;
    typeId: string;
    type: string;
    validFrom: string;
    validUntil: string;
    revokedAt: string | null;
}
export interface Weekly {
    dayOfWeek: number;
    startsAt: string;
    endsAt: string;
    timeZone: string;
    validFrom: string;
    validUntil: string;
}
export interface ExceptionWindow {
    startsAt: string;
    endsAt: string;
    available: boolean;
}
export interface Profile {
    person: Person;
    credentials: Credential[];
    weekly: Weekly[];
    exceptions: ExceptionWindow[];
}
export interface TeamDetails {
    team: Team;
    roles: Role[];
    memberships: Membership[];
    approvals: Approval[];
}
export interface Readiness {
    ready: boolean;
    issues: string[];
}
export interface Shift {
    id: string;
    teamId: string;
    roleId: string;
    sourceKind: string;
    sourceId: string | null;
    startsAt: string;
    endsAt: string;
    positions: number;
    status: string;
    version: number;
    scheduleRevision: number;
}
export interface Assignment {
    id: string;
    shiftId: string;
    volunteerId: string;
    status: string;
    version: number;
}
export interface InvitationView {
    invitation: {
        id: string;
        assignmentId: string;
        version: number;
        status: string;
        expiresAt: string;
    };
    effectiveStatus: string;
    team: string;
    role: string;
    startsAt: string;
    endsAt: string;
    readiness: Readiness;
}
export interface Booking {
    assignment: Assignment;
    name: string;
    readiness: Readiness;
    invitations: InvitationView[];
}
export interface ShiftView {
    shift: Shift;
    team: string;
    role: string;
    assignments: Booking[];
    reserved: number;
    confirmed: number;
    ready: number;
    vacancies: number;
}
export declare const servingApi: {
    volunteers: (search?: string, offset?: number) => Promise<{
        volunteers: Person[];
        total: number;
    }>;
    profile: (id: string) => Promise<Profile>;
    register: (body: object) => Promise<{
        id: string;
    }>;
    status: (id: string, body: object) => Promise<void>;
    types: () => Promise<CredentialType[]>;
    createType: (body: object) => Promise<void>;
    credential: (id: string, body: object) => Promise<void>;
    revokeCredential: (id: string, body: object) => Promise<void>;
    availability: (id: string, body: object) => Promise<void>;
    teams: (offset?: number) => Promise<{
        teams: Team[];
        total: number;
    }>;
    team: (id: string) => Promise<TeamDetails>;
    createTeam: (body: object) => Promise<{
        id: string;
    }>;
    updateTeam: (id: string, body: object) => Promise<void>;
    role: (id: string, body: object) => Promise<void>;
    join: (id: string, body: object) => Promise<void>;
    approve: (id: string, body: object) => Promise<void>;
    revoke: (id: string, body: object) => Promise<void>;
    shifts: (from: string, until: string, teamId?: string, offset?: number) => Promise<{
        shifts: ShiftView[];
        total: number;
    }>;
    shift: (id: string) => Promise<ShiftView>;
    createShift: (body: object) => Promise<{
        id: string;
    }>;
    bulkShifts: (body: object[]) => Promise<string[]>;
    updateShift: (id: string, body: object) => Promise<void>;
    assign: (body: object) => Promise<{
        id: string;
    }>;
    assignment: (id: string, body: object) => Promise<void>;
    readiness: (volunteerId: string, shiftId: string) => Promise<Readiness>;
    invite: (id: string, body: object) => Promise<void>;
    respond: (id: string, body: object) => Promise<void>;
    own: (offset?: number) => Promise<InvitationView[]>;
    expire: () => Promise<number>;
    history: (id: string, offset?: number) => Promise<{
        id: string;
        action: string;
        reason: string;
        occurredAt: string;
    }[]>;
    activities: (from: string, until: string, kind?: string, offset?: number) => Promise<{
        id: string;
        label: string;
        startsAt: string;
        endsAt: string;
        venueLabel?: string;
    }[]>;
};
export declare function servingError(error: unknown): string;
export declare function required(value: unknown): true | "This field is required.";
export declare function timeLabel(value: string): string;
