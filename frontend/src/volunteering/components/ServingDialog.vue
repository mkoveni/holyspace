<template>
  <v-dialog
    v-model="open"
    max-width="760"
    persistent
    scrollable
  >
    <v-card class="directory-form">
      <div class="directory-form__header">
        <div>
          <span class="directory-eyebrow">SERVING TOGETHER</span>
          <h2>{{ title }}</h2>
          <p>{{ subtitle }}</p>
        </div>

        <v-btn
          aria-label="Close form"
          :disabled="task.busy.value"
          icon="mdi-close"
          variant="text"
          @click="open = false"
        />
      </div>

      <v-card-text>
        <TaskAlert
          :conflict="task.conflict.value"
          :error="task.error.value"
          @reload="reload"
        />

        <v-form
          :id="id"
          ref="form"
          @submit.prevent="save"
        >
          <slot />
        </v-form>
      </v-card-text>

      <v-card-actions class="directory-form__footer">
        <span class="directory-muted">{{ note }}</span>

        <v-spacer />

        <v-btn
          :disabled="task.busy.value"
          variant="text"
          @click="open = false"
        >
          Cancel
        </v-btn>

        <v-btn
          color="primary"
          :disabled="task.conflict.value || submitDisabled"
          :form="id"
          :loading="task.busy.value"
          type="submit"
          variant="flat"
        >
          {{ saveLabel || 'Save changes' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script setup lang="ts">
  import { ref, useId, watch } from 'vue'
  import TaskAlert from '@/directory/components/TaskAlert.vue'
  import { useTask } from '@/directory/useTask'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{
    title: string
    subtitle: string
    submit: () => Promise<unknown>
    submitDisabled?: boolean
    saveLabel?: string
    note?: string
  }>()
  const emit = defineEmits<{ saved: [], reload: [] }>()
  const task = useTask({ preserveConflictMessage: true })
  const form = ref<{ validate: () => Promise<{ valid: boolean }> }>()
  const id = useId()
  watch(open, () => task.clear())
  async function save () {
    if (!(await form.value?.validate())?.valid) return
    const result = await task.run(props.submit)
    if (result) {
      open.value = false
      emit('saved')
    }
  }
  function reload () {
    task.clear()
    emit('reload')
  }
</script>
