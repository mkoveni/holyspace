<template>
  <FormDialog
    ref="formRef"
    v-model="model"
    :loading="peopleStore.isSaving"
    title="Add Address"
    @cancel="close"
    @submit="handleSubmit"
  >
    <v-select v-model="form.type" :items="typeOptions" label="Address type" :rules="[required]" />
    <v-text-field v-model="form.line1" label="Address line 1" :rules="[required]" />
    <v-text-field v-model="form.line2" label="Address line 2 (optional)" />
    <v-text-field v-model="form.city" label="City" :rules="[required]" />
    <v-text-field v-model="form.province" label="Province (optional)" />
    <v-text-field v-model="form.postalCode" label="Postal code (optional)" />
    <v-text-field v-model="form.country" label="Country" :rules="[required]" />

  </FormDialog>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import { required } from '@/utils/rules'

  const props = defineProps<{ modelValue: boolean, personId: string }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const peopleStore = usePeopleStore()
  const ui = useUiStore()
  const formRef = ref()

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const typeOptions = ['residential', 'postal', 'work', 'other']

  const form = reactive({
    type: 'residential',
    line1: '',
    line2: '',
    city: '',
    province: '',
    postalCode: '',
    country: 'South Africa',
  })

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    if (peopleStore.isSaving) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await peopleStore.addAddress(props.personId, {
        type: form.type,
        line1: form.line1,
        line2: form.line2 || null,
        city: form.city,
        province: form.province || null,
        postalCode: form.postalCode || null,
        country: form.country,
      })
      ui.notifySuccess('Address added.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to add address.'))
    }
  }
</script>
