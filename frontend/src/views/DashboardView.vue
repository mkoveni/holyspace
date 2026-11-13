<template>
  <div>
    <PageHeader eyebrow="Community overview" :subtitle="`${formattedDate} · Pick up where you left off.`" :title="`${greeting}, ${firstName}`" />

    <section v-if="quickActions.length > 0" aria-label="Start something" class="dash-actions">
      <router-link v-for="action in quickActions" :key="action.to" class="dash-action" :to="action.to">
        <span class="dash-action__icon"><v-icon :icon="action.icon" size="22" /></span>
        <span class="dash-action__text"><strong>{{ action.title }}</strong><small>{{ action.description }}</small></span>
        <v-icon class="dash-action__go" icon="mdi-arrow-right" size="18" />
      </router-link>
    </section>

    <v-alert v-if="loadError" class="mb-4" type="warning" variant="tonal">
      {{ loadError }}
      <v-btn :loading="isLoading" variant="text" @click="loadDashboard">Retry</v-btn>
    </v-alert>

    <v-card v-if="authStore.hasPermission('member.portal')" class="pa-5 mb-4 member-card">
      <div>
        <h2>My church</h2>
        <p v-if="authStore.user?.personId" class="mt-1">Your details, household, registrations and giving statements. Authenticator setup is required before opening your records.</p>
        <p v-else class="mt-1">Your account needs to be linked to your person record before My church can show your information.</p>
        <p v-if="!authStore.user?.personId && !authStore.hasPermission('identity.manage')" class="mt-1">Ask your church account administrator to link your account to the correct person.</p>
      </div>

      <v-btn v-if="authStore.user?.personId" color="primary" to="/member">Open My church</v-btn>
      <v-btn v-else-if="authStore.hasPermission('identity.manage')" color="primary" to="/user-accounts">Set up member access</v-btn>
    </v-card>

    <v-alert v-else-if="summaryCards.length === 0" class="mb-4" title="Account setup required" type="info">
      Your account does not have member portal access. Ask your church administrator to open User Accounts, find your username, and enable My church for your linked person.
      <p class="mt-2">Signing in alone does not grant access to member records.</p>
    </v-alert>

    <section v-if="attention.items.value.length > 0" aria-label="Needs your attention" class="attention-strip">
      <router-link
        v-for="item in attention.items.value"
        :key="item.id"
        class="attention-item"
        :class="`attention-item--${item.tone}`"
        :to="item.to"
      >
        <v-icon :color="item.tone" :icon="item.icon" size="22" />
        <span><strong>{{ item.title }}</strong><small>{{ item.detail }}</small></span>
        <v-icon icon="mdi-chevron-right" size="18" />
      </router-link>
    </section>

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

            <v-skeleton-loader v-if="isLoading" class="skeleton-block" height="40" type="heading" />
            <div v-else class="text-h4 font-heading font-weight-bold">{{ card.value }}</div>
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
              :subtitle="membershipLabels[person.membershipType]"
              :title="fullName(person)"
              :to="`/people/${person.id}`"
            >
              <template #prepend>
                <v-avatar color="primary-lighten-1">
                  <span class="text-secondary font-weight-bold">{{ initials(person) }}</span>
                </v-avatar>
              </template>
            </v-list-item>
          </v-list>

          <v-skeleton-loader v-else-if="isLoading" type="list-item-avatar-two-line@3" />

          <div v-else-if="loaded.people" class="empty-state">
            <div class="empty-state__icon"><v-icon icon="mdi-account-plus-outline" size="28" /></div>
            <h3>No people yet</h3>
            <p>Add your first person to start building your community directory.</p>
            <v-btn v-if="authStore.hasPermission('people.manage')" color="primary" to="/people?action=create" variant="flat">Add person</v-btn>
          </div>

          <v-card-text v-else class="text-medium-emphasis">People are unavailable. Retry above.</v-card-text>
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

          <v-skeleton-loader v-else-if="isLoading" type="list-item-two-line@3" />

          <div v-else-if="loaded.events" class="empty-state">
            <div class="empty-state__icon"><v-icon icon="mdi-calendar-star" size="28" /></div>
            <h3>No life events yet</h3>
            <p>Baptisms, weddings and milestones you record will appear here.</p>
          </div>

          <v-card-text v-else class="text-medium-emphasis">Life events are unavailable. Retry above.</v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script lang="ts" setup>
  import type { PersonSummary } from '@/directory/types'
  import { computed, onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useAttention } from '@/composables/useAttention'
  import { directoryApi } from '@/directory/api'
  import { fullName, initials, membershipLabels } from '@/directory/presentation'
  import { visibleQuickActions } from '@/navigation/quickActions'
  import { useAuthStore } from '@/stores/auth'
  import { useFamiliesStore } from '@/stores/families'
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { formatDate } from '@/utils/format'

  const directoryPeople = ref<PersonSummary[]>([])
  const familiesStore = useFamiliesStore()
  const lifeEventsStore = useLifeEventsStore()
  const authStore = useAuthStore()

  const isLoading = ref(false)
  const loadError = ref('')
  const loaded = ref({ people: false, families: false, events: false })

  const attention = useAttention()
  onMounted(() => attention.refresh())
  const quickActions = computed(() => visibleQuickActions(permission => authStore.hasPermission(permission)).slice(0, 6))
  const greeting = computed(() => {
    const hour = new Date().getHours()
    return hour < 12 ? 'Good morning' : (hour < 18 ? 'Good afternoon' : 'Good evening')
  })

  const firstName = computed(() => authStore.displayName?.split(' ', 1)[0] ?? 'Admin')

  const formattedDate = computed(() => new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }))

  const summaryCards = computed(() => [
    { permission: 'people.read', title: 'People', value: loaded.value.people ? peopleCount.value : '—', icon: 'mdi-account-group-outline', color: 'primary', tint: '#2c4d9414', to: '/people' },
    { permission: 'families.read', title: 'Families', value: loaded.value.families ? familiesStore.families.length : '—', icon: 'mdi-home-heart', color: 'secondary', tint: '#25242610', to: '/families' },
    { permission: 'life_events.read', title: 'Life Events', value: loaded.value.events ? lifeEventsStore.lifeEvents.length : '—', icon: 'mdi-calendar-star', color: 'primary', tint: '#fce9b960', to: '/life-events' },
  ].filter(card => authStore.hasPermission(card.permission)))

  const recentPeople = computed(() => directoryPeople.value.slice(0, 5))
  // The directory returns one page of 25, so larger churches see "25+".
  const peopleCount = computed(() => (directoryPeople.value.length >= 25 ? '25+' : directoryPeople.value.length))
  // eslint-disable-next-line unicorn/no-array-sort
  const recentLifeEvents = computed(() => [...lifeEventsStore.lifeEvents].sort((a, b) => Date.parse(b.createdAt) - Date.parse(a.createdAt)).slice(0, 5))

  async function loadDashboard () {
    if (isLoading.value) return
    isLoading.value = true
    loadError.value = ''
    const results = await Promise.allSettled([
      authStore.hasPermission('people.read')
        ? directoryApi.people().then(result => {
          directoryPeople.value = result
        })
        : Promise.resolve(),
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
      loadError.value = extractErrorMessage(failed.reason, 'Unable to load dashboard data.')
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
.member-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.attention-strip {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 12px;
  margin-bottom: 24px;
}
.attention-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid var(--workspace-border);
  border-left-width: 4px;
  border-radius: 14px;
  background: var(--workspace-paper);
  color: var(--workspace-ink);
  text-decoration: none;
}
.attention-item--error { border-left-color: rgb(var(--v-theme-error)); }
.attention-item--warning { border-left-color: rgb(var(--v-theme-warning)); }
.attention-item--info { border-left-color: rgb(var(--v-theme-info)); }
.attention-item > span { flex: 1; min-width: 0; }
.attention-item strong { display: block; font-size: 14px; }
.attention-item small { display: block; font-size: 12px; color: var(--workspace-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dash-actions {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 12px;
  margin-bottom: 24px;
}
.dash-action {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border: 1px solid var(--workspace-border);
  border-radius: 14px;
  background: var(--workspace-paper);
  color: var(--workspace-ink);
  text-decoration: none;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}
.dash-action:hover {
  border-color: var(--workspace-blue);
  box-shadow: var(--workspace-shadow);
  transform: translateY(-1px);
}
.dash-action:focus-visible {
  outline: 2px solid var(--workspace-blue);
  outline-offset: 2px;
}
.dash-action__icon {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  border-radius: 12px;
  background: var(--workspace-tint);
  color: var(--workspace-blue);
}
.dash-action__text {
  flex: 1;
  min-width: 0;
}
.dash-action__text strong {
  display: block;
  font-size: 14px;
}
.dash-action__text small {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  color: var(--workspace-muted);
}
.dash-action__go {
  color: var(--workspace-muted);
  transition: transform 0.18s ease;
}
.dash-action:hover .dash-action__go {
  transform: translateX(3px);
  color: var(--workspace-blue);
}
</style>
