<template>
  <EventDialog
    v-model="open"
    :keep-open="reviewing"
    :save-label="preview ? 'Create reviewed dates' : 'Preview dates'"
    :submit="save"
    subtitle="Set the details once, then review every date before creating it."
    :title="event ? 'Add a series' : 'Plan an event'"
    @reload="invalidate"
    @saved="emit('saved')"
  >
    <template v-if="!event">
      <v-text-field
        v-model="title"
        label="Event title"
        maxlength="200"
        :rules="[required]"
      />

      <v-select
        v-model="type"
        :items="eventTypes"
        label="Event type"
      />

      <v-textarea
        v-model="description"
        label="Description"
        maxlength="5000"
      />
    </template>

    <ScheduleFields v-model="schedule" />

    <v-alert
      v-if="preview"
      class="mt-4"
      type="info"
      variant="tonal"
    >
      {{ preview.dates.length }} dates at {{ preview.venue.name }} ·
      {{ schedule.timeZone }}
    </v-alert>

    <div
      v-if="preview"
      aria-label="Schedule preview"
      class="event-preview"
    >
      <div
        v-for="date in preview.dates"
        :key="date.originDate"
        class="event-change"
      >
        {{ dateLabel(date.startsAt, schedule.timeZone) }} –
        {{ dateLabel(date.endsAt, schedule.timeZone) }}
      </div>
    </div>
  </EventDialog>
</template>
<script setup lang="ts">
  import { ref, watch } from 'vue'
  import {
    dateLabel,
    type Event,
    eventApi,
    eventTypes,
    newSchedule,
    required,
    type SchedulePreview,
  } from '../api'
  import EventDialog from './EventDialog.vue'
  import ScheduleFields from './ScheduleFields.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ event?: Event }>()
  const emit = defineEmits<{ saved: [] }>()
  const title = ref(''),
        type = ref('SERVICE'),
        description = ref(''),
        schedule = ref(newSchedule()),
        preview = ref<SchedulePreview>(),
        reviewing = ref(false)
  let requestId = crypto.randomUUID()
  function invalidate () {
    preview.value = undefined
    requestId = crypto.randomUUID()
  }
  watch([schedule, title, type, description], invalidate, { deep: true })
  watch(open, value => {
    if (value) {
      schedule.value = newSchedule()
      title.value = ''
      description.value = ''
      invalidate()
    }
  })
  async function save () {
    if (!preview.value) {
      reviewing.value = true
      preview.value = await eventApi.preview(schedule.value)
      return
    }
    reviewing.value = false
    const body = {
      requestId,
      schedule: schedule.value,
      previewHash: preview.value.hash,
    }
    await (props.event
      ? eventApi.addSeries(props.event.id, {
        ...body,
        expectedVersion: props.event.version,
      })
      : eventApi.create({
        ...body,
        title: title.value,
        type: type.value,
        description: description.value,
      }))
  }
</script>
