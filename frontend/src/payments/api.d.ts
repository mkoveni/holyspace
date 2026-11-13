export interface Provider {
    id: string;
    name: string;
    available: boolean;
    mode: string;
    recurring: boolean;
}
export interface Payment {
    id: string;
    donorId: string;
    donorName: string;
    fundId: string;
    amountMinor: number;
    provider: string;
    mode: string;
    recurring: boolean;
    status: string;
    givingStatus?: string;
    subscriptionStatus: string;
    reviewReason: string | null;
    version: number;
    createdAt: string;
}
export interface Charge {
    id: string;
    reference: string;
    amountMinor: number;
    receivedOn: string;
    giftId: string | null;
}
export interface Review {
    version: number;
    settlementReference: string;
    feeMinor: number;
    bankMinor: number;
    refundedMinor: number;
    note: string;
}
export interface ChargeView {
    charge: Charge;
    refundedMinor: number;
    settlement: Review | null;
    needsReview: boolean;
    givingStatus?: string;
    settlementStatus?: string;
}
export interface Refund {
    id: string;
    chargeId: string | null;
    reference: string;
    amountMinor: number;
    refundedOn: string;
    applied: boolean;
    reason: string;
}
export interface Detail {
    order: Payment;
    charges: ChargeView[];
    refunds: Refund[];
    history: {
        action: string;
        note: string;
        occurredAt: string;
    }[];
}
export interface Checkout {
    id: string;
    url: string;
    fields: Record<string, string>;
}
export interface ReceiptReview {
    contacts: {
        id: string;
        value: string;
    }[];
    preview: {
        hash: string;
        recipient: string;
        subject: string;
        html: string;
        templateVersion: number;
    } | null;
    receiptId: string | null;
    delivery: {
        id: string;
        status: string;
        captured: boolean;
        version: number;
        errorCode: string;
    } | null;
}
export declare const paymentsApi: {
    retryPosting: (id: string, expectedVersion: number) => Promise<{
        complete: boolean;
        message: string;
    }>;
    receipt: (id: string, chargeId: string, contactId: string | null) => Promise<ReceiptReview>;
    approveReceipt: (id: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
    retryReceipt: (id: string, receiptId: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
    providers: () => Promise<Provider[]>;
    list: (offset: number, reviewOnly: boolean) => Promise<{
        items: Payment[];
        total: number;
    }>;
    detail: (id: string) => Promise<Detail>;
    start: (body: object) => Promise<{
        id: string;
    }>;
    checkout: (id: string) => Promise<Checkout>;
    refund: (id: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
    review: (id: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
    subscription: (id: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
};
