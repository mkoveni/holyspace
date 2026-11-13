export interface MinistryDestination {
    key: string;
    title: string;
    to: string;
    icon: string;
    permissionsAny: string[];
}
export interface MinistryNavigationGroup {
    title: string;
    icon: string;
    items: MinistryDestination[];
}
export declare function ministryNavigation(hasPermission: (permission: string) => boolean): MinistryNavigationGroup[];
