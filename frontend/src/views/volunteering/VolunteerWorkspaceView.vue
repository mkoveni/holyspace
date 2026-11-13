<template>
  <div>
    <PageHeader subtitle="Your serving schedule and invitation responses" title="Volunteer workspace" />
    <v-alert v-if="error" class="mb-4" type="error">{{ error }}<v-btn variant="text" @click="load">Reload schedule</v-btn></v-alert>
    <p v-if="items.length === 0">No assignments are available for your linked person record.</p>

    <v-card v-for="item in items" :key="item.assignment.id" class="mb-4"><v-card-title>{{ item.roleName }}</v-card-title>

      <v-card-text>
        <p>{{ item.occurrence?.title }} · {{ item.occurrence?.location }}</p><p>{{ item.startsAt }} to {{ item.endsAt }} · {{ item.assignment.status }}</p>

        <div v-for="invitation in item.invitations" :key="invitation.id" class="mt-3"><p>Invitation: {{ invitation.status }} · Respond by {{ invitation.expiresAt }}</p>
          <template v-if="invitation.status === 'pending'"><v-btn color="primary" :loading="busy" @click="respond(invitation, 'accepted')">Accept invitation</v-btn><v-btn class="ml-2" :disabled="busy" @click="respond(invitation, 'declined')">Decline invitation</v-btn></template>
        </div>

        <v-btn
          v-for="plan in plans.filter(p => p.occurrenceId === item.occurrence?.id)"
          :key="plan.id"
          class="mt-3"
          :to="`/service-plans/${plan.id}`"
          variant="text"
        >View service order</v-btn>
      </v-card-text></v-card>
  </div>
</template>
<script setup lang="ts">
  import { onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  interface Invitation { id: string, status: string, expiresAt: string, version: number }
  interface Schedule { assignment: { id: string, status: string }, roleName: string, occurrence: { id: string, title: string, location: string } | null, startsAt: string, endsAt: string, invitations: Invitation[] }
  const items = ref<Schedule[]>([]); const plans = ref<{ id: string, occurrenceId: string }[]>([]); const error = ref(''); const busy = ref(false)
  async function load () {
    error.value = ''; try {
      items.value = (await useApi().get<{ items: Schedule[] }>('/api/volunteer-workspace')).data.items; plans.value = (await useApi().get<{ items: { id: string, occurrenceId: string }[] }>('/api/service-plans/mine')).data.items
    } catch (error_) {
      items.value = []; plans.value = []; error.value = extractErrorMessage(error_, 'Unable to load your schedule.')
    }
  }
  async function respond (invitation: Invitation, decision: string) {
    if (busy.value) return; busy.value = true; error.value = ''; try {
      await useApi().post(`/api/volunteer-workspace/invitations/${invitation.id}/response`, { decision, expectedVersion: invitation.version }); await load()
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to respond. Reload before trying again.')
    } finally {
      busy.value = false
    }
  }
  onMounted(load)
</script>
