<template>
  <div class="directory-workspace">
    <router-link
      class="directory-back"
      to="/families"
    ><v-icon icon="mdi-arrow-left" size="17" />Back to households</router-link>

    <v-alert
      v-if="error"
      class="mb-5"
      type="error"
      variant="tonal"
    >{{ error }}<v-btn variant="text" @click="load">Retry</v-btn></v-alert>

    <v-skeleton-loader v-if="loading && !profile" type="article, list-item-three-line@3" />

    <template v-if="profile">
      <DirectoryHeading
        eyebrow="HOUSEHOLD PROFILE"
        subtitle="The people who share a place in each other’s lives."
        :title="profile.household.name"
      ><v-btn
         v-if="canManage"
         :disabled="!canChange"
         prepend-icon="mdi-pencil-outline"
         variant="outlined"
         @click="editor = true"
       >Edit household</v-btn>

        <v-btn
          v-if="canManage"
          color="primary"
          :disabled="!canChange"
          prepend-icon="mdi-plus"
          @click="memberEditor = true"
        >Add member</v-btn></DirectoryHeading>

      <TaskAlert :conflict="task.conflict.value" :error="task.error.value" @reload="reload" />

      <v-alert
        v-if="referenceError"
        class="mb-5"
        type="warning"
        variant="tonal"
      >Some member names could not be loaded.<v-btn variant="text" @click="load">Retry</v-btn></v-alert>

      <div class="directory-profile-grid directory-profile-grid--household">
        <aside>
          <section class="directory-surface directory-panel">
            <span class="directory-home-mark mb-4"><v-icon icon="mdi-home-heart" size="30" /></span>

            <span class="directory-eyebrow d-block mb-3">A SHARED PLACE</span>

            <h2 class="mb-3">
              {{ currentMembers.length }} {{ currentMembers.length === 1 ? 'person' : 'people' }}. One
              household.
            </h2>

            <p class="directory-muted">
              A household brings people together. Parenthood and guardianship are recorded separately on their
              profiles.
            </p>

            <div class="directory-footnote">
              <v-icon icon="mdi-calendar-outline" size="17" />

              <span>Created {{ dateLabel(profile.household.createdAt) }}</span>
            </div>
          </section>

          <section class="directory-surface directory-panel mt-5">
            <div class="directory-panel__title">
              <h2>Primary contact</h2>
              <v-icon icon="mdi-account-star-outline" size="20" />
            </div>

            <router-link
              v-if="currentContact"
              class="directory-connection"
              :to="`/people/${currentContact.personId}`"
            ><span class="directory-mini-icon"><v-icon icon="mdi-account-outline" /></span>

              <span><strong>{{ nameFor(currentContact.personId) }}</strong>

                <small>Your point of contact</small></span></router-link>

            <p v-else class="directory-muted">No primary contact selected.</p>

            <template v-if="canManage"><v-select
                                         v-model="contactId"
                                         class="mt-5"
                                         clearable
                                         :disabled="!canChange || Boolean(referenceError) || task.busy.value"
                                         hide-details
                                         :items="
                                           currentMembers.map((member) => ({ value: member.id, title: nameFor(member.personId) }))
                                         "
                                         label="Household contact"
                                       />

              <v-btn
                block
                class="mt-3"
                :disabled="
                  !canChange ||
                    Boolean(referenceError) ||
                    contactId === profile.household.primaryContactMembershipId
                "
                :loading="task.busy.value"
                variant="outlined"
                @click="saveContact"
              >Save contact</v-btn></template>

            <p class="directory-help mt-4">
              Must be a current member. This does not grant guardianship or child pickup rights.
            </p>
          </section>
        </aside>

        <div>
          <section class="directory-surface directory-panel">
            <div class="directory-panel__title">
              <div>
                <span class="directory-eyebrow">PEOPLE WHO BELONG</span>
                <h2>Household members</h2>
              </div>

              <span class="directory-count">{{ currentMembers.length }}</span>
            </div>

            <div v-for="member in currentMembers" :key="member.id" class="directory-entry">
              <router-link class="directory-avatar" :to="`/people/${member.personId}`">{{
                people[member.personId] ? initials(people[member.personId]) : '—'
              }}</router-link>

              <div class="directory-entry__body">
                <router-link :to="`/people/${member.personId}`">{{ nameFor(member.personId) }}</router-link>

                <small>Joined {{ dateLabel(member.validFrom) }}</small>

                <div class="directory-inline mt-2">
                  <span
                    v-if="member.primary"
                    class="directory-badge directory-badge--regular_visitor"
                  >Primary household</span>

                  <span
                    v-if="member.id === profile.household.primaryContactMembershipId"
                    class="directory-badge"
                  >Primary contact</span>
                </div>
              </div>

              <v-btn
                v-if="canManage"
                :aria-label="`End membership for ${nameFor(member.personId)}`"
                :disabled="!canChange || task.busy.value"
                icon="mdi-minus-circle-outline"
                variant="text"
                @click="endMembership(member)"
              />
            </div>

            <DirectoryEmpty
              v-if="currentMembers.length === 0"
              description="Add a person from your directory to bring this household together."
              icon="mdi-account-group-outline"
              title="Their people belong here"
            ><v-btn
              v-if="canManage"
              color="primary"
              :disabled="!canChange"
              variant="outlined"
              @click="memberEditor = true"
            >Add a member</v-btn></DirectoryEmpty>
          </section>

          <section class="directory-surface directory-panel mt-5">
            <div class="directory-panel__title">
              <div>
                <span class="directory-eyebrow">PART OF THE STORY</span>
                <h2>Previous memberships</h2>
              </div>

              <v-icon icon="mdi-history" />
            </div>

            <div
              v-for="member in profile.memberships.filter((item) => item.validTo)"
              :key="member.id"
              class="directory-history-row"
            >
              <span class="directory-history-dot" />

              <div>
                <router-link :to="`/people/${member.personId}`">{{ nameFor(member.personId) }}</router-link>

                <small>{{ dateLabel(member.validFrom) }} — {{ dateLabel(member.validTo) }}</small>
              </div>
            </div>

            <p v-if="!profile.memberships.some((item) => item.validTo)" class="directory-quiet-empty">
              No previous memberships. When someone leaves, their connection remains part of this household’s
              history.
            </p>
          </section>
        </div>
      </div>

      <HouseholdEditor v-model="editor" :household="profile.household" @reloaded="load" @saved="load" />

      <AssociationEditor
        v-model="memberEditor"
        :household="profile"
        kind="member"
        @reload="reload"
        @saved="load"
      />
    </template>
  </div>
