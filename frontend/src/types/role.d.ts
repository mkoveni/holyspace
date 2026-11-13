export interface Role {
    id: string;
    name: string;
    description?: string;
    /** Permission codes granted by the role. */
    permissions?: string[];
}
export interface CreateRolePayload {
    name: string;
    description?: string;
}
