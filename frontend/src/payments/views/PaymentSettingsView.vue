<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="ADMINISTRATION · PAYMENTS"
      subtitle="Manage providers with protected credentials, deliberate activation and a clear history."
      title="Payment provider settings."
    />

    <v-alert
      v-if="error"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-alert
      v-if="success"
      class="mb-4"
      type="success"
      variant="tonal"
    >
      {{ success }}
    </v-alert>

    <v-alert
      v-if="settings && !settings?.encryptionReady"
      class="mb-4"
      type="warning"
      variant="tonal"
    >
      The server encryption key needs to be configured before credentials can be
      saved. The key stays on the server and is never entered here.
    </v-alert>

    <v-progress-linear
      v-if="loading"
      class="mb-4"
      indeterminate
    />

    <div
      v-if="settings"
      class="payment-settings-layout"
    >
      <aside
        aria-label="Payment providers"
        class="directory-surface payment-settings-providers"
      >
        <span class="directory-eyebrow">YOUR PROVIDERS</span>

        <button
          v-for="provider in settings.providers"
          :key="provider.settings.provider"
          :aria-pressed="providerId === provider.settings.provider"
          class="payment-provider-choice"
          :class="{
            'payment-provider-choice--active':
              providerId === provider.settings.provider,
          }"
          type="button"
          @click="selectProvider(provider.settings.provider)"
        >
          <v-icon
            :icon="
              provider.settings.provider === 'payfast'
                ? 'mdi-credit-card-outline'
                : 'mdi-contactless-payment-circle-outline'
            "
          />

          <span>
            <strong>{{ providerName(provider.settings.provider) }}</strong>

            <small>
              {{
                provider.settings.enabled
                  ? 'New checkouts enabled'
                  : provider.settings.managed
                    ? 'New checkouts disabled'
                    : 'Not configured in settings'
              }}
            </small>
          </span>

          <v-icon
            v-if="providerId === provider.settings.provider"
            icon="mdi-chevron-right"
          />
        </button>

        <p class="directory-muted mt-4">
          Existing payment records and subscriptions retain their original
          connection details.
        </p>
      </aside>

      <section
        v-if="current"
        class="directory-surface payment-settings-editor"
      >
        <div class="giving-section-heading">
          <div>
            <span class="directory-eyebrow">
              {{ providerName(providerId).toUpperCase() }} CONNECTION
            </span>

            <h2>Configure with confidence.</h2>
          </div>

          <v-chip :color="current.settings.enabled ? 'success' : undefined">
            {{
              current.settings.enabled
                ? 'Enabled · ' + modeLabel(current.settings.mode)
                : 'New checkouts off'
            }}
          </v-chip>
        </div>

        <v-alert
          v-if="dirty"
          class="mb-5"
          type="info"
          variant="tonal"
        >
          You have unsaved changes. Save with new checkouts disabled before
          testing changed connection details.
        </v-alert>

        <v-alert
          v-if="conflict"
          class="mb-4"
          type="warning"
          variant="tonal"
        >
          These settings changed. Reload to use the latest version.
          <v-btn
            class="ml-2"
            variant="text"
            @click="refresh"
          >
            Reload settings
          </v-btn>
        </v-alert>

        <v-form
          ref="form"
          @submit.prevent="save"
        >
          <div class="payment-settings-fields">
            <div>
              <h3>1. Choose the environment</h3>

              <p class="directory-muted mb-4">
                Test and live credentials are saved separately.
              </p>

              <v-btn-toggle
                color="primary"
                :disabled="busy"
                divided
                mandatory
                :model-value="mode"
                @update:model-value="selectMode"
              >
                <v-btn value="test">Test environment</v-btn>
                <v-btn value="live">Live environment</v-btn>
              </v-btn-toggle>
            </div>

            <v-text-field
              v-model="publicUrl"
              autocomplete="off"
              :disabled="busy"
              hint="Public HTTPS address of the frontend. Donors return here after checkout."
              label="Frontend website URL"
              persistent-hint
              placeholder="https://church.example"
              :rules="[publicOrigin]"
            />

            <v-text-field
              v-model="backendUrl"
              autocomplete="off"
              :disabled="busy"
              hint="Public HTTPS address of the backend API. Yoco and Payfast send payment notifications here."
              label="Backend API URL"
              persistent-hint
              placeholder="https://api.church.example"
              :rules="[publicOrigin]"
            />

            <div class="payment-callback">
              <span class="directory-eyebrow">BACKEND CALLBACK URL</span>

              <p>
                {{
                  callbackUrl ||
                    'Enter the backend API URL to see the callback URL.'
                }}
              </p>

              <v-btn
                v-if="callbackUrl"
                prepend-icon="mdi-content-copy"
                size="small"
                variant="text"
                @click="copyCallback"
              >
                Copy callback URL
              </v-btn>
            </div>

            <div>
              <h3>2. Save protected credentials</h3>

              <p class="directory-muted mb-4">
                Saved values stay hidden. Leave a field blank to keep its value
                for this environment.
              </p>

              <div class="payment-settings-fields">
                <template v-if="providerId === 'payfast'">
                  <v-text-field
                    v-model="merchantId"
                    autocomplete="new-password"
                    :disabled="busy"
                    :hint="savedHint(environment?.merchantIdSet)"
                    label="Merchant ID"
                    maxlength="1000"
                    persistent-hint
                    :placeholder="savedHint(environment?.merchantIdSet)"
                    type="password"
                  />

                  <v-text-field
                    v-model="merchantKey"
                    autocomplete="new-password"
                    :disabled="busy"
                    :hint="savedHint(environment?.merchantKeySet)"
                    label="Merchant key"
                    maxlength="1000"
                    persistent-hint
                    :placeholder="savedHint(environment?.merchantKeySet)"
                    type="password"
                  />

                  <v-text-field
                    v-model="passphrase"
                    autocomplete="new-password"
                    :disabled="busy"
                    :hint="savedHint(environment?.passphraseSet)"
                    label="Passphrase"
                    maxlength="1000"
                    persistent-hint
                    :placeholder="savedHint(environment?.passphraseSet)"
                    type="password"
                  />

                  <v-expansion-panels variant="accordion">
                    <v-expansion-panel title="Advanced callback protection">
                      <v-expansion-panel-text>
                        <v-textarea
                          v-model="ipRanges"
                          :disabled="busy"
                          hint="Comma-separated provider IP addresses or CIDR networks. Keep these aligned with Payfast's published networks."
                          label="Trusted Payfast notification networks"
                          maxlength="2000"
                          persistent-hint
                        />
                      </v-expansion-panel-text>
                    </v-expansion-panel>
                  </v-expansion-panels>
                </template>

                <template v-else>
                  <v-text-field
                    v-model="yocoKey"
                    autocomplete="new-password"
                    :disabled="busy"
                    :hint="`${savedHint(environment?.yocoKeySet)} Use the secret sk_${mode}_... key for this environment.`"
                    label="Yoco secret API key"
                    maxlength="1000"
                    persistent-hint
                    :placeholder="savedHint(environment?.yocoKeySet)"
                    type="password"
                  />

                  <v-text-field
                    v-model="webhookSecret"
                    autocomplete="new-password"
                    :disabled="busy"
                    :hint="`${savedHint(environment?.webhookSecretSet)} Use the complete whsec_... signing secret returned when registering this callback URL, including any trailing = characters. This is separate from your API key.`"
                    label="Webhook signing secret"
                    maxlength="1000"
                    persistent-hint
                    :placeholder="savedHint(environment?.webhookSecretSet)"
                    type="password"
                  />
                </template>
              </div>
            </div>

            <YocoWebhookPanel
              v-if="providerId === 'yoco'"
              :key="mode"
              :callback="callbackUrl"
              :disabled="busy || dirty || !environment?.revisionId"
              :enabled="Boolean(current?.settings.enabled)"
              :mode="mode"
              :version="current?.settings.version || 0"
              @changed="reloadWebhookSettings"
            />

            <div class="payment-settings-activation">
              <h3>3. Test, then activate</h3>

              <p class="directory-muted mb-4">
                Connection checks use the saved settings and never collect a
                payment.
              </p>

              <div class="giving-section-heading">
                <v-chip
                  :color="
                    checkPassed
                      ? 'success'
                      : environment?.connection
                        ? 'warning'
                        : undefined
                  "
                >
                  {{
                    checkPassed
                      ? 'Connection check passed'
                      : environment?.connection
                        ? 'Connection needs attention'
                        : 'Not checked'
                  }}
                </v-chip>

                <v-btn
                  :disabled="
                    busy ||
                      dirty ||
                      !environment?.revisionId ||
                      !settings?.encryptionReady
                  "
                  :loading="checking"
                  prepend-icon="mdi-connection"
                  variant="tonal"
                  @click="checkConnection"
                >
                  Test saved connection
                </v-btn>
              </div>

              <p
                v-if="environment?.connection"
                class="directory-muted"
              >
                {{ environment.connection.message }}
                <br>
                Checked
                {{
                  new Date(environment.connection.checkedAt).toLocaleString()
                }}
              </p>

              <v-switch
                v-model="enabled"
                color="primary"
                :disabled="busy || (!checkPassed && !current?.settings.enabled)"
                hide-details
                label="Enable new checkouts"
              />

              <v-checkbox
                v-if="providerId === 'payfast'"
                v-model="monthly"
                :disabled="busy"
                hide-details
                label="Offer monthly giving"
              />

              <p class="directory-muted mt-3">
                Disabling new checkouts does not cancel existing subscriptions.
                Keep earlier provider keys and webhook registrations valid while
                payments remain outstanding.
              </p>
            </div>

            <v-textarea
              v-model="reason"
              :disabled="busy"
              hint="Explain what changed. Keep credentials out of this note."
              label="Reason for this change"
              maxlength="1000"
              persistent-hint
              rows="2"
              :rules="[required]"
            />

            <div class="payment-settings-actions">
              <v-btn
                :disabled="busy"
                variant="text"
                @click="resetForm"
              >
                Discard changes
              </v-btn>

              <v-btn
                color="primary"
                :disabled="busy || conflict || !settings?.encryptionReady"
                :loading="saving"
                type="submit"
              >
                Save provider settings
              </v-btn>
            </div>
          </div>
        </v-form>
      </section>
    </div>

    <section
      v-if="settings"
      class="directory-surface giving-section mt-6"
    >
      <div class="giving-section-heading">
        <div>
          <span class="directory-eyebrow">ACCOUNTABILITY</span>
          <h2>Recent configuration activity</h2>
        </div>

        <v-btn
          :disabled="busy"
          variant="text"
          @click="refresh"
        >
          Refresh settings
        </v-btn>
      </div>

      <p
        v-if="settings.history.length === 0"
        class="directory-muted"
      >
        Configuration changes and connection checks will appear here.
      </p>

      <article
        v-for="(entry, index) in settings.history"
        :key="index"
        class="giving-evidence"
      >
        <strong>
          {{ providerName(entry.provider) }} · {{ modeLabel(entry.mode) }} ·
          {{
            entry.action === 'SETTINGS_SAVED'
              ? 'Settings updated'
              : 'Connection checked'
          }}
        </strong>

        <p>{{ entry.note }}</p>

        <p
          v-if="entry.changedFields"
          class="directory-muted"
        >
          {{ entry.changedFields }}
        </p>

        <small class="directory-muted">
          {{ new Date(entry.occurredAt).toLocaleString() }} · Account
          {{ entry.actorId }}
        </small>
      </article>
    </section>
  </div>
