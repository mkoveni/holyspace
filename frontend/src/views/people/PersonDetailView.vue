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
        <v-card>
          <v-card-text class="text-center">
            <v-avatar color="primary" size="88">
              <span class="text-white text-h5 font-weight-bold">{{ initials }}</span>
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

        <v-card class="mt-4">
          <v-card-title class="d-flex align-center justify-space-between">
            Families
            <v-icon color="medium-emphasis" icon="mdi-home-heart" size="20" />
          </v-card-title>

          <v-divider />

          <v-list v-if="peopleStore.families.length > 0">
            <v-list-item
              v-for="personFamily in peopleStore.families"
              :key="personFamily.id"
              :subtitle="personFamily.role"
              :title="personFamily.name"
              :to="`/families/${personFamily.id}`"
            />
          </v-list>

          <v-card-text v-else class="text-medium-emphasis">
            Not part of any family yet.
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="8">
        <v-card class="mb-4">
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

        <v-card class="mb-4">
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

        <v-card>
          <v-tabs v-model="profileTab" color="primary">
            <v-tab value="addresses">Addresses</v-tab>
            <v-tab value="communication">Communication</v-tab>
            <v-tab value="education">Education</v-tab>
            <v-tab value="employment">Employment</v-tab>
          </v-tabs>

          <v-divider />

          <v-window v-model="profileTab">
            <v-window-item value="addresses">
              <v-list v-if="addresses.length > 0" lines="two">
                <v-list-item
                  v-for="address in addresses"
                  :key="address.id"
                  :subtitle="[address.province, address.postal_code, address.country].filter(Boolean).join(', ')"
                  :title="`${address.line1}${address.line2 ? ', ' + address.line2 : ''}, ${address.city}`"
                >
                  <template #prepend>
                    <v-icon color="primary" icon="mdi-map-marker-outline" />
                  </template>

                  <template #append>
                    <v-chip size="small" variant="tonal">{{ address.type }}</v-chip>
                  </template>
                </v-list-item>
              </v-list>

              <v-card-text v-else class="text-medium-emphasis">No addresses recorded.</v-card-text>

              <v-card-actions>
                <v-spacer />
                <v-btn prepend-icon="mdi-plus" size="small" variant="tonal" @click="addressDialog = true">Add Address</v-btn>
              </v-card-actions>
            </v-window-item>

            <v-window-item value="communication">
              <v-list v-if="communicationOptions.length > 0">
                <v-list-item
                  v-for="option in communicationOptions"
                  :key="option.id"
                  :subtitle="option.channel"
                  :title="option.value"
                >
                  <template #prepend>
                    <v-icon color="primary" icon="mdi-at" />
                  </template>

                  <template #append>
                    <v-chip v-if="option.preferred" color="primary" size="small" variant="tonal">Preferred</v-chip>
                  </template>
                </v-list-item>
              </v-list>

              <v-card-text v-else class="text-medium-emphasis">No communication options recorded.</v-card-text>

              <v-card-actions>
                <v-spacer />
                <v-btn prepend-icon="mdi-plus" size="small" variant="tonal" @click="communicationDialog = true">Add Option</v-btn>
              </v-card-actions>
            </v-window-item>

            <v-window-item value="education">
              <v-list v-if="education.length > 0" lines="two">
                <v-list-item
                  v-for="entry in education"
                  :key="entry.id"
                  :subtitle="`${entry.qualification}${entry.field_of_study ? ' · ' + entry.field_of_study : ''}`"
                  :title="entry.institution"
                >
                  <template #prepend>
                    <v-icon color="primary" icon="mdi-school-outline" />
                  </template>

                  <template #append>
                    <span class="text-caption text-medium-emphasis">{{ formatRange(entry.start_date, entry.end_date) }}</span>
                  </template>
                </v-list-item>
              </v-list>

              <v-card-text v-else class="text-medium-emphasis">No education history recorded.</v-card-text>

              <v-card-actions>
                <v-spacer />
                <v-btn prepend-icon="mdi-plus" size="small" variant="tonal" @click="educationDialog = true">Add Education</v-btn>
              </v-card-actions>
            </v-window-item>

            <v-window-item value="employment">
              <v-list v-if="employment.length > 0" lines="two">
                <v-list-item
                  v-for="entry in employment"
                  :key="entry.id"
                  :subtitle="`${entry.job_title}${entry.industry ? ' · ' + entry.industry : ''}`"
                  :title="entry.employer"
                >
                  <template #prepend>
                    <v-icon color="primary" icon="mdi-briefcase-outline" />
                  </template>

                  <template #append>
                    <span class="text-caption text-medium-emphasis">{{ formatRange(entry.start_date, entry.end_date) }}</span>
                  </template>
                </v-list-item>
              </v-list>

              <v-card-text v-else class="text-medium-emphasis">No employment history recorded.</v-card-text>

              <v-card-actions>
                <v-spacer />
                <v-btn prepend-icon="mdi-plus" size="small" variant="tonal" @click="employmentDialog = true">Add Employment</v-btn>
              </v-card-actions>
            </v-window-item>
          </v-window>
        </v-card>
      </v-col>
    </v-row>

    <PersonFormDialog v-model="editDialog" :person="person" @saved="loadPerson" />

    <RelationshipFormDialog
      v-model="relationshipDialog"
      :person-id="person.id"
      @saved="peopleStore.fetchRelationships(person.id)"
    />

    <AddressFormDialog v-model="addressDialog" :person-id="person.id" />
    <CommunicationOptionFormDialog v-model="communicationDialog" :person-id="person.id" />
    <EducationFormDialog v-model="educationDialog" :person-id="person.id" />
    <EmploymentFormDialog v-model="employmentDialog" :person-id="person.id" />
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
  import AddressFormDialog from './components/AddressFormDialog.vue'
  import CommunicationOptionFormDialog from './components/CommunicationOptionFormDialog.vue'
  import EducationFormDialog from './components/EducationFormDialog.vue'
  import EmploymentFormDialog from './components/EmploymentFormDialog.vue'
  import PersonFormDialog from './components/PersonFormDialog.vue'
  import RelationshipFormDialog from './components/RelationshipFormDialog.vue'

  const props = defineProps<{ id: string }>()

  const peopleStore = usePeopleStore()
  const lifeEventsStore = useLifeEventsStore()
  const ui = useUiStore()

  const editDialog = ref(false)
  const relationshipDialog = ref(false)
  const addressDialog = ref(false)
  const communicationDialog = ref(false)
  const educationDialog = ref(false)
  const employmentDialog = ref(false)
  const statusValue = ref('')
  const lifeEvents = ref<LifeEvent[]>([])
  const profileTab = ref('addresses')

  const person = computed(() => peopleStore.current)

  const addresses = computed(() => peopleStore.profile?.addresses ?? [])
  const communicationOptions = computed(() => peopleStore.profile?.communicationOptions ?? [])
  const education = computed(() => peopleStore.profile?.education ?? [])
  const employment = computed(() => peopleStore.profile?.employment ?? [])

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

  function formatRange (start: string | null, end: string | null) {
    const from = start ? new Date(start).getFullYear() : '—'
    const to = end ? new Date(end).getFullYear() : 'Present'
    return `${from} – ${to}`
  }

  async function loadPerson () {
    try {
      const data = await peopleStore.fetchOne(props.id)
      statusValue.value = data.status
      await Promise.all([
        peopleStore.fetchRelationships(props.id),
        peopleStore.fetchProfile(props.id),
        peopleStore.fetchFamilies(props.id),
      ])
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
