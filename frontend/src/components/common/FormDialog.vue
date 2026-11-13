<template>
  <v-dialog
    v-model="model"
    class="form-dialog"
    :max-width="widths[size]"
    :persistent="persistent"
    scrollable
  >
    <v-card>
      <v-card-title class="form-dialog__title">
        <span>{{ title }}</span>
        <small v-if="subtitle">{{ subtitle }}</small>
      </v-card-title>

      <v-divider />

      <v-form
        ref="formRef"
        class="form-dialog__form"
        @change="dirty = true"
        @input="dirty = true"
        @submit.prevent="emit('submit')"
      >
        <v-card-text class="form-dialog__body">
          <v-alert
            v-if="error"
            class="mb-4"
            density="compact"
            type="error"
            variant="tonal"
          >
            {{ error }}
          </v-alert>

          <slot />
        </v-card-text>

        <v-card-actions class="form-dialog__actions">
          <slot name="actions-start" />
          <v-spacer />

          <v-btn
            variant="text"
            @click="cancel"
          >
            {{ cancelLabel }}
          </v-btn>

          <v-btn
            :color="submitColor"
            :disabled="disabled"
            :loading="loading"
            type="submit"
            variant="flat"
          >
            {{ submitLabel }}
          </v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
  import { onBeforeUnmount, ref, watch } from 'vue'
  import { useUiStore } from '@/stores/ui'

  /**
   * The one layout every form dialog uses: title, scrolling body, and a right-aligned
   * Cancel / primary action footer. The parent keeps its own submit logic and can still
   * call `formRef.value.validate()`, which is forwarded to the inner form.
   */
  withDefaults(defineProps<{
    title: string
    subtitle?: string
    submitLabel?: string
    cancelLabel?: string
    submitColor?: string
    loading?: boolean
    disabled?: boolean
    error?: string
    persistent?: boolean
    size?: 'sm' | 'md' | 'lg'
  }>(), {
    submitLabel: 'Save',
    cancelLabel: 'Cancel',
    submitColor: 'primary',
    persistent: true,
    size: 'sm',
  })
  const emit = defineEmits<{ submit: [], cancel: [] }>()
  const model = defineModel<boolean>({ default: false })
  const formRef = ref()
  const ui = useUiStore()
  // Set by any edit inside the form, so closing can warn before work is lost.
  const dirty = ref(false)
  const widths = { sm: 520, md: 680, lg: 860 } as const

  async function cancel () {
    if (dirty.value && !(await ui.confirmAction({
      title: 'Discard your changes?',
      message: 'You have unsaved changes in this form. They will be lost if you close it.',
      confirmText: 'Discard',
      cancelText: 'Keep editing',
      color: 'error',
    }))) return
    emit('cancel')
    model.value = false
  }

  function warnBeforeLeaving (event: BeforeUnloadEvent) {
    if (dirty.value) event.preventDefault()
  }
  watch(model, open => {
    dirty.value = false
    if (open) globalThis.addEventListener('beforeunload', warnBeforeLeaving)
    else globalThis.removeEventListener('beforeunload', warnBeforeLeaving)
  })
  onBeforeUnmount(() => globalThis.removeEventListener('beforeunload', warnBeforeLeaving))

  defineExpose({
    validate: () => formRef.value.validate(),
    resetValidation: () => formRef.value?.resetValidation(),
    reset: () => formRef.value?.reset(),
  })
</script>

<style scoped>
  .form-dialog__title {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .form-dialog__title small {
    font: 400 13px 'Manrope', sans-serif;
    color: var(--workspace-muted);
  }
  .form-dialog__form {
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }
  .form-dialog__body {
    overflow-y: auto;
  }
</style>
