export declare const personGenders: readonly ["male", "female", "other", "unspecified"];
export type PersonGender = typeof personGenders[number];
export declare const membershipStatuses: readonly ["prospect", "active", "inactive", "transferred", "deceased"];
export type MembershipStatus = typeof membershipStatuses[number];
export interface Person {
    id: string;
    firstName: string;
    lastName: string;
    dateOfBirth: string | null;
    gender: PersonGender;
    email: string | null;
    phone: string | null;
    status: MembershipStatus;
    createdAt: string;
    updatedAt: string;
}
export interface CreatePersonPayload {
    firstName: string;
    lastName: string;
    dateOfBirth?: string | null;
    gender?: PersonGender;
    email?: string | null;
    phone?: string | null;
}
export type UpdatePersonPayload = Required<CreatePersonPayload>;
export interface ChangePersonStatusPayload {
    status: MembershipStatus;
}
