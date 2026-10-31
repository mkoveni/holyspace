<template>
  <div>
    <PageHeader eyebrow="People & community" subtitle="Manage church members, visitors, and their membership status" title="People">
      <template #actions>
        <v-btn v-if="access.hasPermission('people.manage')" color="primary" prepend-icon="mdi-plus" variant="flat" @click="openCreateDialog">
          Add Person
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="loadError" class="mb-4" type="error" variant="tonal">
      {{ loadError }}
      <v-btn :loading="peopleStore.isLoading" variant="text" @click="loadPeople">Retry</v-btn>
    </v-alert>

    <v-card>
      <v-card-text class="workspace-filter-panel">
        <div class="workspace-section-title">People directory</div>

        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              clearable
              density="comfortable"
              hide-details
              placeholder="Search by name, email, or phone"
              prepend-inner-icon="mdi-magnify"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="statusFilter"
              clearable
              hide-details
              :items="statusOptions"
              label="Filter by status"
            />
          </v-col>
        </v-row>
      </v-card-text>

      <v-data-table
        :headers="headers"
        item-value="id"
        :items="filteredPeople"
        :loading="peopleStore.isLoading"
      >
        <template #item.name="{ item }">
          <router-link class="table-identity" :to="`/people/${item.id}`">
            <span class="table-monogram">{{ item.firstName.charAt(0) }}{{ item.lastName.charAt(0) }}</span>
            <span><strong>{{ item.firstName }} {{ item.lastName }}</strong><small>{{ item.email || 'No email recorded' }}</small></span>
          </router-link>
        </template>

        <template #item.status="{ item }">
          <StatusChip :status="item.status" />
        </template>

        <template #item.dateOfBirth="{ item }">
          {{ item.dateOfBirth ? formatDate(item.dateOfBirth) : '—' }}
        </template>

        <template #item.actions="{ item }">
          <v-btn icon="mdi-eye-outline" size="small" :to="`/people/${item.id}`" variant="text" />
          <v-btn v-if="access.hasPermission('people.manage')" icon="mdi-pencil-outline" size="small" variant="text" @click="openEditDialog(item)" />
        </template>
      </v-data-table>
    </v-card>

    <PersonFormDialog v-model="dialog" :person="editingPerson" @saved="loadPeople" />
  </div>
</template>

<script lang="ts" setup>
import { useAuthStore } from '@/stores/auth'
  import type { MembershipStatus, Person } from '@/types/person'
  import { computed, onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { matchesPerson, personSearchItem } from '@/components/common/peopleSearch'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { usePeopleStore } from '@/stores/people'
  import { membershipStatuses } from '@/types/person'
  import PersonFormDialog from './components/PersonFormDialog.vue'

  const peopleStore = usePeopleStore()

  const loadError = ref('')
  const search = ref('')
  const statusFilter = ref<MembershipStatus | null>(null)
  const dialog = ref(false)
  const editingPerson = ref<Person | null>(null)

  const headers = [
    { title: 'Name', key: 'name', sortable: false },
    { title: 'Email', key: 'email' },
    { title: 'Phone', key: 'phone' },
    { title: 'Date of Birth', key: 'dateOfBirth' },
    { title: 'Status', key: 'status' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ] as const

  const statusOptions = membershipStatuses

  const filteredPeople = computed(() => peopleStore.people.filter(person => {
    const item = personSearchItem(person)
    const matchesSearch = matchesPerson(item.title, search.value ?? '', { raw: item })
    const matchesStatus = !statusFilter.value || person.status === statusFilter.value
    return matchesSearch && matchesStatus
  }))

  function openCreateDialog () {
    editingPerson.value = null
    dialog.value = true
  }

  function openEditDialog (person: Person) {
    editingPerson.value = person
    dialog.value = true
  }

  function formatDate (value: string) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  async function loadPeople () {
    loadError.value = ''
    try {
      await peopleStore.fetchAll()
    } catch (error) {
      loadError.value = error instanceof Error ? error.message : 'Unable to load people.'
    }
  }

  onMounted(loadPeople)

const access = useAuthStore()
</script>
