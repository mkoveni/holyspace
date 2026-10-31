<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="A PLACE TO BELONG"
      subtitle="Discover the people, leadership and gatherings that keep your church connected."
      :title="
        kind === 'GROUP'
          ? 'Life happens in community.'
          : 'Serve a shared purpose.'
      "
    >
      <v-btn
        v-if="manage"
        color="primary"
        prepend-icon="mdi-plus"
        @click="startCreate"
      >
        {{ kind === 'GROUP' ? 'Create group' : 'Create ministry' }}
      </v-btn>
    </DirectoryHeading>

    <div class="community-navigation">
      <v-btn
        to="/groups"
        :variant="kind === 'GROUP' ? 'tonal' : 'text'"
      >
        Groups
      </v-btn>

      <v-btn
        to="/ministries"
        :variant="kind === 'MINISTRY' ? 'tonal' : 'text'"
      >
        Ministries
      </v-btn>
    </div>

    <section class="directory-surface community-section">
      <div class="community-fields">
        <v-text-field
          v-model="search"
          clearable
          label="Search by name"
          prepend-inner-icon="mdi-magnify"
        />

        <v-switch
          v-model="archived"
          color="primary"
          label="Include archived"
        />
      </div>

      <v-alert
        v-if="error"
        type="error"
        variant="tonal"
      >
        {{ error }}
      </v-alert>

      <v-progress-linear
        v-if="loading"
        indeterminate
      />

      <div class="community-grid">
        <article
          v-for="item in rows"
          :key="item.id"
          class="directory-surface community-card"
        >
          <div class="d-flex ga-2">
            <v-chip
              size="small"
              variant="tonal"
            >
              {{ item.kind === 'GROUP' ? 'Group' : 'Ministry' }}
            </v-chip>

            <v-chip
              v-if="!item.active"
              size="small"
            >
              Archived
            </v-chip>
          </div>

          <h2>{{ item.name }}</h2>

          <p class="community-card__description directory-muted">
            {{
              item.description ||
                'Build meaningful connections and make space for every person.'
            }}
          </p>

          <p
            v-if="item.campus"
            class="directory-muted"
          >
            <v-icon
              icon="mdi-map-marker-outline"
              size="small"
            />
            {{ item.campus }}
          </p>

          <v-btn
            append-icon="mdi-arrow-right"
            :to="'/communities/' + item.id"
            variant="tonal"
          >
            Open workspace
          </v-btn>
        </article>
      </div>

      <p
        v-if="!loading && rows.length === 0"
        class="directory-muted py-8"
      >
        No communities in this view. Leaders see communities where they have a
        current, linked appointment.
      </p>

      <div class="community-footer">
        <span>{{ total }} communities</span>

        <v-btn
          :disabled="offset === 0 || loading"
          variant="text"
          @click="page(-25)"
        >
          Previous
        </v-btn>

        <v-btn
          :disabled="offset + 25 >= total || loading"
          variant="text"
          @click="page(25)"
        >
          Next
        </v-btn>
      </div>
    </section>

    <CommunityDialog
      v-model="createOpen"
      :submit="save"
      subtitle="Membership and leadership are managed separately. Creating a community grants no volunteer or safeguarding access."
      :title="kind === 'GROUP' ? 'Create a group' : 'Create a ministry'"
      @reload="reload"
      @saved="load"
    >
      <v-text-field
        v-model="draft.name"
        label="Name"
        maxlength="150"
        :rules="[required]"
      />

      <v-textarea
        v-model="draft.description"
        label="Purpose and description"
        maxlength="5000"
      />

      <v-autocomplete
        v-if="kind === 'GROUP'"
        v-model="draft.parentId"
        clearable
        item-title="name"
        item-value="id"
        :items="ministries"
        label="Parent ministry (optional)"
      />

      <v-text-field
        v-model="draft.campus"
        hint="An organisational label; it does not grant or restrict access."
        label="Campus or area (optional)"
        maxlength="150"
        persistent-hint
      />
    </CommunityDialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { type Community, communityApi, errorMessage, required } from '../api'
  import CommunityDialog from '../components/CommunityDialog.vue'
  import '@/directory/directory.scss'
  import '../communities.css'
  const route = useRoute(),
        auth = useAuthStore(),
        kind = computed(() => (route.path === '/groups' ? 'GROUP' : 'MINISTRY')),
        manage = computed(() => auth.hasPermission('ministries.manage'))
  const rows = ref<Community[]>([]),
        total = ref(0),
        search = ref(''),
        archived = ref(false),
        offset = ref(0),
        loading = ref(false),
        error = ref(''),
        ministries = ref<Community[]>([])
  let generation = 0,
      timer: ReturnType<typeof setTimeout> | undefined
  async function load () {
    const current = ++generation
    loading.value = true
    try {
      const result = await communityApi.directory({
        kind: kind.value,
        search: search.value || '',
        archived: archived.value,
        offset: offset.value,
      })
      if (current === generation) {
        rows.value = result.communities
        total.value = result.total
        error.value = ''
      }
    } catch (error_) {
      if (current === generation) error.value = errorMessage(error_)
    } finally {
      if (current === generation) loading.value = false
    }
  }
  function page (delta: number) {
    offset.value += delta
    load()
  }
  watch([kind, search, archived], () => {
    offset.value = 0
    clearTimeout(timer)
    timer = setTimeout(load, 250)
  })
  const createOpen = ref(false),
        draft = ref({
          name: '',
          description: '',
          parentId: null as string | null,
          campus: '',
        })
  let request = crypto.randomUUID()
  async function startCreate () {
    draft.value = { name: '', description: '', parentId: null, campus: '' }
    request = crypto.randomUUID()
    createOpen.value = true
    if (kind.value === 'GROUP') {
      try {
        ministries.value = (
          await communityApi.directory({ kind: 'MINISTRY', limit: 100 })
        ).communities
      } catch (error_) {
        error.value = errorMessage(error_)
      }
    }
  }
  async function save () {
    await communityApi.create({
      requestId: request,
      kind: kind.value,
      ...draft.value,
    })
  }
  function reload () {
    createOpen.value = false
    load()
  }
  onMounted(load)
  onBeforeUnmount(() => {
    clearTimeout(timer)
    generation++
  })
</script>
