<template>
  <div>
    <PageHeader eyebrow="Church calendar" :subtitle="event ? `${event.type} · ${event.timezone}` : 'Plan services, meetings, classes, and outreach'" :title="event?.title ?? 'Events'">
      <template #actions>
        <v-btn v-if="id" prepend-icon="mdi-arrow-left" to="/events" variant="text">All events</v-btn>

        <v-btn
          v-if="access.hasPermission('events.manage') && (!id || event)"
          color="primary"
          prepend-icon="mdi-plus"
          variant="flat"
          @click="eventDialog = true"
        >{{ id ? 'Edit details' : 'Create event' }}</v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="error" class="mb-4" type="error" variant="tonal">{{ error }} <v-btn variant="text" @click="load">Retry</v-btn></v-alert>
    <v-progress-linear v-if="store.isLoading" color="primary" indeterminate />

    <v-card v-if="!id">
      <v-card-text>
        <v-text-field
          v-model="search"
          clearable
          hide-details
          label="Search events"
          prepend-inner-icon="mdi-magnify"
        />
      </v-card-text>

      <v-data-table :headers="eventHeaders" :items="rows" :loading="store.isLoading" :search="search ?? ''">
        <template #item.title="{ item }"><router-link class="table-identity" :to="`/events/${item.id}`">{{ item.title }}</router-link></template>
        <template #item.next="{ item }">{{ item.next ? formatTime(item.next, item.timezone) : 'No upcoming occurrences' }}</template>
        <template #item.actions="{ item }"><v-btn icon="mdi-eye-outline" :to="`/events/${item.id}`" variant="text" /></template>
      </v-data-table>
    </v-card>

    <template v-else-if="event">
      <PortalImage :editable="access.hasPermission('events.manage')" label="Event banner" :owner-id="event.id" target="event" />
      <v-card class="mb-4"><v-card-text>{{ event.description || 'No description.' }}</v-card-text></v-card>

      <v-card>
        <v-card-text>
          <div class="workspace-section-title">Scheduled occurrences</div>

          <v-row>
            <v-col cols="12" sm="4"><v-select
              v-model="month"
              clearable
              hide-details
              :items="months"
              label="Month"
            /></v-col>

            <v-col cols="12" sm="4"><v-text-field
              v-model="year"
              clearable
              hide-details
              label="Year"
              type="number"
            /></v-col>
          </v-row>

          <p class="text-caption mt-3">Times shown in {{ event.timezone }}. Changes apply to the selected occurrence.</p>
        </v-card-text>

        <v-data-table :headers="occurrenceHeaders" :items="occurrences" :sort-by="[{ key: 'startsAt', order: 'asc' }]">
          <template #item.startsAt="{ item }">{{ formatTime(item.startsAt, event.timezone) }}</template>
          <template #item.endsAt="{ item }">{{ formatTime(item.endsAt, event.timezone) }}</template>
          <template #item.status="{ item }"><v-chip :color="item.status === 'cancelled' ? 'error' : 'success'" size="small">{{ item.status }}</v-chip></template>

          <template #item.actions="{ item }"><v-btn
            v-if="access.hasPermission('events.manage')"
            :disabled="store.isSaving"
            prepend-icon="mdi-pencil-outline"
            size="small"
            variant="text"
            @click="editOccurrence(item)"
          >Edit</v-btn></template>
        </v-data-table>
      </v-card>

      <EventRegistrations :key="event.id" :event="event" />
    </template>

    <EventFormDialog v-model="eventDialog" :event="id ? event : null" @saved="saved" />

    <FormDialog
      ref="occurrenceForm"
      v-model="occurrenceDialog"
      :error="occurrenceError"
      :loading="store.isSaving"
      size="md"
      submit-label="Save occurrence"
      title="Edit occurrence"
      @submit="saveOccurrence"
    >
      <p class="text-caption mb-3">Times are in {{ event?.timezone }}.</p>
      <v-text-field v-model="occurrenceInput.startsAt" label="Starts" :rules="[required]" type="datetime-local" />
      <v-text-field v-model="occurrenceInput.endsAt" label="Ends" :rules="[required, endAfterStart]" type="datetime-local" />
      <v-text-field v-model="occurrenceInput.location" label="Location" maxlength="255" :rules="[required]" />
      <v-select v-model="occurrenceInput.status" :items="['scheduled', 'cancelled']" label="Status" />
    </FormDialog>
  </div>
