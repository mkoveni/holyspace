<template>
  <div>
    <PageHeader eyebrow="Community overview" :subtitle="`${formattedDate} · Here's a snapshot of your church community today.`" :title="`Welcome back, ${firstName}`" />

    <v-alert v-if="loadError" class="mb-4" type="warning" variant="tonal">
      {{ loadError }}
      <v-btn :loading="isLoading" variant="text" @click="loadDashboard">Retry</v-btn>
    </v-alert>

    <v-card v-if="authStore.hasPermission('member.portal')" class="pa-5 mb-4">
      <h2>My church</h2>
      <p v-if="authStore.user?.personId" class="my-3">View personal details, household membership, registrations and giving statements. Authenticator setup is required before opening your records.</p>
      <p v-else class="my-3">Your account needs to be linked to your person record before My church can show your information.</p>
      <v-btn v-if="authStore.user?.personId" to="/member" color="primary">Open My church</v-btn>
      <v-btn v-else-if="authStore.hasPermission('identity.manage')" to="/user-accounts" color="primary">Set up member access</v-btn>
      <p v-else>Ask your church account administrator to link your account to the correct person.</p>
    </v-card>
    <v-alert v-else-if="!summaryCards.length" type="info" class="mb-4" title="Account setup required">
      Your account does not have member portal access. Ask your church administrator to open User Accounts, find your username, and enable My church for your linked person.
      <p class="mt-2">Signing in alone does not grant access to member records.</p>
    </v-alert>

    <v-row>
      <v-col
        v-for="card in summaryCards"
        :key="card.title"
        cols="12"
        md="3"
        sm="6"
      >
        <v-card class="summary-card elevate-on-hover" :to="card.to">
          <v-card-text>
            <div class="d-flex align-center mb-3">
              <div class="stat-icon-badge" :style="{ backgroundColor: card.tint }">
                <v-icon :color="card.color" :icon="card.icon" size="22" />
              </div>
            </div>

            <div class="text-h4 font-heading font-weight-bold">{{ isLoading ? '…' : card.value }}</div>
            <div class="text-caption text-medium-emphasis mt-1">{{ card.title }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mt-2">
      <v-col v-if="authStore.hasPermission('people.read')" cols="12" md="7">
        <v-card class="h-100">
          <v-card-item>
            <template #append>
              <v-btn size="small" to="/people" variant="tonal">View all</v-btn>
            </template>

            <v-card-title class="font-heading font-weight-bold">Recent People</v-card-title>
          </v-card-item>

          <v-divider />

          <v-list v-if="recentPeople.length > 0" class="py-0" lines="two">
            <v-list-item
              v-for="person in recentPeople"
              :key="person.id"
              class="px-4"
              :subtitle="person.email ?? 'No email on file'"
              :title="`${person.firstName} ${person.lastName}`"
              :to="`/people/${person.id}`"
            >
              <template #prepend>
                <v-avatar color="primary-lighten-1">
                  <span class="text-secondary font-weight-bold">{{ person.firstName.charAt(0) }}{{ person.lastName.charAt(0) }}</span>
                </v-avatar>
              </template>

              <template #append>
                <StatusChip :status="person.status" />
              </template>
            </v-list-item>
          </v-list>

          <v-card-text v-else class="text-medium-emphasis">
            {{ isLoading ? 'Loading people…' : loaded.people ? 'No people recorded yet.' : 'People are unavailable. Retry above.' }}
          </v-card-text>
        </v-card>
      </v-col>

      <v-col v-if="authStore.hasPermission('life_events.read')" cols="12" md="5">
        <v-card class="h-100">
          <v-card-item>
            <template #append>
              <v-btn size="small" to="/life-events" variant="tonal">View all</v-btn>
            </template>

            <v-card-title class="font-heading font-weight-bold">Recent Life Events</v-card-title>
          </v-card-item>

          <v-divider />

          <v-list v-if="recentLifeEvents.length > 0" class="py-0">
            <v-list-item
              v-for="event in recentLifeEvents"
              :key="event.id"
              class="px-4"
              :subtitle="formatDate(event.eventDate)"
              :title="event.type"
              :to="`/life-events/${event.id}`"
            >
              <template #prepend>
                <div class="stat-icon-badge" style="width: 40px; height: 40px; background-color: #2c4d9414;">
                  <v-icon color="primary" icon="mdi-calendar-star" size="20" />
                </div>
              </template>
            </v-list-item>
          </v-list>

          <v-card-text v-else class="text-medium-emphasis">
            {{ isLoading ? 'Loading life events…' : loaded.events ? 'No life events recorded yet.' : 'Life events are unavailable. Retry above.' }}
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { useAuthStore } from '@/stores/auth'
  import { useFamiliesStore } from '@/stores/families'
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { usePeopleStore } from '@/stores/people'

  const peopleStore = usePeopleStore()
  const familiesStore = useFamiliesStore()
  const lifeEventsStore = useLifeEventsStore()
  const authStore = useAuthStore()

  const isLoading = ref(false)
  const loadError = ref('')
  const loaded = ref({ people: false, families: false, events: false })

  const firstName = computed(() => authStore.displayName?.split(' ', 1)[0] ?? 'Admin')

  const formattedDate = computed(() => new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }))

  const summaryCards = computed(() => [
    { permission: 'people.read', title: 'People', value: loaded.value.people ? peopleStore.people.length : '—', icon: 'mdi-account-group-outline', color: 'primary', tint: '#2c4d9414', to: '/people' },
    { permission: 'families.read', title: 'Families', value: loaded.value.families ? familiesStore.families.length : '—', icon: 'mdi-home-heart', color: 'secondary', tint: '#25242610', to: '/families' },
    { permission: 'life_events.read', title: 'Life Events', value: loaded.value.events ? lifeEventsStore.lifeEvents.length : '—', icon: 'mdi-calendar-star', color: 'primary', tint: '#fce9b960', to: '/life-events' },
    { permission: 'people.read', title: 'Active Members', value: loaded.value.people ? activeMembersCount.value : '—', icon: 'mdi-account-check-outline', color: 'success', tint: '#1E8E5A16', to: '/people' },
  ].filter(card => authStore.hasPermission(card.permission)))

  const activeMembersCount = computed(
    () => peopleStore.people.filter(person => person.status?.toLowerCase() === 'active').length,
  )

  // Copy before sorting to keep store order intact and support the existing ES2022 target.
  // eslint-disable-next-line unicorn/no-array-sort
  const recentPeople = computed(() => [...peopleStore.people].sort((a, b) => Date.parse(b.createdAt) - Date.parse(a.createdAt)).slice(0, 5))
  // eslint-disable-next-line unicorn/no-array-sort
  const recentLifeEvents = computed(() => [...lifeEventsStore.lifeEvents].sort((a, b) => Date.parse(b.createdAt) - Date.parse(a.createdAt)).slice(0, 5))

  function formatDate (value: string) {
    if (!value) return ''
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  async function loadDashboard () {
    if (isLoading.value) return
    isLoading.value = true
    loadError.value = ''
    const results = await Promise.allSettled([
      authStore.hasPermission('people.read') ? peopleStore.fetchAll() : Promise.resolve(),
      authStore.hasPermission('families.read') ? familiesStore.fetchAll() : Promise.resolve(),
      authStore.hasPermission('life_events.read') ? lifeEventsStore.fetchAll() : Promise.resolve(),
    ])
    loaded.value = {
      people: results[0]?.status === 'fulfilled',
      families: results[1]?.status === 'fulfilled',
      events: results[2]?.status === 'fulfilled',
    }
    const failed = results.find(result => result.status === 'rejected')
    if (failed?.status === 'rejected') {
      loadError.value = failed.reason instanceof Error ? failed.reason.message : 'Unable to load dashboard data.'
    }
    isLoading.value = false
  }

  onMounted(loadDashboard)

</script>

<style scoped>
.summary-card {
  height: 100%;
  cursor: pointer;
}
</style>
