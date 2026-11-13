<template>
  <div>
    <PageHeader eyebrow="Milestones & moments" subtitle="Track marriages, baptisms, and other milestones" title="Life Events">
      <template #actions>
        <v-btn
          v-if="access.hasPermission('life_events.manage')"
          color="primary"
          prepend-icon="mdi-plus"
          variant="flat"
          @click="openCreateDialog"
        >
          Record Event
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="loadError" class="mb-4" type="error" variant="tonal">
      {{ loadError }}
      <v-btn :loading="lifeEventsStore.isLoading" variant="text" @click="loadEvents">Retry</v-btn>
    </v-alert>

    <v-card>
      <v-card-text class="workspace-filter-panel">
        <div class="workspace-section-title">Life events</div>

        <v-row>
          <v-col cols="12" md="4">
            <v-select
              v-model="typeFilter"
              clearable
              hide-details
              :items="eventTypes"
              label="Filter by type"
              @update:model-value="loadEvents"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="monthFilter"
              clearable
              hide-details
              :items="months"
              label="Month"
              @update:model-value="loadEvents"
            />
          </v-col>

          <v-col cols="12" md="2">
            <v-text-field
              v-model.number="yearFilter"
              clearable
              hide-details
              label="Year"
              max="9999"
              min="1"
              type="number"
              @update:model-value="loadEvents"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-switch
              v-model="anniversaries"
              color="primary"
              hide-details
              label="Annual recurrences"
              @update:model-value="loadEvents"
            />
          </v-col>
        </v-row>

        <p class="text-caption mt-3">Annual recurrences includes earlier events in the selected month, up to the selected year. Choose Birth for birthdays or Marriage for wedding anniversaries.</p>
      </v-card-text>

      <v-data-table :headers="headers" item-value="id" :items="lifeEventsStore.lifeEvents" :loading="lifeEventsStore.isLoading">
        <template #item.type="{ item }">
          <router-link class="table-identity" :to="`/life-events/${item.id}`">
            <span class="table-monogram"><v-icon icon="mdi-calendar-heart" size="18" /></span>
            <span><strong class="text-capitalize">{{ item.type }}</strong><small>View milestone</small></span>
          </router-link>
        </template>

        <template #item.eventDate="{ item }">
          {{ formatDate(item.eventDate) }}
        </template>

        <template #item.participants="{ item }">
          {{ item.participants.length }} participant(s)
        </template>

        <template #item.status="{ item }">
          <StatusChip :status="item.status" />
        </template>

        <template #item.actions="{ item }">
          <v-btn icon="mdi-eye-outline" size="small" :to="`/life-events/${item.id}`" variant="text" />
        </template>
      </v-data-table>
    </v-card>

    <LifeEventFormDialog v-model="dialog" @saved="loadEvents" />
  </div>
</template>

<script lang="ts" setup>
  import { onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useAuthStore } from '@/stores/auth'
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { type LifeEventType, lifeEventTypes } from '@/types/lifeEvent'
  import { formatDate } from '@/utils/format'
  import LifeEventFormDialog from './components/LifeEventFormDialog.vue'

  const lifeEventsStore = useLifeEventsStore()

  const loadError = ref('')
  const dialog = ref(false)
  const typeFilter = ref<LifeEventType | null>(null)

  const monthFilter = ref<number | null>(null)
  const yearFilter = ref<number | null>(null)
  const anniversaries = ref(false)
  const months = Array.from({ length: 12 }, (_, index) => ({
    title: new Date(2000, index, 1).toLocaleDateString(undefined, { month: 'long' }),
    value: index + 1,
  }))

  const eventTypes = lifeEventTypes

  const headers = [
    { title: 'Type', key: 'type', sortable: false },
    { title: 'Event Date', key: 'eventDate' },
    { title: 'Participants', key: 'participants', sortable: false },
    { title: 'Status', key: 'status' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ] as const

  function openCreateDialog () {
    dialog.value = true
  }

  async function loadEvents () {
    loadError.value = ''
    try {
      await lifeEventsStore.fetchAll(typeFilter.value ?? undefined, {
        month: monthFilter.value ?? undefined,
        year: yearFilter.value || undefined,
        anniversaries: anniversaries.value,
      })
    } catch (error) {
      loadError.value = extractErrorMessage(error, 'Unable to load life events.')
    }
  }

  onMounted(loadEvents)

  const access = useAuthStore()
</script>
