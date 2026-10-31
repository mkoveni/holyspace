<template>
  <div>
    <PageHeader eyebrow="Church operations" :subtitle="definition.subtitle" :title="definition.title" />
    <v-alert v-if="loadError" class="mb-4" type="error" variant="tonal">{{ loadError }} <v-btn @click="load">Retry</v-btn></v-alert>
    <v-progress-linear v-if="loading" color="primary" indeterminate />

    <v-card v-if="definition.filters?.length" class="mb-4">
      <v-card-text>
        <v-row>
          <v-col v-for="field in definition.filters" :key="field.key" cols="12" sm="4">
            <PeopleSearch
              v-if="field.options === 'people'"
              v-model="filters[field.key]"
              clearable
              :label="field.label"
            />

            <v-select
              v-else-if="field.type === 'select'"
              v-model="filters[field.key]"
              clearable
              :items="options(field.options)"
              :label="field.label"
            />

            <v-text-field
              v-else
              v-model="filters[field.key]"
              clearable
              :label="field.label"
              :type="inputType(field)"
            />
          </v-col>
        </v-row>

        <v-btn :loading="loading" variant="tonal" @click="load">Apply filters</v-btn>
      </v-card-text>
    </v-card>

    <v-card v-if="definition.occurrencePlanning" class="mb-4">
      <v-card-text>
        <v-autocomplete
          v-model="planningOccurrenceId"
          clearable
          :disabled="dialog"
          hint="Choose once to plan shifts and assignments for this event. Clear to see all events."
          :items="options('occurrences')"
          label="Plan for event occurrence"
          persistent-hint
        />
      </v-card-text>
    </v-card>

    <v-tabs v-model="tab" class="mb-4" color="primary">
      <v-tab v-for="section in definition.sections" :key="section.key" :value="section.key">{{ section.title }}</v-tab>
    </v-tabs>

    <v-card v-for="section in activeSections" :key="section.key">
      <v-card-title class="d-flex align-center justify-space-between">
        {{ section.title }}
        <v-btn
          v-if="section.createPath"
          color="primary"
          prepend-icon="mdi-plus"
          variant="flat"
          @click="open(section)"
        >Add {{ section.createLabel ?? singular(section.title) }}</v-btn>
      </v-card-title>

      <v-data-table :headers="headers(section)" :items="records(section.key)" :loading="loading">
        <template #item="{ item }">
          <tr>
            <td v-for="column in headers(section).filter(header => header.key !== 'actions')" :key="column.key">{{ display(item[column.key], section.fields.find(field => field.key === column.key) ?? { key: column.key, label: column.title }, item) }}</td>

            <td>
              <v-btn size="small" variant="text" @click="inspect(item)">Details</v-btn>

              <v-btn
                v-if="section.editPath"
                :disabled="store.isSaving"
                size="small"
                variant="text"
                @click="open(section, item)"
              >Edit</v-btn>

              <template v-for="action in section.actions ?? []" :key="action.label">
                <v-btn
                  v-if="!action.available || action.available(item)"
                  :disabled="store.isSaving"
                  size="small"
                  variant="text"
                  @click="act(action, item)"
                >{{ action.label }}</v-btn>
              </template>
            </td>
          </tr>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog max-width="780" :model-value="!!inspected" @update:model-value="inspected = null">
      <v-card v-if="inspected">
        <v-card-title>Record details</v-card-title>

        <v-card-text>
          <v-table><tbody><tr v-for="(value, key) in inspected" v-show="!['id', 'version', 'idempotencyKey'].includes(String(key))" :key="key">
            <th>{{ fieldLabel(String(key)) }}</th><td class="py-2">{{ display(value, { key: String(key), label: fieldLabel(String(key)) }, inspected) }}</td>
          </tr></tbody></v-table>

          <template v-if="Number.isInteger(inspected.version)">
            <h3 class="text-subtitle-1 mt-5 mb-2">Change history</h3>
            <v-progress-linear v-if="auditLoading" indeterminate />
            <v-alert v-if="auditError" class="my-2" type="error">{{ auditError }} <v-btn @click="loadAudit">Retry</v-btn></v-alert>

            <v-table><thead><tr><th>When</th><th>Account</th><th>Action</th><th>Version</th></tr></thead>

              <tbody><template v-for="entry in auditEntries" :key="entry.id">
                <tr><td>{{ new Date(entry.occurredAt).toLocaleString() }}</td><td>{{ entry.actorName ?? entry.actorId ?? 'System operation' }}</td><td>{{ entry.action }}</td><td>{{ entry.version ?? '—' }}</td></tr>

                <tr v-if="entry.changes"><td colspan="4">
                  <v-table density="compact"><thead><tr><th>Field</th><th>Previous value</th><th>New value</th></tr></thead>
                    <tbody><tr v-for="(change, path) in entry.changes" :key="path"><th>{{ String(path).replace(/^\/payload\//, '').split('/').filter(Boolean).map(fieldLabel).join(' / ') }}</th><td>{{ auditValue(change.from, change.fromPresent) }}</td><td>{{ auditValue(change.to, change.toPresent) }}</td></tr></tbody>
                  </v-table>
                </td></tr>

                <tr v-else><td class="text-caption" colspan="4">Legacy history: field values were not recorded.</td></tr>
              </template></tbody>
            </v-table>

            <p v-if="!auditLoading && !auditError && auditEntries.length === 0" class="text-caption my-2">No changes recorded since the audit journal was enabled.</p>

            <v-btn
              v-if="auditNext !== null"
              class="mt-2"
              :loading="auditLoading"
              variant="text"
              @click="loadAudit"
            >Earlier changes</v-btn>
          </template>
        </v-card-text>

        <v-card-actions><v-spacer /><v-btn @click="inspected = null">Close</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="dialog" max-width="780" persistent>
      <v-card v-if="selectedSection">
        <v-card-title>{{ editing ? 'Edit' : 'Add' }} {{ singular(selectedSection.title) }}</v-card-title>

        <v-form ref="formRef" @submit.prevent="submit()">
          <v-card-text>
            <v-alert v-if="formError" class="mb-4" type="error" variant="tonal">{{ formError }}</v-alert>
            <p class="text-caption mb-3">Date and time inputs use your browser timezone ({{ browserTimezone }}).</p>

            <template v-for="field in selectedSection.fields" :key="field.key">
              <span v-if="field.type === 'hidden'" />
              <v-switch v-else-if="field.type === 'boolean'" v-model="form[field.key]" color="primary" :label="field.label" />

              <v-textarea
                v-else-if="field.type === 'textarea'"
                v-model="form[field.key]"
                :label="field.label"
                rows="2"
                :rules="rules(field)"
              />

              <PeopleSearch
                v-else-if="field.options === 'people'"
                v-model="form[field.key]"
                :clearable="!field.required"
                :disabled="!!editing && field.readonlyOnEdit"
                :label="field.label"
                :multiple="field.type === 'multi'"
                :rules="rules(field)"
              />

              <v-select
                v-else-if="field.type === 'select' || field.type === 'multi'"
                v-model="form[field.key]"
                :clearable="!field.required"
                :disabled="(!!editing && field.readonlyOnEdit) || (field.key === 'occurrenceId' && !!planningOccurrenceId)"
                :items="formOptions(field)"
                :label="field.label"
                :multiple="field.type === 'multi'"
                :rules="rules(field)"
              />

              <v-combobox
                v-else-if="field.type === 'tags'"
                v-model="form[field.key]"
                chips
                :label="field.label"
                multiple
              />

              <div v-else-if="field.type === 'intervals'" class="mb-4">
                <div class="text-subtitle-2 mb-2">{{ field.label }}</div>

                <v-row v-for="(window, index) in form[field.key]" :key="index" align="center">
                  <v-col cols="5"><v-text-field v-model="window.startsAt" label="From" :rules="[required]" type="datetime-local" /></v-col>
                  <v-col cols="5"><v-text-field v-model="window.endsAt" label="Until" :rules="[required, value => value > window.startsAt || 'End must be after start.']" type="datetime-local" /></v-col>
                  <v-col cols="2"><v-btn aria-label="Remove availability" icon="mdi-close" variant="text" @click="form[field.key].splice(index, 1)" /></v-col>
                </v-row>

                <v-btn size="small" variant="tonal" @click="form[field.key].push({ startsAt: '', endsAt: '' })">Add availability window</v-btn>
              </div>

              <div v-else-if="field.type === 'allocations'" class="mb-4">
                <div class="text-subtitle-2">Fund allocations</div>

                <v-row v-for="(allocation, index) in form[field.key]" :key="index">
                  <v-col cols="6"><v-select v-model="allocation.fundId" :items="options('giving.funds')" label="Fund" :rules="[required]" /></v-col>

                  <v-col cols="4"><v-text-field
                    v-model="allocation.amount"
                    inputmode="decimal"
                    label="Amount"
                    :rules="[required]"
                    type="text"
                  /></v-col>

                  <v-col cols="2"><v-btn aria-label="Remove allocation" icon="mdi-close" variant="text" @click="form[field.key].splice(index, 1)" /></v-col>
                </v-row>

                <v-btn size="small" variant="tonal" @click="form[field.key].push({ fundId: '', amount: '' })">Add allocation</v-btn>
              </div>

              <v-text-field
                v-else
                v-model="form[field.key]"
                :disabled="!!editing && field.readonlyOnEdit"
                :label="field.label"
                :rules="rules(field)"
                :type="inputType(field)"
              />
            </template>
          </v-card-text>

          <v-card-actions><v-spacer /><v-btn v-if="definition.occurrencePlanning && !editing && ['shifts', 'assignments'].includes(selectedSection.key)" :disabled="store.isSaving" @click="submit(true)">Save and add another</v-btn><v-btn :disabled="store.isSaving" @click="dialog = false">Close</v-btn><v-btn color="primary" :loading="store.isSaving" type="submit" variant="flat">Save</v-btn></v-card-actions>
        </v-form>
      </v-card>
    </v-dialog>
  </div>
</template>
<script setup lang="ts">
  import type { OperationalRecord, WorkflowAction, WorkflowAuditEntry, WorkflowDefinition, WorkflowField, WorkflowSection } from '@/types/workflow'
  import { computed, onMounted, reactive, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import { useEventsStore } from '@/stores/events'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import { useWorkflowsStore } from '@/stores/workflows'
  import { copyFormValue, fieldPayload, localInput } from '@/workflows/forms'
  import { moneyLabel } from '@/workflows/money'
  import { planningRecords } from '@/workflows/volunteerPlanning'

  const props = defineProps<{ definition: WorkflowDefinition }>()
  const store = useWorkflowsStore()
  const people = usePeopleStore()
  const events = useEventsStore()
  const ui = useUiStore()
  const planningOccurrenceId = ref<string | null>(null)
  const tab = ref(props.definition.sections[0]?.key)
  function auditValue (value: unknown, present: boolean): string {
    if (!present) return 'Not set'
    if (value === '[redacted]') return 'Hidden'
    if (value === null) return 'Empty'
    return typeof value === 'object' ? JSON.stringify(value) : String(value)
  }
  const auditEntries = ref<WorkflowAuditEntry[]>([])
  const auditNext = ref<number | null>(null)
  const auditLoading = ref(false)
  const auditError = ref('')
  const inspected = ref<OperationalRecord | null>(null)
  const dialog = ref(false)
  const loading = ref(false)
  const loadError = ref('')
  const formError = ref('')
  const selectedSection = ref<WorkflowSection | null>(null)
  const editing = ref<OperationalRecord | null>(null)
  const formRef = ref()
  // Form values are driven by the typed field schema and normalised before submission.

  const form = reactive<Record<string, any>>({})
  const filters = reactive<Record<string, unknown>>({})
  const filterParams = () => Object.fromEntries(Object.entries(filters).filter(([, value]) => Boolean(value)))
  const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone
  const activeSections = computed(() => props.definition.sections.filter(section => section.key === tab.value))
  const required = (value: unknown) => (Array.isArray(value) ? value.length > 0 : value !== null && value !== undefined && String(value).trim() !== '') || 'This field is required.'
  function rules (field: WorkflowField) {
    return field.required || (editing.value && field.requiredOnEdit) ? [required] : []
  }
  function inputType (field: WorkflowField) {
    return field.type === 'datetime' ? 'datetime-local' : (['date', 'number'].includes(field.type ?? '') ? field.type : 'text')
  }
  function singular (title: string) {
    return title.endsWith('s') ? title.slice(0, -1) : title
  }
  function records (key: string) {
    const data = store.data[props.definition.context] ?? {}
    return planningRecords(key, data[key] ?? [], data.shifts ?? [], planningOccurrenceId.value)
  }
  function headers (section: WorkflowSection) {
    return [...(section.columns ?? section.fields.filter(field => !['hidden', 'intervals', 'allocations', 'tags', 'textarea'].includes(field.type ?? '')).map(field => ({ title: field.label, key: field.key }))), { title: 'Actions', key: 'actions', sortable: false }]
  }
  function options (source?: WorkflowField['options']): { title: string, value: string }[] {
    if (Array.isArray(source)) return source
    if (source === 'people') return people.people.map(person => ({ title: `${person.firstName} ${person.lastName}`, value: person.id }))
    if (source === 'occurrences') return events.events.flatMap(event => event.occurrences.map(o => ({ title: `${event.title} · ${new Date(o.startsAt).toLocaleString()} · ${o.status}`, value: o.id })))
    if (!source) return []
    const [context, kind] = source.split('.')
    return (store.data[context ?? '']?.[kind ?? ''] ?? []).map(record => {
      const personId = record.personId ?? record.childId ?? record.donorId
      let title = record.name ?? record.title ?? record.receiptNumber ?? (personId ? options('people').find(p => p.value === personId)?.title : null)
      if (record.roleId) title = `${options('volunteering.roles').find(role => role.value === record.roleId)?.title ?? 'Shift'} · ${new Date(String(record.startsAt)).toLocaleString()}`
      if (record.classId && record.occurrenceId) title = `${options('children.classes').find(item => item.value === record.classId)?.title ?? 'Session'} · ${options('occurrences').find(item => item.value === record.occurrenceId)?.title ?? ''}`
      if (kind === 'checkins') title = `${title ?? 'Child'} · ${record.checkedOutAt ? 'Collected' : 'Open check-in'}`
      if (kind === 'gifts') title = `${title ?? 'Anonymous'} · ${moneyLabel(Number(record.amountMinor), String(record.currency))} · ${record.receivedOn}`
      return { title: String(title ?? record.id), value: record.id }
    })
  }
  function formOptions (field: WorkflowField) {
    const choices = options(field.options)
    if (field.options !== 'volunteering.shifts' || !planningOccurrenceId.value) return choices
    const ids = new Set(records('shifts').map(shift => shift.id))
    return choices.filter(choice => ids.has(choice.value))
  }
  function inspect (record: OperationalRecord) {
    inspected.value = record
    auditEntries.value = []
    auditNext.value = null
    auditError.value = ''
    if (Number.isInteger(record.version)) loadAudit()
  }
  async function loadAudit () {
    const id = inspected.value?.id
    if (!id) return
    auditLoading.value = true
    auditError.value = ''
    try {
      const page = await store.audit(props.definition.context, id, auditNext.value)
      if (inspected.value?.id === id) {
        auditEntries.value.push(...page.entries)
        auditNext.value = page.nextBefore
      }
    } catch (error) {
      if (inspected.value?.id === id) auditError.value = error instanceof Error ? error.message : 'Unable to load change history.'
    } finally {
      if (inspected.value?.id === id) auditLoading.value = false
    }
  }
  function fieldLabel (key: string): string {
    return props.definition.sections.flatMap(section => section.fields).find(field => field.key === key)?.label ?? key.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/Id(s)?$/, '').replace(/^./, character => character.toUpperCase())
  }
  function display (value: unknown, field?: WorkflowField, record?: OperationalRecord): string {
    if (!value && value !== 0 && value !== false) return '—'
    if (field?.key.endsWith('Minor') && typeof value === 'number' && record?.currency) return moneyLabel(value, String(record.currency))
    if (field?.options) {
      const choices = options(field.options)
      return (Array.isArray(value) ? value : [value]).map(v => choices.find(choice => choice.value === v)?.title ?? String(v)).join(', ')
    }
    if (typeof value === 'boolean') return value ? 'Yes' : 'No'
    if (field?.type === 'datetime') return new Date(String(value)).toLocaleString()
    if (typeof value === 'string') {
      if (/^\d{4}-\d{2}-\d{2}T/.test(value)) return new Date(value).toLocaleString()
      const person = options('people').find(option => option.value === value)
      if (person) return person.title
      const occurrence = options('occurrences').find(option => option.value === value)
      if (occurrence) return occurrence.title
      for (const context of Object.values(store.data)) for (const items of Object.values(context)) {
        const record = items.find(item => item.id === value)
        if (record?.name) return String(record.name)
      }
    }
    if (Array.isArray(value)) return value.map(v => display(v, undefined, record)).join('; ')
    if (typeof value === 'object') return Object.entries(value).map(([key, entry]) => `${fieldLabel(key)}: ${display(entry, { key, label: fieldLabel(key) }, record)}`).join(' · ')
    return String(value)
  }
  async function load () {
    loading.value = true
    loadError.value = ''
    try {
      await Promise.all([store.load(props.definition.context, filterParams()), people.fetchAll(), events.fetchAll(), ...(props.definition.dependencies ?? []).map(context => store.load(context))])
    } catch (error) {
      loadError.value = error instanceof Error ? error.message : 'Unable to load data.'
    } finally {
      loading.value = false
    }
  }
  function open (section: WorkflowSection, record?: OperationalRecord) {
    selectedSection.value = section
    editing.value = record ?? null
    formError.value = ''
    for (const key of Object.keys(form)) delete form[key]
    for (const field of section.fields) {
      const value = record?.[field.key] ?? (typeof field.default === 'function' ? field.default() : field.default) ?? (['multi', 'tags', 'intervals', 'allocations'].includes(field.type ?? '') ? [] : (field.type === 'boolean' ? false : ''))
      form[field.key] = field.type === 'datetime' ? localInput(String(value)) : (field.type === 'intervals' ? (value as { startsAt: string, endsAt: string }[]).map(window => ({ startsAt: localInput(window.startsAt), endsAt: localInput(window.endsAt) })) : copyFormValue(value))
    }
    if (!record && planningOccurrenceId.value) {
      const occurrence = events.events.flatMap(event => event.occurrences).find(item => item.id === planningOccurrenceId.value)
      if (section.key === 'shifts' && occurrence) {
        form.occurrenceId = occurrence.id
        form.startsAt = localInput(occurrence.startsAt)
        form.endsAt = localInput(occurrence.endsAt)
      }
      if (section.key === 'assignments') {
        const shifts = formOptions({ key: 'shiftId', label: 'Shift', options: 'volunteering.shifts' })
        if (shifts.length === 1) form.shiftId = shifts[0]!.value
      }
    }
    formRef.value?.resetValidation()
    dialog.value = true
  }
  async function submit (addAnother = false) {
    if (!selectedSection.value || store.isSaving || !(await formRef.value.validate()).valid) return
    formError.value = ''
    try {
      const section = selectedSection.value
      const path = editing.value ? section.editPath?.(editing.value) : section.createPath
      if (!path) return
      await store.save(path, fieldPayload(section.fields, form), editing.value ? 'PUT' : 'POST', editing.value ? Number(editing.value.version) : undefined)
      const previousShiftId = form.shiftId
      dialog.value = false
      ui.notifySuccess('Saved.')
      await store.load(props.definition.context, filterParams())
      if (addAnother) {
        open(section)
        if (section.key === 'assignments') form.shiftId = previousShiftId
      }
    } catch (error) {
      formError.value = error instanceof Error ? error.message : 'Unable to save.'
    }
  }
  async function act (action: WorkflowAction, record: OperationalRecord) {
    if (store.isSaving) return
    try {
      await store.save(action.path(record), action.payload(record), 'POST', Number.isInteger(record.version) ? Number(record.version) : undefined)
      await store.load(props.definition.context, filterParams())
      ui.notifySuccess('Updated.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to update.')
    }
  }
  onMounted(load)
</script>
