<template>
  <div>
    <PageHeader eyebrow="Accounts & access" subtitle="Define roles and attach permissions to them" title="Roles">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" variant="flat" @click="createDialog = true">
          Create Role
        </v-btn>
      </template>
    </PageHeader>

    <v-alert class="mb-4" type="info" variant="tonal">
      Available church roles appear here. Select a role to manage its permissions.
    </v-alert>

    <v-card>
      <v-data-table :headers="headers" item-value="id" :items="rolesStore.roles">
        <template #item.name="{ item }">
          <div class="table-identity"><span class="table-monogram"><v-icon icon="mdi-shield-account-outline" size="18" /></span><strong>{{ item.name }}</strong></div>
        </template>

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
          <v-autocomplete v-model="permissionId" :items="permissionsStore.permissions" item-title="code" item-value="id" label="Permission" />

          <div class="d-flex ga-2">
            <v-btn color="primary" size="small" variant="tonal" @click="handleAddPermission">Add Permission</v-btn>
            <v-btn color="error" size="small" variant="tonal" @click="handleRemovePermission">Remove Permission</v-btn>
          </div>

          <v-alert class="mt-4" density="compact" type="info" variant="tonal">
            Choose an existing permission. Give group leaders ministries.lead without church-wide management permissions.
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
  import { onMounted, reactive, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { usePermissionsStore } from '@/stores/permissions'
  import { useRolesStore } from '@/stores/roles'
  import { useUiStore } from '@/stores/ui'

  const permissionsStore = usePermissionsStore()
  const rolesStore = useRolesStore()
  const ui = useUiStore()
  onMounted(async () => {try {await Promise.all([rolesStore.fetchAll(), permissionsStore.fetchAll()])} catch (error) {ui.notifyError(error instanceof Error ? error.message : 'Unable to load access catalogue.')} })

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
