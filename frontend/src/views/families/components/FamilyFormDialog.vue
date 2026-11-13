<template>
  <FormDialog
    ref="formRef"
    v-model="model"
    :loading="familiesStore.isSaving"
    submit-label="Create Family"
    title="Add Family"
    @cancel="close"
    @submit="handleSubmit"
  >
    <v-text-field v-model="name" label="Family name" :rules="[required]" />

  </FormDialog>
</template>

<script lang="ts" setup>
  import { computed, ref, watch } from 'vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useFamiliesStore } from '@/stores/families'
  import { useUiStore } from '@/stores/ui'
  import { required } from '@/utils/rules'

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

  watch(() => props.modelValue, value => {
    if (value) name.value = ''
  })

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    if (familiesStore.isSaving) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await familiesStore.create({ name: name.value })
      ui.notifySuccess('Family created successfully.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to create family.'))
    }
  }
</script>
