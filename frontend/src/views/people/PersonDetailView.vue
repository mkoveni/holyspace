<template>
  <div v-if="person">
    <PageHeader :subtitle="`Member since ${formatDate(person.createdAt)}`" :title="`${person.firstName} ${person.lastName}`">
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="$router.push('/people')">Back</v-btn>
        <v-btn color="primary" prepend-icon="mdi-pencil-outline" variant="flat" @click="editDialog = true">Edit</v-btn>
      </template>
    </PageHeader>

    <v-row>
      <v-col cols="12" md="4">
        <v-card elevation="2">
          <v-card-text class="text-center">
            <v-avatar color="primary" size="88">
              <span class="text-secondary text-h5 font-weight-bold">{{ initials }}</span>
            </v-avatar>

            <h2 class="text-h6 mt-3">{{ person.firstName }} {{ person.lastName }}</h2>
            <StatusChip class="mt-1" :status="person.status" />

            <v-divider class="my-4" />

            <v-list density="compact">
              <v-list-item prepend-icon="mdi-email-outline" :title="person.email ?? 'No email'" />
              <v-list-item prepend-icon="mdi-phone-outline" :title="person.phone ?? 'No phone'" />
              <v-list-item prepend-icon="mdi-cake-variant-outline" :title="person.dateOfBirth ? formatDate(person.dateOfBirth) : 'No date of birth'" />
              <v-list-item prepend-icon="mdi-gender-male-female" :title="genderLabel" />
            </v-list>

            <v-divider class="my-4" />

            <v-select
              v-model="statusValue"
              density="compact"
              hide-details
              :items="statusOptions"
              label="Membership status"
              @update:model-value="handleStatusChange"
            />
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="8">
        <v-card class="mb-4" elevation="2">
          <v-card-title class="d-flex align-center justify-space-between">
            Relationships
            <v-btn prepend-icon="mdi-plus" size="small" variant="tonal" @click="relationshipDialog = true">Add</v-btn>
          </v-card-title>

          <v-divider />

          <v-list v-if="peopleStore.relationships.length > 0">
            <v-list-item
              v-for="relationship in peopleStore.relationships"
              :key="relationship.id"
              :subtitle="relationship.type"
              :title="relatedPersonName(relationship.relatedPersonId)"
            >
              <template #prepend>
                <v-icon color="primary" icon="mdi-account-multiple-outline" />
              </template>

              <template #append>
                <v-chip v-if="relationship.endDate" class="mr-2" size="small" variant="tonal">Ended</v-chip>

                <v-btn
                  v-else
                  color="error"
                  size="small"
                  variant="text"
                  @click="handleEndRelationship(relationship.id)"
                >
                  End
                </v-btn>
              </template>
            </v-list-item>
          </v-list>

          <v-card-text v-else class="text-medium-emphasis">
            No relationships recorded.
          </v-card-text>
        </v-card>

        <v-card elevation="2">
          <v-card-title>Life Events</v-card-title>
          <v-divider />

          <v-list v-if="lifeEvents.length > 0">
            <v-list-item
              v-for="event in lifeEvents"
              :key="event.id"
              :subtitle="formatDate(event.eventDate)"
              :title="event.type"
              :to="`/life-events/${event.id}`"
            >
              <template #prepend>
                <v-icon color="primary" icon="mdi-calendar-star" />
              </template>
            </v-list-item>
          </v-list>

          <v-card-text v-else class="text-medium-emphasis">
            No life events recorded for this person.
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <PersonFormDialog v-model="editDialog" :person="person" @saved="loadPerson" />

    <RelationshipFormDialog
      v-model="relationshipDialog"
      :person-id="person.id"
      @saved="peopleStore.fetchRelationships(person.id)"
    />
  </div>

  <v-skeleton-loader v-else type="article" />
</template>

<script lang="ts" setup>
  import type { LifeEvent } from '@/types/lifeEvent'
  import { computed, onMounted, ref, watch } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import PersonFormDialog from './components/PersonFormDialog.vue'
  import RelationshipFormDialog from './components/RelationshipFormDialog.vue'

  const props = defineProps<{ id: string }>()

  const peopleStore = usePeopleStore()
  const lifeEventsStore = useLifeEventsStore()
  const ui = useUiStore()

  const editDialog = ref(false)
  const relationshipDialog = ref(false)
  const statusValue = ref('')
  const lifeEvents = ref<LifeEvent[]>([])

  const person = computed(() => peopleStore.current)

  const initials = computed(() => `${person.value?.firstName.charAt(0) ?? ''}${person.value?.lastName.charAt(0) ?? ''}`)

  const genderLabel = computed(() => {
    const value = person.value?.gender ?? 'unspecified'
    return value.charAt(0).toUpperCase() + value.slice(1)
  })

  const statusOptions = ['active', 'inactive', 'visitor', 'pending']

  function relatedPersonName (id: string) {
    const related = peopleStore.byId(id)
    return related ? `${related.firstName} ${related.lastName}` : 'Unknown person'
  }

  function formatDate (value: string) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  async function loadPerson () {
    try {
      const data = await peopleStore.fetchOne(props.id)
      statusValue.value = data.status
      await peopleStore.fetchRelationships(props.id)
      lifeEvents.value = await lifeEventsStore.fetchForPerson(props.id)
      if (peopleStore.people.length === 0) {
        await peopleStore.fetchAll()
      }
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to load person.')
    }
  }

  async function handleStatusChange (value: string) {
    if (!person.value || value === person.value.status) return
    try {
      await peopleStore.changeStatus(props.id, { status: value })
      ui.notifySuccess('Membership status updated.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to update status.')
      statusValue.value = person.value.status
    }
  }

  async function handleEndRelationship (relationshipId: string) {
    const confirmed = await ui.confirmAction({
      title: 'End Relationship',
      message: 'Are you sure you want to end this relationship?',
      confirmText: 'End Relationship',
      color: 'error',
    })
    if (!confirmed) return

    try {
      await peopleStore.endRelationship(props.id, relationshipId)
      ui.notifySuccess('Relationship ended.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to end relationship.')
    }
  }

  watch(() => props.id, loadPerson)

  onMounted(loadPerson)
</script>
