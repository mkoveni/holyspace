<template>
  <div>
    <div class="station-controls">
      <PageHeader subtitle="Only sessions assigned to your linked staff account appear here" title="Children’s check-in station" />
      <v-alert v-if="error" class="mb-4" type="error">{{ error }}<v-btn variant="text" @click="loadSessions">Reload station</v-btn></v-alert>
      <v-select v-model="sessionId" :items="sessions.map(s => ({title:s.title + ' · ' + (s.occurrence?.startsAt ?? '') + ' · ' + s.status,value:s.id}))" label="Assigned session" @update:model-value="loadRoster" />
      <v-alert v-if="roster" class="mb-4" type="info">{{ roster.className }} · {{ roster.session.status }}. Check the collecting adult’s identity and current authorisation before handover.</v-alert>

      <v-text-field
        v-if="roster"
        v-model="search"
        clearable
        label="Find enrolled child"
        prepend-inner-icon="mdi-magnify"
      />

      <v-list v-if="roster">
        <v-list-item v-for="child in filteredChildren" :key="child.id" :subtitle="child.checkIn ? 'Checked in' : 'Enrolled'" :title="child.name">
          <template #append>
            <v-btn v-if="!child.checkIn" :disabled="busy || roster.session.status !== 'scheduled'" @click="arrive(child)">Check in</v-btn>
            <template v-else><v-btn :disabled="busy" @click="reprint(child)">Labels</v-btn><v-btn :disabled="busy" @click="openCollection(child)">Collect</v-btn></template>
            <v-btn v-if="auth.hasPermission('children.support')" :disabled="busy" @click="openNote(child)">Support note</v-btn>
          </template>
        </v-list-item>
      </v-list>
    </div>

    <v-card v-if="labels" class="my-5 pa-4 label-sheet">
      <div class="station-controls mb-3"><v-btn @click="print">Print paired labels</v-btn><v-btn @click="labels = null">Clear labels</v-btn><p>Print both labels. Give the pickup copy to the authorised adult. Never include support notes on a label.</p></div>

      <div class="label-pair">
        <section v-for="copy in ['Child copy', 'Pickup copy']" :key="copy" class="child-label"><small>{{ copy }}</small><h2>{{ labels.childName }}</h2><p>{{ labels.className }} · {{ labels.occurrence?.title }}</p><p>{{ labels.occurrence?.startsAt }}</p><strong class="pickup-code">{{ labels.code }}</strong><p>Check-in {{ labels.checkInId.slice(-8) }}</p></section>
      </div>
    </v-card>

    <v-dialog v-model="collectionOpen" max-width="550" persistent><v-card title="Verify collection"><v-card-text>
                                                                                                      <p>{{ selected?.name }}</p><v-alert v-if="dialogError" class="my-3" type="error">{{ dialogError }}</v-alert>
                                                                                                      <v-select v-model="collectorId" :items="selected?.collectors.map(c => ({title:c.name,value:c.id})) ?? []" label="Currently authorised adult" />
                                                                                                      <v-text-field v-model="pickupCode" autocomplete="off" label="Code on pickup label" maxlength="20" />
                                                                                                      <v-checkbox v-model="identityVerified" label="I checked this adult’s identity before handover" />
                                                                                                    </v-card-text>

      <v-card-actions><v-btn :disabled="busy" @click="collectionOpen = false">Close</v-btn><v-btn :disabled="!collectorId || !pickupCode || !identityVerified" :loading="busy" @click="collect">Confirm handover</v-btn></v-card-actions></v-card></v-dialog>

    <v-dialog v-model="labelOpen" max-width="550" persistent><v-card title="Paired labels"><v-card-text><v-alert v-if="dialogError" type="error">{{ dialogError }}</v-alert><p>Reprint keeps the same code. Replace lost labels changes the code and invalidates previous copies.</p><v-textarea v-model="replacementReason" label="Reason for replacing lost labels" maxlength="500" /></v-card-text><v-card-actions><v-btn :disabled="busy" @click="labelOpen = false">Close</v-btn><v-btn :loading="busy" @click="issueLabels(false)">Reprint labels</v-btn><v-btn :disabled="!replacementReason.trim()" :loading="busy" @click="issueLabels(true)">Replace lost labels</v-btn></v-card-actions></v-card></v-dialog>
    <v-dialog v-model="noteOpen" max-width="600" persistent><v-card title="Private support note"><v-card-text><v-alert v-if="dialogError" type="error">{{ dialogError }}</v-alert><p>{{ selected?.name }}. Record only actionable care instructions. These notes are never printed.</p><v-textarea v-model="note" label="Support instructions" maxlength="2000" :readonly="!auth.hasPermission('children.manage')" /></v-card-text><v-card-actions><v-btn :disabled="busy" @click="noteOpen = false; note = ''">Close</v-btn><v-btn v-if="auth.hasPermission('children.manage')" :loading="busy" @click="saveNote">Save note</v-btn></v-card-actions></v-card></v-dialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  interface Occurrence { title: string, startsAt: string }
  interface Session { id: string, title: string, status: string, occurrence: Occurrence | null }
  interface Child { id: string, name: string, checkIn: { id: string, version: number } | null, collectors: { id: string, name: string }[] }
  interface Roster { session: { status: string }, className: string, items: Child[] }
  interface Labels { checkInId: string, childName: string, className: string, code: string, labelVersion: number, occurrence: Occurrence | null }
  const auth = useAuthStore()
  const sessions = ref<Session[]>([]), sessionId = ref<string | null>(null), roster = ref<Roster | null>(null), labels = ref<Labels | null>(null)
  const search = ref(''), busy = ref(false), error = ref(''), dialogError = ref('')
  const selected = ref<Child | null>(null), collectionOpen = ref(false), labelOpen = ref(false), noteOpen = ref(false)
  const collectorId = ref<string | null>(null), pickupCode = ref(''), identityVerified = ref(false), labelVersion = ref(0), replacementReason = ref(''), note = ref(''), noteVersion = ref(0)
  const filteredChildren = computed(() => roster.value?.items.filter(c => c.name.toLowerCase().includes((search.value ?? '').toLowerCase())) ?? [])
  async function loadSessions () {
    roster.value = null; labels.value = null; note.value = ''; sessions.value = []; sessionId.value = null; error.value = ''
    try {
      sessions.value = (await useApi().get<{ items: Session[] }>('/api/children-station')).data.items
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to load your sessions.')
    }
  }
  async function loadRoster () {
    roster.value = null; labels.value = null; note.value = ''; search.value = ''; error.value = ''
    if (!sessionId.value) return
    try {
      roster.value = (await useApi().get<Roster>('/api/children-station/sessions/' + sessionId.value)).data
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to load session.')
    }
  }
  async function action (operation: () => Promise<void>, dialog = false) {
    if (busy.value) return; busy.value = true; error.value = ''; dialogError.value = ''
    try {
      await operation()
    } catch (error_) {
      if (dialog) dialogError.value = extractErrorMessage(error_, 'Reload and check the current details.'); else error.value = extractErrorMessage(error_, 'Unable to complete action.')
    } finally {
      busy.value = false
    }
  }
  async function arrive (child: Child) {
    await action(async () => {
      const result = (await useApi().post<Labels>('/api/children-station/sessions/' + sessionId.value + '/arrivals', { childId: child.id })).data; await loadRoster(); labels.value = result
    })
  }
  function openCollection (child: Child) {
    selected.value = child; collectorId.value = null; pickupCode.value = ''; identityVerified.value = false; dialogError.value = ''; labels.value = null; collectionOpen.value = true
  }
  async function collect () {
    await action(async () => {
      await useApi().post('/api/children-station/checkins/' + selected.value?.checkIn?.id + '/collect', { collectorId: collectorId.value, pickupCode: pickupCode.value, identityVerified: identityVerified.value, expectedVersion: selected.value?.checkIn?.version }); collectionOpen.value = false; await loadRoster()
    }, true)
  }
  async function reprint (child: Child) {
    selected.value = child; replacementReason.value = ''; labels.value = null; await action(async () => {
      labelVersion.value = (await useApi().get<{ version: number }>('/api/children-station/checkins/' + child.checkIn?.id + '/label')).data.version; labelOpen.value = true
    })
  }
  async function issueLabels (replace: boolean) {
    await action(async () => {
      labels.value = (await useApi().post<Labels>('/api/children-station/checkins/' + selected.value?.checkIn?.id + '/label', { expectedVersion: labelVersion.value, replacementReason: replace ? replacementReason.value : '' })).data; labelOpen.value = false
    }, true)
  }
  async function openNote (child: Child) {
    selected.value = child; note.value = ''; await action(async () => {
      const result = (await useApi().get<{ note: string, version: number }>('/api/children-station/sessions/' + sessionId.value + '/children/' + child.id + '/support')).data; note.value = result.note; noteVersion.value = result.version; noteOpen.value = true
    })
  }
  async function saveNote () {
    await action(async () => {
      await useApi().put('/api/children-station/sessions/' + sessionId.value + '/children/' + selected.value?.id + '/support', { note: note.value, expectedVersion: noteVersion.value }); noteOpen.value = false; note.value = ''
    }, true)
  }
  function print () {
    window.print()
  }
  onMounted(loadSessions)
</script>
<style>
.label-pair {display:flex;flex-wrap:wrap;gap:12mm}
.child-label {border:1px dashed #555;padding:5mm;width:85mm;min-height:45mm;break-inside:avoid}
.pickup-code {font-family:monospace;font-size:24px;letter-spacing:2px}
@media print {.station-controls,.v-navigation-drawer,.v-app-bar,.v-overlay-container {display:none!important}.v-main{padding:0!important}.label-sheet{box-shadow:none!important;border:0!important}}
</style>
