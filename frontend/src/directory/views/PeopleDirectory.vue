<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="PEOPLE & COMMUNITY"
      subtitle="A thoughtful space for the people who make your church a community."
      title="Every person. A story."
    >
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
      </div>

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
            >
              <td>
                <router-link
                  class="directory-identity"
                  :to="`/people/${person.id}`"
                >
                  <span
                    class="directory-avatar"
                    :class="`directory-avatar--${person.details.firstName.length % 3}`"
                  >
                    {{ initials(person) }}
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
  import { useCreateAction } from '@/navigation/useCreateAction'
  import { useAuthStore } from '@/stores/auth'
  import { directoryApi } from '../api'
  import DirectoryEmpty from '../components/DirectoryEmpty.vue'
  import DirectoryHeading from '../components/DirectoryHeading.vue'
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
  const search = ref('')
  const membership = ref<MembershipType | null>(null)
  const age = ref<AgeCategory | null>(null)
  const archived = ref(false)
  const asOf = ref('')
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
    search.value = ''
    membership.value = null
    age.value = null
    archived.value = false
    asOf.value = ''
    offset.value = 0
  }
  watch([search, membership, age, archived, asOf], () => {
    controller?.abort()
    clearTimeout(timer)
    if (offset.value !== 0) offset.value = 0
    timer = setTimeout(load, 250)
  })
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
