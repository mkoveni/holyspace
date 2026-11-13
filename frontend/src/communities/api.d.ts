import type { Schedule } from '@/events/api';
export { dateLabel, errorMessage, newSchedule, required } from '@/events/api';
export interface Community {
    id: string;
    kind: string;
    name: string;
    description: string;
    parentId: string | null;
    campus: string;
    active: boolean;
    version: number;
}
export interface Membership {
    id: string;
    communityId: string;
    personId: string;
    startedAt: string;
    endedAt: string | null;
    version: number;
}
export interface Member {
    membership: Membership;
    name: string;
}
export interface Leadership {
    id: string;
    membershipId: string;
    communityId: string;
    accountId: string | null;
    role: string;
    startedAt: string;
    endedAt: string | null;
    version: number;
}
export interface Counts {
    meetingId: string;
    expected: number;
    present: number;
    absent: number;
    excused: number;
    unrecorded: number;
    guests: number;
}
export interface Meeting {
    meeting: {
        id: string;
        communityId: string;
        occurrenceId: string;
        status: string;
        rosterFrozen: boolean;
        version: number;
    };
    date: {
        id: string;
        startsAt: string;
        endsAt: string;
        timeZone: string;
        venue: string;
        status: string;
    };
    counts: Counts;
}
export interface Roster {
    id: string;
    meetingId: string;
    personId: string;
    membershipId: string | null;
    guest: boolean;
    status: string;
    version: number;
    name: string;
}
export interface FollowUp {
    id: string;
    communityId: string;
    personId: string;
    name: string;
    note: string;
    dueOn: string;
    status: string;
    version: number;
}
export interface History {
    id: string;
    targetId: string;
    action: string;
    reason: string;
    actorId: string;
    occurredAt: string;
}
export interface NoticeReview {
    hash: string;
    recipient: string;
    subject: string;
    html: string;
    templateVersion: number;
}
export interface MemberDelivery {
    id: string;
    status: string;
    scheduledAt: string;
    attempts: number;
    errorCode: string;
    captured: boolean;
}
export declare const communityApi: {
    deliveries: (id: string, offset?: number) => Promise<MemberDelivery[]>;
    directory: (params: object) => Promise<{
        communities: Community[];
        total: number;
    }>;
    create: (body: object) => Promise<{
        id: string;
    }>;
    detail: (id: string) => Promise<{
        community: Community;
        canOperate: boolean;
    }>;
    update: (id: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
    members: (id: string, ended?: boolean, offset?: number) => Promise<{
        members: Member[];
        total: number;
    }>;
    join: (id: string, personId: string, requestId: string) => Promise<void>;
    leave: (id: string, body: object) => Promise<void>;
    leadership: (id: string, offset?: number) => Promise<{
        leadership: Leadership;
        accountName: string;
    }[]>;
    appoint: (id: string, body: object) => Promise<void>;
    endLeadership: (id: string, body: object) => Promise<void>;
    meetings: (id: string, offset?: number) => Promise<{
        meetings: Meeting[];
        total: number;
    }>;
    preview: (id: string, schedule: Schedule) => Promise<{
        hash: string;
        dates: Meeting["date"][];
    }>;
    schedule: (id: string, body: object) => Promise<void>;
    prepare: (id: string, expectedVersion: number) => Promise<void>;
    roster: (id: string, offset?: number) => Promise<{
        roster: Roster[];
        total: number;
        counts: Counts;
    }>;
    attendance: (id: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
    guest: (id: string, body: object) => Promise<void>;
    finish: (id: string, body: object) => Promise<void>;
    followups: (id: string, completed?: boolean, offset?: number) => Promise<FollowUp[]>;
    followup: (id: string, body: object) => Promise<void>;
    complete: (id: string, body: object) => Promise<void>;
    history: (id: string, offset?: number) => Promise<History[]>;
    contacts: (id: string) => Promise<{
        id: string;
        value: string;
    }[]>;
    reviewNotice: (id: string, body: object) => Promise<NoticeReview>;
    approveNotice: (id: string, body: object) => Promise<{
        id: string;
    }>;
};
