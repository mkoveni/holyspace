<template>
  <FormDialog
    ref="formRef"
    v-model="model"
    :loading="familiesStore.isSaving"
    submit-label="Add Member"
    title="Add Family Member"
    @cancel="close"
    @submit="handleSubmit"
  >
    <PeopleSearch
      v-model="form.personId"
      label="Person"
      :rules="[required]"
    />

    <v-select
      v-model="form.role"
      :items="roleOptions"
      label="Role in family"
    />

  </FormDialog>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useFamiliesStore } from '@/stores/families'
  import { useUiStore } from '@/stores/ui'
  import { required } from '@/utils/rules'

  const props = defineProps<{ modelValue: boolean, familyId: string }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const familiesStore = useFamiliesStore()
  const ui = useUiStore()
  const formRef = ref()

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const roleOptions = ['head', 'spouse', 'child', 'parent', 'other']

  const form = reactive({
    personId: '',
    role: 'other',
  })

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    if (familiesStore.isSaving) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await familiesStore.addMember(props.familyId, { personId: form.personId, role: form.role })
      ui.notifySuccess('Family member added.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to add family member.'))
    }
  }

</script>
