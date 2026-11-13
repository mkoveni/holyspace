export type QuestionType = 'TEXT' | 'LONG_TEXT' | 'EMAIL' | 'NUMBER' | 'RATING' | 'DATE' | 'SINGLE_CHOICE' | 'MULTIPLE_CHOICE' | 'CHECKBOX';
export type Audience = 'PUBLIC' | 'MEMBERS' | 'STAFF' | 'GROUPS';
export type FormKind = 'DATA_COLLECTION' | 'QUIZ';
export type Identity = 'ANONYMOUS' | 'IDENTIFIED';
export declare const questionTypes: {
    value: QuestionType;
    title: string;
}[];
export interface Option {
    id: string;
    label: string;
}
/** A question as the respondent sees it (no answer key). */
export interface PublicQuestion {
    id: string;
    type: QuestionType;
    label: string;
    required: boolean;
    options: Option[];
}
/** A question as staff edit it; `correct` and `points` only apply to quizzes. */
export interface EditQuestion extends PublicQuestion {
    correct?: string[];
    points?: number;
    tolerance?: number;
}
export interface FormPage {
    id: string;
    title: string;
    description: string;
    kind: FormKind;
    identity: Identity;
    version: number;
    questions: PublicQuestion[];
    open: boolean;
    retentionMonths: number;
    leaderboard: boolean;
    challenge?: string;
}
export interface SubmitResult {
    received: boolean;
    score: number | null;
    maxScore: number | null;
    answers: Record<string, unknown> | null;
}
export interface Leaderboard {
    entries: {
        rank: number;
        nickname: string;
        score: number;
        maxScore: number;
    }[];
}
export interface MemberFormSummary {
    id: string;
    title: string;
    description: string;
    kind: FormKind;
    identity: Identity;
    closesAt: string | null;
}
export interface FormSummaryRow {
    id: string;
    title: string;
    kind: FormKind;
    audience: Audience;
    identity: Identity;
    status: 'DRAFT' | 'PUBLISHED' | 'CLOSED';
    responseCount: number;
    version: number;
}
export interface FormDetail {
    id: string;
    title: string;
    description: string;
    kind: FormKind;
    audience: Audience;
    identity: Identity;
    status: 'DRAFT' | 'PUBLISHED' | 'CLOSED';
    opensAt: string | null;
    closesAt: string | null;
    maxResponses: number | null;
    retentionMonths: number;
    leaderboardEnabled: boolean;
    leaderboardSize: number;
    showScore: boolean;
    showAnswers: boolean;
    groupIds: string[];
    definition: {
        questions: EditQuestion[];
    };
    publishedVersion: number;
    responseCount: number;
    nextPurgeOn: string | null;
    version: number;
}
export interface FormBody {
    title: string;
    description: string;
    kind: FormKind;
    audience: Audience;
    identity: Identity;
    opensAt: string | null;
    closesAt: string | null;
    maxResponses: number | null;
    retentionMonths: number;
    leaderboardEnabled: boolean;
    leaderboardSize: number;
    showScore: boolean;
    showAnswers: boolean;
    groupIds: string[];
    definition: {
        questions: EditQuestion[];
    };
}
export interface ResponseRow {
    id: string;
    formVersion: number;
    submittedOn: string;
    submittedAt: string | null;
    personId: string | null;
    nickname: string | null;
    score: number | null;
    maxScore: number | null;
    hidden: boolean;
    answers: Record<string, unknown>;
}
export interface ResponsePage {
    total: number;
    identified: boolean;
    items: ResponseRow[];
}
export interface Summary {
    responses: number;
    hidden: boolean;
    minimumForAnonymous: number;
    questions: Record<string, unknown>[];
}
export declare function newSubmissionKey(): string;
/** Removes blank answers so an unanswered optional question is not sent. */
export declare function cleanAnswers(answers: Record<string, unknown>): Record<string, unknown>;
/** Whether every required question has an answer (the server checks again). */
export declare function missingRequired(questions: PublicQuestion[], answers: Record<string, unknown>): string | null;
/** A starting point for a new question of the given type. */
export declare function blankQuestion(type?: QuestionType): EditQuestion;
export declare const staffForms: {
    list(limit?: number, offset?: number): Promise<FormSummaryRow[]>;
    get(id: string): Promise<FormDetail>;
    create(body: FormBody, requestId?: `${string}-${string}-${string}-${string}-${string}`): Promise<string>;
    update(id: string, body: FormBody, expectedVersion: number): Promise<void>;
    publish(id: string, expectedVersion: number): Promise<{
        token: string | null;
    }>;
    close(id: string, expectedVersion: number): Promise<void>;
    reopen(id: string, expectedVersion: number): Promise<void>;
    unpublish(id: string, expectedVersion: number): Promise<void>;
    responses(id: string, limit?: number, offset?: number): Promise<ResponsePage>;
    summary(id: string): Promise<Summary>;
    hide(id: string, responseId: string): Promise<void>;
    /** The CSV text of every response. */
    exportCsv(id: string): Promise<string>;
};
export declare const memberForms: {
    list(): Promise<MemberFormSummary[]>;
    get(id: string): Promise<FormPage>;
    submit(id: string, body: {
        answers: Record<string, unknown>;
        formVersion: number;
        submissionKey: string;
        nickname?: string;
    }): Promise<SubmitResult>;
    leaderboard(id: string): Promise<Leaderboard>;
};
export declare const publicForms: {
    get(token: string): Promise<FormPage>;
    submit(token: string, body: Record<string, unknown>): Promise<SubmitResult>;
    leaderboard(token: string): Promise<Leaderboard>;
};
