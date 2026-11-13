export interface CsvColumn<T> {
    header: string;
    value: (row: T) => string | number | null | undefined;
}
export declare function toCsv<T>(rows: T[], columns: CsvColumn<T>[]): string;
export declare function downloadCsv(filename: string, csv: string): void;
