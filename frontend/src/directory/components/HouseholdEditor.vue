<template>
  <v-dialog
    v-model="open"
    max-width="580"
    persistent
  ><v-card class="directory-form">
    <div class="directory-form__header">
      <div>
        <span class="directory-eyebrow">FAMILIES & HOUSEHOLDS</span>
        <h2>{{ household ? 'A familiar name' : 'Make room for a household' }}</h2>
        <p>A shared home. A meaningful connection.</p>
      </div>

      <v-btn
        aria-label="Close household form"
        :disabled="task.busy.value"
        icon="mdi-close"
        variant="text"
        @click="open = false"
      />
    </div>

    <v-card-text><TaskAlert :conflict="task.conflict.value" :error="task.error.value" @reload="reload" />

      <v-form
        id="household-editor"
        ref="formRef"
        @submit.prevent="save"
      ><v-text-field
         v-model="name"
         label="Household name *"
         maxlength="160"
         placeholder="e.g. Mabasa household"
         :rules="[(value) => Boolean(value.trim()) || 'Enter a household name.']"
       />

        <div class="directory-note">
          <v-icon icon="mdi-home-heart" />

          <p>
            Members can belong to more than one household. Family relationships are recorded separately on
            each person's profile.
          </p>
        </div></v-form></v-card-text>

    <div class="directory-form__footer">
      <span>* Required field</span>

      <div>
        <v-btn :disabled="task.busy.value" variant="text" @click="open = false">Cancel</v-btn>

        <v-btn
          color="primary"
          :disabled="task.conflict.value"
          form="household-editor"
          :loading="task.busy.value"
          type="submit"
          variant="flat"
        >{{ household ? 'Save changes' : 'Create household' }}</v-btn>
      </div>
    </div>
  </v-card></v-dialog>
</template>
<script setup lang="ts">
  import type { Household } from '../types'
  import { ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { useUiStore } from '@/stores/ui'
  import { directoryApi } from '../api'
  import { useTask } from '../useTask'
  import TaskAlert from './TaskAlert.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ household?: Household | null }>()
  const emit = defineEmits<{ saved: [id: string], reloaded: [] }>()
  const task = useTask()
  const name = ref('')
  const baseline = ref<Household | null>(null)
  const ui = useUiStore()
  const formRef = ref<{ validate: () => Promise<{ valid: boolean }> }>()
  watch(open, value => {
    if (value) {
      baseline.value = props.household ?? null
      name.value = props.household?.name ?? ''
      task.clear()
    }
  })
  async function reload () {
    if (!baseline.value) return
    try {
      const result = await directoryApi.household(baseline.value.id)
      baseline.value = result.household
      name.value = result.household.name
      task.clear()
      emit('reloaded')
    } catch (error) {
      task.error.value = extractErrorMessage(error, 'Unable to reload this household. Please retry.')
    }
  }
  async function save () {
    if (!(await formRef.value?.validate())?.valid) return
    const result = await task.run(() => directoryApi.saveHousehold(name.value.trim(), baseline.value))
    if (result) {
      open.value = false
      ui.notifySuccess('Household saved.')
      emit('saved', result.value)
    }
  }
</script>
