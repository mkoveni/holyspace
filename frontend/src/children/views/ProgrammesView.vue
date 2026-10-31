<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="CHILDREN & BELONGING"
      subtitle="Thoughtfully planned programmes. Meaningful connections. A safe place to grow."
      title="Little people. Big belonging."
    >
      <v-btn
        v-if="auth.hasPermission('children.manage')"
        color="primary"
        prepend-icon="mdi-plus"
        size="large"
        variant="flat"
        @click="editor = true"
      >
        Add programme
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs active="programmes" />

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-hand-heart-outline" />
        </span>

        <div>
          <strong>Every child deserves a warm welcome.</strong>

          <p>
            Bring programmes, family permissions and Sunday preparation
            together.
          </p>
        </div>
      </div>

      <span class="directory-banner__caption">CARE IN EVERY DETAIL</span>
    </div>

    <section
      aria-label="Programmes"
      class="directory-surface"
    >
      <div class="directory-toolbar">
        <v-text-field
          v-model="search"
          aria-label="Search programmes"
          class="directory-search"
          clearable
          density="compact"
          hide-details
          placeholder="Find a programme…"
          prepend-inner-icon="mdi-magnify"
        />

        <span class="children-notice">
          Make room for faith, friendship and discovery.
        </span>
      </div>

      <v-alert
        v-if="workspace.error.value"
        class="ma-5"
        type="error"
        variant="tonal"
      >
        {{ workspace.error.value }}
        <v-btn
          variant="text"
          @click="workspace.load"
        >
          Retry
        </v-btn>
      </v-alert>

      <v-skeleton-loader
        v-if="workspace.loading.value"
        type="card@2"
      />

      <div
        v-else-if="workspace.data.value.length > 0"
        class="children-grid pa-5"
      >
        <article
          v-for="programme in workspace.data.value"
          :key="programme.id"
          class="directory-surface children-card"
        >
          <span class="children-card__icon">
            <v-icon icon="mdi-sprout-outline" />
          </span>

          <div>
            <h2>{{ programme.name }}</h2>

            <p>
              {{
                programme.currentPolicyId
                  ? 'Consent policy ready for families.'
                  : 'Set up a consent policy to welcome families.'
              }}
            </p>
          </div>

          <div class="children-stats">
            <div>
              <strong>{{ programme.minStaff }}</strong>

              <small>Minimum staff</small>
            </div>

            <div>
              <strong>1 : {{ programme.childrenPerStaff }}</strong>

              <small>Staff to children</small>
            </div>
          </div>

          <footer>
            <span
              class="directory-badge"
              :class="{
                'directory-badge--active_member': programme.currentPolicyId,
              }"
            >
              <i />
              {{
                programme.currentPolicyId
                  ? 'Policy published'
                  : 'Getting started'
              }}
            </span>

            <v-btn
              append-icon="mdi-arrow-top-right"
              :aria-label="`Open ${programme.name}`"
              :to="`/children/programmes/${programme.id}`"
              variant="text"
            >
              Open
            </v-btn>
          </footer>
        </article>
      </div>

      <DirectoryEmpty
        v-else-if="!workspace.error.value"
        :description="
          search
            ? 'Try another name.'
            : 'Start with a programme, then create classes and welcome families.'
        "
        :title="
          search
            ? 'No programmes found'
            : 'A little space. A lasting difference.'
        "
      >
        <v-btn
          v-if="auth.hasPermission('children.manage')"
          color="primary"
          @click="editor = true"
        >
          Add programme
        </v-btn>
      </DirectoryEmpty>

      <footer class="directory-pagination">
        <span>25 programmes per page</span>

        <div>
          <v-btn
            :disabled="!offset || workspace.loading.value"
            variant="text"
            @click="offset -= 25"
          >
            Previous
          </v-btn>

          <v-btn
            :disabled="
              workspace.data.value.length < 25 || workspace.loading.value
            "
            variant="text"
            @click="offset += 25"
          >
            Next
          </v-btn>
        </div>
      </footer>
    </section>

    <ProgrammeDialog
      v-model="editor"
      mode="programme"
      @saved="workspace.load"
    />
  </div>
</template>
<script setup lang="ts">
  import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import ProgrammeDialog from '../components/ProgrammeDialog.vue'
  import { useLoad } from '../useLoad'
  const auth = useAuthStore()
  const search = ref('')
  const offset = ref(0)
  const editor = ref(false)
  const workspace = useLoad(
    () => childrenApi.programmes(search.value || '', offset.value),
    [],
  )
  let timer: ReturnType<typeof setTimeout>
  watch(search, () => {
    offset.value = 0
    clearTimeout(timer)
    timer = setTimeout(workspace.load, 250)
  })
  watch(offset, workspace.load)
  onMounted(workspace.load)
  onBeforeUnmount(() => clearTimeout(timer))
</script>
