<template>
  <div v-if="event">
    <PageHeader eyebrow="Milestones & moments" :subtitle="formatDate(event.eventDate)" :title="event.type">
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="$router.push('/life-events')">Back</v-btn>
        <v-btn v-if="access.hasPermission('life_events.manage')" prepend-icon="mdi-pencil-outline" variant="tonal" @click="editDialog = true">Edit</v-btn>

        <v-btn
          v-if="access.hasPermission('life_events.manage') && (event.status !== 'cancelled')"
          color="error"
          prepend-icon="mdi-cancel"
          variant="tonal"
          @click="handleCancel"
        >
          Cancel Event
        </v-btn>

        <v-btn
          v-else-if="access.hasPermission('life_events.manage')"
          color="success"
          prepend-icon="mdi-restore"
          variant="tonal"
          @click="handleRestore"
        >
          Restore Event
        </v-btn>

        <v-btn v-if="access.hasPermission('life_events.manage')" color="error" icon="mdi-delete-outline" variant="text" @click="handleDelete" />
      </template>
    </PageHeader>

    <v-row>
      <v-col cols="12" md="7">
        <v-card>
          <v-card-title class="d-flex align-center justify-space-between">
            Details
            <StatusChip :status="event.status" />
          </v-card-title>

          <v-divider />

          <v-card-text>
            <dl class="detail-grid">
              <div class="detail-field"><dt>Event type</dt><dd class="text-capitalize">{{ event.type }}</dd></div>
              <div class="detail-field"><dt>Event date</dt><dd>{{ formatDate(event.eventDate) }}</dd></div>
            </dl>

            <div class="detail-note"><span class="detail-label">Notes</span><p>{{ event.notes || 'No notes recorded for this event.' }}</p></div>

            <template v-if="detailFields.length > 0">
              <h3 class="workspace-section-title">Additional details</h3>

              <dl class="detail-grid">
                <div v-for="field in detailFields" :key="field.label" class="detail-field"><dt>{{ field.label }}</dt><dd>{{ field.value }}</dd></div>
              </dl>
            </template>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card>
          <v-card-title>Participants</v-card-title>
          <v-divider />

          <v-list v-if="event.participants.length > 0">
            <v-list-item
              v-for="participant in event.participants"
              :key="participant.personId"
              :subtitle="participant.role"
              :title="personName(participant.personId)"
              :to="`/people/${participant.personId}`"
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
import { useAuthStore } from '@/stores/auth'
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
  const detailFields = computed(() => {
    const fields: { label: string, value: string }[] = []
    function visit (value: unknown, path: string[]) {
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        for (const [key, child] of Object.entries(value)) visit(child, [...path, key.replace(/([a-z])([A-Z])/g, '$1 $2').replaceAll('_', ' ')])
      } else {
        const format = (entry: unknown): string => {
          if (entry === null || entry === undefined) return 'Not recorded'
          if (typeof entry === 'boolean') return entry ? 'Yes' : 'No'
          if (Array.isArray(entry)) return entry.map(child => format(child)).join(', ')
          if (typeof entry === 'object') return Object.entries(entry).map(([key, child]) => `${key}: ${format(child)}`).join(' · ')
          return String(entry)
        }
        fields.push({ label: path.join(' · '), value: format(value) })
      }
    }
    visit(event.value?.details ?? {}, [])
    return fields
  })

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
      if (access.hasPermission('people.read') && peopleStore.people.length === 0) {
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

const access = useAuthStore()
</script>

<style scoped>
.detail-note { margin: 24px 0; padding: 20px; border-left: 3px solid var(--workspace-gold); border-radius: 0 12px 12px 0; background: #f8f7f3; }
.detail-note p { margin-top: 9px; font-size: 13px; line-height: 1.8; color: var(--workspace-ink); white-space: pre-wrap; }
</style>
