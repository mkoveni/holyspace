export type FamilyQr = {
    childId: string;
} | {
    pickupCode: string;
};
export declare function parseFamilyQr(raw: string, kind: 'arrival' | 'collection', resourceId: string): FamilyQr;
