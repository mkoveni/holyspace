<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="KEEPING FAMILIES IN THE LOOP"
      subtitle="Prepare consent requests and session reminders, then keep an honest record of contact."
      title="Thoughtful messages. Clear follow-through."
    >
      <v-btn
        v-if="canPrepare"
        color="primary"
        @click="preparing = true"
      >
        Prepare message
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs active="communications" />

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-message-text-outline" />
        </span>

        <div>
          <strong>Reviewed messages. Clear delivery choices.</strong>

          <p>
            Approve verified guardian email for immediate or scheduled delivery,
            or record contact made through your usual channel. SMTP acceptance
            is tracked separately from delivery confirmation.
          </p>
        </div>
      </div>
    </div>

    <section class="directory-surface children-section my-6">
      <div class="communication-filters">
        <v-select
          v-model="status"
          item-title="label"
          item-value="value"
          :items="statuses"
          label="Communication status"
        />

        <v-btn
          :loading="workspace.loading.value"
          variant="tonal"
          @click="apply"
        >
          Apply filters
        </v-btn>

        <v-btn
          v-if="childId"
          variant="text"
          @click="clearChild"
        >
          Show all children
        </v-btn>
      </div>
    </section>

    <v-alert
      v-if="workspace.error.value"
      type="error"
      variant="tonal"
    >
      {{ workspace.error.value }}
      <v-btn @click="load">Retry</v-btn>
    </v-alert>

    <p class="directory-muted mb-4">
      {{ workspace.data.value?.total || 0 }} matching requests ·
      {{ workspace.data.value?.overdue || 0 }} draft contacts due in this status
      view, across all pages.
    </p>

    <section class="directory-surface">
      <div class="directory-toolbar">
        <h2>Guardian contact queue</h2>
        <v-spacer />

        <v-btn
          aria-label="Refresh communications"
          icon="mdi-refresh"
          variant="text"
          @click="load"
        />
      </div>

      <article
        v-for="row in workspace.data.value?.communications || []"
        :key="row.communication.id"
        class="communication-row"
      >
        <div>
          <span class="directory-eyebrow">
            {{
              row.communication.type === 'CONSENT_RENEWAL'
                ? 'Consent renewal'
                : 'Session reminder'
            }}
            ·
            {{
              row.communication.status === 'CONTACTED'
                ? 'Contact recorded'
                : row.communication.status
            }}
          </span>

          <h3>{{ row.childName }}</h3>

          <p>
            For {{ row.guardianName }} · Contact by
            {{ dateLabel(row.communication.dueOn) }}
          </p>
        </div>

        <div class="d-flex ga-2 flex-wrap">
          <v-btn
            v-if="
              row.communication.status === 'DRAFT' &&
                auth.hasPermission('children.communications.send') &&
                !row.delivery
            "
            variant="tonal"
            @click="approve(row.communication.id)"
          >
            Review email delivery
          </v-btn>

          <v-btn
            v-if="row.delivery"
            :to="{
              path: '/children/email-deliveries',
              query: { delivery: row.delivery.id },
            }"
            variant="tonal"
          >
            Review delivery ·
            {{
              row.delivery.captured && row.delivery.status === 'SENT'
                ? 'Captured locally'
                : row.delivery.status
            }}
          </v-btn>

          <v-btn
            v-if="!row.delivery || row.delivery.status === 'CANCELLED'"
            variant="tonal"
            @click="review(row.communication)"
          >
            Review message
          </v-btn>

          <v-btn
            v-if="
              row.communication.status === 'DRAFT' &&
                (!row.delivery || row.delivery.status === 'CANCELLED')
            "
            variant="text"
            @click="cancel(row.communication)"
          >
            Cancel request
          </v-btn>
        </div>
      </article>

      <DirectoryEmpty
        v-if="
          !workspace.loading.value &&
            !workspace.error.value &&
            !workspace.data.value?.communications.length
        "
        description="No communication tasks match this status. Prepare a message when a guardian needs a reminder."
        icon="mdi-message-check-outline"
        title="A little reassurance"
      />

      <footer class="communication-paging">
        <span>{{ workspace.data.value?.total || 0 }} requests</span>

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

    <CommunicationDraftDialog
      v-if="canPrepare"
      v-model="preparing"
      :default-child="childId || undefined"
      :default-programme="programmeId || undefined"
      @saved="load"
    />

    <v-dialog
      v-model="reviewing"
      max-width="760"
      scrollable
    >
      <v-card class="directory-form">
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">A MESSAGE HOME</span>
            <h2>Review guardian message</h2>
          </div>

          <v-btn
            aria-label="Close message"
            icon="mdi-close"
            variant="text"
            @click="reviewing = false"
          />
        </div>

        <v-card-text>
          <TaskAlert
            :conflict="reviewTask.conflict.value"
            :error="reviewTask.error.value"
            @reload="retryReview"
          />

          <template v-if="message">
            <v-alert
              class="mb-4"
              type="info"
              variant="tonal"
            >
              This message has not been sent by this application.
            </v-alert>

            <p class="message-body">{{ message.message }}</p>

            <p v-if="message.contactReference">
              Contact record: {{ message.contactReference }}
            </p>

            <v-btn
              v-if="message.status === 'DRAFT'"
              prepend-icon="mdi-content-copy"
              variant="tonal"
              @click="copy"
            >
              Copy message
            </v-btn>

            <p
              v-if="copied"
              class="text-success mt-3"
            >
              Message copied. Share it through your usual channel.
            </p>

            <v-btn
              v-if="message.status === 'DRAFT'"
              class="ml-3"
              color="primary"
              @click="startContact"
            >
              Record guardian contact
            </v-btn>
          </template>
        </v-card-text>
      </v-card>
    </v-dialog>

    <MinistryDialog
      v-model="contacting"
      :save-label="cancelling ? 'Cancel request' : 'Record contact'"
      :submit="saveContact"
      :subtitle="
        cancelling
          ? 'Record why this draft is no longer needed.'
          : 'Confirm actual contact through your usual channel. This does not record consent or change guardian authority.'
      "
      :title="cancelling ? 'Cancel this request' : 'Record guardian contact'"
      @reload="refreshContact"
      @saved="finished"
    >
      <v-textarea
        v-model="reference"
        :label="
          cancelling
            ? 'Cancellation reason *'
            : 'Contact method and reference *'
        "
        maxlength="500"
        :rules="[required]"
      />

      <v-checkbox
        v-if="!cancelling"
        v-model="confirmed"
        label="I confirm that contact was made with this guardian."
        :rules="[(value) => !!value || 'Confirm guardian contact.']"
      />
    </MinistryDialog>

    <EmailApprovalDialog
      v-model="approving"
      :communication-id="approvalId"
      @saved="approvedDelivery"
    />
  </div>
