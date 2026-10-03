<template>
  <div>
    <PageHeader subtitle="Track marriages, baptisms, and other milestones" title="Life Events">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" variant="flat" @click="openCreateDialog">
          Record Event
        </v-btn>
      </template>
    </PageHeader>

    <v-card elevation="2">
      <v-card-text>
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
        </v-row>
      </v-card-text>

      <v-data-table :headers="headers" item-value="id" :items="lifeEventsStore.lifeEvents" :loading="lifeEventsStore.isLoading">
        <template #item.type="{ item }">
          <router-link class="text-decoration-none text-primary-darken font-weight-medium" :to="`/life-events/${item.id}`">
            {{ item.type }}
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
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { useUiStore } from '@/stores/ui'
  import LifeEventFormDialog from './components/LifeEventFormDialog.vue'

  const lifeEventsStore = useLifeEventsStore()
  const ui = useUiStore()

  const dialog = ref(false)
  const typeFilter = ref<string | null>(null)

  const eventTypes = ['marriage', 'engagement', 'baptism', 'dedication', 'funeral']

  const headers = [
    { title: 'Type', key: 'type', sortable: false },
    { title: 'Event Date', key: 'eventDate' },
    { title: 'Participants', key: 'participants', sortable: false },
    { title: 'Status', key: 'status' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ] as const

  function formatDate (value: string) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  function openCreateDialog () {
    dialog.value = true
  }

  async function loadEvents () {
    try {
      await lifeEventsStore.fetchAll(typeFilter.value ?? undefined)
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to load life events.')
    }
  }

  onMounted(loadEvents)
</script>
