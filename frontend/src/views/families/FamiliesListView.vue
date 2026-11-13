<template>
  <div>
    <PageHeader eyebrow="People & community" subtitle="A clear view of your households, contacts, and shared milestones." title="Family directory">
      <template #actions>
        <v-btn
          v-if="access.hasPermission('families.manage')"
          color="primary"
          prepend-icon="mdi-plus"
          variant="flat"
          @click="createDialog = true"
        >Add family</v-btn>
      </template>
    </PageHeader>

    <StatStrip :items="stats" />

    <v-alert v-if="loadError" class="mb-5" type="error" variant="tonal">
      {{ loadError }}
      <v-btn :loading="familiesStore.isLoadingOverviews" variant="text" @click="load">Retry</v-btn>
    </v-alert>

    <v-card>
      <div class="workspace-toolbar">
        <div><h2>Households</h2><p>{{ filteredFamilies.length }} {{ filteredFamilies.length === 1 ? 'family' : 'families' }}{{ search ? ' matching your search' : ' in the directory' }}</p></div>

        <v-text-field
          v-model="search"
          aria-label="Search families, members, or addresses"
          class="workspace-search"
          clearable
          hide-details
          placeholder="Search families, people, or places"
          prepend-inner-icon="mdi-magnify"
        />
      </div>

      <FamilyDirectoryTable :families="filteredFamilies" :loading="familiesStore.isLoadingOverviews" />
    </v-card>

    <FamilyFormDialog v-model="createDialog" @saved="load" />
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import StatStrip from '@/components/common/StatStrip.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useAuthStore } from '@/stores/auth'
  import { useFamiliesStore } from '@/stores/families'
  import FamilyDirectoryTable from './components/FamilyDirectoryTable.vue'
  import FamilyFormDialog from './components/FamilyFormDialog.vue'

  const familiesStore = useFamiliesStore()
  const search = ref('')
  const createDialog = ref(false)
  const loadError = ref('')
  const loaded = ref(false)
  const stats = computed(() => [
    { label: 'Households', value: loaded.value ? familiesStore.overviews.length : '—' },
    { label: 'Memberships', value: loaded.value ? familiesStore.overviews.reduce((total, family) => total + family.members.length, 0) : '—' },
    { label: 'Upcoming events', value: loaded.value ? new Set(familiesStore.overviews.flatMap(family => family.upcomingEvents.map(event => event.id))).size : '—' },
  ])
  const filteredFamilies = computed(() => {
    const term = search.value?.trim().toLowerCase() ?? ''
    return familiesStore.overviews.filter(family => !term || [
      family.name,
      ...family.members.map(member => `${member.firstName} ${member.lastName}`),
      ...family.addresses.map(address => [address.line1, address.line2, address.city, address.province, address.postalCode, address.country].filter(Boolean).join(' ')),
    ].some(value => value.toLowerCase().includes(term)))
  })

  async function load () {
    loadError.value = ''
    try {
      await familiesStore.fetchOverviews()
      loaded.value = true
    } catch (error) {
      loadError.value = extractErrorMessage(error, 'Unable to load families.')
    }
  }
  onMounted(load)

  const access = useAuthStore()
</script>