</template>
<script setup lang="ts">
  import type { Settings } from '../settings-api'
  import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import YocoWebhookPanel from '../components/YocoWebhookPanel.vue'
  import { paymentSettingsApi } from '../settings-api'
  import '@/giving/giving.css'
  const auth = useAuthStore(),
        settings = ref<Settings | null>(null),
        providerId = ref('payfast'),
        mode = ref('test'),
        loading = ref(false),
        saving = ref(false),
        checking = ref(false),
        error = ref(''),
        success = ref(''),
        conflict = ref(false)
  const enabled = ref(false),
        monthly = ref(false),
        publicUrl = ref(''),
        backendUrl = ref(''),
        ipRanges = ref(''),
        merchantId = ref(''),
        merchantKey = ref(''),
        passphrase = ref(''),
        yocoKey = ref(''),
        webhookSecret = ref(''),
        reason = ref('')
  const form = ref<{
          validate: () => Promise<{ valid: boolean }>
          resetValidation: () => void
        }>(),
        baseline = ref('')
  let generation = 0
  const busy = computed(() => loading.value || saving.value || checking.value)
  const current = computed(() =>
    settings.value?.providers.find(
      p => p.settings.provider === providerId.value,
    ),
  )
  const environment = computed(() =>
    current.value?.environments.find(e => e.mode === mode.value),
  )
  const dirty = computed(() => snapshot() !== baseline.value)
  const checkPassed = computed(() => {
    const check = environment.value?.connection
    if (!check?.successful) return false
    const elapsed = Date.now() - new Date(check.checkedAt).getTime()
    return elapsed < 24 * 60 * 60 * 1000
  })
  const callbackUrl = computed(() => {
    try {
      if (publicOrigin(backendUrl.value) !== true) return ''
      return (
        backendUrl.value.replace(/\/+$/, '')
        + `/api/payments/webhooks/${providerId.value}`
      )
    } catch {
      return ''
    }
  })
  function snapshot () {
    return JSON.stringify([
      mode.value,
      enabled.value,
      monthly.value,
      publicUrl.value,
      backendUrl.value,
      ipRanges.value,
      merchantId.value,
      merchantKey.value,
      passphrase.value,
      yocoKey.value,
      webhookSecret.value,
    ])
  }
  function providerName (id: string) {
    return id === 'payfast' ? 'Payfast' : 'Yoco'
  }
  function modeLabel (value: string) {
    return value === 'live' ? 'Live' : 'Test'
  }
  function savedHint (saved: boolean | undefined) {
    return saved
      ? 'Saved securely · leave blank to keep'
      : 'Not saved · enter a value'
  }
  function publicOrigin (value: string) {
    try {
      const uri = new URL(value)
      return (
        (uri.protocol === 'https:'
          && !uri.username
          && !uri.password
          && !uri.search
          && !uri.hash
          && (uri.pathname === '/' || uri.pathname === ''))
        || 'Enter a public HTTPS address without a page path.'
      )
    } catch {
      return 'Enter a public HTTPS address without a page path.'
    }
  }
  function required (value: string) {
    return Boolean(value.trim()) || 'Explain this change.'
  }
  function clearSecrets () {
    merchantId.value = ''
    merchantKey.value = ''
    passphrase.value = ''
    yocoKey.value = ''
    webhookSecret.value = ''
  }
  function resetForm () {
    const env = environment.value
    const savedSettings = current.value?.settings
    enabled.value
      = savedSettings?.mode === mode.value ? Boolean(savedSettings.enabled) : false
    monthly.value = current.value?.settings.monthly || false
    publicUrl.value = env?.publicUrl || ''
    backendUrl.value = env?.backendUrl ?? env?.publicUrl ?? ''
    ipRanges.value
      = env?.ipRanges
        || (providerId.value === 'payfast' ? '197.97.145.144/28,41.74.179.192/27' : '')
    clearSecrets()
    reason.value = ''
    conflict.value = false
    baseline.value = snapshot()
    void nextTick(() => form.value?.resetValidation())
  }
  function selectProvider (value: string) {
    if (busy.value) return
    providerId.value = value
    mode.value = current.value?.settings.mode || 'test'
    resetForm()
    error.value = ''
    success.value = ''
  }
  function selectMode (value: string) {
    if (busy.value || !['test', 'live'].includes(value)) return
    mode.value = value
    resetForm()
    error.value = ''
    success.value = ''
  }
  async function refresh () {
    const run = ++generation
    loading.value = true
    error.value = ''
    try {
      const loaded = await paymentSettingsApi.get()
      if (run !== generation) return
      settings.value = loaded
      resetForm()
    } catch (error_) {
      if (run === generation) error.value = extractErrorMessage(error_)
    } finally {
      if (run === generation) loading.value = false
    }
  }
  function isStaleSettings (e: unknown, message: string) {
    const status = (e as { response?: { status?: number } })?.response?.status
    return (
      status === 409
      && /settings changed|during the check|current saved settings/i.test(message)
    )
  }
  async function save () {
    if (busy.value || !current.value || !(await form.value?.validate())?.valid)
      return
    saving.value = true
    error.value = ''
    success.value = ''
    try {
      await paymentSettingsApi.save(providerId.value, {
        expectedVersion: current.value.settings.version,
        mode: mode.value,
        enabled: enabled.value,
        monthly: monthly.value,
        publicUrl: publicUrl.value,
        backendUrl: backendUrl.value,
        ipRanges: ipRanges.value,
        credentials: {
          merchantId: merchantId.value,
          merchantKey: merchantKey.value,
          passphrase: passphrase.value,
          yocoKey: yocoKey.value,
          webhookSecret: webhookSecret.value,
        },
        reason: reason.value,
      })
      clearSecrets()
      await refresh()
      success.value
        = 'Provider settings saved. Earlier payments retain their original connection details.'
    } catch (error_) {
      error.value = extractErrorMessage(error_)
      conflict.value = isStaleSettings(error_, error.value)
    } finally {
      saving.value = false
    }
  }
  async function reloadWebhookSettings () {
    await refresh()
    resetForm()
  }
  async function checkConnection () {
    if (busy.value || dirty.value || !current.value) return
    checking.value = true
    error.value = ''
    success.value = ''
    try {
      const result = await paymentSettingsApi.check(
        providerId.value,
        mode.value,
        current.value.settings.version,
      )
      await refresh()
      if (result.successful) success.value = result.message
      else error.value = result.message
    } catch (error_) {
      error.value = extractErrorMessage(error_)
      conflict.value = isStaleSettings(error_, error.value)
    } finally {
      checking.value = false
    }
  }
  async function copyCallback () {
    try {
      await navigator.clipboard.writeText(callbackUrl.value)
      success.value = 'Callback URL copied.'
    } catch {
      error.value = 'Copy the callback URL shown above.'
    }
  }
  watch(
    () => auth.token,
    () => clearSecrets(),
  )
  onMounted(async () => {
    await refresh()
    if (current.value) {
      mode.value = current.value.settings.mode
      resetForm()
    }
  })
  onBeforeUnmount(() => {
    generation++
    clearSecrets()
  })
