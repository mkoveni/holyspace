<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="GATHERING WITH PURPOSE"
      subtitle="One event, a clear rhythm of dates, and a place for everyone."
      title="Make room for meaningful moments."
    >
      <v-btn
        v-if="manage"
        color="primary"
        prepend-icon="mdi-calendar-plus"
        @click="creating = true"
      >
        Plan event
      </v-btn>
    </DirectoryHeading>

    <EventNavigation />

    <v-tabs
      v-model="tab"
      class="mb-6"
    >
      <v-tab value="calendar">Calendar</v-tab>
      <v-tab value="events">Event library</v-tab>
    </v-tabs>

    <EventCalendar
      v-if="tab === 'calendar'"
      ref="calendar"
    />

    <template v-else>
      <section class="directory-surface event-toolbar">
        <v-text-field
          v-model="search"
          clearable
          hide-details
          label="Find an event"
          @keyup.enter="
            () => {
              offset = 0
              load()
            }
          "
        />

        <v-checkbox
          v-model="archived"
          hide-details
          label="Include archived"
        />

        <v-btn
          :loading="loading"
          variant="tonal"
          @click="
            () => {
              offset = 0
              load()
            }
          "
        >
          Search
        </v-btn>
      </section>

      <v-alert
        v-if="error"
        type="error"
        variant="tonal"
      >
        {{ error }}
      </v-alert>

      <div class="event-grid">
        <article
          v-for="event in events"
          :key="event.id"
          class="directory-surface event-card"
        >
          <v-chip
            class="align-self-start"
            size="small"
          >
            {{ event.type }}
          </v-chip>

          <h2>{{ event.title }}</h2>

          <p class="directory-muted">
            {{ event.description || 'A gathering for your community.' }}
          </p>

          <v-chip
            v-if="!event.active"
            size="small"
          >
            Archived
          </v-chip>

          <v-btn
            class="mt-auto"
            color="primary"
            :to="'/events/' + event.id"
            variant="tonal"
          >
            Open event
          </v-btn>
        </article>
      </div>

      <p
        v-if="!loading && events.length === 0"
        class="directory-muted pa-6"
      >
        No events found.
      </p>

      <div class="d-flex justify-end align-center ga-3 mt-5">
        <span>{{ total }} events</span>

        <v-btn
          :disabled="offset === 0 || loading"
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
          :disabled="offset + 25 >= total || loading"
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
    </template>

    <PlanEventDialog
      v-model="creating"
      @saved="saved"
    />
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref, watch } from 'vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useCreateAction } from '@/navigation/useCreateAction'
  import { useAuthStore } from '@/stores/auth'
  import { errorMessage, type Event, eventApi } from '../api'
  import EventCalendar from '../components/EventCalendar.vue'
  import EventNavigation from '../components/EventNavigation.vue'
  import PlanEventDialog from '../components/PlanEventDialog.vue'
  import '@/directory/directory.scss'
  import '../events.css'
  const auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('events.manage'))
  const tab = ref('calendar'),
        creating = ref(false),
        search = ref(''),
        archived = ref(false),
        events = ref<Event[]>([]),
        total = ref(0),
        offset = ref(0),
        loading = ref(false),
        error = ref(''),
        calendar = ref<InstanceType<typeof EventCalendar>>()
  async function load () {
    loading.value = true
    error.value = ''
    try {
      const page = await eventApi.events(
        search.value || '',
        offset.value,
        archived.value,
      )
      events.value = page.events
      total.value = page.total
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      loading.value = false
    }
  }
  function saved () {
    load()
    calendar.value?.load()
  }
  watch(tab, value => {
    if (value === 'events') load()
  })
  onMounted(load)
  useCreateAction(
    () => manage.value,
    () => {
      creating.value = true
    },
  )
</script>
