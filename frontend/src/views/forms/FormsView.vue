<template>
  <div>
    <PageHeader title="Forms" eyebrow="Connect with your church" subtitle="Publish sign-up forms and review every response">
      <template #actions><v-btn v-if="manage" color="primary" @click="edit(null)">Create form</v-btn></template>
    </PageHeader>
    <v-alert v-if="error" class="mb-4" type="error" variant="tonal">{{ error }} <v-btn variant="text" @click="loadForms()">Reload</v-btn></v-alert>
    <v-progress-linear v-if="loading" indeterminate />
    <v-card><v-data-table :headers="headers" :items="forms" :items-per-page="-1" hide-default-footer>
      <template #item.published="{ item }"><v-chip :color="item.published ? 'success' : undefined" size="small">{{ item.published ? 'Published' : 'Draft' }}</v-chip></template>
      <template #item.actions="{ item }">
        <v-btn v-if="item.published" :href="publicUrl(item)" target="_blank" rel="noopener noreferrer" variant="text" size="small">Open public form</v-btn>
        <v-btn v-if="manage" variant="text" size="small" :disabled="busy" @click="publication(item)">{{ item.published ? 'Unpublish' : 'Publish' }}</v-btn>
        <v-btn v-if="manage && !item.published" variant="text" size="small" :disabled="busy" @click="edit(item)">Edit</v-btn>
        <v-btn v-if="manage" variant="text" size="small" @click="select(item)">Review responses</v-btn>
      </template>
    </v-data-table><v-card-actions><v-btn v-if="formsCursor" @click="loadForms()">First page</v-btn><v-btn v-if="nextForms" @click="loadForms(nextForms)">Next page</v-btn></v-card-actions></v-card>
    <v-card v-if="selected" class="mt-5">
      <v-card-title>Responses: {{ selected.title }}</v-card-title>
      <v-card-text><PortalImage target="form" :owner-id="selected.id" label="Form banner" :editable="manage" /></v-card-text>
      <v-card-text><v-text-field v-if="selected.published" :model-value="publicUrl(selected)" label="Shareable form link" readonly /><p>Review the answers and identity before approving. Event requests reserve a place only after approval.</p></v-card-text>
      <v-data-table :headers="responseHeaders" :items="responses" :items-per-page="-1" hide-default-footer>
        <template #item.createdAt="{ item }">{{ new Date(item.createdAt).toLocaleString() }}</template>
        <template #item.actions="{ item }"><v-btn variant="text" size="small" @click="review(item)">{{ item.status === 'pending' ? 'Review submission' : 'View response' }}</v-btn></template>
      </v-data-table>
      <v-card-actions><v-btn v-if="responseCursor" @click="loadResponses()">First page</v-btn><v-btn v-if="nextResponses" @click="loadResponses(nextResponses)">Next page</v-btn></v-card-actions>
    </v-card>
    <v-dialog v-model="editor" max-width="850" persistent><v-card :title="editing ? 'Edit form' : 'Create form'">
      <v-card-text><PortalImage v-if="editing" target="form" :owner-id="editing.id" label="Form banner" :editable="manage" /><p v-else class="text-caption text-medium-emphasis">Save the form first, then open Edit to add a banner.</p></v-card-text>
      <v-form ref="editorForm" @submit.prevent="save">
        <v-card-text>
          <v-alert v-if="dialogError" class="mb-3" type="error" variant="tonal">{{ dialogError }}</v-alert>
          <v-text-field v-model="draft.title" label="Form title" maxlength="200" :rules="[required]" />
          <v-textarea v-model="draft.description" label="Introduction" maxlength="2000" />
          <v-select v-if="access.hasPermission('events.read')" v-model="draft.eventId" :items="eventOptions" label="Event booking (optional)" clearable @update:model-value="draft.occurrenceId = null" />
          <v-select v-if="draft.eventId" v-model="draft.occurrenceId" :items="occurrenceOptions" label="Booking occurrence" :rules="[required]" />
          <v-card v-for="(field, index) in draft.fields" :key="index" variant="outlined" class="mb-3"><v-card-text>
            <v-row><v-col cols="12" sm="6"><v-text-field v-model="field.label" :label="`Field ${index + 1} label`" :rules="[required]" maxlength="200" /></v-col><v-col cols="12" sm="6"><v-text-field v-model="field.key" :label="`Field ${index + 1} key`" hint="Unique lowercase name, e.g. email" :rules="[required]" /></v-col></v-row>
            <v-select v-model="field.type" :items="fieldTypes" :label="`Field ${index + 1} type`" @update:model-value="field.options = []" />
            <v-combobox v-if="field.type === 'select'" v-model="field.options" label="Available choices" multiple chips />
            <v-checkbox v-model="field.required" label="Required" />
            <v-btn variant="text" :disabled="draft.fields.length === 1" @click="draft.fields.splice(index, 1)">Remove field</v-btn>
          </v-card-text></v-card>
          <v-btn :disabled="draft.fields.length >= 20" @click="addField">Add field</v-btn>
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn :disabled="busy" @click="editor = false">Close</v-btn><v-btn color="primary" type="submit" :loading="busy">Save form</v-btn></v-card-actions>
      </v-form>
    </v-card></v-dialog>
    <v-dialog :model-value="Boolean(reviewing)" max-width="700" persistent><v-card title="Review submission">
      <v-form ref="reviewForm" @submit.prevent="saveReview">
        <v-card-text v-if="reviewing">
          <v-alert v-if="dialogError" class="mb-3" type="error" variant="tonal">{{ dialogError }}</v-alert>
          <p class="mb-3">{{ reviewing.snapshot.title }} · {{ reviewing.status }}</p>
          <div v-for="field in reviewing.snapshot.fields" :key="field.key" class="mb-3"><strong>{{ field.label }}</strong><p style="white-space: pre-wrap">{{ typeof reviewing.answers[field.key] === 'boolean' ? (reviewing.answers[field.key] ? 'Yes' : 'No') : reviewing.answers[field.key] }}</p></div>
          <template v-if="reviewing.status === 'pending'">
            <v-select v-model="decision" :items="[{title:'Approve', value:'approved'}, {title:'Reject', value:'rejected'}]" label="Review decision" />
            <PeopleSearch v-if="decision === 'approved' && access.hasPermission('people.read')" v-model="personId" label="Link to person" :rules="reviewing.snapshot.eventId ? [required] : []" />
            <v-btn v-if="decision === 'approved' && access.hasPermission('people.manage')" variant="text" @click="newPerson = true">Create person after identity review</v-btn>
            <p class="text-caption my-3">Search existing people first. Creating a person does not approve this response; select them afterward. No automatic matching or merging takes place.</p>
            <v-alert v-if="reviewing.snapshot.eventId && !access.hasPermission('events.manage')" type="warning" variant="tonal">Event management access is required to approve this booking. You can still reject it.</v-alert>
            <v-textarea v-model="note" label="Review note" maxlength="1000" :rules="[required]" />
          </template>
          <template v-else><p>Review note: {{ reviewing.reviewNote }}</p><p v-if="reviewing.personId">Linked person: {{ reviewing.personId }}</p><p v-if="reviewing.registrationId">Event registration: {{ reviewing.registrationId }}</p></template>
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn :disabled="busy" @click="reviewing = null">Close</v-btn><v-btn v-if="reviewing?.status === 'pending'" color="primary" type="submit" :loading="busy">Save review</v-btn></v-card-actions>
      </v-form>
    </v-card></v-dialog>
    <PersonFormDialog v-model="newPerson" @saved="newPerson = false" />
  </div>
