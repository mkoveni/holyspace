<template>
  <div>
    <PageHeader subtitle="Manage church members, visitors, and their membership status" title="People">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" variant="flat" @click="openCreateDialog">
          Add Person
        </v-btn>
      </template>
    </PageHeader>

    <v-card elevation="2">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              clearable
              density="comfortable"
              hide-details
              label="Search by name or email"
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
          <router-link class="text-decoration-none text-primary-darken font-weight-medium" :to="`/people/${item.id}`">
            {{ item.firstName }} {{ item.lastName }}
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
          <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="openEditDialog(item)" />
        </template>
      </v-data-table>
    </v-card>

    <PersonFormDialog v-model="dialog" :person="editingPerson" @saved="peopleStore.fetchAll()" />
  </div>
</template>

<script lang="ts" setup>
  import type { Person } from '@/types/person'
  import { computed, onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import PersonFormDialog from './components/PersonFormDialog.vue'

  const peopleStore = usePeopleStore()
  const ui = useUiStore()

  const search = ref('')
  const statusFilter = ref<string | null>(null)
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

  const statusOptions = ['active', 'inactive', 'visitor', 'pending']

  const filteredPeople = computed(() => peopleStore.people.filter(person => {
    const matchesSearch = !search.value
      || `${person.firstName} ${person.lastName}`.toLowerCase().includes(search.value.toLowerCase())
      || person.email?.toLowerCase().includes(search.value.toLowerCase())
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

  onMounted(async () => {
    try {
      await peopleStore.fetchAll()
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to load people.')
    }
  })
</script>
