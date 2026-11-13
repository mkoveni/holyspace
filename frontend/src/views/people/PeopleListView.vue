<template>
  <div>
    <PageHeader
      eyebrow="People & community"
      subtitle="Manage church members, visitors, and their membership status"
      title="People"
    >
      <template #actions>
        <v-btn
          prepend-icon="mdi-download-outline"
          variant="tonal"
          @click="exportCsv(false)"
        >
          Export
        </v-btn>

        <v-btn
          v-if="access.hasPermission('people.manage')"
          color="primary"
          prepend-icon="mdi-plus"
          variant="flat"
          @click="openCreateDialog"
        >
          Add Person
        </v-btn>
      </template>
    </PageHeader>

    <v-alert
      v-if="loadError"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ loadError }}
      <v-btn
        :loading="peopleStore.isLoading"
        variant="text"
        @click="loadPeople"
      >
        Retry
      </v-btn>
    </v-alert>

    <v-card>
      <v-card-text class="workspace-filter-panel">
        <v-row>
          <v-col
            cols="12"
            md="4"
          >
            <v-text-field
              v-model="filters.search"
              clearable
              density="comfortable"
              hide-details
              placeholder="Search by name, email, or phone"
              prepend-inner-icon="mdi-magnify"
            />
          </v-col>

          <v-col
            cols="12"
            md="3"
          >
            <v-select
              v-model="filters.status"
              clearable
              hide-details
              :items="statusOptions"
              label="Filter by status"
            />
          </v-col>

          <v-col
            class="d-flex align-center ga-2"
            cols="12"
            md="5"
          >
            <v-menu :close-on-content-click="false">
              <template #activator="{ props: menuProps }">
                <v-btn
                  v-bind="menuProps"
                  append-icon="mdi-chevron-down"
                  prepend-icon="mdi-bookmark-outline"
                  variant="text"
                >
                  Saved views
                </v-btn>
              </template>

              <v-list min-width="260">
                <v-list-item
                  v-for="view in savedFilters.views.value"
                  :key="view.name"
                  :title="view.name"
                  @click="savedFilters.apply(view)"
                >
                  <template #append>
                    <v-btn
                      :aria-label="`Delete view ${view.name}`"
                      icon="mdi-close"
                      size="x-small"
                      variant="text"
                      @click.stop="savedFilters.remove(view.name)"
                    />
                  </template>
                </v-list-item>

                <v-list-item
                  v-if="savedFilters.views.value.length === 0"
                  subtitle="Save the current search and status to reuse it later."
                  title="No saved views yet"
                />

                <v-divider class="my-2" />

                <div class="d-flex ga-2 pa-2">
                  <v-text-field
                    v-model="newViewName"
                    density="compact"
                    hide-details
                    label="Name this view"
                    @keyup.enter="saveView"
                  />

                  <v-btn
                    :disabled="!newViewName.trim()"
                    variant="tonal"
                    @click="saveView"
                  >
                    Save
                  </v-btn>
                </div>
              </v-list>
            </v-menu>

            <v-btn
              v-if="hasFilters"
              variant="text"
              @click="savedFilters.reset()"
            >
              Clear filters
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>

      <v-expand-transition>
        <div
          v-if="selected.length > 0"
          class="bulk-bar"
        >
          <strong>{{ selected.length }} selected</strong>

          <v-spacer />

          <v-btn
            prepend-icon="mdi-content-copy"
            size="small"
            variant="text"
            @click="copyEmails"
          >
            Copy emails
          </v-btn>

          <v-btn
            prepend-icon="mdi-download-outline"
            size="small"
            variant="text"
            @click="exportCsv(true)"
          >
            Export selected
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            @click="selected = []"
          >
            Clear
          </v-btn>
        </div>
      </v-expand-transition>

      <AppDataTable
        v-model="selected"
        clickable
        :headers="headers"
        item-value="id"
        :items="filteredPeople"
        :loading="peopleStore.isLoading"
        return-object
        show-select
        @row-click="(item) => router.push(`/people/${item.id}`)"
      >
        <template #item.name="{ item }">
          <router-link
            class="table-identity"
            :to="`/people/${item.id}`"
          >
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
          <v-btn
            v-if="access.hasPermission('people.manage')"
            aria-label="Edit person"
            icon="mdi-pencil-outline"
            size="small"
            variant="text"
            @click.stop="openEditDialog(item)"
          />
        </template>

        <template #no-data>
          <EmptyState
            icon="mdi-account-search-outline"
            :text="hasFilters ? 'Try a different name or clear the filters.' : 'Add your first person to start your directory.'"
            :title="hasFilters ? 'No one matches those filters' : 'No people yet'"
          >
            <v-btn
              v-if="hasFilters"
              variant="tonal"
              @click="savedFilters.reset()"
            >
              Clear filters
            </v-btn>

            <v-btn
              v-else-if="access.hasPermission('people.manage')"
              color="primary"
              variant="flat"
              @click="openCreateDialog"
            >
              Add person
            </v-btn>
          </EmptyState>
        </template>
      </AppDataTable>
    </v-card>

    <PersonFormDialog
      v-model="dialog"
      :person="editingPerson"
      @saved="loadPeople"
    />
  </div>
