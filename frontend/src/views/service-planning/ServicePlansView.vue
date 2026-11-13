<template>
  <div>
    <PageHeader class="plan-controls" subtitle="Ordered service items and linked resources" :title="details?.plan.title ?? 'Service planning'"><template #actions><v-btn v-if="id" to="/serve">My schedule</v-btn><v-btn v-if="manage" @click="edit(details?.plan ?? null)">{{ id ? 'Edit order' : 'Create service plan' }}</v-btn><v-btn v-if="details" @click="print">Print order</v-btn></template></PageHeader>
    <v-alert v-if="error" class="mb-4 plan-controls" type="error">{{ error }}<v-btn variant="text" @click="load">Reload</v-btn></v-alert>
    <v-card v-if="!id"><v-data-table :headers="headers" :items="plans"><template #item.title="{item}"><router-link :to="`/service-plans/${item.id}`">{{ item.title }}</router-link></template><template #item.actions="{item}"><v-btn v-if="manage" :disabled="busy" @click="publication(item)">{{ item.status === 'published' ? 'Unpublish' : 'Publish' }}</v-btn><v-btn v-if="manage" :disabled="busy" @click="edit(item)">Edit order</v-btn></template></v-data-table></v-card>

    <v-card v-if="details" class="pa-5"><h2>{{ details.plan.title }}</h2><p>{{ details.occurrence?.title }} · {{ details.occurrence?.startsAt }} · {{ details.occurrence?.location }}</p><p>{{ details.plan.status }} · {{ details.durationMinutes }} minutes</p>
      <v-btn v-if="manage" class="plan-controls my-3" :disabled="busy" @click="publication(details.plan)">{{ details.plan.status === 'published' ? 'Unpublish' : 'Publish' }}</v-btn>
      <ol class="ml-5"><li v-for="(item,index) in details.plan.items" :key="index" class="my-4"><strong>{{ item.title }} · {{ item.durationMinutes }} minutes</strong><p style="white-space:pre-wrap">{{ item.notes }}</p><a v-if="item.resourceUrl" :href="item.resourceUrl" rel="noopener noreferrer" target="_blank">Open resource</a></li></ol>
    </v-card>

    <v-dialog v-model="dialog" max-width="850" persistent><v-card title="Service order"><v-form ref="form" @submit.prevent="save"><v-card-text>
                                                                                                                                    <v-alert v-if="dialogError" class="mb-4" type="error">{{ dialogError }}</v-alert>
                                                                                                                                    <v-text-field v-model="title" label="Plan title" maxlength="200" :rules="[required]" />
                                                                                                                                    <template v-if="!editing"><v-select v-model="eventId" :items="eventOptions" label="Plan event" @update:model-value="occurrenceId = null" /><v-select v-model="occurrenceId" :items="occurrenceOptions" label="Plan occurrence" :rules="[required]" /></template>

                                                                                                                                    <v-card v-for="(item,index) in items" :key="index" class="mb-3" variant="outlined"><v-card-text>
                                                                                                                                      <v-text-field v-model="item.title" :label="`Item ${index+1} title`" maxlength="200" :rules="[required]" />

                                                                                                                                      <v-text-field
                                                                                                                                        v-model.number="item.durationMinutes"
                                                                                                                                        :label="`Item ${index+1} minutes`"
                                                                                                                                        max="180"
                                                                                                                                        min="1"
                                                                                                                                        type="number"
                                                                                                                                      />

                                                                                                                                      <v-textarea v-model="item.notes" label="Notes shared with serving volunteers" maxlength="2000" /><v-text-field v-model="item.resourceUrl" label="Resource link (HTTPS)" maxlength="2000" />
                                                                                                                                      <v-btn :disabled="index === 0" @click="move(index,-1)">Move up</v-btn><v-btn :disabled="index === items.length-1" @click="move(index,1)">Move down</v-btn><v-btn :disabled="items.length === 1" @click="items.splice(index,1)">Remove item</v-btn>
                                                                                                                                    </v-card-text></v-card>

                                                                                                                                    <v-btn :disabled="items.length >= 50" @click="items.push({title:'',durationMinutes:5,notes:'',resourceUrl:''})">Add service item</v-btn>
                                                                                                                                    <p class="mt-3">Saving makes a draft. Publish only after reviewing the order. Resource links are opened externally; no file is fetched or uploaded by this system.</p>
                                                                                                                                  </v-card-text>

      <v-card-actions><v-btn :disabled="busy" @click="dialog = false">Close</v-btn><v-btn color="primary" :loading="busy" type="submit">Save plan</v-btn></v-card-actions></v-form></v-card></v-dialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  import { useEventsStore } from '@/stores/events'
  interface Item { title: string, durationMinutes: number, notes?: string, resourceUrl?: string }
  interface Plan { id: string, occurrenceId: string, title: string, status: string, items: Item[], version: number }
  const props = defineProps<{ id?: string }>(); const access = useAuthStore(); const events = useEventsStore()
  const manage = computed(() => access.hasPermission('service_planning.manage'))
  const plans = ref<Plan[]>([]); const details = ref<{ plan: Plan, occurrence: { title: string, startsAt: string, location: string } | null, durationMinutes: number } | null>(null)
  const error = ref(''); const dialogError = ref(''); const busy = ref(false); const dialog = ref(false); const form = ref()
  const editing = ref<Plan | null>(null); const title = ref(''); const items = ref<Item[]>([]); const eventId = ref<string | null>(null); const occurrenceId = ref<string | null>(null)
  const headers = [{ title: 'Plan', key: 'title' }, { title: 'Status', key: 'status' }, { title: '', key: 'actions', sortable: false }]
  const eventOptions = computed(() => events.events.map(e => ({ title: e.title, value: e.id })))
  const occurrenceOptions = computed(() => events.events.find(e => e.id === eventId.value)?.occurrences.filter(o => o.status === 'scheduled').map(o => ({ title: `${o.startsAt} · ${o.location}`, value: o.id })) ?? [])
  const required = (v: unknown) => typeof v === 'string' && Boolean(v.trim()) || 'This field is required.'
  async function load () {
    error.value = ''; details.value = null; try {
      if (props.id) details.value = (await useApi().get(`/api/service-plans/${props.id}`)).data; else plans.value = (await useApi().get<{ items: Plan[] }>('/api/service-plans')).data.items
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to load service plans.')
    }
  }
  async function edit (plan: Plan | null) {
    editing.value = plan; title.value = plan?.title ?? ''; occurrenceId.value = plan?.occurrenceId ?? null; items.value = plan ? plan.items.map(i => ({ ...i })) : [{ title: '', durationMinutes: 5, notes: '', resourceUrl: '' }]; dialogError.value = ''; dialog.value = true; if (!plan) try {
      await events.fetchAll()
    } catch (error_) {
      dialogError.value = extractErrorMessage(error_, 'Event read access is required to select an occurrence.')
    }
  }
  function move (index: number, offset: number) {
    const item = items.value.splice(index, 1)[0]!; items.value.splice(index + offset, 0, item)
  }
  async function save () {
    if (busy.value || !(await form.value.validate()).valid) return; busy.value = true; dialogError.value = ''; try {
      const payload = { occurrenceId: occurrenceId.value, title: title.value, items: items.value, ...(editing.value ? { expectedVersion: editing.value.version } : {}) }; await (editing.value ? useApi().put(`/api/service-plans/${editing.value.id}`, payload) : useApi().post('/api/service-plans', payload)); dialog.value = false; await load()
    } catch (error_) {
      dialogError.value = extractErrorMessage(error_, 'Unable to save plan.')
    } finally {
      busy.value = false
    }
  }
  async function publication (plan: Plan) {
    if (busy.value) return; busy.value = true; error.value = ''; try {
      await useApi().put(`/api/service-plans/${plan.id}/publication`, { expectedVersion: plan.version, published: plan.status !== 'published' }); await load()
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to publish plan.')
    } finally {
      busy.value = false
    }
  }
  function print () {
    window.print()
  }
  watch(() => props.id, () => {
    dialog.value = false; void load()
  }, { immediate: true })
</script>
<style>@media print { .plan-controls, .v-navigation-drawer, .v-app-bar {display:none!important} .v-main {padding:0!important} }</style>
