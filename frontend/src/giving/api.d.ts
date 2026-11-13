export interface Fund {
    id: string;
    name: string;
    description: string;
    active: boolean;
    version: number;
}
export interface GiftSummary {
    id: string;
    donorId: string | null;
    donorName: string;
    amountMinor: number;
    currency: string;
    receivedOn: string;
    method: string;
    externalReference: string | null;
    reversed: boolean;
    reconciled: boolean;
    refundedMinor?: number;
}
export interface GiftDetail {
    refundedMinor: number;
    gift: {
        id: string;
        donorId: string | null;
        money: {
            amountMinor: number;
            currency: string;
        };
        receivedOn: string;
        method: string;
        externalReference: string | null;
        allocations: {
            fundId: string;
            amountMinor: number;
        }[];
        createdBy: string;
        createdAt: string;
    };
    donorName: string;
    fundNames: Record<string, string>;
    reversal: {
        id: string;
        reversedOn: string;
        reason: string;
        createdBy: string;
        createdAt: string;
    } | null;
    history: {
        action: string;
        reason: string;
        actorId: string;
        occurredAt: string;
    }[];
}
export interface Total {
    currency: string;
    grossMinor: string;
    reversedMinor: string;
    netMinor: string;
}
export interface Filter {
    from: string;
    to: string;
    currency?: string;
    fundId?: string;
    donorId?: string;
    limit?: number;
    offset?: number;
}
export interface Pledge {
    id: string;
    donorId: string;
    fundId: string;
    target: {
        amountMinor: number;
        currency: string;
    };
    startsOn: string;
    endsOn: string;
    status: string;
    version: number;
    cancellationReason: string | null;
}
export interface PledgeSummary {
    pledge: Pledge;
    donorName: string;
    fundName: string;
    creditedMinor: number;
    remainingMinor: number;
    fulfilled: boolean;
}
export interface Assignment {
    assignment: {
        id: string;
        giftId: string;
        amountMinor: number;
        status: string;
        version: number;
        cancellationReason: string | null;
    };
    receivedOn: string;
    giftReversed: boolean;
}
export interface PledgeDetail {
    summary: PledgeSummary;
    assignments: Assignment[];
    history: GiftDetail['history'];
}
export interface PledgeCandidate {
    giftId: string;
    receivedOn: string;
    availableMinor: number;
    reference: string | null;
    method: string;
}
export interface Acknowledgement {
    id: string;
    giftId: string;
    number: string;
    donorName: string;
    issuedOn: string;
    receivedOn: string;
    amountMinor: number;
    currency: string;
    method: string;
    reference: string | null;
    voided: boolean;
    reversedOn: string | null;
    reversalReason: string | null;
    lines: {
        fundId: string;
        fundName: string;
        amountMinor: number;
    }[];
}
export interface LedgerLine {
    giftId: string;
    kind: string;
    date: string;
    donorId: string | null;
    donorName: string;
    fundId: string;
    fundName: string;
    currency: string;
    amountMinor: number;
    method: string;
    reference: string | null;
    reason: string;
}
export interface GivingReport {
    donorId: string | null;
    donorName: string;
    from: string;
    to: string;
    generatedAt: string;
    lines: LedgerLine[];
    totals: Total[];
}
export interface ReconciliationReview {
    id: string;
    version: number;
    outcome: string;
    note: string;
    reversedMinor: number;
    createdBy: string;
    createdAt: string;
}
export interface ReconciliationSummary {
    batch: {
        id: string;
        reference: string;
        expectedMinor: number;
        actualMinor: number;
        currency: string;
        reconciledOn: string;
        reason: string;
        giftIds: string[];
    };
    reversedMinor: number;
    currentNetMinor: number;
    version: number;
    review: ReconciliationReview | null;
    needsReview: boolean;
}
export interface ReconciliationDetail {
    summary: ReconciliationSummary;
    items: {
        giftId: string;
        donorName: string;
        receivedOn: string;
        amountMinor: number;
        reversed: boolean;
    }[];
    reviews: ReconciliationReview[];
}
export declare const givingApi: {
    funds(params: object): Promise<{
        items: Fund[];
        total: number;
    }>;
    createFund(body: object): Promise<{
        id: string;
    }>;
    updateFund(id: string, body: object): Promise<void>;
    gifts(params: Filter): Promise<{
        items: GiftSummary[];
        total: number;
    }>;
    totals(params: Filter): Promise<Total[]>;
    gift(id: string): Promise<GiftDetail>;
    record(body: object): Promise<{
        id: string;
    }>;
    reverse(id: string, body: object): Promise<{
        id: string;
    }>;
    pledges(params: object): Promise<{
        items: PledgeSummary[];
        total: number;
    }>;
    pledge(id: string, params?: object): Promise<PledgeDetail>;
    candidates(id: string, params?: object): Promise<PledgeCandidate[]>;
    createPledge(body: object): Promise<{
        id: string;
    }>;
    cancelPledge(id: string, body: object): Promise<void>;
    assign(id: string, body: object): Promise<{
        id: string;
    }>;
    cancelAssignment(id: string, body: object): Promise<void>;
    statement(id: string, from: string, to: string): Promise<GivingReport>;
    ledgerCsv(params: Filter): Promise<Blob>;
    acknowledgement(id: string): Promise<Acknowledgement>;
    giftAcknowledgement(id: string): Promise<Acknowledgement | null>;
    issueAcknowledgement(id: string, body: object): Promise<{
        id: string;
    }>;
    reconciliations(params: object): Promise<{
        items: ReconciliationSummary[];
        total: number;
    }>;
    reconciliation(id: string): Promise<ReconciliationDetail>;
    reconcile(body: object): Promise<{
        id: string;
    }>;
    reviewReconciliation(id: string, body: object): Promise<{
        id: string;
    }>;
};
