<template>
  <v-dialog v-model="model" max-width="440" persistent>
    <v-card>
      <v-card-title>Add Family</v-card-title>
      <v-divider />

      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <v-card-text>
          <v-text-field v-model="name" label="Family name" :rules="[required]" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Cancel</v-btn>
          <v-btn color="primary" :loading="familiesStore.isSaving" type="submit" variant="flat">Create Family</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import { computed, ref, watch } from 'vue'
  import { useFamiliesStore } from '@/stores/families'
  import { useUiStore } from '@/stores/ui'

  const props = defineProps<{ modelValue: boolean }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const familiesStore = useFamiliesStore()
  const ui = useUiStore()
  const formRef = ref()
  const name = ref('')

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const required = (value: string) => Boolean(value) || 'This field is required.'

  watch(() => props.modelValue, value => {
    if (value) name.value = ''
  })

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await familiesStore.create({ name: name.value })
      ui.notifySuccess('Family created successfully.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to create family.')
    }
  }
</script>
