<template>
  <v-dialog v-model="model" max-width="480" persistent>
    <v-card>
      <v-card-title>Add Relationship</v-card-title>
      <v-divider />

      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <v-card-text>
          <v-select
            v-model="form.type"
            :items="relationshipTypes"
            label="Relationship type"
            :rules="[required]"
          />

          <v-select
            v-model="form.relatedPersonId"
            item-title="label"
            item-value="id"
            :items="peopleOptions"
            label="Related person"
            :rules="[required]"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" type="submit" variant="flat">Save</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'

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

  const relationshipTypes = ['spouse', 'parent', 'child', 'sibling', 'guardian', 'other']

  const peopleOptions = computed(() => peopleStore.people
    .filter(person => person.id !== props.personId)
    .map(person => ({ id: person.id, label: `${person.firstName} ${person.lastName}` })))

  const form = reactive({
    type: '',
    relatedPersonId: '',
  })

  const required = (value: string) => Boolean(value) || 'This field is required.'

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
      ui.notifyError(error instanceof Error ? error.message : 'Unable to create relationship.')
    } finally {
      saving.value = false
    }
  }
</script>
