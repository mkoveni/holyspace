<template>
  <div>
    <PageHeader subtitle="Invite eligible volunteers and prepare reminder drafts" title="Volunteer invitations"><template #actions><v-btn to="/volunteering">Volunteer setup</v-btn><v-btn to="/service-plans">Service plans</v-btn></template></PageHeader>
    <v-alert v-if="error" class="mb-4" type="error">{{ error }}<v-btn variant="text" @click="load">Reload</v-btn></v-alert>

    <v-card class="mb-4"><v-card-text><v-form ref="form" @submit.prevent="invite">
      <v-select v-model="shiftId" :items="shifts" label="Shift to invite for" :rules="[required]" />

      <PeopleSearch
        v-if="access.hasPermission('people.read')"
        v-model="personId"
        :disabled="busy"
        label="Volunteer"
        :rules="[required]"
      />

      <p v-else>People read permission is required to search volunteers.</p>

      <v-text-field
        v-model="expires"
        hint="In your browser timezone; must be no later than shift start"
        label="Invitation expires"
        persistent-hint
        :rules="[required]"
        type="datetime-local"
      />

      <v-btn color="primary" :disabled="!access.hasPermission('people.read')" :loading="busy" type="submit">Create invitation</v-btn>
    </v-form></v-card-text></v-card>

    <v-card><v-card-title>Invitations and responses</v-card-title><v-data-table :headers="headers" :items="data?.invitations ?? []"><template #item.personId="{item}">{{ item.personName }}</template></v-data-table></v-card>

    <v-card class="mt-4"><v-card-title>Assignment reminders</v-card-title><v-card-text><p>These actions create draft campaigns. Review and queue them in Communications; no message is sent by preparing a draft.</p></v-card-text>
      <v-data-table :headers="assignmentHeaders" :items="data?.assignments ?? []"><template #item.personId="{item}">{{ item.personName }}</template><template #item.actions="{item}"><v-btn v-if="access.hasPermission('communications.manage') && ['assigned','invited'].includes(item.status)" :disabled="busy" @click="reminder(item.id)">Prepare email reminder</v-btn></template></v-data-table>
      <v-card-text v-if="prepared">Draft campaign prepared: {{ prepared }}. <router-link to="/communications">Review in Communications</router-link>.</v-card-text>
    </v-card>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  interface Rota { roles: { id: string, name: string }[], shifts: { id: string, roleId: string, startsAt: string, vacancies: number, status: string }[], assignments: { id: string, personId: string, personName: string, status: string }[], invitations: { id: string, personId: string, personName: string, status: string, expiresAt: string, startsAt: string }[] }
  const access = useAuthStore(); const data = ref<Rota | null>(null); const error = ref(''); const busy = ref(false)
  const shiftId = ref<string | null>(null); const personId = ref<string | string[] | null>(null); const expires = ref(''); const form = ref(); const prepared = ref('')
  const shifts = computed(() => data.value?.shifts.filter(s => s.status === 'open').map(s => ({ title: `${data.value?.roles.find(r => r.id === s.roleId)?.name} · ${s.startsAt} · ${s.vacancies} places`, value: s.id })) ?? [])
  const headers = [{ title: 'Volunteer', key: 'personId' }, { title: 'Starts', key: 'startsAt' }, { title: 'Expires', key: 'expiresAt' }, { title: 'Response', key: 'status' }]
  const assignmentHeaders = [{ title: 'Volunteer', key: 'personId' }, { title: 'Status', key: 'status' }, { title: '', key: 'actions', sortable: false }]
  const required = (v: unknown) => typeof v === 'string' && Boolean(v.trim()) || 'This field is required.'
  async function load () {
    try {
      data.value = (await useApi().get<Rota>('/api/volunteer-rota')).data
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to load invitations.')
    }
  }
  async function invite () {
    if (busy.value || !(await form.value.validate()).valid) return; busy.value = true; error.value = ''; try {
      await useApi().post('/api/volunteer-rota/invitations', { shiftId: shiftId.value, personId: personId.value, expiresAt: new Date(expires.value).toISOString() }); personId.value = null; await load()
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to invite volunteer.')
    } finally {
      busy.value = false
    }
  }
  async function reminder (id: string) {
    busy.value = true; error.value = ''; try {
      prepared.value = (await useApi().post<{ campaignId: string }>(`/api/volunteer-rota/assignments/${id}/reminder`, { channel: 'email', reference: crypto.randomUUID() })).data.campaignId
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to prepare reminder.')
    } finally {
      busy.value = false
    }
  }
  onMounted(load)
</script>