</template>
<script setup lang="ts">
  import type {
    CommunicationPage,
    CommunicationSummary,
    GuardianCommunication,
  } from '../types'
  import { computed, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import TaskAlert from '@/directory/components/TaskAlert.vue'
  import { useTask } from '@/directory/useTask'
  import { useCreateAction } from '@/navigation/useCreateAction'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import CommunicationDraftDialog from '../components/CommunicationDraftDialog.vue'
  import EmailApprovalDialog from '../components/EmailApprovalDialog.vue'
  import MinistryDialog from '../components/MinistryDialog.vue'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import { dateLabel, required } from '../presentation'
  import { useLoad } from '../useLoad'
  const approving = ref(false),
        approvalId = ref('')
  function approve (id: string) {
    approvalId.value = id
    approving.value = true
  }
  function approvedDelivery () {
    void router.push('/children/email-deliveries')
  }
  const auth = useAuthStore(),
        route = useRoute(),
        router = useRouter()
  const canPrepare = computed(
    () =>
      auth.hasPermission('people.read') && auth.hasPermission('children.read'),
  )
  const childId = ref(
          typeof route.query.child === 'string' ? route.query.child : '',
        ),
        programmeId = ref(
          typeof route.query.programme === 'string' ? route.query.programme : '',
        )
  const status = ref('DRAFT'),
        appliedStatus = ref('DRAFT'),
        offset = ref(0),
        preparing = ref(false),
        reviewing = ref(false),
        contacting = ref(false),
        cancelling = ref(false),
        copied = ref(false),
        confirmed = ref(false),
        reference = ref('')
  useCreateAction(
    () => auth.hasPermission('children.safeguarding') && canPrepare.value,
    () => {
      preparing.value = true
    },
  )
  const selected = ref<CommunicationSummary | null>(null),
        message = ref<GuardianCommunication | null>(null)
  const statuses = [
    { value: 'DRAFT', label: 'Awaiting contact' },
    { value: 'ALL', label: 'All requests' },
    { value: 'CONTACTED', label: 'Contact recorded' },
    { value: 'CANCELLED', label: 'Cancelled' },
  ]
  const workspace = useLoad<CommunicationPage | null>(
    () =>
      childrenApi.communications(
        childId.value || undefined,
        appliedStatus.value,
        offset.value,
      ),
    null,
  )
  const reviewTask = useTask({ preserveConflictMessage: true })
  function load () {
    void workspace.load()
  }
  function apply () {
    appliedStatus.value = status.value
    offset.value = 0
    load()
  }
  function page (delta: number) {
    offset.value += delta
    load()
  }
  function clearChild () {
    childId.value = ''
    programmeId.value = ''
    void router.replace({ query: {} })
    offset.value = 0
    load()
  }
  async function review (row: CommunicationSummary) {
    selected.value = row
    message.value = null
    copied.value = false
    reviewTask.clear()
    reviewing.value = true
    const result = await reviewTask.run(() => childrenApi.communication(row.id))
    if (result && reviewing.value && selected.value?.id === row.id)
      message.value = result.value
  }
  function retryReview () {
    if (selected.value) void review(selected.value)
  }
  async function copy () {
    const text = message.value?.message
    if (text)
      await reviewTask.run(async () => {
        await navigator.clipboard.writeText(text)
        copied.value = true
      })
  }
  function startContact () {
    reviewing.value = false
    reference.value = ''
    confirmed.value = false
    cancelling.value = false
    contacting.value = true
  }
  function cancel (row: CommunicationSummary) {
    selected.value = row
    reference.value = ''
    cancelling.value = true
    contacting.value = true
  }
  async function saveContact () {
    if (!selected.value || (!cancelling.value && !confirmed.value))
      throw new Error('Confirm guardian contact.')
    await childrenApi.recordContact(selected.value.id, {
      expectedVersion: selected.value.version,
      status: cancelling.value ? 'CANCELLED' : 'CONTACTED',
      reference: reference.value,
    })
  }
  function finished () {
    message.value = null
    load()
  }
  function refreshContact () {
    contacting.value = false
    message.value = null
    load()
  }
  watch(reviewing, value => {
    if (!value) {
      message.value = null
      reviewTask.clear()
    }
  })
  load()
</script>
<style scoped>
  .communication-filters,
  .communication-row,
  .communication-paging {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
  }
  .communication-filters > :first-child {
    min-width: 230px;
  }
  .communication-row {
    padding: 24px;
    border-top: 1px solid var(--workspace-border);
    overflow-wrap: anywhere;
  }
  .communication-row h3 {
    margin: 7px 0;
  }
  .communication-row p {
    color: var(--workspace-muted);
  }
  .communication-paging {
    padding: 18px 24px;
    border-top: 1px solid var(--workspace-border);
  }
  .message-body {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    line-height: 1.8;
    margin: 18px 0 28px;
  }
</style>
