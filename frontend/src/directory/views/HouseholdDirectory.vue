<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="FAMILIES & HOUSEHOLDS"
      subtitle="A place for shared homes, family connections, and the people in between."
      title="Together feels like home."
    >
      <v-btn
        v-if="canManage"
        color="primary"
        prepend-icon="mdi-plus"
        size="large"
        variant="flat"
        @click="editor = true"
      >
        Add household
      </v-btn>
    </DirectoryHeading>

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-home-heart" />
        </span>

        <div>
          <strong>Every household has its own shape.</strong>

          <p>
            People can belong to more than one home. There’s room for every
            family.
          </p>
        </div>
      </div>

      <span class="directory-banner__caption">A PLACE TO BELONG</span>
    </div>

    <section class="directory-surface">
      <div class="directory-toolbar">
        <v-text-field
          v-model="search"
          aria-label="Search households"
          class="directory-search"
          clearable
          density="compact"
          hide-details
          placeholder="Find a household…"
          prepend-inner-icon="mdi-magnify"
        />

        <span class="directory-muted">
          {{
            loading
              ? 'Loading households…'
              : `${households.length} on this page`
          }}
        </span>
      </div>

      <v-alert
        v-if="error"
        class="ma-5"
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
        type="list-item-two-line@4"
      />

      <div
        v-else-if="households.length > 0"
        class="directory-household-grid"
      >
        <router-link
          v-for="household in households"
          :key="household.id"
          class="directory-household-card"
          :to="`/families/${household.id}`"
        >
          <div>
            <span class="directory-home-mark">
              <v-icon
                icon="mdi-home-outline"
                size="28"
              />
            </span>

            <v-icon
              icon="mdi-arrow-top-right"
              size="20"
            />
          </div>

          <h2>{{ household.name }}</h2>
          <p>Part of your church community</p>

          <footer>
            <span>Since {{ dateLabel(household.createdAt) }}</span>

            <span>
              View household
              <v-icon
                icon="mdi-arrow-right"
                size="16"
              />
            </span>
          </footer>
        </router-link>
      </div>

      <DirectoryEmpty
        v-else-if="!error"
        :description="
          search
            ? 'Try another name to find the household you’re looking for.'
            : 'Create a household, then bring its people together.'
        "
        icon="mdi-home-heart"
        :title="search ? 'No households found' : 'A place for every family'"
      >
        <v-btn
          v-if="search"
          variant="outlined"
          @click="search = ''"
        >
          Clear search
        </v-btn>

        <v-btn
          v-else-if="canManage"
          color="primary"
          @click="editor = true"
        >
          Create a household
        </v-btn>
      </DirectoryEmpty>

      <footer class="directory-pagination">
        <span>25 households per page</span>

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
            :disabled="households.length < 25 || loading"
            variant="text"
            @click="offset += 25"
          >
            Next
          </v-btn>
        </div>
      </footer>
    </section>

    <HouseholdEditor
      v-model="editor"
      @saved="(id) => router.push(`/families/${id}`)"
    />
  </div>
</template>
<script setup lang="ts">
  import type { Household } from '../types'
  import axios from 'axios'
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import { extractErrorMessage } from '@/composables/useApi'
  import { useCreateAction } from '@/navigation/useCreateAction'
  import { useAuthStore } from '@/stores/auth'
  import { directoryApi } from '../api'
  import DirectoryEmpty from '../components/DirectoryEmpty.vue'
  import DirectoryHeading from '../components/DirectoryHeading.vue'
  import HouseholdEditor from '../components/HouseholdEditor.vue'
  import { dateLabel } from '../presentation'
  const auth = useAuthStore()
  const router = useRouter()
  const canManage = computed(() => auth.hasPermission('people.manage'))
  const households = ref<Household[]>([])
  const search = ref('')
  const offset = ref(0)
  const loading = ref(true)
  const error = ref('')
  const editor = ref(false)
  useCreateAction(
    () => canManage.value,
    () => {
      editor.value = true
    },
  )
  let controller: AbortController | null = null
  let timer: ReturnType<typeof setTimeout> | undefined
  async function load () {
    controller?.abort()
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    error.value = ''
    try {
      const result = await directoryApi.households(
        search.value || '',
        offset.value,
        signal,
      )
      if (!signal.aborted) households.value = result
    } catch (error_) {
      if (!signal.aborted && !axios.isCancel(error_)) {
        households.value = []
        error.value = extractErrorMessage(error_, 'Unable to load households.')
      }
    } finally {
      if (!signal.aborted) loading.value = false
    }
  }
  watch(search, () => {
    controller?.abort()
    clearTimeout(timer)
    if (offset.value) offset.value = 0
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
