<template>
  <div class="directory-workspace">
    <v-btn
      class="mb-3"
      prepend-icon="mdi-arrow-left"
      to="/children"
      variant="text"
    >
      All programmes
    </v-btn>

    <DirectoryHeading
      eyebrow="CHILDREN & BELONGING"
      subtitle="A shared home for classes, consent and family connections."
      :title="workspace.data.value?.programme.name || 'Programme'"
    >
      <v-btn
        v-if="canManage && workspace.data.value"
        color="primary"
        prepend-icon="mdi-calendar-plus"
        :to="{ path: '/children/sessions', query: { programme: id } }"
      >
        Plan a session
      </v-btn>
    </DirectoryHeading>

    <v-alert
      v-if="workspace.error.value"
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
      type="article"
    />

    <template v-else-if="workspace.data.value">
      <div class="children-split">
        <div>
          <section class="directory-surface children-section">
            <div class="children-section__heading">
              <h2>Classes to grow in</h2>

              <v-btn
                v-if="canManage"
                prepend-icon="mdi-plus"
                variant="text"
                @click="edit('class')"
              >
                Add class
              </v-btn>
            </div>

            <p>
              Age-based spaces within this programme. Enrol a child below, then
              manage class placement on their ministry profile.
            </p>

            <div
              v-for="group in workspace.data.value.classes"
              :key="group.id"
              class="children-list-row"
            >
              <span class="directory-avatar directory-avatar--1">
                <v-icon icon="mdi-sprout-outline" />
              </span>

              <div>
                <strong>{{ group.name }}</strong>

                <small>
                  {{ group.minAgeMonths }}–{{ group.maxAgeMonths }} months ·
                  upper age excluded
                </small>
              </div>

              <v-btn
                v-if="canManage"
                :to="{
                  path: '/children/sessions',
                  query: { programme: id, class: group.id },
                }"
                variant="text"
              >
                Plan session
              </v-btn>
            </div>

            <DirectoryEmpty
              v-if="workspace.data.value.classes.length === 0"
              description="Create an age range that helps children feel at home."
              title="Room for a first class"
            />
          </section>

          <ProgrammeRoster
            :programme-id="id"
            :revision="rosterRevision"
          />

          <section class="directory-surface children-section">
            <h2>A child’s ministry journey</h2>

            <p>
              Find a child to manage enrolment, class placement, family
              permissions and care.
            </p>

            <PersonPicker
              v-if="auth.hasPermission('people.read')"
              v-model="childId"
              age-category="CHILD"
              label="Find a child"
              @selected="selectedChild = $event"
            />

            <p
              v-else
              class="children-notice"
            >
              People directory access is needed to find a child. Ask your
              administrator for access.
            </p>

            <div class="d-flex flex-wrap ga-3 mt-5">
              <v-btn
                v-if="canManage"
                color="primary"
                :disabled="!childId"
                prepend-icon="mdi-account-plus-outline"
                @click="enrolEditor = true"
              >
                Enrol in programme
              </v-btn>

              <v-btn
                :disabled="!childId"
                :to="
                  childId
                    ? {
                      path: `/children/participants/${childId}`,
                      query: { programme: id },
                    }
                    : undefined
                "
                variant="outlined"
              >
                Open ministry profile
              </v-btn>

              <v-btn
                v-if="auth.hasPermission('people.manage')"
                variant="text"
                @click="personEditor = true"
              >
                Add a child to People
              </v-btn>
            </div>

            <v-alert
              v-if="enrolled"
              class="mt-5"
              type="success"
              variant="tonal"
            >
              Child enrolled in {{ workspace.data.value.programme.name }}. Open
              their ministry profile to choose a class and record family
              permissions.
            </v-alert>
          </section>
        </div>

        <aside>
          <section class="directory-surface children-section">
            <div class="children-section__heading">
              <h2>Family consent</h2>

              <v-icon
                color="primary"
                icon="mdi-shield-check-outline"
              />
            </div>

            <template v-if="workspace.data.value.policy">
              <span class="directory-badge directory-badge--active_member">
                <i />
                Policy published
              </span>

              <h3 class="mt-5 mb-3">{{ workspace.data.value.policy.label }}</h3>

              <a
                v-if="safeLink(workspace.data.value.policy.documentReference)"
                class="text-primary"
                :href="safeLink(workspace.data.value.policy.documentReference)"
                rel="noopener noreferrer"
                target="_blank"
              >
                View policy document
                <v-icon
                  icon="mdi-open-in-new"
                  size="15"
                />
              </a>

              <p
                v-else
                class="children-notice"
              >
                {{ workspace.data.value.policy.documentReference }}
              </p>

              <p class="children-notice mt-4">
                Published
                {{
                  dateLabel(workspace.data.value.policy.createdAt.slice(0, 10))
                }}. New versions require fresh family consent.
              </p>
            </template>

            <p v-else>
              Publish the consent policy families will agree to before their
              children attend.
            </p>

            <v-btn
              v-if="canManage"
              class="mt-5"
              variant="tonal"
              @click="edit('policy')"
            >
              {{
                workspace.data.value.policy
                  ? 'Publish a new version'
                  : 'Publish consent policy'
              }}
            </v-btn>
          </section>

          <section class="directory-surface children-section">
            <h2>A thoughtful staff policy</h2>

            <div class="children-stats">
              <div>
                <strong>{{ workspace.data.value.programme.minStaff }}</strong>

                <small>Minimum staff present</small>
              </div>

              <div>
                <strong>
                  1 : {{ workspace.data.value.programme.childrenPerStaff }}
                </strong>

                <small>Staff to children</small>
              </div>
            </div>

            <p class="children-notice">
              Every open session also needs a qualified, present leader. Room
              size and actual staffing determine session capacity.
            </p>
          </section>
        </aside>
      </div>
    </template>

    <ProgrammeDialog
      v-model="editor"
      :mode="mode"
      :programme="workspace.data.value?.programme"
      @reload="workspace.load"
      @saved="workspace.load"
    />

    <ParticipationDialog
      v-if="childId && workspace.data.value && canManage"
      v-model="enrolEditor"
      :child-id="childId"
      :child-name="selectedChild ? fullName(selectedChild) : 'Selected child'"
      :default-programme="id"
      :enrolments="[]"
      mode="enrol"
      :names="{}"
      :programmes="[workspace.data.value.programme]"
      :safeguarding="null"
      @saved="enrolmentSaved"
    />

    <PersonEditor
      v-model="personEditor"
      @saved="createdPerson"
    />
  </div>
