<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="A MESSAGE, WITH FOLLOW-THROUGH"
      subtitle="Review scheduled email, SMTP acceptance, provider outcomes and the few messages that need your attention."
      title="Every message. A clear outcome."
    >
      <v-btn
        to="/children/communications"
        variant="tonal"
      >
        Prepare a guardian message
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs active="delivery" />

    <v-btn
      v-if="auth.hasPermission('communications.templates.read')"
      class="my-3"
      prepend-icon="mdi-email-edit-outline"
      to="/communications/templates"
      variant="text"
    >
      Manage email templates
    </v-btn>

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-email-check-outline" />
        </span>

        <div>
          <strong>Clear outcomes, without guesswork.</strong>

          <p>
            Sent means the mail server accepted the message. Delivered requires
            a trusted provider receipt. Uncertain sends need reconciliation
            before any retry.
          </p>
        </div>
      </div>
    </div>

    <section class="directory-surface children-section my-6">
      <div class="delivery-controls">
        <v-select
          v-model="status"
          item-title="label"
          item-value="value"
          :items="states"
          label="Delivery status"
        />

        <v-btn
          :loading="workspace.loading.value"
          variant="tonal"
          @click="apply"
        >
          Apply filters
        </v-btn>

        <v-btn
          aria-label="Refresh deliveries"
          icon="mdi-refresh"
          variant="text"
          @click="load"
        />
      </div>
    </section>

    <v-alert
      v-if="workspace.error.value"
      type="error"
      variant="tonal"
    >
      {{ workspace.error.value }}
    </v-alert>

    <p class="directory-muted mb-4">
      {{ workspace.data.value?.waiting || 0 }} waiting or sending ·
      {{ workspace.data.value?.failed || 0 }} need attention, across all
      statuses and pages.
    </p>

    <section class="directory-surface">
      <article
        v-for="delivery in workspace.data.value?.deliveries || []"
        :key="delivery.id"
        class="delivery-row"
      >
        <div>
          <v-chip :color="color(delivery.status)">
            {{ label(delivery.status, delivery.captured) }}
          </v-chip>

          <h3>{{ timeLabel(delivery.scheduledAt) }}</h3>

          <p>
            {{ delivery.attempts }} send attempts{{
              delivery.errorCode ? ' · Review required' : ''
            }}
          </p>
        </div>

        <v-btn
          variant="tonal"
          @click="show(delivery.id)"
        >
          Review delivery
        </v-btn>
      </article>

      <DirectoryEmpty
        v-if="
          !workspace.loading.value &&
            !workspace.error.value &&
            !workspace.data.value?.deliveries.length
        "
        description="Approved guardian emails will appear here, including scheduled reminders."
        icon="mdi-email-check-outline"
        title="A clear delivery queue"
      />

      <footer class="delivery-controls pa-5">
        <span>{{ workspace.data.value?.total || 0 }} matching deliveries</span>

        <div>
          <v-btn
            :disabled="!offset || workspace.loading.value"
            variant="text"
            @click="page(-25)"
          >
            Previous
          </v-btn>

          <v-btn
            :disabled="
              offset + 25 >= (workspace.data.value?.total || 0) ||
                workspace.loading.value
            "
            variant="text"
            @click="page(25)"
          >
            Next
          </v-btn>
        </div>
      </footer>
    </section>

    <v-dialog
      v-model="detailsOpen"
      max-width="800"
      scrollable
    >
      <v-card class="directory-form">
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">DELIVERY RECORD</span>
            <h2>From approval to outcome</h2>
          </div>

          <v-btn
            aria-label="Close delivery details"
            icon="mdi-close"
            variant="text"
            @click="detailsOpen = false"
          />
        </div>

        <v-card-text>
          <v-alert
            v-if="details.error.value"
            type="error"
            variant="tonal"
          >
            {{ details.error.value }}
          </v-alert>

          <template v-if="details.data.value">
            <v-chip :color="color(details.data.value.delivery.status)">
              {{
                label(
                  details.data.value.delivery.status,
                  details.data.value.delivery.captured,
                )
              }}
            </v-chip>

            <h3 class="mt-5">{{ details.data.value.delivery.recipient }}</h3>

            <EmailPreview
              v-if="details.data.value.delivery.contentType === 'text/html'"
              :html="details.data.value.delivery.body"
              title="Approved email wording"
            />

            <p
              v-else
              class="delivery-body"
            >
              {{ details.data.value.delivery.body }}
            </p>

            <v-alert
              v-if="details.data.value.delivery.status === 'UNKNOWN'"
              type="warning"
              variant="tonal"
            >
              The outcome is uncertain. Check the provider using this delivery's
              Message-ID before any further send. Automatic retry is disabled.
            </v-alert>

            <p class="directory-muted">
              Message-ID: {{ details.data.value.delivery.messageId }}
            </p>

            <div
              v-if="canSend"
              class="d-flex ga-3 flex-wrap my-5"
            >
              <v-btn
                v-if="
                  ['QUEUED', 'SCHEDULED', 'FAILED', 'BLOCKED'].includes(
                    details.data.value.delivery.status,
                  )
                "
                variant="tonal"
                @click="change('cancel')"
              >
                Cancel delivery
              </v-btn>

              <v-btn
                v-if="
                  ['FAILED', 'BLOCKED'].includes(
                    details.data.value.delivery.status,
                  ) && details.data.value.delivery.attempts < 3
                "
                color="primary"
                @click="change('retry')"
              >
                Review & retry
              </v-btn>
            </div>

            <h3>Delivery history</h3>

            <article
              v-for="event in details.data.value.events"
              :key="event.id"
              class="delivery-event"
            >
              <strong>{{ event.type.replaceAll('_', ' ') }}</strong>
              <small>{{ timeLabel(event.occurredAt) }}</small>
              <p>{{ event.reference }}</p>
            </article>

            <div class="d-flex justify-space-between">
              <v-btn
                :disabled="!eventOffset"
                variant="text"
                @click="history(-25)"
              >
                Newer events
              </v-btn>

              <v-btn
                :disabled="details.data.value.events.length < 25"
                variant="text"
                @click="history(25)"
              >
                Older events
              </v-btn>
            </div>
          </template>
        </v-card-text>
      </v-card>
    </v-dialog>

    <MinistryDialog
      v-model="changing"
      :save-label="action === 'retry' ? 'Approve retry' : 'Cancel delivery'"
      :submit="save"
      :subtitle="
        action === 'retry'
          ? 'Recheck the displayed recipient and message. Eligibility will be checked again. At most three send attempts are allowed.'
          : 'Waiting messages will not be sent. In-progress or uncertain sends cannot be cancelled.'
      "
      :title="
        action === 'retry' ? 'Approve this retry' : 'Cancel this delivery'
      "
      @reload="reloadChange"
      @saved="changed"
    >
      <v-textarea
        v-model="reason"
        label="Reason *"
        maxlength="500"
        :rules="[required]"
      />

      <v-checkbox
        v-if="action === 'retry'"
        v-model="confirmed"
        label="I reviewed the recipient and wording and approve another attempt."
        :rules="[(value) => !!value || 'Confirm approval.']"
      />
    </MinistryDialog>
  </div>
