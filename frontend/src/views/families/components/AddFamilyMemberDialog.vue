<template>
  <v-dialog v-model="model" max-width="480" persistent>
    <v-card>
      <v-card-title>Add Family Member</v-card-title>
      <v-divider />

      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <v-card-text>
          <v-select
            v-model="form.personId"
            item-title="label"
            item-value="id"
            :items="peopleOptions"
            label="Person"
            :rules="[required]"
          />

          <v-select
            v-model="form.role"
            :items="roleOptions"
            label="Role in family"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Cancel</v-btn>
          <v-btn color="primary" :loading="familiesStore.isSaving" type="submit" variant="flat">Add Member</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import { computed, onMounted, reactive, ref } from 'vue'
  import { useFamiliesStore } from '@/stores/families'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'

  const props = defineProps<{ modelValue: boolean, familyId: string }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const familiesStore = useFamiliesStore()
  const peopleStore = usePeopleStore()
  const ui = useUiStore()
  const formRef = ref()

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const roleOptions = ['head', 'spouse', 'child', 'dependent', 'member']

  const form = reactive({
    personId: '',
    role: 'member',
  })

  const peopleOptions = computed(() => peopleStore.people
    .map(person => ({ id: person.id, label: `${person.firstName} ${person.lastName}` })))

  const required = (value: string) => Boolean(value) || 'This field is required.'

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await familiesStore.addMember(props.familyId, { personId: form.personId, role: form.role })
      ui.notifySuccess('Family member added.')
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to add family member.')
    }
  }

  onMounted(async () => {
    if (peopleStore.people.length === 0) {
      await peopleStore.fetchAll()
    }
  })
</script>
