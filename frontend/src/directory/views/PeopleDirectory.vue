<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="PEOPLE & COMMUNITY"
      subtitle="A thoughtful space for the people who make your church a community."
      title="Every person. A story."
    >
      <v-btn
        prepend-icon="mdi-download-outline"
        variant="tonal"
        @click="exportCsv(false)"
      >
        Export page
      </v-btn>

      <v-btn
        v-if="canManage"
        color="primary"
        prepend-icon="mdi-plus"
        size="large"
        variant="flat"
        @click="editor = true"
      >
        Add person
      </v-btn>
    </DirectoryHeading>

    <div class="directory-banner">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-account-heart-outline" />
        </span>

        <div>
          <strong>Know your people. Nurture your community.</strong>
          <p>Keep the essentials close and the connections meaningful.</p>
        </div>
      </div>

      <span class="directory-banner__caption">PEOPLE FIRST, ALWAYS</span>
    </div>

    <section
      aria-label="People directory"
      class="directory-surface"
    >
      <div class="directory-toolbar">
        <v-text-field
          v-model="search"
          aria-label="Search people"
          class="directory-search"
          clearable
          density="compact"
          hide-details
          placeholder="Find someone by name…"
          prepend-inner-icon="mdi-magnify"
        />

        <v-select
          v-model="membership"
          clearable
          density="compact"
          hide-details
          item-title="label"
          item-value="code"
          :items="metadata?.membershipTypes || []"
          label="Membership"
        />

        <v-select
          v-model="age"
          clearable
          density="compact"
          hide-details
          :items="ageOptions"
          label="Age group"
        />

        <v-btn
          prepend-icon="mdi-tune-variant"
          :variant="filters ? 'tonal' : 'text'"
          @click="filters = !filters"
        >
          Options
        </v-btn>

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

          <v-list min-width="280">
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
              subtitle="Save the current search and filters to reuse them."
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
      </div>

      <v-expand-transition>
        <div
          v-if="selected.length > 0"
          class="directory-bulk-bar"
        >
          <strong>{{ selected.length }} selected</strong>
          <v-spacer />

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

      <div
        v-if="filters"
        class="directory-filter-drawer"
      >
        <v-text-field
          v-model="asOf"
          density="compact"
          hide-details
          label="Age as of"
          type="date"
        />

        <v-checkbox
          v-model="archived"
          color="primary"
          hide-details
          label="Include archived people"
        />

        <v-btn
          variant="text"
          @click="clearFilters"
        >
          Reset filters
        </v-btn>
      </div>

      <div class="directory-results-label">
        <span>
          {{
            loading
              ? 'Finding your people…'
              : people.length > 0
                ? `Showing ${offset + 1}–${offset + people.length}`
                : 'People directory'
          }}
        </span>

        <span>Names, connections, belonging.</span>
      </div>

      <v-alert
        v-if="error"
        class="ma-5"
        role="alert"
        type="error"
        variant="tonal"
      >
        {{ error }}
        <v-btn
          variant="text"
          @click="load"
        >
          Retry
        </v-btn>
      </v-alert>

      <v-skeleton-loader
        v-if="loading"
        type="table-row@5"
      />

      <div
        v-else-if="people.length > 0"
        class="directory-table-scroll"
      >
        <table class="directory-table">
          <thead>
            <tr>
              <th class="directory-check">
                <v-checkbox-btn
                  aria-label="Select everyone on this page"
                  :model-value="allSelected"
                  @update:model-value="toggleAll"
                />
              </th>

              <th>Person</th>
              <th>Membership</th>
              <th>Age group</th>
              <th>Date of birth</th>
              <th><span class="sr-only">Actions</span></th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="person in people"
              :key="person.id"
              class="row-clickable"
              @click="router.push(`/people/${person.id}`)"
            >
              <td
                class="directory-check"
                @click.stop
              >
                <v-checkbox-btn
                  :aria-label="`Select ${fullName(person)}`"
                  :model-value="selected.includes(person.id)"
                  @update:model-value="toggleOne(person.id)"
                />
              </td>

              <td>
                <router-link
                  class="directory-identity"
                  :to="`/people/${person.id}`"
                >
                  <span
                    class="directory-avatar"
                    :class="`directory-avatar--${person.details.firstName.length % 3}`"
                  >
                    <MediaThumb
                      :alt="`Photo of ${fullName(person)}`"
                      :owner-id="person.id"
                      target="person"
                      :variant="64"
                    >{{ initials(person) }}</MediaThumb>
                  </span>

                  <span>
                    <strong>{{ fullName(person) }}</strong>

                    <small>
                      {{ titleLabel(person) }}
                      <template v-if="person.archivedAt">· Archived</template>
                    </small>
                  </span>
                </router-link>
              </td>

              <td>
                <span
                  class="directory-badge"
                  :class="`directory-badge--${person.membershipType.toLowerCase()}`"
                >
                  <i />
                  {{ membershipLabels[person.membershipType] }}
                </span>
              </td>

              <td>
                <span class="directory-age">
                  <v-icon
                    :icon="
                      person.ageCategory === 'CHILD'
                        ? 'mdi-sprout-outline'
                        : 'mdi-account-outline'
                    "
                    size="16"
                  />
                  {{ ageLabels[person.ageCategory] }}
                </span>
              </td>

              <td class="directory-muted">
                {{ dateLabel(person.details.dateOfBirth) }}
              </td>

              <td>
                <v-btn
                  :aria-label="`View ${fullName(person)}`"
                  icon="mdi-arrow-top-right"
                  size="small"
                  :to="`/people/${person.id}`"
                  variant="text"
                  @click.stop
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DirectoryEmpty
        v-else-if="!error"
        :description="
          hasFilters
            ? 'Try a different name or adjust your filters.'
            : 'Add your first person to start building a connected church directory.'
        "
        :title="
          hasFilters
            ? 'No one matches just yet'
            : 'A community starts with one person'
        "
      >
        <v-btn
          v-if="hasFilters"
          variant="outlined"
          @click="clearFilters"
        >
          Clear filters
        </v-btn>

        <v-btn
          v-else-if="canManage"
          color="primary"
          @click="editor = true"
        >
          Add your first person
        </v-btn>
      </DirectoryEmpty>

      <footer class="directory-pagination">
        <span>
          25 people per page
          <span v-if="asOf">· Age on {{ dateLabel(asOf) }}</span>
        </span>

        <div>
          <v-btn
            :disabled="offset === 0 || loading"
            prepend-icon="mdi-chevron-left"
            variant="text"
            @click="offset -= 25"
          >
            Previous
          </v-btn>

          <v-btn
            append-icon="mdi-chevron-right"
            :disabled="people.length < 25 || loading"
            variant="text"
            @click="offset += 25"
          >
            Next
          </v-btn>
        </div>
      </footer>
    </section>

    <PersonEditor
      v-model="editor"
      :person="editing"
      @saved="(id) => router.push(`/people/${id}`)"
    />
  </div>