</template>

<script lang="ts" setup>
  import type { MembershipStatus, Person } from '@/types/person'
  import { computed, onMounted, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import AppDataTable from '@/components/common/AppDataTable.vue'
  import EmptyState from '@/components/common/EmptyState.vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { matchesPerson, personSearchItem } from '@/components/common/peopleSearch'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useSavedFilters } from '@/composables/useSavedFilters'
  import { useAuthStore } from '@/stores/auth'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import { membershipStatuses } from '@/types/person'
  import { type CsvColumn, downloadCsv, toCsv } from '@/utils/csv'
  import { formatDate } from '@/utils/format'
  import PersonFormDialog from './components/PersonFormDialog.vue'

  const peopleStore = usePeopleStore()
  const router = useRouter()
  const ui = useUiStore()
  const access = useAuthStore()

  interface PeopleFilters {
    search: string
    status: MembershipStatus | null
  }

  const loadError = ref('')
  const filters = ref<PeopleFilters>({ search: '', status: null })
  const savedFilters = useSavedFilters<PeopleFilters>('people', filters, { search: '', status: null })
  const newViewName = ref('')
  const selected = ref<Person[]>([])
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

  const hasFilters = computed(() => Boolean(filters.value.search || filters.value.status))
  const filteredPeople = computed(() => peopleStore.people.filter(person => {
    const item = personSearchItem(person)
    const matchesSearch = matchesPerson(item.title, filters.value.search ?? '', { raw: item })
    const matchesStatus = !filters.value.status || person.status === filters.value.status
    return matchesSearch && matchesStatus
  }))

  const csvColumns: CsvColumn<Person>[] = [
    { header: 'First name', value: person => person.firstName },
    { header: 'Last name', value: person => person.lastName },
    { header: 'Email', value: person => person.email },
    { header: 'Phone', value: person => person.phone },
    { header: 'Date of birth', value: person => person.dateOfBirth },
    { header: 'Status', value: person => person.status },
  ]

  function saveView () {
    savedFilters.save(newViewName.value)
    ui.notifySuccess(`Saved view “${newViewName.value.trim()}”.`)
    newViewName.value = ''
  }

  function exportCsv (onlySelected: boolean) {
    const rows = onlySelected ? selected.value : filteredPeople.value
    downloadCsv('people.csv', toCsv(rows, csvColumns))
    ui.notifySuccess(`Exported ${rows.length} ${rows.length === 1 ? 'person' : 'people'}.`)
  }

  async function copyEmails () {
    const emails = selected.value.map(person => person.email).filter(Boolean)
    if (emails.length === 0) {
      ui.notifyError('None of the selected people have an email address.')
      return
    }
    try {
      await navigator.clipboard.writeText(emails.join(', '))
      ui.notifySuccess(`Copied ${emails.length} email ${emails.length === 1 ? 'address' : 'addresses'}.`)
    } catch {
      ui.notifyError('Your browser did not allow copying. Use Export selected instead.')
    }
  }

  function openCreateDialog () {
    editingPerson.value = null
    dialog.value = true
  }

  function openEditDialog (person: Person) {
    editingPerson.value = person
    dialog.value = true
  }

  async function loadPeople () {
    loadError.value = ''
    try {
      await peopleStore.fetchAll()
    } catch (error) {
      loadError.value = extractErrorMessage(error, 'Unable to load people.')
    }
  }

  onMounted(loadPeople)
</script>

<style scoped>
  .bulk-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    border-top: 1px solid var(--workspace-border);
    background: var(--workspace-tint);
    color: var(--workspace-blue);
  }
</style>
