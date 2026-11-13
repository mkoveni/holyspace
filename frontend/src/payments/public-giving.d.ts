import type { Checkout } from './api';
export { formatRand, randsToMinor } from './giving-amount';
export interface PublicGivingOptions {
    funds: {
        id: string;
        name: string;
        description: string;
    }[];
    providers: {
        id: string;
        name: string;
    }[];
    minimumAmountMinor: number;
}
export interface PublicCheckout {
    id: string;
    accessToken: string | null;
    checkout: Checkout | null;
}
export type GuestState = 'AWAITING_PAYMENT' | 'PAID' | 'FAILED' | 'TEST_PAYMENT';
export interface GuestStatus {
    id: string;
    state: GuestState;
    amountMinor: number;
    confirmationQueued: boolean;
}
export declare const tokenKey: (orderId: string) => string;
export declare const publicGiving: {
    options: () => Promise<PublicGivingOptions>;
    start: (body: {
        requestId: string;
        fundId: string;
        amountMinor: number;
        provider: string;
        email: string;
        name: string;
        humanToken: string | null;
        website: string;
    }) => Promise<PublicCheckout>;
    status: (accessToken: string) => Promise<GuestStatus>;
};
/** Sends the giver to the provider's hosted page; card details are only ever entered there. */
export declare function launchCheckout(checkout: Checkout): void;
