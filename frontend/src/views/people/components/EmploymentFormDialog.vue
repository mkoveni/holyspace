<template>
  <v-dialog v-model="model" max-width="520" persistent>
    <v-card>
      <v-card-title>Add Employment History</v-card-title>
      <v-divider />

      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <v-card-text>
          <v-text-field v-model="form.employer" label="Employer" :rules="[required]" />
          <v-text-field v-model="form.jobTitle" label="Job title" :rules="[required]" />
          <v-text-field v-model="form.industry" label="Industry (optional)" />

          <v-row>
            <v-col cols="6">
              <v-text-field v-model="form.startDate" label="Start date" type="date" />
            </v-col>

            <v-col cols="6">
              <v-text-field v-model="form.endDate" label="End date" type="date" />
            </v-col>
          </v-row>

          <v-text-field v-model="form.workEmail" label="Work email (optional)" />
          <v-text-field v-model="form.workPhone" label="Work phone (optional)" />
          <v-textarea v-model="form.notes" label="Notes (optional)" rows="2" />
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

  const form = reactive({
    employer: '',
    jobTitle: '',
    industry: '',
    startDate: '',
    endDate: '',
    workEmail: '',
    workPhone: '',
    notes: '',
  })

  const required = (value: string) => Boolean(value) || 'This field is required.'

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await peopleStore.addEmployment(props.personId, {
        employer: form.employer,
        jobTitle: form.jobTitle,
        industry: form.industry || null,
        startDate: form.startDate || null,
        endDate: form.endDate || null,
        workEmail: form.workEmail || null,
        workPhone: form.workPhone || null,
        notes: form.notes || null,
      })
      ui.notifySuccess('Employment history added.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to add employment history.')
    }
  }
</script>
