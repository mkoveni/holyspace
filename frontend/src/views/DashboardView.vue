<template>
  <div>
    <PageHeader :subtitle="`${formattedDate} · Here's a snapshot of your church community today.`" :title="`Welcome back, ${firstName}`" />

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

            <div class="text-h4 font-heading font-weight-bold">{{ card.value }}</div>
            <div class="text-caption text-medium-emphasis mt-1">{{ card.title }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mt-2">
      <v-col cols="12" md="7">
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
            No people recorded yet.
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card class="h-100">
          <v-card-item>
            <template #append>
              <v-btn size="small" to="/life-events" variant="tonal">View all</v-btn>
            </template>

            <v-card-title class="font-heading font-weight-bold">Upcoming Life Events</v-card-title>
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
            No life events recorded yet.
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { useAuthStore } from '@/stores/auth'
  import { useFamiliesStore } from '@/stores/families'
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'

  const peopleStore = usePeopleStore()
  const familiesStore = useFamiliesStore()
  const lifeEventsStore = useLifeEventsStore()
  const authStore = useAuthStore()
  const ui = useUiStore()

  const firstName = computed(() => authStore.displayName?.split(' ', 1)[0] ?? 'Admin')

  const formattedDate = computed(() => new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }))

  const summaryCards = computed(() => [
    { title: 'People', value: peopleStore.people.length, icon: 'mdi-account-group-outline', color: 'primary', tint: '#2c4d9414', to: '/people' },
    { title: 'Families', value: familiesStore.families.length, icon: 'mdi-home-heart', color: 'secondary', tint: '#25242610', to: '/families' },
    { title: 'Life Events', value: lifeEventsStore.lifeEvents.length, icon: 'mdi-calendar-star', color: 'primary', tint: '#fce9b960', to: '/life-events' },
    { title: 'Active Members', value: activeMembersCount.value, icon: 'mdi-account-check-outline', color: 'success', tint: '#1E8E5A16', to: '/people' },
  ])

  const activeMembersCount = computed(
    () => peopleStore.people.filter(person => person.status?.toLowerCase() === 'active').length,
  )

  const recentPeople = computed(() => peopleStore.people.slice(0, 5))
  const recentLifeEvents = computed(() => lifeEventsStore.lifeEvents.slice(0, 5))

  function formatDate (value: string) {
    if (!value) return ''
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  onMounted(async () => {
    try {
      await Promise.all([
        peopleStore.fetchAll(),
        familiesStore.fetchAll(),
        lifeEventsStore.fetchAll(),
      ])
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to load dashboard data.')
    }
  })
</script>

<style scoped>
.summary-card {
  height: 100%;
  cursor: pointer;
}
</style>
