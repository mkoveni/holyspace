<template>
  <MinistryDialog
    v-model="open"
    :save-label="
      mode === 'room' && newLocation ? 'Create location & room' : undefined
    "
    :submit="save"
    subtitle="Thoughtful spaces and a team you can count on."
    :title="titles[mode]"
    @reload="load"
    @saved="$emit('saved')"
  >
    <v-alert
      v-if="error"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <template v-if="mode === 'location'">
      <v-text-field
        v-model="name"
        label="Location name *"
        maxlength="120"
        :rules="[required]"
      />

      <v-combobox
        v-model="zone"
        hint="For example, Africa/Johannesburg"
        :items="timeZones"
        label="Time zone *"
        persistent-hint
        :rules="[required]"
      />
    </template>

    <template v-if="mode === 'room'">
      <p
        v-if="newLocation"
        class="children-form-note"
      >
        Create a location for this room, such as your church or campus. Save
        this form to create the location and its room.
      </p>

      <v-select
        v-else
        v-model="locationId"
        item-title="name"
        item-value="id"
        :items="availableLocations"
        label="Location *"
        :rules="[required]"
      />

      <v-btn
        v-if="!newLocation || availableLocations.length > 0"
        class="mb-4"
        size="small"
        type="button"
        variant="text"
        @click="newLocation = !newLocation"
      >
        {{ newLocation ? 'Use an existing location' : 'Add a new location' }}
      </v-btn>

      <template v-if="newLocation">
        <v-text-field
          v-model="locationName"
          label="Location name *"
          maxlength="120"
          :rules="[required]"
        />

        <v-combobox
          v-model="zone"
          hint="For example, Africa/Johannesburg"
          :items="timeZones"
          label="Time zone *"
          persistent-hint
          :rules="[required]"
        />
      </template>

      <v-text-field
        v-model="name"
        label="Room name *"
        maxlength="120"
        :rules="[required]"
      />

      <v-text-field
        v-model.number="capacity"
        label="Room capacity *"
        min="1"
        :rules="[positive]"
        type="number"
      />
    </template>

    <template v-if="mode === 'worker'">
      <PersonPicker
        v-model="personId"
        age-category="ADULT"
        class="mb-5"
        :initial-person="defaultPerson"
        label="Team member *"
        :rules="[required]"
      />

      <v-btn
        :disabled="!personId"
        :loading="lookingUp"
        size="small"
        variant="text"
        @click="findWorker"
      >
        Look up existing clearance
      </v-btn>

      <p
        v-if="worker"
        class="children-form-note"
      >
        Existing clearance loaded.
        {{ worker.revokedAt ? 'Currently revoked.' : 'Currently recorded.' }}
        Saving uses its current revision.
      </p>

      <v-autocomplete
        v-if="!worker && auth.hasPermission('identity.manage')"
        v-model="accountId"
        v-model:search="accountSearch"
        clearable
        item-title="label"
        item-value="id"
        :items="accountItems"
        label="Linked user account *"
        :loading="accounts.isLoading"
        no-filter
        :rules="[required]"
      />

      <p
        v-else-if="!worker"
        class="children-form-note"
      >
        An account administrator is needed to link a new team member to their
        account.
      </p>

      <v-row>
        <v-col
          cols="12"
          sm="6"
        >
          <DateTimeSelector
            v-model="screenedUntil"
            label="Screening valid until (local time) *"
            :rules="[required]"
          />
        </v-col>

        <v-col
          cols="12"
          sm="6"
        >
          <DateTimeSelector
            v-model="trainedUntil"
            label="Training valid until (local time) *"
            :rules="[required]"
          />
        </v-col>
      </v-row>

      <v-text-field
        v-model="evidence"
        label="Assessment evidence reference *"
        maxlength="255"
        :rules="[required]"
      />

      <v-checkbox
        v-if="worker"
        v-model="revoked"
        color="primary"
        label="Revoke clearance"
      />

      <div class="children-form-note">
        Verify the person, their account and evidence before recording
        clearance. Screening and training must cover the full session.
      </div>
    </template>

    <template v-if="mode === 'purge'">
      <v-alert
        type="warning"
        variant="tonal"
      >
        Remove up to 50 expired care records. Current records stay available;
        the removal is recorded in the audit history.
      </v-alert>

      <v-checkbox
        v-model="confirmed"
        label="Remove expired care information"
        :rules="[(v) => v === true || 'Please confirm removal.']"
      />
    </template>
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type { Location, Worker } from '../types'
  import type { PersonSummary } from '@/directory/types'
  import axios from 'axios'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import DateTimeSelector from '@/components/common/DateTimeSelector.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { useAuthStore } from '@/stores/auth'
  import { useUserAccountsStore } from '@/stores/userAccounts'
  import { childrenApi } from '../api'
  import { instant, localInput, positive, required } from '../presentation'
  import MinistryDialog from './MinistryDialog.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{
    mode: 'location' | 'room' | 'worker' | 'purge'
    locations: Location[]
    defaultLocation?: string
    defaultPerson?: PersonSummary
  }>()
  const emit = defineEmits<{
    'saved': []
    'location-created': [location: Location]
  }>()
  const timeZones = [
    'Africa/Johannesburg',
    'Africa/Harare',
    'Africa/Gaborone',
    'Europe/London',
    'America/New_York',
  ]
  const newLocation = ref(false)
  const locationName = ref('')
  const createdLocation = ref<Location | null>(null)
  const availableLocations = computed(() => {
    const created = createdLocation.value
    return created
      && !props.locations.some(location => location.id === created.id)
      ? [...props.locations, created]
      : props.locations
  })
  const auth = useAuthStore()
  const accounts = useUserAccountsStore()
  const titles = {
    location: 'A welcoming location',
    room: 'Make room for little people',
    worker: 'A trusted team',
    purge: 'Care record retention',
  }
  const name = ref('')
  const zone = ref('Africa/Johannesburg')
  const locationId = ref('')
  const capacity = ref(20)
  const personId = ref<string | null>(null)
  const accountId = ref<string | null>(null)
  const accountSearch = ref('')
  const accountItems = computed(() =>
    accounts.accounts
      .filter(account => account.status === 'active')
      .map(account => ({
        id: account.id,
        label: `${account.displayName || account.username} · ${account.username}`,
      })),
  )
  const screenedUntil = ref('')
  const trainedUntil = ref('')
  const evidence = ref('')
  const revoked = ref(false)
  const worker = ref<Worker | null>(null)
  const error = ref('')
  const lookingUp = ref(false)
  const confirmed = ref(false)
  let timer: ReturnType<typeof setTimeout>
  let sequence = 0
  async function load () {
    if (props.mode !== 'worker') return
    if (personId.value) await findWorker()
    if (auth.hasPermission('identity.manage')) {
      try {
        await accounts.fetchAll(accountSearch.value)
      } catch (error_) {
        error.value = extractErrorMessage(error_)
      }
    }
  }
  async function findWorker () {
    const current = ++sequence
    if (!personId.value) {
      lookingUp.value = false
      return
    }
    lookingUp.value = true
    error.value = ''
    try {
      const value = await childrenApi.worker(personId.value)
      if (current === sequence) {
        worker.value = value
        screenedUntil.value = localInput(new Date(value.screenedUntil))
        trainedUntil.value = localInput(new Date(value.trainedUntil))
        evidence.value = value.evidenceReference
        revoked.value = !!value.revokedAt
      }
    } catch (error_) {
      if (current === sequence) {
        worker.value = null
        if (!(axios.isAxiosError(error_) && error_.response?.status === 404))
          error.value = extractErrorMessage(error_)
      }
    } finally {
      if (current === sequence) lookingUp.value = false
    }
  }
  watch(personId, () => {
    worker.value = null
    accountId.value = null
    evidence.value = ''
    revoked.value = false
    void findWorker()
  })
  watch(accountSearch, () => {
    clearTimeout(timer)
    timer = setTimeout(async () => {
      if (
        open.value
        && !accountId.value
        && auth.hasPermission('identity.manage')
      ) {
        try {
          await accounts.fetchAll(accountSearch.value)
        } catch (error_) {
          error.value = extractErrorMessage(error_)
        }
      }
    }, 250)
  })
  watch(open, value => {
    if (value) {
      name.value = ''
      locationName.value = ''
      createdLocation.value = null
      newLocation.value = props.locations.length === 0
      zone.value = 'Africa/Johannesburg'
      locationId.value = props.defaultLocation || props.locations[0]?.id || ''
      personId.value = props.defaultPerson?.id || null
      accountId.value = null
      accountSearch.value = ''
      worker.value = null
      screenedUntil.value = localInput(new Date(Date.now() + 365 * 86_400_000))
      trainedUntil.value = screenedUntil.value
      evidence.value = ''
      revoked.value = false
      confirmed.value = false
      error.value = ''
      void load()
    }
  })
  onBeforeUnmount(() => {
    sequence++
    clearTimeout(timer)
  })
  async function createLocation (locationName: string) {
    const values = { name: locationName, timeZone: zone.value }
    const result = await childrenApi.createLocation(values)
    const location = { ...values, id: result.id }
    createdLocation.value = location
    emit('location-created', location)
    return location
  }
  async function save () {
    if (props.mode === 'location') return createLocation(name.value)
    if (props.mode === 'room') {
      if (newLocation.value) {
        const location = await createLocation(locationName.value)
        locationId.value = location.id
        newLocation.value = false
      }
      return childrenApi.createRoom({
        locationId: locationId.value,
        name: name.value,
        capacity: capacity.value,
      })
    }
    if (props.mode === 'purge') {
      if (!confirmed.value) throw new Error('Confirm removal first.')
      return childrenApi.purgeSupport()
    }
    if (lookingUp.value)
      throw new Error('Wait for the clearance lookup to finish.')
    const values = {
      personId: personId.value!,
      screenedUntil: instant(screenedUntil.value),
      trainedUntil: instant(trainedUntil.value),
      evidenceReference: evidence.value,
    }
    if (worker.value)
      return childrenApi.reviewWorker({
        ...values,
        expectedVersion: worker.value.version,
        revoked: revoked.value,
      })
    if (!accountId.value) throw new Error('Choose the linked account first.')
    return childrenApi.registerWorker({ ...values, accountId: accountId.value })
  }
</script>
