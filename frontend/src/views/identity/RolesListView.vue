<template>
  <div>
    <PageHeader
      eyebrow="Accounts & access"
      subtitle="Define roles and choose the permissions each one grants"
      title="Roles"
    >
      <template #actions>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          variant="flat"
          @click="createDialog = true"
        >
          Create Role
        </v-btn>
      </template>
    </PageHeader>

    <v-card>
      <v-card-text class="workspace-filter-panel">
        <v-text-field
          v-model="search"
          clearable
          hide-details
          placeholder="Search roles or permissions"
          prepend-inner-icon="mdi-magnify"
          style="max-width: 420px"
        />
      </v-card-text>

      <AppDataTable
        clickable
        :empty-text="search ? 'Try a different name or permission.' : 'Roles bundle permissions so you can give people the right access in one step.'"
        :empty-title="search ? 'No roles match your search' : 'No roles yet'"
        :headers="headers"
        item-value="id"
        :items="filteredRoles"
        :loading="loading"
        @row-click="openManageDialog"
      >
        <template #item.name="{ item }">
          <div class="table-identity">
            <span class="table-monogram"><v-icon
              icon="mdi-shield-account-outline"
              size="18"
            /></span>

            <span>
              <strong>{{ item.name }}</strong>
              <small>{{ item.description || 'No description' }}</small>
            </span>
          </div>
        </template>

        <template #item.permissions="{ item }">
          <div
            v-if="(item.permissions ?? []).length > 0"
            class="chip-row"
          >
            <v-chip
              v-for="code in (item.permissions ?? []).slice(0, 4)"
              :key="code"
              color="primary"
              size="small"
            >
              {{ code }}
            </v-chip>

            <v-chip
              v-if="(item.permissions ?? []).length > 4"
              size="small"
            >
              +{{ (item.permissions ?? []).length - 4 }} more
            </v-chip>
          </div>

          <span
            v-else
            class="table-muted"
          >No permissions yet</span>
        </template>

        <template #item.actions="{ item }">
          <v-btn
            :aria-label="`Manage ${item.name}`"
            size="small"
            variant="tonal"
            @click.stop="openManageDialog(item)"
          >
            Manage
          </v-btn>
        </template>

      </AppDataTable>
    </v-card>

    <FormDialog
      ref="formRef"
      v-model="createDialog"
      :loading="rolesStore.isSaving"
      submit-label="Create"
      title="Create Role"
      @submit="handleCreate"
    >
      <v-text-field
        v-model="form.name"
        label="Role name"
        :rules="[required]"
      />

      <v-textarea
        v-model="form.description"
        label="Description"
        rows="2"
      />
    </FormDialog>

    <v-dialog
      v-model="manageDialog"
      max-width="620"
      scrollable
    >
      <v-card v-if="selectedRole">
        <v-card-title class="d-flex flex-column ga-1">
          {{ selectedRole.name }}
          <small class="text-medium-emphasis">{{ selectedRole.description || 'Permissions granted by this role' }}</small>
        </v-card-title>

        <v-divider />

        <v-card-text>
          <h3 class="section-label">
            Granted permissions ({{ (selectedRole.permissions ?? []).length }})
          </h3>

          <div
            v-if="(selectedRole.permissions ?? []).length > 0"
            class="chip-row mb-5"
          >
            <v-chip
              v-for="code in selectedRole.permissions"
              :key="code"
              closable
              color="primary"
              @click:close="handleRemovePermission(code)"
            >
              {{ code }}
            </v-chip>
          </div>

          <p
            v-else
            class="text-medium-emphasis mb-5"
          >
            This role does not grant anything yet. Add a permission below.
          </p>

          <div class="d-flex ga-2 align-start">
            <v-autocomplete
              v-model="permissionId"
              class="flex-grow-1"
              hide-details
              item-title="code"
              item-value="id"
              :items="availablePermissions"
              label="Add a permission"
              no-data-text="Every permission is already granted"
            />

            <v-btn
              color="primary"
              :disabled="!permissionId"
              height="48"
              variant="flat"
              @click="handleAddPermission"
            >
              Add
            </v-btn>
          </div>

          <v-alert
            class="mt-4"
            density="compact"
            type="info"
            variant="tonal"
          >
            Give group leaders <strong>ministries.lead</strong> without church-wide management permissions.
          </v-alert>
        </v-card-text>

        <v-card-actions>
          <v-spacer />

          <v-btn
            variant="text"
            @click="manageDialog = false"
          >
            Done
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script lang="ts" setup>
  import type { Role } from '@/types/role'
  import { computed, onMounted, reactive, ref } from 'vue'
  import AppDataTable from '@/components/common/AppDataTable.vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useAction } from '@/composables/useAction'
  import { usePermissionsStore } from '@/stores/permissions'
  import { useRolesStore } from '@/stores/roles'
  import { useUiStore } from '@/stores/ui'
  import { required } from '@/utils/rules'

  const permissionsStore = usePermissionsStore()
  const rolesStore = useRolesStore()
  const ui = useUiStore()
  const action = useAction()

  const loading = ref(false)
  const search = ref('')
  const createDialog = ref(false)
  const manageDialog = ref(false)
  const formRef = ref()
  const permissionId = ref('')
  const selectedRoleId = ref('')

  const form = reactive({ name: '', description: '' })

  const headers = [
    { title: 'Role', key: 'name', sortable: false },
    { title: 'Permissions', key: 'permissions', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' },
  ] as const

  // Read the role from the store so added and removed permissions show up immediately.
  const selectedRole = computed(() => rolesStore.roles.find(role => role.id === selectedRoleId.value) ?? null)
  const availablePermissions = computed(() => {
    const granted = new Set(selectedRole.value?.permissions)
    return permissionsStore.permissions.filter(permission => !granted.has(permission.code))
  })
  const filteredRoles = computed(() => {
    const needle = (search.value ?? '').trim().toLowerCase()
    if (!needle) return rolesStore.roles
    return rolesStore.roles.filter(role =>
      role.name.toLowerCase().includes(needle)
      || (role.permissions ?? []).some(code => code.toLowerCase().includes(needle)),
    )
  })

  onMounted(async () => {
    loading.value = true
    try {
      await Promise.all([rolesStore.fetchAll(), permissionsStore.fetchAll()])
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to load access catalogue.'))
    } finally {
      loading.value = false
    }
  })

  function openManageDialog (role: Role) {
    selectedRoleId.value = role.id
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
      ui.notifyError(extractErrorMessage(error, 'Unable to create role.'))
    }
  }

  async function handleAddPermission () {
    const permission = permissionsStore.permissions.find(item => item.id === permissionId.value)
    const role = selectedRole.value
    if (!role || !permission) return
    const ok = await action.run(() => rolesStore.addPermission(role.id, permission.id, permission.code), {
      success: `${permission.code} added.`,
      failure: 'Unable to add permission.',
    })
    if (ok) permissionId.value = ''
  }

  async function handleRemovePermission (code: string) {
    const permission = permissionsStore.permissions.find(item => item.code === code)
    const role = selectedRole.value
    if (!role || !permission) return
    await action.run(() => rolesStore.removePermission(role.id, permission.id, code), {
      confirm: { title: `Remove ${code}?`, message: `Everyone with the ${role.name} role loses this permission.`, confirmText: 'Remove', color: 'error' },
      success: `${code} removed.`,
      failure: 'Unable to remove permission.',
      undo: () => rolesStore.addPermission(role.id, permission.id, code),
    })
  }
</script>
