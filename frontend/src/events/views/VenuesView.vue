<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="PLACES THAT BRING US TOGETHER"
      subtitle="Maintain venues and protect spaces that can host only one gathering at a time."
      title="A thoughtful place for every gathering."
    >
      <v-btn
        v-if="manage"
        color="primary"
        prepend-icon="mdi-map-marker-plus-outline"
        @click="start()"
      >
        Add venue
      </v-btn>
    </DirectoryHeading>

    <EventNavigation />

    <section class="directory-surface event-toolbar">
      <v-text-field
        v-model="search"
        clearable
        hide-details
        label="Find a venue"
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

    <v-progress-linear
      v-if="loading"
      indeterminate
    />

    <div class="event-grid">
      <article
        v-for="venue in venues"
        :key="venue.id"
        class="directory-surface event-card"
      >
        <v-icon
          class="align-self-start"
          color="primary"
          icon="mdi-map-marker-outline"
        />

        <h2>{{ venue.name }}</h2>

        <p class="directory-muted">
          {{ venue.address || 'No address provided' }}
        </p>

        <div class="d-flex ga-2">
          <v-chip size="small">
            {{ venue.exclusive ? 'Exclusive use' : 'Shared space' }}
          </v-chip>

          <v-chip
            v-if="!venue.active"
            color="warning"
            size="small"
          >
            Archived
          </v-chip>
        </div>

        <v-btn
          v-if="manage"
          class="mt-auto"
          variant="tonal"
          @click="start(venue)"
        >
          Edit venue
        </v-btn>
      </article>
    </div>

    <p
      v-if="!loading && venues.length === 0"
      class="directory-muted pa-6"
    >
      Add your first venue to start planning events.
    </p>

    <div class="d-flex justify-end align-center ga-3 mt-5">
      <span>{{ total }} venues</span>

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

    <EventDialog
      v-model="open"
      :submit="save"
      subtitle="Existing dates retain their recorded venue details. Apply a scheduling change to move an occurrence."
      :title="selected ? 'Edit venue' : 'Add a venue'"
      @reload="reload"
      @saved="load"
    >
      <v-text-field
        v-model="name"
        label="Venue name"
        maxlength="150"
        :rules="[required]"
      />

      <v-textarea
        v-model="address"
        label="Address"
        maxlength="1000"
      />

      <v-switch
        v-if="!selected"
        v-model="exclusive"
        color="primary"
        label="Exclusive use: prevent overlapping events"
      />

      <v-switch
        v-else
        v-model="active"
        color="primary"
        label="Available for new dates"
      />

      <p class="directory-muted">
        Exclusive-use rules are fixed when a venue is created.
      </p>
    </EventDialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { errorMessage, eventApi, required, type Venue } from '../api'
  import EventDialog from '../components/EventDialog.vue'
  import EventNavigation from '../components/EventNavigation.vue'
  import '@/directory/directory.scss'
  import '../events.css'
  const auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('events.manage'))
  const search = ref(''),
        archived = ref(false),
        venues = ref<Venue[]>([]),
        offset = ref(0),
        total = ref(0),
        loading = ref(false),
        error = ref(''),
        open = ref(false),
        selected = ref<Venue>(),
        name = ref(''),
        address = ref(''),
        active = ref(true),
        exclusive = ref(false)
  async function load () {
    loading.value = true
    error.value = ''
    try {
      const page = await eventApi.venues(
        search.value || '',
        offset.value,
        archived.value,
      )
      venues.value = page.venues
      total.value = page.total
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      loading.value = false
    }
  }
  function start (venue?: Venue) {
    selected.value = venue
    name.value = venue?.name || ''
    address.value = venue?.address || ''
    active.value = venue?.active ?? true
    exclusive.value = venue?.exclusive ?? false
    open.value = true
  }
  async function reload () {
    open.value = false
    await load()
  }
  async function save () {
    await (selected.value
      ? eventApi.updateVenue(selected.value.id, {
        expectedVersion: selected.value.version,
        name: name.value,
        address: address.value,
        active: active.value,
      })
      : eventApi.createVenue({
        name: name.value,
        address: address.value,
        exclusive: exclusive.value,
      }))
  }
  onMounted(load)
</script>
