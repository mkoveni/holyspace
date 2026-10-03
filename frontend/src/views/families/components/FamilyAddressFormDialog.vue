<template>
  <v-dialog v-model="model" max-width="520" persistent>
    <v-card>
      <v-card-title>Add Family Address</v-card-title>
      <v-divider />

      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <v-card-text>
          <v-select v-model="form.type" :items="typeOptions" label="Address type" :rules="[required]" />
          <v-text-field v-model="form.line1" label="Address line 1" :rules="[required]" />
          <v-text-field v-model="form.line2" label="Address line 2 (optional)" />
          <v-text-field v-model="form.city" label="City" :rules="[required]" />
          <v-text-field v-model="form.province" label="Province (optional)" />
          <v-text-field v-model="form.postalCode" label="Postal code (optional)" />
          <v-text-field v-model="form.country" label="Country" :rules="[required]" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Cancel</v-btn>
          <v-btn color="primary" :loading="familiesStore.isSaving" type="submit" variant="flat">Save</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue'
  import { useFamiliesStore } from '@/stores/families'
  import { useUiStore } from '@/stores/ui'

  const props = defineProps<{ modelValue: boolean, familyId: string }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const familiesStore = useFamiliesStore()
  const ui = useUiStore()
  const formRef = ref()

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const typeOptions = ['residential', 'postal', 'other']

  const form = reactive({
    type: 'residential',
    line1: '',
    line2: '',
    city: '',
    province: '',
    postalCode: '',
    country: 'South Africa',
  })

  const required = (value: string) => Boolean(value) || 'This field is required.'

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await familiesStore.addFamilyAddress(props.familyId, {
        type: form.type,
        line1: form.line1,
        line2: form.line2 || null,
        city: form.city,
        province: form.province || null,
        postalCode: form.postalCode || null,
        country: form.country,
      })
      ui.notifySuccess('Family address added.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to add family address.')
    }
  }
</script>