</template>
<script setup lang="ts">
  import type { EventOccurrence, OccurrencePayload } from '@/types/event'
  import { computed, reactive, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import FormDialog from '@/components/common/FormDialog.vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import PortalImage from '@/components/common/PortalImage.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { nextOccurrence } from '@/events/schedule'
  import { useAuthStore } from '@/stores/auth'
  import { useEventsStore } from '@/stores/events'
  import { useUiStore } from '@/stores/ui'
  import EventFormDialog from './components/EventFormDialog.vue'
  import EventRegistrations from './components/EventRegistrations.vue'

  const props = defineProps<{ id?: string }>()
  const store = useEventsStore()
  const ui = useUiStore()
  const router = useRouter()
  const event = computed(() => props.id && store.current?.id === props.id ? store.current : null)
  const error = ref('')
  const search = ref('')
  const month = ref<number | null>(null)
  const year = ref<string | null>(null)
  const eventDialog = ref(false)
  const occurrenceDialog = ref(false)
  const occurrenceError = ref('')
  const occurrenceId = ref('')
  const occurrenceForm = ref()
  const occurrenceInput = reactive<OccurrencePayload>({ startsAt: '', endsAt: '', location: '', status: 'scheduled' })
  const required = (value: string) => Boolean(value?.trim()) || 'This field is required.'
  const endAfterStart = (value: string) => value > occurrenceInput.startsAt || 'End must be after start.'
  const months = Array.from({ length: 12 }, (_, index) => ({ title: new Date(2000, index, 1).toLocaleDateString(undefined, { month: 'long' }), value: index + 1 }))
  const rows = computed(() => store.events.map(item => ({ ...item, next: nextOccurrence(item)?.startsAt ?? null, count: item.occurrences.length })))
  const occurrences = computed(() => (event.value?.occurrences ?? []).filter(o => (!month.value || Number(o.startsAt.slice(5, 7)) === month.value) && (!year.value || o.startsAt.slice(0, 4) === String(year.value))))
  const eventHeaders = [{ title: 'Event', key: 'title' }, { title: 'Type', key: 'type' }, { title: 'Next occurrence', key: 'next' }, { title: 'Timezone', key: 'timezone' }, { title: 'Occurrences', key: 'count' }, { title: '', key: 'actions', sortable: false }]
  const occurrenceHeaders = [{ title: 'Starts', key: 'startsAt' }, { title: 'Ends', key: 'endsAt' }, { title: 'Location', key: 'location' }, { title: 'Status', key: 'status' }, { title: '', key: 'actions', sortable: false }]
  function formatTime (value: string, timezone: string) {
    return new Date(value).toLocaleString(undefined, { timeZone: timezone, dateStyle: 'medium', timeStyle: 'short' })
  }
  async function load () {
    error.value = ''
    try {
      await (props.id ? store.fetchOne(props.id) : store.fetchAll())
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to load events.')
    }
  }
  watch(() => props.id, () => {
    month.value = null
    year.value = null
    eventDialog.value = false
    occurrenceDialog.value = false
    void load()
  }, { immediate: true })
  async function saved (id: string) {
    ui.notifySuccess('Event saved.')
    await (props.id ? load() : router.push(`/events/${id}`))
  }
  function editOccurrence (occurrence: EventOccurrence) {
    occurrenceId.value = occurrence.id
    occurrenceError.value = ''
    Object.assign(occurrenceInput, { startsAt: occurrence.startsAt.slice(0, 16), endsAt: occurrence.endsAt.slice(0, 16), location: occurrence.location, status: occurrence.status })
    occurrenceForm.value?.resetValidation()
    occurrenceDialog.value = true
  }
  async function saveOccurrence () {
    if (!props.id || store.isSaving || !(await occurrenceForm.value.validate()).valid) return
    occurrenceError.value = ''
    try {
      await store.updateOccurrence(props.id, occurrenceId.value, { ...occurrenceInput })
      occurrenceDialog.value = false
      ui.notifySuccess('Occurrence updated.')
    } catch (error_) {
      occurrenceError.value = extractErrorMessage(error_, 'Unable to update occurrence.')
    }
  }

  const access = useAuthStore()
</script>
