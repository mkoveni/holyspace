<template>
  <v-dialog
    v-model="open"
    max-width="860"
    scrollable
  >
    <v-card class="directory-form">
      <div class="directory-form__header">
        <div>
          <span class="directory-eyebrow">RESTRICTED FOLLOW-UP</span>
          <h2>Care, with a clear next step</h2>
          <p>Opening these details and each history page is audited.</p>
        </div>

        <v-btn
          aria-label="Close incident details"
          icon="mdi-close"
          variant="text"
          @click="open = false"
        />
      </div>

      <v-card-text>
        <v-alert
          v-if="privateData.error.value"
          type="error"
          variant="tonal"
        >
          {{ privateData.error.value }}
          <v-btn
            variant="text"
            @click="load"
          >
            Retry access
          </v-btn>
        </v-alert>

        <v-progress-linear
          v-if="privateData.loading.value"
          indeterminate
        />

        <template v-if="privateData.data.value">
          <v-chip class="mb-4">
            {{ privateData.data.value.incident.status.replaceAll('_', ' ') }}
          </v-chip>

          <h3>What happened</h3>

          <p class="private-note">
            {{ privateData.data.value.details.description }}
          </p>

          <h3>Immediate response</h3>

          <p class="private-note">
            {{ privateData.data.value.details.immediateAction }}
          </p>

          <p class="directory-muted">
            Review {{ dateLabel(privateData.data.value.incident.reviewOn) }} ·
            Private details retained through
            {{ dateLabel(privateData.data.value.incident.retainUntil) }}
          </p>

          <TaskAlert
            :conflict="task.conflict.value"
            :error="task.error.value"
            @reload="load"
          />

          <v-form
            ref="form"
            class="mt-6"
            @submit.prevent="save"
          >
            <v-select
              v-model="action"
              item-title="label"
              item-value="value"
              :items="actions"
              label="Follow-up action *"
            />

            <template v-if="action === 'TRIAGED'">
              <v-autocomplete
                v-model="ownerId"
                :error-messages="owners.error.value"
                item-title="name"
                item-value="id"
                :items="ownerItems"
                label="Responsible coordinator *"
                :loading="owners.loading.value"
                no-filter
                :rules="[required]"
                @update:search="findOwners"
              />

              <DateSelector
                v-model="reviewOn"
                label="Review by *"
                :rules="[required]"
              />
            </template>

            <v-select
              v-if="action === 'GUARDIAN_CONTACT'"
              v-model="guardianAuthorityId"
              :error-messages="guardians.error.value"
              item-title="name"
              item-value="authorityId"
              :items="guardians.data.value"
              label="Verified guardian contacted *"
              :rules="[required]"
            />

            <v-textarea
              v-model="note"
              :label="
                action === 'CLOSED'
                  ? 'Resolution and closure note *'
                  : 'Private follow-up note *'
              "
              maxlength="3000"
              :rules="[required]"
            />

            <v-btn
              color="primary"
              :disabled="task.conflict.value"
              :loading="task.busy.value"
              type="submit"
            >
              Save follow-up
            </v-btn>
          </v-form>

          <h3 class="mt-8">Follow-up history</h3>

          <article
            v-for="event in privateData.data.value.events"
            :key="event.id"
            class="incident-event"
          >
            <strong>{{ event.type.replaceAll('_', ' ') }}</strong>
            <small>{{ timeLabel(event.occurredAt) }}</small>
            <p class="private-note">{{ event.note }}</p>
          </article>

          <div class="d-flex justify-space-between mt-4">
            <v-btn
              :disabled="!offset || privateData.loading.value"
              variant="text"
              @click="page(-25)"
            >
              Newer actions
            </v-btn>

            <span>{{ privateData.data.value.totalEvents }} actions</span>

            <v-btn
              :disabled="
                offset + 25 >= privateData.data.value.totalEvents ||
                  privateData.loading.value
              "
              variant="text"
              @click="page(25)"
            >
              Older actions
            </v-btn>
          </div>
        </template>
      </v-card-text>

      <v-card-actions class="directory-form__footer">
        <v-spacer />
        <v-btn @click="open = false">Close private details</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script setup lang="ts">
  import type { IncidentPrivateView } from '../types'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import DateSelector from '@/components/common/DateSelector.vue'
  import TaskAlert from '@/directory/components/TaskAlert.vue'
  import { useTask } from '@/directory/useTask'
  import { childrenApi } from '../api'
  import { dateLabel, required, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ incidentId: string }>()
  const emit = defineEmits<{ saved: [] }>()
  const offset = ref(0)
  const privateData = useLoad<IncidentPrivateView | null>(
    () => childrenApi.accessIncident(props.incidentId, offset.value),
    null,
  )
  const owners = useLoad(
    () => childrenApi.incidentOwners(ownerSearch.value),
    [] as { id: string, name: string }[],
  )
  const ownerSearch = ref(''),
        ownerId = ref<string | null>(null),
        selectedOwner = ref<{ id: string, name: string } | null>(null)
  const ownerItems = computed(() =>
    selectedOwner.value
    && !owners.data.value.some(o => o.id === selectedOwner.value?.id)
      ? [...owners.data.value, selectedOwner.value]
      : owners.data.value,
  )
  watch(ownerId, id => {
    selectedOwner.value = ownerItems.value.find(o => o.id === id) || null
  })
  const guardians = useLoad(
    () => childrenApi.incidentGuardians(props.incidentId),
    [] as { authorityId: string, guardianId: string, name: string }[],
  )
  const action = ref('TRIAGED'),
        reviewOn = ref(''),
        guardianAuthorityId = ref<string | null>(null),
        note = ref('')
  const actions = computed(() =>
    privateData.data.value?.incident.status === 'CLOSED'
      ? [{ value: 'REOPENED', label: 'Reopen for further review' }]
      : [
        { value: 'TRIAGED', label: 'Assign responsibility and review date' },
        { value: 'ACTION', label: 'Record an action' },
        { value: 'GUARDIAN_CONTACT', label: 'Record guardian contact' },
        { value: 'CLOSED', label: 'Close with a resolution' },
      ],
  )
  const task = useTask({ preserveConflictMessage: true })
  const form = ref<{ validate: () => Promise<{ valid: boolean }> }>()
  let timer: ReturnType<typeof setTimeout> | undefined
  function findOwners (value: string) {
    clearTimeout(timer)
    ownerSearch.value = value
    timer = setTimeout(() => {
      void owners.load()
    }, 250)
  }
  async function load () {
    task.clear()
    privateData.data.value = null
    await privateData.load()
    const incident = (privateData.data.value as IncidentPrivateView | null)
      ?.incident
    if (incident) {
      reviewOn.value = incident.reviewOn
      action.value = incident.status === 'CLOSED' ? 'REOPENED' : 'TRIAGED'
    }
  }
  function page (delta: number) {
    offset.value += delta
    void privateData.load()
  }
  watch(
    open,
    value => {
      if (value) {
        offset.value = 0
        note.value = ''
        ownerId.value = null
        guardianAuthorityId.value = null
        void load()
        void owners.load()
      } else {
        privateData.data.value = null
        note.value = ''
        task.clear()
      }
    },
    { immediate: true },
  )
  watch(action, value => {
    if (value === 'GUARDIAN_CONTACT') void guardians.load()
  })
  async function save () {
    if (!(await form.value?.validate())?.valid || !privateData.data.value)
      return
    const result = await task.run(() =>
      childrenApi.followUpIncident(props.incidentId, {
        expectedVersion: privateData.data.value!.incident.version,
        action: action.value,
        ownerId: ownerId.value,
        reviewOn: reviewOn.value,
        guardianAuthorityId: guardianAuthorityId.value,
        note: note.value,
      }),
    )
    if (result) {
      note.value = ''
      offset.value = 0
      await load()
      emit('saved')
    }
  }
  onBeforeUnmount(() => {
    clearTimeout(timer)
    privateData.data.value = null
  })
</script>
<style scoped>
  .private-note {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    margin: 10px 0 20px;
  }
  .incident-event {
    padding: 18px 0;
    border-bottom: 1px solid #e7ecef;
  }
  .incident-event small {
    display: block;
    color: #75818b;
    margin-top: 5px;
  }
</style>
