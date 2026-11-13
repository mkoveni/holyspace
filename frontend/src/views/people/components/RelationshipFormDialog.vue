<template>
  <FormDialog
    ref="formRef"
    v-model="model"
    :loading="saving"
    title="Add Relationship"
    @cancel="close"
    @submit="handleSubmit"
  >
    <v-select
      v-model="form.type"
      :items="relationshipTypes"
      label="Relationship type"
      :rules="[required]"
    />

    <PeopleSearch
      v-model="form.relatedPersonId"
      :exclude-ids="[personId]"
      label="Related person"
      :rules="[required]"
    />

  </FormDialog>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import { required } from '@/utils/rules'

  const props = defineProps<{
    modelValue: boolean
    personId: string
  }>()

  const emit = defineEmits<{
    'update:model-value': [value: boolean]
    'saved': []
  }>()

  const peopleStore = usePeopleStore()
  const ui = useUiStore()
  const formRef = ref()
  const saving = ref(false)

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const relationshipTypes = ['spouse', 'parent', 'child', 'sibling', 'guardian', 'ward', 'other']

  const form = reactive({
    type: '',
    relatedPersonId: '',
  })

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    saving.value = true
    try {
      await peopleStore.createRelationship(props.personId, {
        relatedPersonId: form.relatedPersonId,
        type: form.type,
      })
      ui.notifySuccess('Relationship created successfully.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to create relationship.'))
    } finally {
      saving.value = false
    }
  }
</script>