</template>
<script setup lang="ts">
  import type { AgeCategory, MembershipType, PersonSummary } from '../types'
  import axios from 'axios'
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import { extractErrorMessage } from '@/composables/useApi'
  import { useSavedFilters } from '@/composables/useSavedFilters'
  import { useCreateAction } from '@/navigation/useCreateAction'
  import { useAuthStore } from '@/stores/auth'
  import { useUiStore } from '@/stores/ui'
  import { type CsvColumn, downloadCsv, toCsv } from '@/utils/csv'
  import { directoryApi } from '../api'
  import DirectoryEmpty from '../components/DirectoryEmpty.vue'
  import DirectoryHeading from '../components/DirectoryHeading.vue'
  import MediaThumb from '../components/MediaThumb.vue'
  import PersonEditor from '../components/PersonEditor.vue'
  import {
    ageLabels,
    dateLabel,
    fullName,
    initials,
    membershipLabels,
  } from '../presentation'
  import { useDirectoryStore } from '../store'
  const router = useRouter()
  const auth = useAuthStore()
  const catalogue = useDirectoryStore()
  const metadata = computed(() => catalogue.metadata)
  const canManage = computed(() => auth.hasPermission('people.manage'))
  const people = ref<PersonSummary[]>([])
  const loading = ref(true)
  const error = ref('')
  const ui = useUiStore()
  interface DirectoryFilters {
    search: string
    membership: MembershipType | null
    age: AgeCategory | null
    archived: boolean
    asOf: string
  }
  const emptyFilters: DirectoryFilters = { search: '', membership: null, age: null, archived: false, asOf: '' }
  // One object so saved views can capture and restore every filter together.
  const state = ref<DirectoryFilters>({ ...emptyFilters })
  const savedFilters = useSavedFilters<DirectoryFilters>('people-directory', state, emptyFilters)
  const search = computed({ get: () => state.value.search, set: value => {
    state.value.search = value ?? ''
  } })
  const membership = computed({ get: () => state.value.membership, set: value => {
    state.value.membership = value ?? null
  } })
  const age = computed({ get: () => state.value.age, set: value => {
    state.value.age = value ?? null
  } })
  const archived = computed({ get: () => state.value.archived, set: value => {
    state.value.archived = Boolean(value)
  } })
  const asOf = computed({ get: () => state.value.asOf, set: value => {
    state.value.asOf = value ?? ''
  } })
  const newViewName = ref('')
  const selected = ref<string[]>([])
  const offset = ref(0)
  const filters = ref(false)
  const editor = ref(false)
  const editing = ref<PersonSummary | null>(null)
  useCreateAction(
    () => canManage.value,
    () => {
      editing.value = null
      editor.value = true
    },
  )
  const hasFilters = computed(() => [search.value, membership.value, age.value, archived.value, asOf.value].some(Boolean))
  const ageOptions = [
    { title: 'Children', value: 'CHILD' },
    { title: 'Adults', value: 'ADULT' },
    { title: 'Age not recorded', value: 'UNKNOWN' },
  ]
  function titleLabel (person: PersonSummary) {
    return (
      catalogue.metadata?.titles.find(
        title => title.code === person.details.titleCode,
      )?.label || 'Community profile'
    )
  }
  let controller: AbortController | null = null
  let timer: ReturnType<typeof setTimeout> | undefined
  async function load () {
    controller?.abort()
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    error.value = ''
    try {
      if (!catalogue.metadata) await catalogue.loadMetadata()
      const result = await directoryApi.people(
        {
          search: search.value || '',
          membershipType: membership.value,
          ageCategory: age.value,
          includeArchived: archived.value,
          asOf: asOf.value || undefined,
          offset: offset.value,
        },
        signal,
      )
      if (!signal.aborted) people.value = result
    } catch (error_) {
      if (!signal.aborted && !axios.isCancel(error_)) {
        people.value = []
        error.value = extractErrorMessage(error_, 'Unable to load people.')
      }
    } finally {
      if (!signal.aborted) loading.value = false
    }
  }
  function clearFilters () {
    savedFilters.reset()
    offset.value = 0
  }
  const allSelected = computed(() => people.value.length > 0 && people.value.every(person => selected.value.includes(person.id)))
  function toggleAll (on: boolean | null) {
    selected.value = on ? people.value.map(person => person.id) : []
  }
  function toggleOne (id: string) {
    selected.value = selected.value.includes(id) ? selected.value.filter(item => item !== id) : [...selected.value, id]
  }
  function saveView () {
    savedFilters.save(newViewName.value)
    ui.notifySuccess(`Saved view “${newViewName.value.trim()}”.`)
    newViewName.value = ''
  }
  const csvColumns: CsvColumn<PersonSummary>[] = [
    { header: 'First name', value: person => person.details.firstName },
    { header: 'Last name', value: person => person.details.lastName },
    { header: 'Membership', value: person => membershipLabels[person.membershipType] },
    { header: 'Age group', value: person => ageLabels[person.ageCategory] },
    { header: 'Date of birth', value: person => person.details.dateOfBirth },
  ]
  function exportCsv (onlySelected: boolean) {
    const rows = onlySelected ? people.value.filter(person => selected.value.includes(person.id)) : people.value
    downloadCsv('people.csv', toCsv(rows, csvColumns))
    ui.notifySuccess(`Exported ${rows.length} ${rows.length === 1 ? 'person' : 'people'} from this page.`)
  }
  watch(people, () => {
    selected.value = selected.value.filter(id => people.value.some(person => person.id === id))
  })
  watch(state, () => {
    controller?.abort()
    clearTimeout(timer)
    if (offset.value !== 0) offset.value = 0
    timer = setTimeout(load, 250)
  }, { deep: true })
  watch(offset, () => {
    clearTimeout(timer)
    void load()
  })
  onMounted(load)
  onBeforeUnmount(() => {
    clearTimeout(timer)
    controller?.abort()
  })
</script>
