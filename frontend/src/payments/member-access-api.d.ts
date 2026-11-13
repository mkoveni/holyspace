export interface MemberLink {
    accountId: string;
    accountName: string;
    personId: string;
    personName: string;
    active: boolean;
    version: number;
}
export declare const memberAccessApi: {
    access: (offset?: number) => Promise<{
        links: MemberLink[];
        hasMore: boolean;
    }>;
    link: (account: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
};
