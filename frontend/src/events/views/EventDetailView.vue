<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="AN EVENT, A SHARED RHYTHM"
      :subtitle="
        details?.event.description ||
          'Manage the event, its series and each dated gathering.'
      "
      :title="details?.event.title || 'Event details'"
    >
      <v-btn
        prepend-icon="mdi-arrow-left"
        to="/events"
        variant="text"
      >
        All events
      </v-btn>

      <v-btn
        v-if="manage && details?.event.active"
        color="primary"
        prepend-icon="mdi-calendar-plus"
        @click="planning = true"
      >
        Add series
      </v-btn>

      <v-btn
        v-if="manage && details"
        variant="tonal"
        @click="edit"
      >
        Edit details
      </v-btn>
    </DirectoryHeading>

    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-progress-linear
      v-if="loading"
      indeterminate
    />

    <template v-if="details">
      <v-alert
        v-if="!details.event.active"
        class="mb-5"
        type="warning"
        variant="tonal"
      >
        This event is archived.
      </v-alert>

      <section class="directory-surface event-section">
        <h2>Cover image</h2>

        <p class="directory-muted">
          Shown on the public tickets pages and in the member app. Use a wide (16:9) picture; JPEG, PNG or WebP up to 5 MB.
        </p>

        <PortalImage
          :editable="manage"
          label="Event cover image"
          :owner-id="id"
          target="event"
        />
      </section>

      <section class="directory-surface event-section">
        <h2>Series at a glance</h2>

        <div
          v-for="series in details.series"
          :key="series.id"
          class="event-change d-flex flex-wrap ga-3 align-center"
        >
          <v-chip size="small">{{ series.frequency }}</v-chip>

          <span>
            {{ series.count }} dates · every {{ series.interval }} · from
            {{ series.firstDate }}
          </span>

          <span class="directory-muted">
            {{ series.startsAt }} – {{ series.endsAt }} · {{ series.timeZone }}
          </span>
        </div>

        <div class="d-flex justify-end ga-2 mt-3">
          <span class="directory-muted">{{ details.totalSeries }} series</span>

          <v-btn
            :disabled="offset === 0"
            variant="text"
            @click="
              () => {
                offset -= 25
                load()
              }
            "
          >
            Previous
          </v-btn>

          <v-btn
            :disabled="offset + 25 >= details.totalSeries"
            variant="text"
            @click="
              () => {
                offset += 25
                load()
              }
            "
          >
            Next
          </v-btn>
        </div>
      </section>

      <MemberPublication
        v-if="manage"
        :event-id="id"
      />

      <EventCalendar
        :key="calendarRevision"
        :event-id="id"
      />

      <PlanEventDialog
        v-model="planning"
        :event="details.event"
        @saved="refresh"
      />

      <EventDialog
        v-model="editing"
        :submit="save"
        subtitle="Date and venue changes are reviewed on the occurrence. Cancel future dates before archiving an event."
        title="Edit event details"
        @reload="
          () => {
            editing = false
            load()
          }
        "
        @saved="load"
      >
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

        <v-switch
          v-model="active"
          color="primary"
          label="Active event"
        />

        <v-textarea
          v-model="reason"
          label="Reason for change"
          maxlength="1000"
          :rules="[required]"
        />
      </EventDialog>
    </template>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { useRoute } from 'vue-router'
  import PortalImage from '@/components/common/PortalImage.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    errorMessage,
    type Event,
    eventApi,
    eventTypes,
    required,
    type Series,
  } from '../api'
  import EventCalendar from '../components/EventCalendar.vue'
  import EventDialog from '../components/EventDialog.vue'
  import MemberPublication from '../components/MemberPublication.vue'
  import PlanEventDialog from '../components/PlanEventDialog.vue'
  import '@/directory/directory.scss'
  import '../events.css'
  const route = useRoute(),
        id = String(route.params.id),
        auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('events.manage'))
  const details = ref<{
          event: Event
          series: Series[]
          totalSeries: number
        }>(),
        offset = ref(0),
        loading = ref(false),
        error = ref(''),
        planning = ref(false),
        editing = ref(false),
        calendarRevision = ref(0),
        title = ref(''),
        type = ref('SERVICE'),
        description = ref(''),
        active = ref(true),
        reason = ref('')
  async function load () {
    loading.value = true
    try {
      details.value = await eventApi.event(id, offset.value)
      error.value = ''
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      loading.value = false
    }
  }
  function refresh () {
    calendarRevision.value++
    load()
  }
  function edit () {
    if (!details.value) return
    const event = details.value.event
    title.value = event.title
    type.value = event.type
    description.value = event.description
    active.value = event.active
    reason.value = ''
    editing.value = true
  }
  async function save () {
    if (!details.value) return
    await eventApi.update(id, {
      expectedVersion: details.value.event.version,
      title: title.value,
      type: type.value,
      description: description.value,
      active: active.value,
      reason: reason.value,
    })
  }
  onMounted(load)
</script>
