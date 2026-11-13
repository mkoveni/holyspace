<template>
  <div>
    <PageHeader eyebrow="Accounts & access" subtitle="Define the granular permissions assignable to roles" title="Permissions">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" variant="flat" @click="dialog = true">
          Create Permission
        </v-btn>
      </template>
    </PageHeader>

    <v-alert class="mb-4" type="info" variant="tonal">
      Available church permissions appear here. Use the Roles page to attach a permission to a role.
    </v-alert>

    <v-card>
      <v-data-table :headers="headers" item-value="id" :items="permissionsStore.permissions">
        <template #item.code="{ item }"><v-chip color="primary" size="small" variant="tonal">{{ item.code }}</v-chip></template>
      </v-data-table>
    </v-card>

    <FormDialog
      ref="formRef"
      v-model="dialog"
      :loading="permissionsStore.isSaving"
      submit-label="Create"
      title="Create Permission"
      @submit="handleCreate"
    >
      <v-text-field v-model="form.code" label="Permission code" placeholder="e.g. people.manage" :rules="[required]" />
      <v-textarea v-model="form.description" label="Description" rows="2" />

    </FormDialog>
  </div>
</template>

<script lang="ts" setup>
  import { onMounted, reactive, ref } from 'vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { usePermissionsStore } from '@/stores/permissions'
  import { useUiStore } from '@/stores/ui'
  import { required } from '@/utils/rules'

  const permissionsStore = usePermissionsStore()
  const ui = useUiStore()
  onMounted(async () => {
    try {
      await permissionsStore.fetchAll()
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to load permissions.'))
    }
  })

  const dialog = ref(false)
  const formRef = ref()
  const form = reactive({ code: '', description: '' })

  const headers = [
    { title: 'Code', key: 'code', sortable: false },
    { title: 'Description', key: 'description', sortable: false },
  ] as const

  async function handleCreate () {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    try {
      await permissionsStore.create({ code: form.code, description: form.description || undefined })
      ui.notifySuccess('Permission created successfully.')
      dialog.value = false
      form.code = ''
      form.description = ''
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to create permission.'))
    }
  }
</script>
