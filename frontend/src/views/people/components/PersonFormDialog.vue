<template>
  <FormDialog
    ref="formRef"
    v-model="model"
    :loading="peopleStore.isSaving"
    size="md"
    :submit-label="isEdit ? 'Save Changes' : 'Create Person'"
    :title="isEdit ? 'Edit Person' : 'Add Person'"
    @cancel="close"
    @submit="handleSubmit"
  >
    <v-row>
      <v-col cols="12" sm="6">
        <v-text-field v-model="form.firstName" label="First name" :rules="[required]" />
      </v-col>

      <v-col cols="12" sm="6">
        <v-text-field v-model="form.lastName" label="Last name" :rules="[required]" />
      </v-col>

      <v-col cols="12" sm="6">
        <v-text-field v-model="form.dateOfBirth" label="Date of birth" type="date" />
      </v-col>

      <v-col cols="12" sm="6">
        <v-select v-model="form.gender" :items="genderOptions" label="Gender" />
      </v-col>

      <v-col cols="12" sm="6">
        <v-text-field v-model="form.email" label="Email" type="email" />
      </v-col>

      <v-col cols="12" sm="6">
        <v-text-field v-model="form.phone" label="Phone" />
      </v-col>
    </v-row>

  </FormDialog>
</template>

<script lang="ts" setup>
  import type { Person, PersonGender } from '@/types/person'
  import { computed, reactive, ref, watch } from 'vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import { required } from '@/utils/rules'

  const props = defineProps<{
    modelValue: boolean
    person?: Person | null
  }>()

  const emit = defineEmits<{
    'update:model-value': [value: boolean]
    'saved': []
  }>()

  const peopleStore = usePeopleStore()
  const ui = useUiStore()
  const formRef = ref()

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const isEdit = computed(() => Boolean(props.person))

  const genderOptions: { title: string, value: PersonGender }[] = [
    { title: 'Unspecified', value: 'unspecified' },
    { title: 'Male', value: 'male' },
    { title: 'Female', value: 'female' },
    { title: 'Other', value: 'other' },
  ]

  const form = reactive({
    firstName: '',
    lastName: '',
    dateOfBirth: '',
    gender: 'unspecified' as PersonGender,
    email: '',
    phone: '',
  })

  watch(() => props.modelValue, value => {
    if (value) {
      form.firstName = props.person?.firstName ?? ''
      form.lastName = props.person?.lastName ?? ''
      form.dateOfBirth = props.person?.dateOfBirth ?? ''
      form.gender = props.person?.gender ?? 'unspecified'
      form.email = props.person?.email ?? ''
      form.phone = props.person?.phone ?? ''
    }
  })

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    if (peopleStore.isSaving) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    const payload = {
      firstName: form.firstName,
      lastName: form.lastName,
      dateOfBirth: form.dateOfBirth || null,
      gender: form.gender,
      email: form.email || null,
      phone: form.phone || null,
    }

    try {
      if (isEdit.value && props.person) {
        await peopleStore.update(props.person.id, payload)
        ui.notifySuccess('Person updated successfully.')
      } else {
        await peopleStore.create(payload)
        ui.notifySuccess('Person created successfully.')
      }
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to save person.'))
    }
  }
</script>
