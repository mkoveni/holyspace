/** Rands typed by a person ("250", "250.5", "250,50") to exact integer cents; null when it is not a valid amount. */
export declare function randsToMinor(value: string): number | null;
export declare function formatRand(minor: number): string;
