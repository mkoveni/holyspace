<template>
  <div v-if="person">
    <PageHeader
      eyebrow="People & community"
      :title="`${person.firstName} ${person.lastName}`"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="$router.push('/people')">Back</v-btn>

        <v-btn
          v-if="access.hasPermission('people.manage')"
          color="primary"
          prepend-icon="mdi-pencil-outline"
          variant="flat"
          @click="editDialog = true"
        >Edit</v-btn>
      </template>
    </PageHeader>

    <v-card aria-label="Person overview" class="person-overview">
      <div class="person-overview__portrait">
        <PortalImage :key="photoRevision" label="Person photo" :owner-id="person.id" target="person" />

        <v-btn
          v-if="access.hasPermission('people.manage')"
          prepend-icon="mdi-camera-outline"
          size="small"
          variant="text"
          @click="photoDialog = true"
        >Change photo</v-btn>
      </div>

      <div class="person-overview__content">
        <div class="person-overview__heading">
          <div><span class="section-eyebrow">AT A GLANCE</span><h2>Contact & membership</h2></div>
          <StatusChip :status="person.status" />
        </div>

        <dl class="person-facts">
          <div class="person-fact"><v-icon icon="mdi-email-outline" /><div><dt>Email address</dt><dd><a v-if="person.email" :href="`mailto:${person.email}`">{{ person.email }}</a><span v-else>Not provided</span></dd></div></div>
          <div class="person-fact"><v-icon icon="mdi-phone-outline" /><div><dt>Phone number</dt><dd><a v-if="person.phone" :href="`tel:${person.phone}`">{{ person.phone }}</a><span v-else>Not provided</span></dd></div></div>
          <div class="person-fact"><v-icon icon="mdi-cake-variant-outline" /><div><dt>Date of birth</dt><dd>{{ person.dateOfBirth ? formatDate(person.dateOfBirth) : 'Not provided' }}</dd></div></div>
          <div class="person-fact"><v-icon icon="mdi-gender-male-female" /><div><dt>Gender</dt><dd>{{ genderLabel }}</dd></div></div>
        </dl>

        <div class="membership-bar">
          <span class="text-caption text-medium-emphasis">Part of the community since {{ formatDate(person.createdAt) }}</span>

          <v-select
            v-if="access.hasPermission('people.manage')"
            v-model="statusValue"
            class="membership-bar__select"
            density="compact"
            hide-details
            :items="statusOptions"
            label="Membership status"
            @update:model-value="handleStatusChange"
          />
        </div>
      </div>
    </v-card>

    <v-card class="profile-details">
      <div class="section-heading">
        <div><span class="section-eyebrow">THE DETAILS</span><h2>Personal information</h2></div>
        <v-icon color="primary" icon="mdi-account-details-outline" size="24" />
      </div>

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
              :subtitle="[address.province, address.postalCode, address.country].filter(Boolean).join(', ')"
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

            <v-btn
              v-if="access.hasPermission('people.manage')"
              prepend-icon="mdi-plus"
              size="small"
              variant="tonal"
              @click="addressDialog = true"
            >Add
              Address</v-btn>
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

            <v-btn
              v-if="access.hasPermission('people.manage')"
              prepend-icon="mdi-plus"
              size="small"
              variant="tonal"
              @click="communicationDialog = true"
            >Add
              Option</v-btn>
          </v-card-actions>
        </v-window-item>

        <v-window-item value="education">
          <v-list v-if="education.length > 0" lines="two">
            <v-list-item
              v-for="entry in education"
              :key="entry.id"
              :subtitle="`${entry.qualification}${entry.fieldOfStudy ? ' · ' + entry.fieldOfStudy : ''}`"
              :title="entry.institution"
            >
              <template #prepend>
                <v-icon color="primary" icon="mdi-school-outline" />
              </template>

              <template #append>
                <span class="text-caption text-medium-emphasis">{{ formatRange(entry.startDate, entry.endDate)
                }}</span>
              </template>
            </v-list-item>
          </v-list>

          <v-card-text v-else class="text-medium-emphasis">No education history recorded.</v-card-text>

          <v-card-actions>
            <v-spacer />

            <v-btn
              v-if="access.hasPermission('people.manage')"
              prepend-icon="mdi-plus"
              size="small"
              variant="tonal"
              @click="educationDialog = true"
            >Add
              Education</v-btn>
          </v-card-actions>
        </v-window-item>

        <v-window-item value="employment">
          <v-list v-if="employment.length > 0" lines="two">
            <v-list-item
              v-for="entry in employment"
              :key="entry.id"
              :subtitle="`${entry.jobTitle}${entry.industry ? ' · ' + entry.industry : ''}`"
              :title="entry.employer"
            >
              <template #prepend>
                <v-icon color="primary" icon="mdi-briefcase-outline" />
              </template>

              <template #append>
                <span class="text-caption text-medium-emphasis">{{ formatRange(entry.startDate, entry.endDate)
                }}</span>
              </template>
            </v-list-item>
          </v-list>

          <v-card-text v-else class="text-medium-emphasis">No employment history recorded.</v-card-text>

          <v-card-actions>
            <v-spacer />

            <v-btn
              v-if="access.hasPermission('people.manage')"
              prepend-icon="mdi-plus"
              size="small"
              variant="tonal"
              @click="employmentDialog = true"
            >Add
              Employment</v-btn>
          </v-card-actions>
        </v-window-item>
      </v-window>
    </v-card>

    <div class="community-heading"><span class="section-eyebrow">CONNECTED IN COMMUNITY</span><h2>Family, relationships & milestones</h2></div>

    <div class="person-community">
      <v-card class="profile-section">
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

      <v-card class="profile-section">
        <v-card-title class="d-flex align-center justify-space-between">
          Relationships
          <v-btn
            v-if="access.hasPermission('people.manage')"
            prepend-icon="mdi-plus"
            size="small"
            variant="tonal"
            @click="relationshipDialog = true"
          >Add</v-btn>
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
              <v-chip v-if="relationship.endedAt" class="mr-2" size="small" variant="tonal">Ended</v-chip>

              <v-btn
                v-else-if="access.hasPermission('people.manage')"
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

      <v-card class="profile-section">
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
    </div>

    <v-dialog v-model="photoDialog" max-width="600" scrollable>
      <v-card title="Person photo">
        <v-card-text><PortalImage
          v-if="photoDialog"
          editable
          label="Person photo"
          :owner-id="person.id"
          target="person"
        /></v-card-text>

        <v-card-actions><v-spacer /><v-btn @click="closePhotoDialog">Done</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

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
  import PortalImage from '@/components/common/PortalImage.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useAuthStore } from '@/stores/auth'
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import { type MembershipStatus, membershipStatuses } from '@/types/person'
  import { formatDate } from '@/utils/format'
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

  const photoDialog = ref(false)
  const photoRevision = ref(0)
  function closePhotoDialog () {
    photoDialog.value = false
  }
  watch(photoDialog, () => {
    if (!photoDialog.value) photoRevision.value++
  })
  const editDialog = ref(false)
  const relationshipDialog = ref(false)
  const addressDialog = ref(false)
  const communicationDialog = ref(false)
  const educationDialog = ref(false)
  const employmentDialog = ref(false)
  const statusValue = ref<MembershipStatus>('prospect')
  const lifeEvents = ref<LifeEvent[]>([])
  const profileTab = ref('addresses')

  const person = computed(() => peopleStore.current)

  const addresses = computed(() => peopleStore.profile?.addresses ?? [])
  const communicationOptions = computed(() => peopleStore.profile?.communicationOptions ?? [])
  const education = computed(() => peopleStore.profile?.education ?? [])
  const employment = computed(() => peopleStore.profile?.employment ?? [])

  const genderLabel = computed(() => {
    const value = person.value?.gender ?? 'unspecified'
    return value.charAt(0).toUpperCase() + value.slice(1)
  })

  const statusOptions = membershipStatuses

  function relatedPersonName (id: string) {
    const related = peopleStore.relationById(id)
    return related ? `${related.relatedFirstName} ${related.relatedLastName}` : 'Unknown person'
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
      lifeEvents.value = access.hasPermission('life_events.read') ? await lifeEventsStore.fetchForPerson(props.id) : []
      if (peopleStore.people.length === 0) {
        await peopleStore.fetchAll()
      }
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to load person.'))
    }
  }

  async function handleStatusChange (value: MembershipStatus) {
    if (!person.value || value === person.value.status) return
    try {
      await peopleStore.changeStatus(props.id, { status: value })
      ui.notifySuccess('Membership status updated.')
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to update status.'))
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
      ui.notifyError(extractErrorMessage(error, 'Unable to end relationship.'))
    }
  }

  watch(() => props.id, loadPerson)

  onMounted(loadPerson)

  const access = useAuthStore()
