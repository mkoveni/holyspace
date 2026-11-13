<template>
  <v-card class="mt-4">
    <v-card-title>Event registration</v-card-title>

    <v-card-text>
      <v-select v-model="selectedOccurrence" :disabled="busy" :items="occurrenceOptions" label="Registration occurrence" />

      <v-alert v-if="error" class="mb-4" type="error" variant="tonal">
        {{ error }} <v-btn :disabled="busy" variant="text" @click="load()">Reload</v-btn>
      </v-alert>

      <v-progress-linear v-if="loading" indeterminate />

      <template v-if="overview">
        <p class="mb-4">{{ overview.counts.confirmed }} confirmed · {{ overview.counts.pending_payment }} awaiting payment · {{ overview.counts.waitlisted }} waiting · {{ overview.remaining === null ? 'Unlimited places' : `${overview.remaining} places available` }}</p>
        <v-alert v-if="!overview.acceptingRegistrations" class="mb-4" type="info" variant="tonal">New registrations are closed. Cancelled and ended occurrences cannot accept registrations.</v-alert>

        <v-form v-if="canManage" ref="settingsForm" @submit.prevent="saveSettings">
          <v-row align="center">
            <v-col cols="12" sm="4"><v-text-field
              v-model="capacity"
              :disabled="busy"
              hint="Leave blank for unlimited places"
              label="Registration capacity"
              max="1000"
              min="1"
              persistent-hint
              :rules="[validCapacity]"
              type="number"
            /></v-col>

            <v-col cols="12" sm="3"><v-switch v-model="open" :disabled="busy" hide-details label="Registrations open" /></v-col>
            <v-col cols="12" sm="3"><v-switch v-model="waitlistEnabled" :disabled="busy" hide-details label="Enable waitlist" /></v-col>
            <v-col cols="12" sm="2"><v-btn color="primary" :loading="busy" type="submit">Save registration settings</v-btn></v-col>
          </v-row>
        </v-form>

        <div v-if="canManage" class="workspace-note">
          <v-text-field
            v-model="ticketPrice"
            hint="Leave blank for free registration. Paid, open occurrences appear in Giving and tickets for members. Minimum R5.00."
            inputmode="decimal"
            label="Ticket price (ZAR)"
            persistent-hint
          />

          <v-btn :disabled="ticketAmount === undefined" :loading="busy" @click="saveTicketPrice">Save ticket price</v-btn>
          <v-alert v-if="overview.settings.ticketPriceMinor !== null" class="mt-4" type="info" variant="tonal">This event uses member hosted checkout. Pending payments reserve places for 15 minutes. Ticket income is separate from giving; paid waitlist entries are not created automatically.</v-alert>
        </div>

        <v-form v-if="canManage && canSearchPeople && overview.settings.ticketPriceMinor === null" ref="registrationForm" class="mt-4" @submit.prevent="register">
          <v-row align="center">
            <v-col cols="12" sm="8"><PeopleSearch v-model="personId" :disabled="busy || !overview.acceptingRegistrations" label="Attendee" :rules="[required]" /></v-col>
            <v-col cols="12" sm="4"><v-btn color="primary" :disabled="!overview.acceptingRegistrations" :loading="busy" type="submit">Register attendee</v-btn></v-col>
          </v-row>
        </v-form>

        <p v-else-if="canManage && !canSearchPeople" class="text-caption">People read access is required to find an attendee.</p>

        <v-data-table
          :headers="headers"
          hide-default-footer
          :items="overview.items"
          :items-per-page="-1"
          show-expand
        >
          <template #item.status="{ item }"><v-chip :color="item.status === 'confirmed' ? 'success' : item.status === 'waitlisted' ? 'warning' : undefined" size="small">{{ item.status }}</v-chip></template>

          <template #item.actions="{ item }"><v-btn
            v-if="canManage && item.status !== 'cancelled'"
            :disabled="busy"
            size="small"
            variant="text"
            @click="cancelling = item; reason = ''"
          >Cancel registration</v-btn></template>

          <template #expanded-row="{ columns, item }"><tr><td :colspan="columns.length"><p v-for="(change, index) in item.history" :key="index" class="py-2">{{ change.previousStatus }} → {{ change.status }}: {{ change.reason }} ({{ new Date(change.changedAt).toLocaleString() }})</p><p v-if="item.history.length === 0" class="py-2">No changes since registration.</p></td></tr></template>
        </v-data-table>

        <v-btn v-if="cursor" :disabled="loading || busy" variant="text" @click="load()">First page</v-btn>
        <v-btn v-if="overview.nextAfter" :disabled="loading || busy" variant="text" @click="load(overview.nextAfter)">Next page</v-btn>
        <p class="text-caption mt-3">A registration reserves a place; it does not mark attendance. Vacancies promote waiting attendees in registration order. Disabling the waitlist stops new waiting entries and preserves existing ones.</p>
      </template>
    </v-card-text>

    <FormDialog
      ref="cancelForm"
      :error="error"
      :loading="busy"
      :model-value="Boolean(cancelling)"
      submit-color="error"
      submit-label="Confirm cancellation"
      title="Cancel registration"
      @submit="cancel"
      @update:model-value="open => { if (!open) cancelling = null }"
    >
      <p class="mb-3">{{ cancelling?.personName }}</p>

      <v-textarea
        v-model="reason"
        :disabled="busy"
        label="Cancellation reason"
        maxlength="1000"
        :rules="[required]"
      />
    </FormDialog>
  </v-card>
