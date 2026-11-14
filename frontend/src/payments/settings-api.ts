import { useApi } from '@/composables/useApi'
export interface ConnectionCheck {
  id: string
  revisionId: string
  successful: boolean
  message: string
  checkedBy: string
  checkedAt: string
}
export interface Environment {
  mode: string
  revisionId: string | null
  publicUrl: string
  backendUrl: string
  yocoKeySet: boolean
  webhookSecretSet: boolean
  complete: boolean
  connection: ConnectionCheck | null
}
export interface ProviderSettings {
  provider: string
  mode: string
  enabled: boolean
  monthly: boolean
  managed: boolean
  version: number
}
export interface SettingsProvider {
  settings: ProviderSettings
  environments: Environment[]
}
export interface ConfigurationChange {
  provider: string
  mode: string
  version: number
  action: string
  note: string
  changedFields: string
  actorId: string
  actorName: string
  occurredAt: string
}
export interface Settings {
  encryptionReady: boolean
  providers: SettingsProvider[]
  history: ConfigurationChange[]
}
export interface WebhookListing {
  subscriptions: { id: string, name: string, url: string, mode: string }[]
  pending: {
    requestId: string
    kind: string
    targetId: string | null
    status: string
    createdAt: string
  }[]
}
export interface WebhookResult {
  requestId: string
  status: string
  message: string
}
export const paymentSettingsApi = {
  webhooks: async (mode: string) =>
    (
      await useApi().get<WebhookListing>(
        '/api/payments/settings/yoco/webhooks',
        { params: { mode } },
      )
    ).data,
  registerWebhook: async (body: object) =>
    (
      await useApi().post<WebhookResult>(
        '/api/payments/settings/yoco/webhooks',
        body,
      )
    ).data,
  removeWebhook: async (id: string, body: object) =>
    (
      await useApi().post<WebhookResult>(
        `/api/payments/settings/yoco/webhooks/${encodeURIComponent(id)}/removal`,
        body,
      )
    ).data,
  closeWebhook: async (id: string, body: object) =>
    useApi().post(
      `/api/payments/settings/yoco/webhook-operations/${id}/close`,
      body,
    ),
  get: async () =>
    (await useApi().get<Settings>('/api/payments/settings')).data,
  save: async (provider: string, body: object) =>
    useApi().put(`/api/payments/settings/${provider}`, body),
  check: async (provider: string, mode: string, expectedVersion: number) =>
    (
      await useApi().post<ConnectionCheck>(
        `/api/payments/settings/${provider}/connection-check`,
        { mode, expectedVersion },
        { timeout: 30_000 },
      )
    ).data,
}
