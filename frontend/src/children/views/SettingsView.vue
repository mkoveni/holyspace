<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="CHILDREN & BELONGING"
      subtitle="Welcoming rooms, clear responsibilities and a team prepared to care."
      title="Good care has good foundations."
    >
      <v-btn
        v-if="auth.hasPermission('children.manage')"
        color="primary"
        prepend-icon="mdi-plus"
        @click="edit('location')"
      >
        Add location
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs active="settings" />

    <div class="children-split">
      <section
        v-if="auth.hasPermission('children.read')"
        class="directory-surface children-section"
      >
        <div class="children-section__heading">
          <h2>Places to belong</h2>

          <v-btn
            v-if="auth.hasPermission('children.manage')"
            variant="text"
            @click="edit('room')"
          >
            Add room
          </v-btn>
        </div>

        <v-alert
          v-if="workspace.error.value"
          type="error"
          variant="tonal"
        >
          {{ workspace.error.value }}
          <v-btn
            variant="text"
            @click="workspace.load"
          >
            Retry
          </v-btn>
        </v-alert>

        <v-select
          v-model="locationId"
          class="mb-5"
          hide-details
          item-title="name"
          item-value="id"
          :items="workspace.data.value"
          label="Location"
        />

        <p
          v-if="location"
          class="children-notice"
        >
          {{ location.timeZone }}
        </p>

        <v-skeleton-loader
          v-if="rooms.loading.value"
          type="list-item-two-line@3"
        />

        <v-alert
          v-if="rooms.error.value"
          type="error"
          variant="tonal"
        >
          {{ rooms.error.value }}
        </v-alert>

        <div
          v-for="room in rooms.data.value"
          :key="room.id"
          class="children-list-row"
        >
          <span class="directory-avatar directory-avatar--1">
            <v-icon icon="mdi-home-heart" />
          </span>

          <div>
            <strong>{{ room.name }}</strong>

            <small>{{ room.capacity }} room seats</small>
          </div>
        </div>

        <DirectoryEmpty
          v-if="rooms.data.value.length === 0 && !rooms.loading.value"
          :description="
            workspace.data.value.length > 0
              ? 'Add rooms to this location, then bring them to life with sessions.'
              : 'Choose Add room to create your first location and room together.'
          "
          title="A room for possibility"
        />

        <div class="d-flex justify-end mt-4">
          <v-btn
            :disabled="!offset"
            variant="text"
            @click="offset -= 100"
          >
            Previous locations
          </v-btn>

          <v-btn
            :disabled="workspace.data.value.length < 100"
            variant="text"
            @click="offset += 100"
          >
            Next locations
          </v-btn>
        </div>
      </section>

      <aside>
        <section
          v-if="auth.hasPermission('children.safeguarding')"
          class="directory-surface children-section"
        >
          <span class="directory-eyebrow">PREPARED TO CARE</span>
          <h2 class="mt-4">A trusted team</h2>

          <p>
            Record or review a team member’s screening, training and account
            connection before assigning them to a session.
          </p>

          <v-btn
            color="primary"
            variant="tonal"
            @click="edit('worker')"
          >
            Manage team clearance
          </v-btn>
        </section>

        <section
          v-if="
            auth.hasPermission('children.support') &&
              auth.hasPermission('children.supervise')
          "
          class="directory-surface children-section"
        >
          <h2>Keep care information current</h2>
          <p>Remove expired care information in small, recorded batches.</p>

          <v-btn
            variant="outlined"
            @click="edit('purge')"
          >
            Remove expired care records
          </v-btn>
        </section>
      </aside>
    </div>

    <SetupDialog
      v-model="editor"
      :default-location="locationId"
      :locations="workspace.data.value"
      :mode="mode"
      @location-created="locationCreated"
      @saved="saved"
    />

    <v-snackbar
      v-model="success"
      color="primary"
    >
      Changes saved.
    </v-snackbar>
  </div>
</template>
<script setup lang="ts">
  import type { Location } from '../types'
  import { computed, onMounted, ref, watch } from 'vue'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import SetupDialog from '../components/SetupDialog.vue'
  import { useLoad } from '../useLoad'
  const auth = useAuthStore()
  const locationId = ref('')
  const offset = ref(0)
  const editor = ref(false)
  const mode = ref<'location' | 'room' | 'worker' | 'purge'>('location')
  const success = ref(false)
  const workspace = useLoad(
    () =>
      auth.hasPermission('children.read')
        ? childrenApi.locations(offset.value)
        : Promise.resolve([]),
    [],
  )
  const rooms = useLoad(
    () =>
      locationId.value
        ? childrenApi.rooms(locationId.value)
        : Promise.resolve([]),
    [],
  )
  const location = computed(() =>
    workspace.data.value.find(l => l.id === locationId.value),
  )
  function edit (value: typeof mode.value) {
    mode.value = value
    editor.value = true
  }
  function locationCreated (location: Location) {
    workspace.data.value = [...workspace.data.value, location]
    locationId.value = location.id
  }
  async function saved () {
    success.value = true
    const selectedLocation = location.value
    await workspace.load()
    if (
      selectedLocation
      && !workspace.data.value.some(item => item.id === selectedLocation.id)
    ) {
      workspace.data.value = [...workspace.data.value, selectedLocation]
    }
    if (!locationId.value) locationId.value = workspace.data.value[0]?.id || ''
    await rooms.load()
  }
  watch(locationId, rooms.load)
  watch(offset, async () => {
    await workspace.load()
    locationId.value = workspace.data.value[0]?.id || ''
  })
  onMounted(async () => {
    await workspace.load()
    locationId.value = workspace.data.value[0]?.id || ''
  })
</script>
