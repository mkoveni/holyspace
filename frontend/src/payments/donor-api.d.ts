import type { Checkout, Provider } from './api';
export interface OnlineFund {
    id: string;
    name: string;
    description: string;
    active: boolean;
    enabled: boolean;
    version: number;
}
export interface DonorOrder {
    id: string;
    fundId: string;
    amountMinor: number;
    provider: string;
    mode: string;
    recurring: boolean;
    status: string;
    subscriptionStatus: string;
    version: number;
    createdAt: string;
}
export interface OwnGift {
    id: string;
    amountMinor: number;
    currency: string;
    receivedOn: string;
    method: string;
    reversed: boolean;
    refundedMinor: number;
}
export interface MyGiving {
    donor: {
        id: string;
        name: string;
    } | null;
    funds: OnlineFund[];
    providers: Provider[];
    payments: {
        items: DonorOrder[];
        total: number;
    };
    gifts: OwnGift[];
    giftTotal: number;
}
export interface DonorDetail {
    order: DonorOrder;
    charges: {
        id: string;
        amountMinor: number;
        refundedMinor: number;
        receivedOn: string;
        givingStatus: string;
        confirmationStatus: string;
        captured: boolean;
        confirmation?: {
            subject: string;
            html: string;
        } | null;
    }[];
    refunds: {
        amountMinor: number;
        refundedOn: string;
        applied: boolean;
    }[];
}
export interface DonorLink {
    accountId: string;
    accountName: string;
    personId: string;
    personName: string;
    active: boolean;
    version: number;
}
export declare const donorApi: {
    overview: (paymentOffset?: number, giftOffset?: number) => Promise<MyGiving>;
    detail: (id: string) => Promise<DonorDetail>;
    start: (body: object) => Promise<{
        id: string;
    }>;
    checkout: (id: string) => Promise<Checkout>;
    subscription: (id: string, expectedVersion: number, cancel: boolean) => Promise<import("axios").AxiosResponse<any, {
        expectedVersion: number;
        cancel: boolean;
    }, {}, any>>;
    access: (offset?: number) => Promise<{
        links: DonorLink[];
        funds: OnlineFund[];
        hasMore: boolean;
    }>;
    link: (account: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
    /** The online-giving catalogue alone: needs giving.manage only (the donor-access list also needs identity.manage). */
    onlineFunds: () => Promise<{
        funds: OnlineFund[];
        truncated: boolean;
    }>;
    fund: (fund: OnlineFund) => Promise<import("axios").AxiosResponse<any, {
        enabled: boolean;
        expectedVersion: number;
    }, {}, any>>;
};
