<template>
  <FormDialog
    ref="formRef"
    v-model="model"
    :loading="userAccountsStore.isSaving"
    submit-label="Create Account"
    title="Create User Account"
    @cancel="close"
    @submit="handleSubmit"
  >
    <v-text-field v-model="form.displayName" label="Full name" maxlength="160" :rules="[required]" />

    <v-text-field
      v-model="form.email"
      label="Email address"
      maxlength="254"
      :rules="[required]"
      type="email"
    />

    <v-text-field v-model="form.username" label="Username" :rules="[required]" />

    <v-text-field
      v-model="form.password"
      label="Temporary password"
      :rules="[required, (value) => value.length >= 12 || 'Use at least 12 characters.']"
      type="password"
    />

  </FormDialog>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useUiStore } from '@/stores/ui'
  import { useUserAccountsStore } from '@/stores/userAccounts'
  import { required } from '@/utils/rules'

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
        email: form.email,
        displayName: form.displayName,
        password: form.password,
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
      ui.notifyError(extractErrorMessage(error, 'Unable to create user account.'))
    }
  }
</script>
