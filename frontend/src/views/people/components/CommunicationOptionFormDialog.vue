<template>
  <v-dialog v-model="model" max-width="480" persistent>
    <v-card>
      <v-card-title>Add Communication Option</v-card-title>
      <v-divider />

      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <v-card-text>
          <v-select v-model="form.channel" :items="channelOptions" label="Channel" :rules="[required]" />
          <v-text-field v-model="form.value" label="Value" :rules="[required]" />
          <v-switch v-model="form.preferred" color="primary" hide-details label="Preferred" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Cancel</v-btn>
          <v-btn color="primary" :loading="peopleStore.isSaving" type="submit" variant="flat">Save</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'

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

  const required = (value: string) => Boolean(value) || 'This field is required.'

  function close () {
    model.value = false
  }

  async function handleSubmit () {
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
      ui.notifyError(error instanceof Error ? error.message : 'Unable to add communication option.')
    }
  }
</script>
