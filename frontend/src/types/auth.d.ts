export interface AuthenticatedUser {
    id: string;
    displayName?: string;
    email?: string;
    username: string;
    personId: string | null;
    status: string;
    roles: string[];
    permissions: string[];
    mfaEnabled?: boolean;
    mfaVerified?: boolean;
    createdAt: string;
    updatedAt: string;
}
export interface LoginCredentials {
    username: string;
    password: string;
}
export interface LoginResponse {
    token: string;
}
