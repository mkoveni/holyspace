import type { Checkout } from './api';
export declare function hostedCheckout(checkout: Checkout): {
    url: string;
    post: boolean;
};
