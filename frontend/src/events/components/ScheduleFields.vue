<template>
  <VenuePicker v-model="model.venueId" />

  <div class="event-grid-fields">
    <DateSelector
      v-model="model.firstDate"
      label="First date"
      :rules="[required]"
    />

    <v-text-field
      v-model="model.timeZone"
      hint="IANA zone, e.g. Africa/Johannesburg"
      label="Time zone"
      persistent-hint
      :rules="[required]"
    />

    <v-text-field
      v-model="model.startsAt"
      label="Starts at"
      placeholder="09:00"
      :rules="[required, timeRule]"
    />

    <v-text-field
      v-model="model.endsAt"
      label="Ends at"
      placeholder="10:30"
      :rules="[required, timeRule]"
    />

    <v-select
      v-model="model.frequency"
      :items="['ONCE', 'DAILY', 'WEEKLY', 'MONTHLY']"
      label="Repeats"
    />

    <v-number-input
      v-if="model.frequency !== 'ONCE'"
      v-model="model.interval"
      label="Every"
      :max="52"
      :min="1"
    />

    <v-number-input
      v-if="model.frequency !== 'ONCE'"
      v-model="model.count"
      label="Number of dates"
      :max="104"
      :min="1"
    />

    <v-number-input
      v-model="model.endDayOffset"
      label="Ends days after start"
      :max="7"
      :min="0"
    />
  </div>

  <p class="directory-muted">
    Times use the selected time zone. The preview checks daylight saving and
    exclusive venue conflicts. A series contains up to 104 dates within two
    years.
  </p>
</template>
<script setup lang="ts">
  import { watch } from 'vue'
  import DateSelector from '@/components/common/DateSelector.vue'
  import { required, type Schedule } from '../api'
  import VenuePicker from './VenuePicker.vue'
  const model = defineModel<Schedule>({ required: true })
  watch(
    () => model.value.frequency,
    value => {
      if (value === 'ONCE') model.value.count = 1
    },
  )
  function timeRule (v: string) {
    return (
      /^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(v)
      || 'Enter a time such as 09:30.'
    )
  }
</script>
