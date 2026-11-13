import type { QuickAction } from './quickActions';
export interface PaletteEntry {
    id: string;
    title: string;
    subtitle: string;
    icon: string;
    to: string;
    kind: 'Action' | 'Page' | 'Person';
}
export declare function pageEntries(hasPermission: (permission: string) => boolean): PaletteEntry[];
export declare function actionEntries(actions: QuickAction[]): PaletteEntry[];
/** Rank entries: prefix matches first, then substring matches; empty query keeps order. */
export declare function filterEntries(entries: PaletteEntry[], query: string, limit?: number): PaletteEntry[];
