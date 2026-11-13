import type { PersonSummary } from './types';
export declare const membershipLabels: {
    ACTIVE_MEMBER: string;
    REGULAR_VISITOR: string;
    VISITOR: string;
    TRANSFERRED: string;
};
export declare const ageLabels: {
    CHILD: string;
    ADULT: string;
    UNKNOWN: string;
};
export declare const relationLabels: Record<string, string>;
export declare function fullName(person: PersonSummary): string;
export declare function initials(person: PersonSummary): string;
export declare function dateLabel(value?: string | null): string;
export declare function churchToday(timeZone?: string): string;
