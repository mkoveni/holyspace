<template>
  <v-dialog
    v-model="open"
    max-width="640"
    persistent
    scrollable
  ><v-card class="directory-form">
    <div class="directory-form__header">
      <div>
        <span class="directory-eyebrow">{{
          kind === 'member' ? 'HOUSEHOLD MEMBERSHIP' : 'FAMILY CONNECTIONS'
        }}</span>

        <h2>{{ kind === 'member' ? 'Bring someone home' : 'Record a relationship' }}</h2>
        <p>{{ kind === 'member' ? household?.household.name : person ? fullName(person) : '' }}</p>
      </div>

      <v-btn
        aria-label="Close association form"
        :disabled="task.busy.value"
        icon="mdi-close"
        variant="text"
        @click="open = false"
      />
    </div>

    <v-card-text><TaskAlert :conflict="task.conflict.value" :error="task.error.value" @reload="refresh" />

      <v-form id="association-editor" @submit.prevent="save">
        <template v-if="kind === 'relationship'"><p class="directory-field-intro">{{ person ? fullName(person) : 'This person' }} is…</p>

          <v-select
            v-model="type"
            :items="relationOptions"
            label="Relationship *"
          /></template>

        <PersonPicker
          v-model="selected"
          :exclude="excluded"
          :label="kind === 'member' ? 'Person to add *' : 'Related person *'"
        />

        <v-alert
          v-if="selectionError"
          class="mt-4"
          type="error"
          variant="tonal"
        >{{ selectionError }}<v-btn variant="text" @click="checkMemberships">Retry</v-btn></v-alert>

        <template v-if="kind === 'member'">
          <v-switch
            v-model="primary"
            color="primary"
            :disabled="checking || hasPrimary"
            hide-details
            label="This is their primary household"
            :loading="checking"
          />

          <p v-if="hasPrimary" class="directory-help mt-2">
            They already have a primary household. They can join this household too. To change their primary
            home, first end the existing primary membership.
          </p>
        </template>

        <div class="directory-note mt-6">
          <v-icon icon="mdi-information-outline" />

          <p>
            {{
              kind === 'member'
                ? 'Household membership starts today. It does not establish parenthood or guardianship.'
                : 'This connection starts today. Relationships do not grant permission to collect a child.'
            }}
          </p>
        </div>
      </v-form>
    </v-card-text>

    <div class="directory-form__footer">
      <span>* Required fields</span>

      <div>
        <v-btn :disabled="task.busy.value" variant="text" @click="open = false">Cancel</v-btn>

        <v-btn
          color="primary"
          :disabled="!selected || checking || Boolean(selectionError) || task.conflict.value"
          form="association-editor"
          :loading="task.busy.value"
          type="submit"
          variant="flat"
        >{{ kind === 'member' ? 'Add member' : 'Save relationship' }}</v-btn>
      </div>
    </div>
  </v-card></v-dialog>
</template>
<script setup lang="ts">
  import type { HouseholdProfile, PersonProfile, RelationshipType } from '../types'
  import axios from 'axios'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { useUiStore } from '@/stores/ui'
  import { directoryApi } from '../api'
  import { fullName, relationLabels } from '../presentation'
  import { useTask } from '../useTask'
  import PersonPicker from './PersonPicker.vue'
  import TaskAlert from './TaskAlert.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{
    kind: 'member' | 'relationship'
    household?: HouseholdProfile
    person?: PersonProfile
  }>()
  const emit = defineEmits<{ saved: [], reload: [] }>()
  const selected = ref<string | null>(null)
  const primary = ref(false)
  const hasPrimary = ref(false)
  const checking = ref(false)
  const selectionError = ref('')
  const type = ref<RelationshipType>('PARENT_OF')
  const task = useTask()
  const ui = useUiStore()
  const relationOptions = (['PARENT_OF', 'GUARDIAN_OF', 'SPOUSE_OF', 'SIBLING_OF', 'OTHER'] as const).map(
    value => ({ value, title: relationLabels[value] }),
  )
  const excluded = computed(() =>
    props.kind === 'member'
      ? (props.household?.memberships.filter(member => !member.validTo).map(member => member.personId)
        ?? [])
      : [props.person?.id ?? ''],
  )
  let controller: AbortController | null = null
  watch(open, value => {
    if (value) {
      selected.value = null
      primary.value = false
      hasPrimary.value = false
      task.clear()
      type.value = 'PARENT_OF'
      selectionError.value = ''
    } else controller?.abort()
  })
  watch(selected, checkMemberships)
  async function checkMemberships () {
    controller?.abort()
    checking.value = false
    hasPrimary.value = false
    selectionError.value = ''
    if (props.kind !== 'member' || !selected.value) return
    controller = new AbortController()
    const signal = controller.signal
    checking.value = true
    try {
      const memberships = await directoryApi.personHouseholds(selected.value, signal)
      if (!signal.aborted) {
        hasPrimary.value = memberships.some(member => member.primary && !member.validTo)
        if (hasPrimary.value) primary.value = false
      }
    } catch (error) {
      if (!axios.isCancel(error))
        selectionError.value = extractErrorMessage(error, 'Unable to check their household memberships.')
    } finally {
      if (!signal.aborted) checking.value = false
    }
  }
  onBeforeUnmount(() => controller?.abort())
  function refresh () {
    open.value = false
    emit('reload')
  }
  async function save () {
    if (!selected.value || checking.value || selectionError.value) return
    const id = selected.value
    const result = await task.run(async () => {
      if (props.kind === 'member' && props.household)
        await directoryApi.addMember(props.household.household, id, primary.value)
      else if (props.kind === 'relationship' && props.person)
        await directoryApi.addRelationship(props.person.id, id, type.value)
      else throw new Error('Reload this page before saving.')
    })
    if (result) {
      open.value = false
      ui.notifySuccess(props.kind === 'member' ? 'Household member added.' : 'Relationship recorded.')
      emit('saved')
    }
  }
</script>