</template>
<script setup lang="ts">
  import type { EmailDeliveryDetail, EmailDeliveryPage } from '../types'
  import { computed, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import EmailPreview from '@/communications/components/EmailPreview.vue'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import MinistryDialog from '../components/MinistryDialog.vue'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import { required, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const auth = useAuthStore(),
        canSend = computed(() => auth.hasPermission('children.communications.send'))
  const status = ref('ALL'),
        applied = ref('ALL'),
        offset = ref(0),
        eventOffset = ref(0),
        deliveryId = ref(''),
        detailsOpen = ref(false),
        changing = ref(false),
        action = ref('cancel'),
        reason = ref(''),
        confirmed = ref(false)
  const states = [
    { value: 'ALL', label: 'All deliveries' },
    ...[
      'QUEUED',
      'SCHEDULED',
      'SENDING',
      'SENT',
      'DELIVERED',
      'FAILED',
      'UNKNOWN',
      'CANCELLED',
      'BLOCKED',
    ].map(value => ({
      value,
      label:
        value === 'UNKNOWN'
          ? 'Uncertain outcome'
          : value.charAt(0) + value.slice(1).toLowerCase(),
    })),
  ]
  const workspace = useLoad<EmailDeliveryPage | null>(
    () => childrenApi.emailDeliveries(applied.value, offset.value),
    null,
  )
  const details = useLoad<EmailDeliveryDetail | null>(
    () => childrenApi.emailDelivery(deliveryId.value, eventOffset.value),
    null,
  )
  function load () {
    void workspace.load()
  }
  function apply () {
    applied.value = status.value
    offset.value = 0
    load()
  }
  function page (delta: number) {
    offset.value += delta
    load()
  }
  function label (state: string, capture: boolean) {
    if (capture && state === 'SENT') return 'Captured in local inbox'
    return states.find(v => v.value === state)?.label || state
  }
  function color (state: string) {
    if (['DELIVERED', 'SENT'].includes(state)) return 'success'
    if (['FAILED', 'BLOCKED', 'UNKNOWN'].includes(state)) return 'warning'
    return 'primary'
  }
  function show (id: string) {
    deliveryId.value = id
    eventOffset.value = 0
    details.data.value = null
    detailsOpen.value = true
    void details.load()
  }
  function history (delta: number) {
    eventOffset.value += delta
    void details.load()
  }
  function change (value: string) {
    action.value = value
    reason.value = ''
    confirmed.value = false
    changing.value = true
  }
  async function save () {
    const d = details.data.value?.delivery
    if (!d || (action.value === 'retry' && !confirmed.value))
      throw new Error('Review this delivery before approving an action.')
    const body = { expectedVersion: d.version, reason: reason.value }
    await (action.value === 'retry'
      ? childrenApi.retryDelivery(d.id, body)
      : childrenApi.cancelDelivery(d.id, body))
  }
  function changed () {
    changing.value = false
    void details.load()
    load()
  }
  function reloadChange () {
    changing.value = false
    void details.load()
    load()
  }
  watch(detailsOpen, value => {
    if (!value) details.data.value = null
  })
  load()
  const route = useRoute()
  if (typeof route.query.delivery === 'string') show(route.query.delivery)
</script>
<style scoped>
  .delivery-controls,
  .delivery-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
  }
  .delivery-controls > :first-child {
    min-width: 220px;
  }
  .delivery-row {
    padding: 24px;
    border-bottom: 1px solid #e7ecef;
  }
  .delivery-row h3 {
    margin: 12px 0 6px;
  }
  .delivery-row p,
  .delivery-event small {
    color: #75818b;
  }
  .delivery-body {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    line-height: 1.8;
    margin: 20px 0;
  }
  .delivery-event {
    padding: 18px 0;
    border-bottom: 1px solid #e7ecef;
  }
  .delivery-event small {
    display: block;
    margin: 6px 0;
  }
</style>
