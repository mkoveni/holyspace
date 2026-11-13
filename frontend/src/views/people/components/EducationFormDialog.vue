<template>
  <FormDialog
    ref="formRef"
    v-model="model"
    :loading="peopleStore.isSaving"
    title="Add Education History"
    @cancel="close"
    @submit="handleSubmit"
  >
    <v-text-field v-model="form.institution" label="Institution" :rules="[required]" />
    <v-text-field v-model="form.qualification" label="Qualification" :rules="[required]" />
    <v-text-field v-model="form.fieldOfStudy" label="Field of study (optional)" />

    <v-row>
      <v-col cols="6">
        <v-text-field v-model="form.startDate" label="Start date" type="date" />
      </v-col>

      <v-col cols="6">
        <v-text-field v-model="form.endDate" label="End date" type="date" />
      </v-col>
    </v-row>

    <v-textarea v-model="form.notes" label="Notes (optional)" rows="2" />

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

  const form = reactive({
    institution: '',
    qualification: '',
    fieldOfStudy: '',
    startDate: '',
    endDate: '',
    notes: '',
  })

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    if (peopleStore.isSaving) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await peopleStore.addEducation(props.personId, {
        institution: form.institution,
        qualification: form.qualification,
        fieldOfStudy: form.fieldOfStudy || null,
        startDate: form.startDate || null,
        endDate: form.endDate || null,
        notes: form.notes || null,
      })
      ui.notifySuccess('Education history added.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to add education history.'))
    }
  }
</script>
