<template>
  <section class="directory-surface children-section programme-roster">
    <div class="children-section__heading">
      <div>
        <span class="directory-eyebrow">BELONGING TOGETHER</span>
        <h2 class="mt-3">Enrolled children</h2>
      </div>

      <v-chip
        color="primary"
        size="small"
      >
        {{ roster.data.value.total }}
        {{ roster.data.value.total === 1 ? 'enrolment' : 'enrolments' }}
      </v-chip>
    </div>

    <p>
      Find every child’s enrolment and current class placement in this
      programme. Readiness is checked for today; session check-in still verifies
      permissions and available places.
    </p>

    <div class="programme-roster__filters">
      <v-text-field
        v-model="search"
        clearable
        hide-details
        label="Search enrolled children"
        placeholder="Search by name"
        prepend-inner-icon="mdi-magnify"
      />

      <v-select
        v-model="status"
        hide-details
        :items="statuses"
        label="Enrolment status"
      />
    </div>

    <v-alert
      v-if="roster.error.value"
      class="mt-5"
      type="error"
      variant="tonal"
    >
      {{ roster.error.value }}
      <v-btn
        type="button"
        variant="text"
        @click="roster.load"
      >
        Retry
      </v-btn>
    </v-alert>

    <v-skeleton-loader
      v-else-if="roster.loading.value"
      class="mt-5"
      type="list-item-avatar-two-line@3"
    />

    <template v-else>
      <div
        v-for="child in roster.data.value.children"
        :key="child.enrolmentId"
        class="programme-roster__row"
      >
        <div class="programme-roster__identity">
          <span class="directory-avatar directory-avatar--1">
            <v-icon icon="mdi-account-heart-outline" />
          </span>

          <div>
            <router-link
              class="programme-roster__name"
              :to="profile(child.childId)"
            >
              {{ child.name }}
            </router-link>

            <small>
              {{ dateLabel(child.validFrom) }} –
              {{ dateLabel(lastDay(child.validUntil)) }}
            </small>
          </div>
        </div>

        <div class="programme-roster__placement">
          <span class="directory-muted">Current class</span>

          <strong>
            {{
              child.classes.map((group) => group.name).join(', ') ||
                'No current class'
            }}
          </strong>
        </div>

        <v-chip
          :color="
            child.status === 'ACTIVE'
              ? 'success'
              : child.status === 'UPCOMING'
                ? 'primary'
                : undefined
          "
          size="small"
        >
          {{ statusName(child.status) }}
        </v-chip>

        <v-btn
          v-if="child.readiness"
          :aria-label="`${child.readiness.ready ? 'Ready to attend' : 'Needs attention'}: ${child.name}`"
          :color="child.readiness.ready ? 'success' : 'warning'"
          size="small"
          :to="profile(child.childId)"
          variant="tonal"
        >
          {{ child.readiness.ready ? 'Ready to attend' : 'Needs attention' }}
          · {{ child.readiness.completed }}/{{ child.readiness.total }}
        </v-btn>

        <v-btn
          :aria-label="`Open ministry profile for ${child.name}`"
          icon="mdi-arrow-top-right"
          :to="profile(child.childId)"
          variant="text"
        />
      </div>

      <DirectoryEmpty
        v-if="roster.data.value.children.length === 0"
        :description="
          filtered
            ? 'Try another name or enrolment status.'
            : 'No enrolments have been recorded for this programme yet.'
        "
        :title="
          filtered ? 'No matching enrolments' : 'Their journey starts here'
        "
      >
        <v-btn
          v-if="filtered"
          class="mt-4"
          variant="text"
          @click="reset"
        >
          Clear filters
        </v-btn>
      </DirectoryEmpty>

      <div
        v-if="roster.data.value.total"
        class="programme-roster__pagination"
      >
        <span class="directory-muted">
          {{ offset + 1 }}–{{
            Math.min(offset + 25, roster.data.value.total)
          }}
          of {{ roster.data.value.total }}
        </span>

        <div>
          <v-btn
            :disabled="!offset"
            variant="text"
            @click="offset -= 25"
          >
            Previous children
          </v-btn>

          <v-btn
            :disabled="offset + 25 >= roster.data.value.total"
            variant="text"
            @click="offset += 25"
          >
            Next children
          </v-btn>
        </div>
      </div>
    </template>
  </section>
</template>
<script setup lang="ts">
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import { childrenApi } from '../api'
  import { dateLabel, lastDay } from '../presentation'
  import { useLoad } from '../useLoad'
  const props = defineProps<{ programmeId: string, revision: number }>()
  const search = ref<string | null>('')
  const appliedSearch = ref('')
  const status = ref('ALL')
  const offset = ref(0)
  const statuses = [
    { title: 'All enrolments', value: 'ALL' },
    { title: 'Active', value: 'ACTIVE' },
    { title: 'Upcoming', value: 'UPCOMING' },
    { title: 'Expired', value: 'EXPIRED' },
    { title: 'Ended', value: 'ENDED' },
  ]
  const filtered = computed(
    () => !!appliedSearch.value || status.value !== 'ALL',
  )
  const roster = useLoad(
    () =>
      childrenApi.programmeChildren(
        props.programmeId,
        appliedSearch.value,
        status.value,
        offset.value,
      ),
    { children: [], total: 0 },
  )
  let timer: ReturnType<typeof setTimeout> | undefined
  function profile (childId: string) {
    return {
      path: `/children/participants/${childId}`,
      query: { programme: props.programmeId },
    }
  }
  function statusName (value: string) {
    return statuses.find(item => item.value === value)?.title ?? value
  }
  function reset () {
    search.value = ''
    appliedSearch.value = ''
    status.value = 'ALL'
  }
  watch(search, value => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      appliedSearch.value = value?.trim() || ''
    }, 300)
  })
  watch([appliedSearch, status, () => props.revision], () => {
    if (offset.value) offset.value = 0
    else void roster.load()
  })
  watch(offset, roster.load)
  watch(
    () => props.programmeId,
    () => {
      clearTimeout(timer)
      reset()
      offset.value = 0
      void roster.load()
    },
    { immediate: true },
  )
  onBeforeUnmount(() => clearTimeout(timer))
</script>
<style scoped>
  .programme-roster__filters {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
    gap: 12px;
    margin: 24px 0;
  }
  .programme-roster__row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding: 20px 0;
    border-top: 1px solid #e5e9ef;
  }
  .programme-roster__identity {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1 1 220px;
    min-width: 0;
  }
  .programme-roster__identity > div {
    min-width: 0;
  }
  .programme-roster__identity small {
    display: block;
    color: #718087;
    margin-top: 6px;
  }
  .programme-roster__name {
    color: #26364c;
    font-weight: 600;
    text-decoration: none;
    overflow-wrap: anywhere;
  }
  .programme-roster__name:hover {
    color: #2c4d94;
    text-decoration: underline;
  }
  .programme-roster__placement {
    flex: 1 1 150px;
    min-width: 0;
  }
  .programme-roster__placement span,
  .programme-roster__placement strong {
    display: block;
    font-size: 0.8rem;
    overflow-wrap: anywhere;
  }
  .programme-roster__placement strong {
    margin-top: 4px;
    font-weight: 500;
  }
  .programme-roster__pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
  }
  @media (max-width: 600px) {
    .programme-roster__filters {
      grid-template-columns: minmax(0, 1fr);
    }
  }
</style>
