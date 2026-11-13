export interface QuickAction {
    title: string;
    description: string;
    icon: string;
    to: string;
    permissions: string[];
}
export declare function visibleQuickActions(hasPermission: (permission: string) => boolean): QuickAction[];
