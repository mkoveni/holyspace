export declare function required(value: unknown): true | "This field is required.";
export declare function positive(value: unknown): true | "Enter a whole number greater than zero.";
export declare function dayAfter(day: string): string;
export declare function lastDay(day: string): string;
export declare function localInput(date?: Date): string;
export declare function instant(value: string): string;
export declare function timeLabel(value: string, timeZone?: string): string;
export declare function sessionRange(day: string): {
    from: string;
    until: string;
};
export declare function safeLink(value: string): string | undefined;
export declare function statusLabel(value: string): string;
export declare function documentHash(file: File): Promise<string>;
export declare function validPeriod(from: string, until: string): void;
export { dateLabel } from '@/directory/presentation';
