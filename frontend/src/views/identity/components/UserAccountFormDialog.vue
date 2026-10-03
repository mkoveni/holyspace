<template>
  <v-dialog v-model="model" max-width="480" persistent>
    <v-card>
      <v-card-title>Create User Account</v-card-title>
      <v-divider />

      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <v-card-text>
          <v-text-field v-model="form.username" label="Username" :rules="[required]" />

          <v-text-field
            v-model="form.password"
            label="Temporary password"
            :rules="[required]"
            type="password"
          />

          <v-select
            v-model="form.personId"
            clearable
            item-title="label"
            item-value="id"
            :items="peopleOptions"
            label="Linked person (optional)"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Cancel</v-btn>
          <v-btn color="primary" :loading="userAccountsStore.isSaving" type="submit" variant="flat">Create Account</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import { computed, onMounted, reactive, ref } from 'vue'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import { useUserAccountsStore } from '@/stores/userAccounts'

  const props = defineProps<{ modelValue: boolean }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const userAccountsStore = useUserAccountsStore()
  const peopleStore = usePeopleStore()
  const ui = useUiStore()
  const formRef = ref()

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const form = reactive({
    username: '',
    password: '',
    personId: '',
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
      await userAccountsStore.create({
        username: form.username,
        password: form.password,
        personId: form.personId || undefined,
      })
      ui.notifySuccess('User account created successfully.')
      emit('saved')
      close()
      form.username = ''
      form.password = ''
      form.personId = ''
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to create user account.')
    }
  }

  onMounted(async () => {
    if (peopleStore.people.length === 0) {
      await peopleStore.fetchAll()
    }
  })
</script>