</template>
<script setup lang="ts">
  import type { HouseholdMembership, HouseholdProfile, PersonProfile } from '../types'
  import axios from 'axios'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  import { useUiStore } from '@/stores/ui'
  import { directoryApi } from '../api'
  import AssociationEditor from '../components/AssociationEditor.vue'
  import DirectoryEmpty from '../components/DirectoryEmpty.vue'
  import DirectoryHeading from '../components/DirectoryHeading.vue'
  import HouseholdEditor from '../components/HouseholdEditor.vue'
  import TaskAlert from '../components/TaskAlert.vue'
  import { dateLabel, fullName, initials } from '../presentation'
  import { personReferences } from '../references'
  import { useTask } from '../useTask'
  const props = defineProps<{ id: string }>()
  const profile = ref<HouseholdProfile | null>(null)
  const people = ref<Record<string, PersonProfile>>({})
  const error = ref('')
  const referenceError = ref(false)
  const loading = ref(false)
  const editor = ref(false)
  const memberEditor = ref(false)
  const contactId = ref<string | null>(null)
  const auth = useAuthStore()
  const ui = useUiStore()
  const task = useTask()
  const canManage = computed(() => auth.hasPermission('people.manage'))
  const canChange = computed(() => canManage.value && !loading.value && !error.value && !task.conflict.value)
  const currentMembers = computed(() => profile.value?.memberships.filter(member => !member.validTo) ?? [])
  const currentContact = computed(() =>
    currentMembers.value.find(member => member.id === profile.value?.household.primaryContactMembershipId),
  )
  function nameFor (id: string) {
    return people.value[id] ? fullName(people.value[id]) : 'View person'
  }
  let controller: AbortController | null = null
  async function load () {
    controller?.abort()
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    error.value = ''
    referenceError.value = false
    try {
      const result = await directoryApi.household(props.id, signal)
      if (signal.aborted) return
      profile.value = result
      contactId.value = result.household.primaryContactMembershipId
      try {
        const references = await personReferences(
          result.memberships.map(member => member.personId),
          signal,
        )
        if (!signal.aborted) people.value = references
      } catch (error_) {
        if (!signal.aborted && !axios.isCancel(error_)) referenceError.value = true
      }
    } catch (error_) {
      if (!signal.aborted && !axios.isCancel(error_))
        error.value = extractErrorMessage(error_, 'Unable to load household.')
    } finally {
      if (!signal.aborted) loading.value = false
    }
  }
  async function reload () {
    task.clear()
    await load()
  }
  watch(
    () => props.id,
    () => {
      profile.value = null
      people.value = {}
      task.clear()
      editor.value = false
      memberEditor.value = false
      void load()
    },
    { immediate: true },
  )
  onBeforeUnmount(() => controller?.abort())
  async function saveContact () {
    if (!profile.value) return
    const household = profile.value.household
    const result = await task.run(() => directoryApi.setContact(household, contactId.value))
    if (result) {
      ui.notifySuccess('Primary contact updated.')
      await load()
    }
  }
  async function endMembership (member: HouseholdMembership) {
    if (!profile.value) return
    const household = profile.value.household
    if (
      !(await ui.confirmAction({
        title: 'End this household membership?',
        message:
          'This takes effect today. Their history remains, and they are removed as primary contact if selected. Their person profile stays in the directory.',
        confirmText: 'End membership',
      }))
    )
      return
    const result = await task.run(() => directoryApi.endMembership(household, member.id))
    if (result) {
      ui.notifySuccess('Household membership ended.')
      await load()
    }
  }
</script>
