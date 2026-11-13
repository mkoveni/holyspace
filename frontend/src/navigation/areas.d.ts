export interface AreaItem {
    title: string;
    to: string;
}
export interface NavigationArea {
    key: string;
    title: string;
    icon: string;
    /** Where the sidebar link goes: the first page the person may open. */
    to: string;
    /** Sibling pages, shown as tabs at the top of every page in the area. */
    items: AreaItem[];
}
/** Collapse the detailed navigation into a handful of top-level areas. */
export declare function visibleAreas(hasPermission: (permission: string) => boolean): NavigationArea[];
/** The best-matching page in an area for a path (longest destination wins). */
export declare function activeAreaItem(area: NavigationArea, path: string): string;
