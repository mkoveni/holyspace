export { moneyLabel } from '@/workflows/money';
/** Short calendar date such as "13 Nov 2026". Empty or invalid values give an em dash. */
export declare function formatDate(value?: string | null): string;
/** Date with a time of day, for audit trails and deliveries. */
export declare function formatDateTime(value?: string | null): string;
/** "Ada Lovelace" -> "AL", for avatars and monograms. */
export declare function initialsOf(name?: string | null, fallback?: string): string;