</template>
<script setup lang="ts">
  import type { ChurchEvent } from '@/types/event'
  import type { EventRegistration, RegistrationOverview } from '@/types/eventRegistration'
  import { computed, ref, watch } from 'vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'

  const props = defineProps<{ event: ChurchEvent }>()
  const access = useAuthStore()
  const canManage = computed(() => access.hasPermission('events.manage'))
  const canSearchPeople = computed(() => access.hasPermission('people.read'))
  const occurrenceOptions = computed(() => props.event.occurrences.map(item => ({ value: item.id, title: `${new Date(item.startsAt).toLocaleString(undefined, { timeZone: props.event.timezone })} · ${item.location} · ${item.status}` })))
  const selectedOccurrence = ref('')
  const overview = ref<RegistrationOverview | null>(null)
  const loading = ref(false)
  const busy = ref(false)
  const error = ref('')
  const cursor = ref<string | null>(null)
  const capacity = ref('')
  const ticketPrice = ref('')
  const ticketAmount = computed(() => {
    if (ticketPrice.value.trim() === '') return null; if (!/^\d{1,7}(\.\d{1,2})?$/.test(ticketPrice.value.trim())) return undefined; const [whole, fraction = ''] = ticketPrice.value.trim().split('.'); const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0')); return minor >= 500 && minor <= 100_000_000 ? minor : undefined
  })
  const open = ref(false)
  const waitlistEnabled = ref(true)
  const personId = ref<string | string[] | null>(null)
  const cancelling = ref<EventRegistration | null>(null)
  const reason = ref('')
  const settingsForm = ref()
  const registrationForm = ref()
  const cancelForm = ref()
  const headers = [{ title: 'Attendee', key: 'personName' }, { title: 'Status', key: 'status' }, { title: '', key: 'actions', sortable: false }, { title: 'History', key: 'data-table-expand', sortable: false }]
  const required = (value: unknown) => (typeof value === 'string' && Boolean(value.trim())) || 'This field is required.'
  const validCapacity = (value: string) => value === '' || (Number.isInteger(Number(value)) && Number(value) >= 1 && Number(value) <= 1000) || 'Enter a whole number from 1 to 1000.'
  const base = () => `/api/events/${props.event.id}/occurrences/${selectedOccurrence.value}/registrations`
  let requestSequence = 0
  async function load (after: string | null = null) {
    const sequence = ++requestSequence
    if (!selectedOccurrence.value) {
      overview.value = null; loading.value = false; return
    }
    loading.value = true
    error.value = ''
    try {
      const { data } = await useApi().get<RegistrationOverview>(base(), { params: after ? { after } : {} })
      if (sequence !== requestSequence) return
      overview.value = data
      cursor.value = after
      capacity.value = data.settings.capacity === null ? '' : String(data.settings.capacity)
      ticketPrice.value = data.settings.ticketPriceMinor === null ? '' : (data.settings.ticketPriceMinor / 100).toFixed(2)
      open.value = data.settings.open
      waitlistEnabled.value = data.settings.waitlistEnabled
    } catch (error_) {
      if (sequence === requestSequence) error.value = extractErrorMessage(error_, 'Unable to load registrations.')
    } finally {
      if (sequence === requestSequence) loading.value = false
    }
  }
  async function mutate (operation: () => Promise<unknown>) {
    if (busy.value || loading.value) return
    busy.value = true
    error.value = ''
    try {
      await operation()
      cancelling.value = null
      personId.value = null
      await load()
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to save registration changes. Reload before retrying a conflict.')
    } finally {
      busy.value = false
    }
  }
  async function saveSettings () {
    if (!overview.value || !(await settingsForm.value.validate()).valid) return
    await mutate(() => useApi().put(`${base()}/settings`, { open: open.value, capacity: capacity.value === '' ? null : Number(capacity.value), waitlistEnabled: waitlistEnabled.value, expectedVersion: overview.value!.settings.version }))
  }
  async function saveTicketPrice () {
    if (!overview.value || ticketAmount.value === undefined) return; await mutate(() => useApi().put(base() + '/ticket-price', { amountMinor: ticketAmount.value, expectedVersion: overview.value!.settings.version }))
  }
  async function register () {
    if (!(await registrationForm.value.validate()).valid) return
    await mutate(() => useApi().post(base(), { personId: personId.value }))
  }
  async function cancel () {
    if (!cancelling.value || !(await cancelForm.value.validate()).valid) return
    const registration = cancelling.value
    await mutate(() => useApi().post(`${base()}/${registration.id}/cancel`, { reason: reason.value, expectedVersion: registration.version }))
  }
  watch(() => props.event.id, () => {
    selectedOccurrence.value = props.event.occurrences[0]?.id ?? ''
  }, { immediate: true })
  watch(selectedOccurrence, () => {
    overview.value = null; cancelling.value = null; personId.value = null; void load()
  }, { immediate: true })
  watch(() => props.event, () => {
    void load()
  })
</script>
