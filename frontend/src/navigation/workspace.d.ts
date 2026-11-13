interface NavigationItem {
    title: string;
    to: string;
    permission?: string;
    permissionsAny?: string[];
    permissionsAll?: string[];
}
interface NavigationGroup {
    title: string;
    icon: string;
    items: NavigationItem[];
}
export declare function visibleNavigation(hasPermission: (permission: string) => boolean): NavigationGroup[];
export declare function routeMatches(path: string, destination: string): boolean;
export declare function navigationIcon(to: string): string;
export {};
