<template>
  <FormDialog
    ref="formRef"
    v-model="model"
    :loading="peopleStore.isSaving"
    title="Add Communication Option"
    @cancel="close"
    @submit="handleSubmit"
  >
    <v-select v-model="form.channel" :items="channelOptions" label="Channel" :rules="[required]" />
    <v-text-field v-model="form.value" label="Value" :rules="[required]" />
    <v-switch v-model="form.preferred" color="primary" hide-details label="Preferred" />

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

  const channelOptions = ['email', 'phone', 'sms', 'whatsapp', 'other']

  const form = reactive({
    channel: 'email',
    value: '',
    preferred: false,
  })

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    if (peopleStore.isSaving) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await peopleStore.addCommunicationOption(props.personId, {
        channel: form.channel,
        value: form.value,
        preferred: form.preferred,
      })
      ui.notifySuccess('Communication option added.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to add communication option.'))
    }
  }
</script>
