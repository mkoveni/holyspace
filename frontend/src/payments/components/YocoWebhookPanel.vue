<template>
  <section class="webhook-panel">
    <div class="giving-section-heading">
      <div>
        <h3>Yoco webhooks</h3>

        <p class="directory-muted">
          Manage subscriptions for the saved {{ mode }} environment.
        </p>
      </div>

      <v-btn
        :disabled="disabled || working"
        variant="text"
        @click="load"
      >
        Load webhooks
      </v-btn>
    </div>

    <v-alert
      v-if="message"
      class="my-3"
      :type="failed ? 'warning' : 'success'"
      variant="tonal"
    >
      {{ message }}
    </v-alert>

    <p
      v-if="disabled"
      class="directory-muted"
    >
      Save the backend URL and API key before managing webhooks.
    </p>

    <template v-if="listing">
      <p
        v-if="listing.subscriptions.length === 0"
        class="directory-muted"
      >
        No subscriptions found for this environment.
      </p>

      <article
        v-for="entry in listing.subscriptions"
        :key="entry.id"
        class="webhook-row"
      >
        <div>
          <strong>{{ entry.name }}</strong>
          <p>{{ entry.url }}</p>
          <small>{{ entry.id }}</small>
        </div>

        <v-btn
          color="error"
          :disabled="disabled || working"
          variant="text"
          @click="begin('REMOVE', entry.id, entry.url)"
        >
          Remove
        </v-btn>
      </article>

      <article
        v-for="pending in listing.pending"
        :key="pending.requestId"
        class="webhook-row"
      >
        <div>
          <strong>
            {{ pending.kind === 'REGISTER' ? 'Registration' : 'Removal' }} ·
            {{ pending.status.toLowerCase() }}
          </strong>

          <p>Request {{ pending.requestId }}</p>
        </div>

        <v-btn
          v-if="pending.status === 'READY'"
          :disabled="disabled || working"
          variant="tonal"
          @click="begin('REGISTER', '', '', pending.requestId)"
        >
          Finish saving registration
        </v-btn>

        <v-btn
          v-else-if="pending.kind === 'REMOVE' && pending.status === 'UNKNOWN'"
          :disabled="disabled || working"
          variant="tonal"
          @click="
            begin('REMOVE', pending.targetId || '', '', pending.requestId)
          "
        >
          Recheck removal
        </v-btn>

        <v-btn
          v-if="pending.status !== 'STARTED' || interrupted(pending.createdAt)"
          :disabled="disabled || working"
          variant="text"
          @click="begin('CLOSE', '', '', pending.requestId)"
        >
          Close after provider review
        </v-btn>

        <p
          v-else
          class="directory-muted"
        >
          In progress. If interrupted, reload after five minutes to close it
          after provider review.
        </p>
      </article>
    </template>

    <v-btn
      class="mt-3"
      :disabled="disabled || enabled || working"
      prepend-icon="mdi-webhook"
      variant="tonal"
      @click="begin('REGISTER')"
    >
      Register saved callback
    </v-btn>

    <p
      v-if="enabled"
      class="directory-muted mt-2"
    >
      Disable new checkouts and save before registering a replacement.
    </p>

    <v-dialog
      v-model="open"
      max-width="620"
      :persistent="working"
    >
      <v-card rounded="xl">
        <v-card-title>
          {{
            kind === 'REGISTER'
              ? 'Register Yoco callback'
              : kind === 'REMOVE'
                ? 'Remove Yoco subscription'
                : 'Close webhook operation'
          }}
        </v-card-title>

        <v-card-text>
          <p class="mb-3">
            {{
              kind === 'REGISTER'
                ? 'The saved backend callback will be registered. Its signing secret is encrypted and saved automatically. Test the connection afterwards.'
                : kind === 'REMOVE'
                  ? 'Yoco will stop sending notifications to this subscription. This disables new checkouts and requires a fresh connection test. Callbacks referenced by existing payments cannot be removed here.'
                  : 'Review the provider subscription list first. Closing does not change Yoco; it disables new checkouts and requires a fresh connection check.'
            }}
          </p>

          <p class="webhook-address">{{ targetUrl || callback }}</p>

          <v-textarea
            v-model="reason"
            class="mt-3"
            label="Webhook change reason"
            maxlength="500"
          />

          <v-checkbox
            v-if="kind !== 'REGISTER'"
            v-model="reviewed"
            hide-details
            label="I reviewed this subscription and its use by other integrations"
          />

          <v-alert
            v-if="dialogError"
            class="mt-3"
            type="error"
            variant="tonal"
          >
            {{ dialogError }}
          </v-alert>
        </v-card-text>

        <v-card-actions class="pa-4">
          <v-spacer />

          <v-btn
            :disabled="working"
            @click="open = false"
          >
            Cancel
          </v-btn>

          <v-btn
            color="primary"
            :disabled="!reason.trim() || (kind !== 'REGISTER' && !reviewed)"
            :loading="working"
            variant="flat"
            @click="change"
          >
            {{
              kind === 'REGISTER'
                ? 'Register and save securely'
                : kind === 'REMOVE'
                  ? 'Remove subscription'
                  : 'Confirm provider review'
            }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </section>
</template>
<script setup lang="ts">
  import { ref } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { paymentSettingsApi, type WebhookListing } from '../settings-api'
  const props = defineProps<{
    mode: string
    version: number
    callback: string
    disabled: boolean
    enabled: boolean
  }>()
  const emit = defineEmits<{ changed: [] }>()
  const listing = ref<WebhookListing | null>(null),
        working = ref(false),
        message = ref(''),
        failed = ref(false)
  const open = ref(false),
        kind = ref('REGISTER'),
        target = ref(''),
        targetUrl = ref(''),
        reason = ref(''),
        reviewed = ref(false),
        dialogError = ref('')
  let requestId: string = crypto.randomUUID()
  function interrupted (createdAt: string) {
    return Date.now() - Date.parse(createdAt) >= 300_000
  }
  async function load () {
    if (props.disabled || working.value) return
    working.value = true
    message.value = ''
    try {
      listing.value = await paymentSettingsApi.webhooks(props.mode)
    } catch (error) {
      message.value = extractErrorMessage(error)
      failed.value = true
    } finally {
      working.value = false
    }
  }
  function begin (operation: string, id = '', url = '', request?: string) {
    kind.value = operation
    target.value = id
    targetUrl.value = url
    reason.value = ''
    reviewed.value = false
    dialogError.value = ''
    requestId = request || crypto.randomUUID()
    open.value = true
  }
  async function change () {
    if (
      props.disabled
      || working.value
      || !reason.value.trim()
      || (kind.value !== 'REGISTER' && !reviewed.value)
    )
      return
    working.value = true
    dialogError.value = ''
    try {
      const body = {
        requestId,
        mode: props.mode,
        expectedVersion: props.version,
        reason: reason.value,
      }
      if (kind.value === 'CLOSE') {
        await paymentSettingsApi.closeWebhook(requestId, body)
        message.value
          = 'Provider review recorded. Test the saved connection before activation.'
        failed.value = false
      } else {
        const result
          = kind.value === 'REGISTER'
            ? await paymentSettingsApi.registerWebhook(body)
            : await paymentSettingsApi.removeWebhook(target.value, body)
        message.value = result.message
        failed.value = result.status !== 'APPLIED'
      }
      open.value = false
      listing.value = await paymentSettingsApi.webhooks(props.mode)
      emit('changed')
    } catch (error) {
      dialogError.value = extractErrorMessage(error)
      emit('changed')
    } finally {
      working.value = false
    }
  }
</script>
<style scoped>
.webhook-panel {
  border: 1px solid var(--workspace-border);
  border-radius: 18px;
  padding: 20px;
}
.webhook-row {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  padding: 16px 0;
  border-bottom: 1px solid var(--workspace-border);
}
.webhook-row > div {
  min-width: 0;
  flex: 1 1 220px;
}
.webhook-row p,
.webhook-address {
  overflow-wrap: anywhere;
}
.webhook-row p,
.webhook-row small {
  color: var(--workspace-muted);
  font-size: 0.8rem;
  margin-top: 6px;
}
</style>