</script>
<style scoped>
.payment-settings-layout {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 24px;
  align-items: start;
}
.payment-settings-providers,
.payment-settings-editor {
  padding: clamp(20px, 3vw, 32px);
}
.payment-settings-fields {
  display: grid;
  gap: 28px;
}
.payment-provider-choice {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 18px 12px;
  margin-top: 14px;
  border-radius: 14px;
  text-align: left;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
}
.payment-provider-choice span {
  flex: 1;
}
.payment-provider-choice strong,
.payment-provider-choice small {
  display: block;
}
.payment-provider-choice small {
  color: rgb(var(--v-theme-on-surface-variant));
  margin-top: 4px;
}
.payment-provider-choice--active {
  background: rgba(var(--v-theme-primary), 0.06);
  border-color: rgba(var(--v-theme-primary), 0.25);
}
.payment-settings-actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 12px;
}
.payment-callback,
.payment-settings-activation {
  padding: 20px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 16px;
  background: rgba(var(--v-theme-primary), 0.025);
}
.payment-callback p {
  margin: 10px 0;
  overflow-wrap: anywhere;
}
@media (max-width: 960px) {
  .payment-settings-layout {
    grid-template-columns: 1fr;
  }
  .payment-settings-providers {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }
  .payment-settings-providers > .directory-eyebrow,
  .payment-settings-providers > p {
    width: 100%;
  }
  .payment-provider-choice {
    flex: 1;
    min-width: min(100%, 220px);
    margin: 0;
  }
  .payment-settings-editor {
    min-width: 0;
  }
}
@media (max-width: 600px) {
  .payment-settings-actions > .v-btn {
    width: 100%;
  }
  .payment-settings-editor h2 {
    font-size: 1.5rem;
  }
  .v-btn-toggle {
    max-width: 100%;
  }
}
</style>
