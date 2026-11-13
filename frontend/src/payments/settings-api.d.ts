export interface ConnectionCheck {
    id: string;
    revisionId: string;
    successful: boolean;
    message: string;
    checkedBy: string;
    checkedAt: string;
}
export interface Environment {
    mode: string;
    revisionId: string | null;
    publicUrl: string;
    backendUrl: string;
    ipRanges: string;
    merchantIdSet: boolean;
    merchantKeySet: boolean;
    passphraseSet: boolean;
    yocoKeySet: boolean;
    webhookSecretSet: boolean;
    complete: boolean;
    connection: ConnectionCheck | null;
}
export interface ProviderSettings {
    provider: string;
    mode: string;
    enabled: boolean;
    monthly: boolean;
    managed: boolean;
    version: number;
}
export interface SettingsProvider {
    settings: ProviderSettings;
    environments: Environment[];
}
export interface ConfigurationChange {
    provider: string;
    mode: string;
    version: number;
    action: string;
    note: string;
    changedFields: string;
    actorId: string;
    actorName: string;
    occurredAt: string;
}
export interface Settings {
    encryptionReady: boolean;
    providers: SettingsProvider[];
    history: ConfigurationChange[];
}
export interface WebhookListing {
    subscriptions: {
        id: string;
        name: string;
        url: string;
        mode: string;
    }[];
    pending: {
        requestId: string;
        kind: string;
        targetId: string | null;
        status: string;
        createdAt: string;
    }[];
}
export interface WebhookResult {
    requestId: string;
    status: string;
    message: string;
}
export declare const paymentSettingsApi: {
    webhooks: (mode: string) => Promise<WebhookListing>;
    registerWebhook: (body: object) => Promise<WebhookResult>;
    removeWebhook: (id: string, body: object) => Promise<WebhookResult>;
    closeWebhook: (id: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
    get: () => Promise<Settings>;
    save: (provider: string, body: object) => Promise<import("axios").AxiosResponse<any, object, {}, any>>;
    check: (provider: string, mode: string, expectedVersion: number) => Promise<ConnectionCheck>;
};
