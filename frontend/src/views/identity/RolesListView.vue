<template>
  <div>
    <PageHeader subtitle="Define roles and attach permissions to them" title="Roles">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" variant="flat" @click="createDialog = true">
          Create Role
        </v-btn>
      </template>
    </PageHeader>

    <v-alert class="mb-4" type="info" variant="tonal">
      A role listing endpoint is not yet available on the backend, so roles created during this
      session are shown below alongside permission management.
    </v-alert>

    <v-card elevation="2">
      <v-data-table :headers="headers" item-value="id" :items="rolesStore.roles">
        <template #item.actions="{ item }">
          <v-btn size="small" variant="tonal" @click="openManageDialog(item)">Manage Permissions</v-btn>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="createDialog" max-width="480" persistent>
      <v-card>
        <v-card-title>Create Role</v-card-title>
        <v-divider />

        <v-form ref="formRef" @submit.prevent="handleCreate">
          <v-card-text>
            <v-text-field v-model="form.name" label="Role name" :rules="[required]" />
            <v-textarea v-model="form.description" label="Description" rows="2" />
          </v-card-text>

          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="createDialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="rolesStore.isSaving" type="submit" variant="flat">Create</v-btn>
          </v-card-actions>
        </v-form>
      </v-card>
    </v-dialog>

    <v-dialog v-model="manageDialog" max-width="480">
      <v-card v-if="selectedRole">
        <v-card-title>{{ selectedRole.name }}</v-card-title>
        <v-divider />

        <v-card-text>
          <v-text-field v-model="permissionId" density="comfortable" label="Permission ID" />

          <div class="d-flex ga-2">
            <v-btn color="primary" size="small" variant="tonal" @click="handleAddPermission">Add Permission</v-btn>
            <v-btn color="error" size="small" variant="tonal" @click="handleRemovePermission">Remove Permission</v-btn>
          </div>

          <v-alert class="mt-4" density="compact" type="info" variant="tonal">
            Enter the ID of a permission created on the Permissions page.
          </v-alert>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="manageDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script lang="ts" setup>
  import type { Role } from '@/types/role'
  import { reactive, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { useRolesStore } from '@/stores/roles'
  import { useUiStore } from '@/stores/ui'

  const rolesStore = useRolesStore()
  const ui = useUiStore()

  const createDialog = ref(false)
  const manageDialog = ref(false)
  const formRef = ref()
  const permissionId = ref('')
  const selectedRole = ref<Role | null>(null)

  const form = reactive({ name: '', description: '' })

  const headers = [
    { title: 'Name', key: 'name', sortable: false },
    { title: 'Description', key: 'description', sortable: false },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ] as const

  const required = (value: string) => Boolean(value) || 'This field is required.'

  function openManageDialog (role: Role) {
    selectedRole.value = role
    permissionId.value = ''
    manageDialog.value = true
  }

  async function handleCreate () {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    try {
      await rolesStore.create({ name: form.name, description: form.description || undefined })
      ui.notifySuccess('Role created successfully.')
      createDialog.value = false
      form.name = ''
      form.description = ''
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to create role.')
    }
  }

  async function handleAddPermission () {
    if (!selectedRole.value || !permissionId.value) return
    try {
      await rolesStore.addPermission(selectedRole.value.id, permissionId.value)
      ui.notifySuccess('Permission added to role.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to add permission.')
    }
  }

  async function handleRemovePermission () {
    if (!selectedRole.value || !permissionId.value) return
    try {
      await rolesStore.removePermission(selectedRole.value.id, permissionId.value)
      ui.notifySuccess('Permission removed from role.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to remove permission.')
    }
  }
</script>
