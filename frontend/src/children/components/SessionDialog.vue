<template>
  <MinistryDialog
    v-model="open"
    save-label="Create session"
    :submit="save"
    subtitle="Choose a class, a welcoming room and time together."
    title="Plan a little belonging"
    @reload="load"
    @saved="$emit('saved')"
  >
    <v-alert
      v-if="error"
      class="mb-5"
      type="error"
      variant="tonal"
    >
      {{ error }}
      <v-btn
        variant="text"
        @click="load"
      >
        Retry
      </v-btn>
    </v-alert>

    <v-select
      v-model="programmeId"
      item-title="name"
      item-value="id"
      :items="programmes"
      label="Programme *"
      :rules="[required]"
    />

    <v-select
      v-model="classId"
      item-title="name"
      item-value="id"
      :items="classes"
      label="Class *"
      :rules="[required]"
    />

    <v-row>
      <v-col
        cols="12"
        sm="6"
      >
        <v-select
          v-model="locationId"
          item-title="name"
          item-value="id"
          :items="locations"
          label="Location *"
          :rules="[required]"
        />
      </v-col>

      <v-col
        cols="12"
        sm="6"
      >
        <v-select
          v-model="roomId"
          item-title="name"
          item-value="id"
          :items="rooms"
          label="Room *"
          :rules="[required]"
        />
      </v-col>
    </v-row>

    <v-row>
      <v-col
        cols="12"
        sm="6"
      >
        <DateTimeSelector
          v-model="startsAt"
          label="Starts (your local time) *"
          :rules="[required]"
        />
      </v-col>

      <v-col
        cols="12"
        sm="6"
      >
        <DateTimeSelector
          v-model="endsAt"
          label="Ends (your local time) *"
          :rules="[required]"
        />
      </v-col>
    </v-row>

    <v-text-field
      v-model.number="seats"
      :hint="room ? `Room capacity: ${room.capacity}` : 'Choose a room first.'"
      label="Session seats *"
      :max="room?.capacity"
      min="1"
      persistent-hint
      :rules="[positive]"
      type="number"
    />

    <div class="children-form-note mt-5">
      {{ location ? `Location time zone: ${location.timeZone}. ` : '' }}Times
      entered here use your device’s time zone ({{ deviceZone }}). Assign a
      qualified team and open the session when you are ready for arrivals.
    </div>

    <p
      v-if="locations.length === 0 && !loading"
      class="children-notice"
    >
      Create a location and room in
      <router-link
        class="text-primary"
        to="/children/settings"
      >
        Spaces & team
      </router-link>
      first.
    </p>
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type { Location, MinistryClass, Programme, Room } from '../types'
  import { computed, ref, watch } from 'vue'
  import DateTimeSelector from '@/components/common/DateTimeSelector.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { childrenApi } from '../api'
  import {
    instant,
    localInput,
    positive,
    required,
    validPeriod,
  } from '../presentation'
  import MinistryDialog from './MinistryDialog.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{
    defaultProgramme?: string
    defaultClass?: string
  }>()
  defineEmits<{ saved: [] }>()
  const programmes = ref<Programme[]>([])
  const classes = ref<MinistryClass[]>([])
  const locations = ref<Location[]>([])
  const rooms = ref<Room[]>([])
  const programmeId = ref('')
  const classId = ref('')
  const locationId = ref('')
  const roomId = ref('')
  const startsAt = ref('')
  const endsAt = ref('')
  const seats = ref(20)
  const error = ref('')
  const loading = ref(false)
  const deviceZone = Intl.DateTimeFormat().resolvedOptions().timeZone
  const room = computed(() => rooms.value.find(r => r.id === roomId.value))
  const location = computed(() =>
    locations.value.find(l => l.id === locationId.value),
  )
  let classSequence = 0
  let roomSequence = 0
  async function load () {
    error.value = ''
    loading.value = true
    try {
      const [p, l] = await Promise.all([
        childrenApi.programmes(),
        childrenApi.locations(),
      ])
      if (
        props.defaultProgramme
        && !p.some(v => v.id === props.defaultProgramme)
      )
        p.push(await childrenApi.programme(props.defaultProgramme))
      programmes.value = p
      locations.value = l
      programmeId.value = props.defaultProgramme || p[0]?.id || ''
      locationId.value = l[0]?.id || ''
      await Promise.all([loadClasses(), loadRooms()])
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      loading.value = false
    }
  }
  async function loadClasses () {
    const sequence = ++classSequence
    classes.value = []
    classId.value = ''
    if (!programmeId.value) return
    try {
      const result = await childrenApi.classes(programmeId.value)
      if (sequence === classSequence) {
        classes.value = result
        classId.value
          = result.find(c => c.id === props.defaultClass)?.id
            || result[0]?.id
            || ''
      }
    } catch (error_) {
      if (sequence === classSequence) error.value = extractErrorMessage(error_)
    }
  }
  async function loadRooms () {
    const sequence = ++roomSequence
    rooms.value = []
    roomId.value = ''
    if (!locationId.value) return
    try {
      const result = await childrenApi.rooms(locationId.value)
      if (sequence === roomSequence) {
        rooms.value = result
        roomId.value = result[0]?.id || ''
        seats.value = result[0]?.capacity || 20
      }
    } catch (error_) {
      if (sequence === roomSequence) error.value = extractErrorMessage(error_)
    }
  }
  watch(programmeId, loadClasses)
  watch(locationId, loadRooms)
  watch(open, value => {
    if (value) {
      startsAt.value = localInput()
      endsAt.value = localInput(new Date(Date.now() + 3_600_000))
      void load()
    }
  })
  async function save () {
    validPeriod(startsAt.value, endsAt.value)
    await childrenApi.createSession({
      classId: classId.value,
      roomId: roomId.value,
      startsAt: instant(startsAt.value),
      endsAt: instant(endsAt.value),
      seatLimit: seats.value,
    })
  }
</script>