</script>

<style scoped>
.person-overview { display: grid; grid-template-columns: 168px minmax(0, 1fr); gap: 32px; padding: 28px; margin-bottom: 24px; border: 1px solid rgb(var(--v-theme-primary), .12); background: linear-gradient(120deg, rgb(var(--v-theme-primary), .045), transparent 55%), rgb(var(--v-theme-surface)); }
.person-overview__portrait { align-self: center; text-align: center; }
.person-overview__portrait :deep(.portal-image) { margin-bottom: 8px; }
.person-overview__portrait :deep(.portal-image__preview) { border: 4px solid rgb(var(--v-theme-surface)); box-shadow: 0 4px 20px rgb(var(--v-theme-on-surface), .08); }
.person-overview__heading, .section-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.person-overview h2, .section-heading h2, .community-heading h2 { font-size: 1.125rem; font-weight: 650; letter-spacing: -.025em; margin-top: 5px; }
.section-eyebrow { font-size: .64rem; font-weight: 750; letter-spacing: .16em; color: rgb(var(--v-theme-primary)); }
.person-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px 32px; padding: 24px 0; }
.person-fact { display: flex; align-items: center; gap: 12px; min-width: 0; }
.person-fact > .v-icon { border-radius: 12px; padding: 18px; font-size: 20px; color: rgb(var(--v-theme-primary)); background: rgb(var(--v-theme-primary), .07); }
.person-fact > div { min-width: 0; }
.person-fact dt { font-size: .72rem; color: rgb(var(--v-theme-on-surface), .58); margin-bottom: 4px; }
.person-fact dd { font-size: .875rem; font-weight: 550; overflow-wrap: anywhere; margin: 0; }
.person-fact a { color: inherit; text-decoration: none; }
.person-fact a:hover { color: rgb(var(--v-theme-primary)); text-decoration: underline; }
.membership-bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-top: 16px; border-top: 1px solid rgb(var(--v-theme-on-surface), .08); }
.membership-bar__select { flex: 0 0 210px; }
.profile-details { margin-bottom: 28px; }
.section-heading { padding: 22px 24px 16px; }
.community-heading { margin-bottom: 16px; }
.person-community { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; align-items: start; }
.profile-section { min-width: 0; }
.profile-section :deep(.v-card-title) { min-height: 64px; font-size: 1rem; font-weight: 650; }
.profile-section :deep(.v-card-text) { min-height: 100px; display: flex; align-items: center; font-size: .85rem; }
.profile-section :deep(.v-list-item-title) { white-space: normal; overflow-wrap: anywhere; }
@media (max-width: 1100px) { .person-community { grid-template-columns: repeat(2, minmax(0, 1fr)); } .person-community > :last-child { grid-column: 1 / -1; } .person-overview { gap: 24px; grid-template-columns: 140px minmax(0, 1fr); padding: 24px; } }
@media (max-width: 700px) { .person-overview { grid-template-columns: 1fr; gap: 20px; padding: 20px; } .person-overview__portrait { width: 132px; justify-self: center; } .person-facts { gap: 20px 16px; } .person-fact:nth-child(-n+2) { grid-column: 1 / -1; } .person-fact { align-items: flex-start; gap: 8px; } .person-fact > .v-icon { display: none; } .membership-bar { align-items: stretch; flex-direction: column; } .membership-bar__select { flex: auto; width: 100%; } .person-community { grid-template-columns: 1fr; } .person-community > :last-child { grid-column: auto; } .section-heading { padding: 20px 16px 12px; } }
</style>