</template>
<script setup lang="ts">
import PortalImage from '@/components/common/PortalImage.vue'
  import { computed, onMounted, reactive, ref } from 'vue'
  import type { ChurchForm, FormField, FormSubmission, FormsPage } from '@/types/forms'
  import PageHeader from '@/components/common/PageHeader.vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import PersonFormDialog from '@/views/people/components/PersonFormDialog.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  import { useEventsStore } from '@/stores/events'
  const access = useAuthStore()
  const events = useEventsStore()
  const manage = computed(() => access.hasPermission('forms.manage'))
  const forms = ref<ChurchForm[]>([])
  const nextForms = ref<string | null>(null)
  const formsCursor = ref<string | null>(null)
  const responses = ref<FormSubmission[]>([])
  const nextResponses = ref<string | null>(null)
  const responseCursor = ref<string | null>(null)
  const selected = ref<ChurchForm | null>(null)
  const error = ref('')
  const dialogError = ref('')
  const loading = ref(false)
  const busy = ref(false)
  const editor = ref(false)
  const editing = ref<ChurchForm | null>(null)
  const editorForm = ref()
  const reviewForm = ref()
  const reviewing = ref<FormSubmission | null>(null)
  const decision = ref<'approved' | 'rejected'>('approved')
  const personId = ref<string | string[] | null>(null)
  const note = ref('')
  const newPerson = ref(false)
  const draft = reactive<{title: string, description: string, fields: FormField[], eventId: string | null, occurrenceId: string | null}>({ title: '', description: '', fields: [], eventId: null, occurrenceId: null })
  const fieldTypes = ['text', 'email', 'textarea', 'select', 'checkbox']
  const eventOptions = computed(() => events.events.map(item => ({ title: item.title, value: item.id })))
  const occurrenceOptions = computed(() => events.events.find(item => item.id === draft.eventId)?.occurrences.filter(item => item.status === 'scheduled').map(item => ({ title: `${item.startsAt} · ${item.location}`, value: item.id })) ?? [])
  const required = (value: unknown) => (typeof value === 'string' && Boolean(value.trim())) || 'This field is required.'
  const headers = [{title: 'Form', key: 'title'}, {title: 'Status', key: 'published'}, {title: '', key: 'actions', sortable: false}]
  const responseHeaders = [{title: 'Received', key: 'createdAt'}, {title: 'Status', key: 'status'}, {title: '', key: 'actions', sortable: false}]
  const publicUrl = (form: ChurchForm) => `${location.origin}/forms/public/${form.publicToken}`
  async function loadForms (after: string | null = null) {
    loading.value = true; error.value = ''
    try { const { data } = await useApi().get<FormsPage<ChurchForm>>('/api/forms', { params: after ? {after} : {} }); forms.value = data.items; nextForms.value = data.nextAfter; formsCursor.value = after }
    catch (failure) { error.value = extractErrorMessage(failure, 'Unable to load forms.') }
    finally { loading.value = false }
  }
  async function loadResponses (after: string | null = null) {
    if (!selected.value) return
    const id = selected.value.id
    try { const { data } = await useApi().get<FormsPage<FormSubmission>>(`/api/forms/${id}/submissions`, { params: after ? {after} : {} }); if (selected.value.id !== id) return; responses.value = data.items; nextResponses.value = data.nextAfter; responseCursor.value = after }
    catch (failure) { error.value = extractErrorMessage(failure, 'Unable to load responses.') }
  }
  function select (form: ChurchForm) { selected.value = form; responses.value = []; void loadResponses() }
  function addField () {
    let index = 1
    while (draft.fields.some(field => field.key === `field_${index}`)) ++index
    draft.fields.push({ key: `field_${index}`, label: '', type: 'text', required: false, options: [] })
  }
  async function edit (form: ChurchForm | null) {
    editing.value = form; dialogError.value = ''
    Object.assign(draft, form ? {title: form.title, description: form.description, fields: form.fields.map(field => ({...field, options: [...(field.options ?? [])]})), eventId: form.eventId, occurrenceId: form.occurrenceId} : {title:'', description:'', fields:[], eventId:null, occurrenceId:null})
    if (!form) addField()
    editor.value = true
    if (access.hasPermission('events.read')) try { await events.fetchAll() } catch (failure) { dialogError.value = extractErrorMessage(failure, 'Unable to load events.') }
  }
  async function save () {
    if (busy.value || !(await editorForm.value.validate()).valid) return
    busy.value = true; dialogError.value = ''
    try {
      if (editing.value) await useApi().put(`/api/forms/${editing.value.id}`, {...draft, expectedVersion: editing.value.version})
      else await useApi().post('/api/forms', draft)
      editor.value = false; await loadForms()
    } catch (failure) { dialogError.value = extractErrorMessage(failure, 'Unable to save form.') }
    finally { busy.value = false }
  }
  async function publication (form: ChurchForm) {
    if (busy.value) return
    busy.value = true; error.value = ''
    try { const {data} = await useApi().put<ChurchForm>(`/api/forms/${form.id}/publication`, {published: !form.published, expectedVersion: form.version}); if (selected.value?.id === form.id) selected.value = data; await loadForms() }
    catch (failure) { error.value = extractErrorMessage(failure, 'Unable to change publication.') }
    finally { busy.value = false }
  }
  function review (submission: FormSubmission) { reviewing.value = submission; dialogError.value = ''; decision.value = 'approved'; personId.value = null; note.value = '' }
  async function saveReview () {
    if (!reviewing.value || busy.value || !(await reviewForm.value.validate()).valid) return
    busy.value = true; dialogError.value = ''
    try { await useApi().put(`/api/forms/submissions/${reviewing.value.id}/review`, {status: decision.value, note: note.value, personId: decision.value === 'approved' ? personId.value : null, expectedVersion: reviewing.value.version}); reviewing.value = null; await loadResponses() }
    catch (failure) { dialogError.value = extractErrorMessage(failure, 'Unable to review response. Reload before retrying a conflict.') }
    finally { busy.value = false }
  }
  onMounted(() => { void loadForms() })
</script>