</template>
<script setup lang="ts">
  import type { PersonSummary } from '@/directory/types'
  import { computed, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import PersonEditor from '@/directory/components/PersonEditor.vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { fullName } from '@/directory/presentation'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import ParticipationDialog from '../components/ParticipationDialog.vue'
  import ProgrammeDialog from '../components/ProgrammeDialog.vue'
  import ProgrammeRoster from '../components/ProgrammeRoster.vue'
  import { dateLabel, safeLink } from '../presentation'
  import { useLoad } from '../useLoad'
  const props = defineProps<{ id: string }>()
  const auth = useAuthStore()
  const router = useRouter()
  const canManage = computed(() => auth.hasPermission('children.manage'))
  const editor = ref(false)
  const mode = ref<'class' | 'policy'>('class')
  const childId = ref<string | null>(null)
  const selectedChild = ref<PersonSummary | null>(null)
  const enrolEditor = ref(false)
  const enrolled = ref(false)
  const rosterRevision = ref(0)
  function enrolmentSaved () {
    enrolled.value = true
    rosterRevision.value++
  }
  watch(childId, () => {
    enrolled.value = false
    enrolEditor.value = false
  })
  const personEditor = ref(false)
  const workspace = useLoad(async () => {
    const programme = await childrenApi.programme(props.id)
    const [classes, policy] = await Promise.all([
      childrenApi.classes(props.id),
      programme.currentPolicyId
        ? childrenApi.policy(programme.currentPolicyId)
        : null,
    ])
    return { programme, classes, policy }
  }, null)
  function edit (value: 'class' | 'policy') {
    mode.value = value
    editor.value = true
  }
  function createdPerson (id: string) {
    void router.push({
      path: `/children/participants/${id}`,
      query: { programme: props.id },
    })
  }
  watch(
    () => props.id,
    () => {
      childId.value = null
      editor.value = false
      enrolEditor.value = false
      enrolled.value = false
      void workspace.load()
    },
    { immediate: true },
  )
</script>
