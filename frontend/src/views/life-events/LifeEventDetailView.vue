<template>
  <div v-if="event">
    <PageHeader :subtitle="formatDate(event.eventDate)" :title="event.type">
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="$router.push('/life-events')">Back</v-btn>
        <v-btn prepend-icon="mdi-pencil-outline" variant="tonal" @click="editDialog = true">Edit</v-btn>

        <v-btn
          v-if="event.status !== 'cancelled'"
          color="error"
          prepend-icon="mdi-cancel"
          variant="tonal"
          @click="handleCancel"
        >
          Cancel Event
        </v-btn>

        <v-btn
          v-else
          color="success"
          prepend-icon="mdi-restore"
          variant="tonal"
          @click="handleRestore"
        >
          Restore Event
        </v-btn>

        <v-btn color="error" icon="mdi-delete-outline" variant="text" @click="handleDelete" />
      </template>
    </PageHeader>

    <v-row>
      <v-col cols="12" md="7">
        <v-card elevation="2">
          <v-card-title class="d-flex align-center justify-space-between">
            Details
            <StatusChip :status="event.status" />
          </v-card-title>

          <v-divider />

          <v-list density="comfortable">
            <v-list-item prepend-icon="mdi-tag-outline" subtitle="Type" :title="event.type" />
            <v-list-item prepend-icon="mdi-calendar-outline" subtitle="Event Date" :title="formatDate(event.eventDate)" />
            <v-list-item prepend-icon="mdi-note-text-outline" subtitle="Notes" :title="event.notes || '—'" />
          </v-list>

          <v-card-text v-if="event.details && Object.keys(event.details).length > 0">
            <div class="text-subtitle-2 mb-2">Additional Details</div>

            <v-sheet class="pa-3" color="background" rounded="sm">
              <pre class="text-caption">{{ JSON.stringify(event.details, null, 2) }}</pre>
            </v-sheet>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card elevation="2">
          <v-card-title>Participants</v-card-title>
          <v-divider />

          <v-list v-if="event.participants.length > 0">
            <v-list-item
              v-for="participantId in event.participants"
              :key="participantId"
              :title="personName(participantId)"
              :to="`/people/${participantId}`"
            >
              <template #prepend>
                <v-icon color="primary" icon="mdi-account-outline" />
              </template>
            </v-list-item>
          </v-list>

          <v-card-text v-else class="text-medium-emphasis">
            No participants recorded.
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <LifeEventFormDialog v-model="editDialog" :life-event="event" @saved="load" />
  </div>

  <v-skeleton-loader v-else type="article" />
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import PageHeader from '@/components/common/PageHeader.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import LifeEventFormDialog from './components/LifeEventFormDialog.vue'

  const props = defineProps<{ id: string }>()

  const lifeEventsStore = useLifeEventsStore()
  const peopleStore = usePeopleStore()
  const ui = useUiStore()
  const router = useRouter()

  const editDialog = ref(false)

  const event = computed(() => lifeEventsStore.current)

  function personName (id: string) {
    const person = peopleStore.byId(id)
    return person ? `${person.firstName} ${person.lastName}` : id
  }

  function formatDate (value: string) {
    return new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
  }

  async function load () {
    try {
      await lifeEventsStore.fetchOne(props.id)
      if (peopleStore.people.length === 0) {
        await peopleStore.fetchAll()
      }
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to load life event.')
    }
  }

  async function handleCancel () {
    const confirmed = await ui.confirmAction({
      title: 'Cancel Life Event',
      message: 'This event will be marked as cancelled. Continue?',
      confirmText: 'Cancel Event',
      color: 'error',
    })
    if (!confirmed) return
    try {
      await lifeEventsStore.cancel(props.id)
      ui.notifySuccess('Life event cancelled.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to cancel life event.')
    }
  }

  async function handleRestore () {
    try {
      await lifeEventsStore.restore(props.id)
      ui.notifySuccess('Life event restored.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to restore life event.')
    }
  }

  async function handleDelete () {
    const confirmed = await ui.confirmAction({
      title: 'Delete Life Event',
      message: 'This action cannot be undone. Are you sure you want to delete this life event?',
      confirmText: 'Delete',
      color: 'error',
    })
    if (!confirmed) return
    try {
      await lifeEventsStore.remove(props.id)
      ui.notifySuccess('Life event deleted.')
      await router.push('/life-events')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to delete life event.')
    }
  }

  watch(() => props.id, load)

  onMounted(load)
</script>
