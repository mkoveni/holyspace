/**
 * Person profile sub-resources: addresses, communication options, education
 * and employment history.
 *
 * Responses and creation payloads use camelCase properties.
 */
export interface ProfileAddress {
    id: string;
    personId: string;
    type: string;
    line1: string;
    line2: string | null;
    city: string;
    province: string | null;
    postalCode: string | null;
    country: string;
    createdAt: string;
}
export interface AddAddressPayload {
    type: string;
    line1: string;
    line2?: string | null;
    city: string;
    province?: string | null;
    postalCode?: string | null;
    country: string;
}
export interface ProfileCommunicationOption {
    id: string;
    personId: string;
    channel: string;
    value: string;
    preferred: boolean;
    createdAt: string;
}
export interface AddCommunicationOptionPayload {
    channel: string;
    value: string;
    preferred?: boolean;
}
export interface ProfileEducationEntry {
    id: string;
    personId: string;
    institution: string;
    qualification: string;
    startDate: string | null;
    endDate: string | null;
    fieldOfStudy: string | null;
    notes: string | null;
    createdAt: string;
}
export interface AddEducationPayload {
    institution: string;
    qualification: string;
    startDate?: string | null;
    endDate?: string | null;
    fieldOfStudy?: string | null;
    notes?: string | null;
}
export interface ProfileEmploymentEntry {
    id: string;
    personId: string;
    employer: string;
    jobTitle: string;
    startDate: string | null;
    endDate: string | null;
    industry: string | null;
    workEmail: string | null;
    workPhone: string | null;
    notes: string | null;
    createdAt: string;
}
export interface AddEmploymentPayload {
    employer: string;
    jobTitle: string;
    startDate?: string | null;
    endDate?: string | null;
    industry?: string | null;
    workEmail?: string | null;
    workPhone?: string | null;
    notes?: string | null;
}
export interface PersonProfile {
    addresses: ProfileAddress[];
    communicationOptions: ProfileCommunicationOption[];
    education: ProfileEducationEntry[];
    employment: ProfileEmploymentEntry[];
}
