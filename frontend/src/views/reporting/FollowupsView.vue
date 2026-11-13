<template>
  <div><PageHeader subtitle="Assigned staff, due dates and recorded outcomes" title="Follow-up tasks"><template #actions><v-btn v-if="auth.hasPermission('reports.read')" to="/reports">Reports and segments</v-btn></template></PageHeader>
    <v-alert v-if="error" class="mb-4" type="error">{{ error }}</v-alert><v-btn class="mb-4" :loading="busy" @click="load">Refresh tasks</v-btn><v-alert v-if="!busy && tasks.length === 0" type="info">No follow-up tasks are available to your account. Managers assign tasks from Reports and segments; source permissions also apply.</v-alert>
    <v-card v-for="task in tasks" :key="task.id" class="workspace-panel"><div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-clipboard-check-outline" /><div><h2>{{ task.title }}</h2><p>{{ task.personName }}</p></div></div><v-chip :color="task.overdue ? 'error' : 'primary'">{{ task.status.replaceAll('_', ' ') }}</v-chip></div><p>{{ task.personName }} · assigned to {{ task.assigneeName }} · {{ task.status }} · due {{ task.dueOn }}{{ task.overdue ? ' · overdue' : '' }}</p><p v-if="task.ruleId">Created by an automation rule</p><v-btn class="my-3" :disabled="busy" @click="edit(task)">Record progress or outcome</v-btn><v-btn v-if="auth.hasPermission('followups.manage')" :disabled="busy" @click="assign(task)">Reassign task</v-btn><details class="workspace-note"><summary>Task history</summary><p v-for="(entry,index) in task.history" :key="index">{{ entry.at }} · {{ entry.action }} · {{ entry.status ?? '' }} · {{ entry.note }}</p></details></v-card>
    <FormDialog v-model="dialog" :error="dialogError" title="Follow-up progress" size="md" :loading="busy" submit-label="Save outcome" @submit="save"><p>{{ selected?.personName }} · {{ selected?.title }}</p><v-select v-model="status" :items="['open','in_progress','completed','cancelled']" label="Task status" /><v-textarea v-model="note" label="Outcome or reason" maxlength="1000" /><p>An outcome is required to complete, cancel or reopen a closed task. Keep notes brief and avoid sensitive case information.</p></FormDialog>

    <FormDialog v-model="assignmentDialog" :error="dialogError" title="Reassign follow-up" size="md" :loading="busy" :disabled="!assigneeId || !note.trim()" submit-label="Save assignment" @submit="reassign"><v-select
                                                                                                           v-model="assigneeId"
                                                                                                           item-title="username"
                                                                                                           item-value="id"
                                                                                                           :items="assignees"
                                                                                                           label="New staff account"
                                                                                                         />

                                                                                                         <v-text-field v-model="dueOn" label="New due date" type="date" /><v-textarea v-model="note" label="Reason for reassignment" maxlength="1000" /></FormDialog>
  </div>
</template>
<script setup lang="ts">
  import FormDialog from '@/components/common/FormDialog.vue'
  import { onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  interface Task { id: string, title: string, personName: string, assigneeName: string, status: string, dueOn: string, overdue: boolean, version: number, ruleId: string | null, history: { at: string, action: string, status?: string, note: string }[] }
  const auth = useAuthStore(), tasks = ref<Task[]>([]), busy = ref(false), error = ref(''), dialogError = ref(''), selected = ref<Task | null>(null), status = ref('in_progress'), note = ref(''), dialog = ref(false), assignmentDialog = ref(false), dueOn = ref(''), assigneeId = ref<string | null>(null), assignees = ref<{ id: string, username: string }[]>([])
  async function refresh () {
    tasks.value = (await useApi().get<{ items: Task[] }>('/api/followups')).data.items
  }
  async function action (operation: () => Promise<void>, inDialog = false) {
    if (busy.value) return; busy.value = true; error.value = ''; dialogError.value = ''; try {
      await operation()
    } catch (error_) {
      const message = extractErrorMessage(error_, 'Unable to update follow-up tasks.'); if (inDialog) dialogError.value = message; else error.value = message
    } finally {
      busy.value = false
    }
  }
  async function load () {
    tasks.value = []; await action(refresh)
  }
  function edit (task: Task) {
    selected.value = task; status.value = task.status === 'open' ? 'in_progress' : task.status; note.value = ''; dialogError.value = ''; dialog.value = true
  }
  async function save () {
    await action(async () => {
      await useApi().post('/api/followups/' + selected.value?.id + '/status', { expectedVersion: selected.value?.version, status: status.value, note: note.value }); dialog.value = false; note.value = ''; await refresh()
    }, true)
  }
  async function assign (task: Task) {
    selected.value = task; assigneeId.value = null; dueOn.value = task.dueOn; note.value = ''; await action(async () => {
      assignees.value = (await useApi().get('/api/reports/assignees', { params: { taskId: task.id } })).data.items; assignmentDialog.value = true
    })
  }
  async function reassign () {
    await action(async () => {
      await useApi().put('/api/followups/' + selected.value?.id + '/assignment', { expectedVersion: selected.value?.version, assigneeId: assigneeId.value, dueOn: dueOn.value, reason: note.value }); assignmentDialog.value = false; note.value = ''; await refresh()
    }, true)
  }
  onMounted(load)
</script>
