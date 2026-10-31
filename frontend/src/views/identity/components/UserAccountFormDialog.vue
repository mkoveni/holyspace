<template>
  <v-dialog v-model="model" max-width="480" persistent>
    <v-card>
      <v-card-title>Create User Account</v-card-title>
      <v-divider />

      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <v-card-text>
          <template v-if="isJavaApi"><v-text-field v-model="form.displayName" label="Full name" maxlength="160" :rules="[required]" />

            <v-text-field
              v-model="form.email"
              label="Email address"
              maxlength="254"
              :rules="[required]"
              type="email"
            /></template>

          <v-text-field v-model="form.username" label="Username" :rules="[required]" />

          <v-text-field
            v-model="form.password"
            label="Temporary password"
            :rules="[required, (value) => value.length >= 12 || 'Use at least 12 characters.']"
            type="password"
          />

          <PeopleSearch
            v-if="!isJavaApi"
            v-model="form.personId"
            clearable
            label="Linked person (optional)"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Cancel</v-btn>

          <v-btn
            color="primary"
            :loading="userAccountsStore.isSaving"
            type="submit"
            variant="flat"
          >Create Account</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import { isJavaApi } from '@/directory/backend'
  import { useUiStore } from '@/stores/ui'
  import { useUserAccountsStore } from '@/stores/userAccounts'

  const props = defineProps<{ modelValue: boolean }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const userAccountsStore = useUserAccountsStore()
  const ui = useUiStore()
  const formRef = ref()

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const form = reactive({
    username: '',
    email: '',
    displayName: '',
    password: '',
    personId: '',
  })

  const required = (value: string) => Boolean(value) || 'This field is required.'

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    if (userAccountsStore.isSaving) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    try {
      await userAccountsStore.create({
        username: form.username,
        ...(isJavaApi ? { email: form.email, displayName: form.displayName } : {}),
        password: form.password,
        personId: form.personId || undefined,
      })
      ui.notifySuccess('User account created successfully.')
      emit('saved')
      close()
      form.email = ''
      form.displayName = ''
      form.username = ''
      form.password = ''
      form.personId = ''
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to create user account.')
    }
  }
</script>
