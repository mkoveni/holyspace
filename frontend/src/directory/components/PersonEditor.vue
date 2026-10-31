<template>
  <v-dialog v-model="open" max-width="840" persistent scrollable>
    <v-card class="directory-form">
      <div class="directory-form__header">
        <div>
          <span class="directory-eyebrow">PEOPLE & COMMUNITY</span>
          <h2>{{ person ? 'Edit person' : 'A new connection' }}</h2>

          <p>
            {{
              person
                ? 'Keep their details thoughtful, accurate, and up to date.'
                : 'Start with a name. Build the rest of their story over time.'
            }}
          </p>
        </div>

        <v-btn
          aria-label="Close person form"
          :disabled="task.busy.value"
          icon="mdi-close"
          variant="text"
          @click="open = false"
        />
      </div>

      <v-card-text>
        <TaskAlert :conflict="task.conflict.value" :error="task.error.value" @reload="reload" />

        <v-alert
          v-if="metadataError"
          class="mb-5"
          type="error"
          variant="tonal"
        >{{ metadataError }} <v-btn variant="text" @click="loadMetadata">Retry</v-btn></v-alert>

        <v-form id="person-editor" ref="formRef" @submit.prevent="save">
          <div class="directory-section-heading">
            <span>01</span>

            <div>
              <h3>Personal details</h3>
              <p>The name your community knows them by.</p>
            </div>
          </div>

          <v-row>
            <v-col
              cols="12"
              sm="3"
            ><v-select
              v-model="form.titleCode"
              clearable
              hide-details="auto"
              item-title="label"
              item-value="code"
              :items="catalogue.metadata?.titles || []"
              label="Title"
            /></v-col>

            <v-col
              class="d-flex align-center"
              cols="12"
              sm="9"
            ><v-btn
              prepend-icon="mdi-plus"
              size="small"
              variant="text"
              @click="showTitle = !showTitle"
            >Add a title</v-btn></v-col>
          </v-row>

          <div v-if="showTitle" class="directory-inset mb-5">
            <p class="mb-3">Add a title to the shared catalogue.</p>

            <TaskAlert
              :conflict="titleTask.conflict.value"
              :error="titleTask.error.value"
              @reload="reloadTitles"
            />

            <v-row><v-col cols="5"><v-text-field
                     v-model="title.code"
                     hide-details
                     label="Code"
                     maxlength="20"
                     placeholder="DR"
                   /></v-col>

              <v-col cols="7"><v-text-field
                v-model="title.label"
                hide-details
                label="Display title"
                maxlength="40"
                placeholder="Dr"
              /></v-col></v-row>

            <v-btn
              class="mt-3"
              :disabled="!title.code.trim() || !title.label.trim() || titleTask.conflict.value"
              :loading="titleTask.busy.value"
              size="small"
              variant="outlined"
              @click="addTitle"
            >Save title</v-btn>
          </div>

          <v-row>
            <v-col
              cols="12"
              sm="6"
            ><v-text-field
              v-model="form.firstName"
              autocomplete="given-name"
              label="First name *"
              maxlength="100"
              :rules="[required]"
            /></v-col>

            <v-col
              cols="12"
              sm="6"
            ><v-text-field
              v-model="form.lastName"
              autocomplete="family-name"
              label="Last name *"
              maxlength="100"
              :rules="[required]"
            /></v-col>

            <v-col
              cols="12"
              sm="6"
            ><v-text-field
              v-model="form.dateOfBirth"
              hint="Used to identify children and adults. Leave blank if unknown."
              label="Date of birth"
              :max="today"
              persistent-hint
              :rules="[pastDate]"
              type="date"
            /></v-col>

            <v-col
              cols="12"
              sm="6"
            ><v-select
              v-model="form.gender"
              :items="genderOptions"
              label="Gender"
            /></v-col>
          </v-row>

          <div class="directory-section-heading">
            <span>02</span>

            <div>
              <h3>Church membership</h3>
              <p>Where they are in their journey with your church.</p>
            </div>
          </div>

          <v-select
            v-model="membership"
            item-title="label"
            item-value="code"
            :items="catalogue.metadata?.membershipTypes || []"
            label="Membership type *"
            :rules="[required]"
          />

          <details class="directory-disclosure">
            <summary>Life status <span>Optional details</span></summary>
            <v-select v-model="form.lifeStatus" class="mt-4" :items="lifeOptions" label="Life status" />

            <v-text-field
              v-if="form.lifeStatus === 'DECEASED'"
              v-model="form.dateOfDeath"
              label="Date of death"
              :max="today"
              :min="form.dateOfBirth || undefined"
              :rules="[pastDate, deathDate]"
              type="date"
            />
          </details>
        </v-form>
      </v-card-text>

      <div class="directory-form__footer">
        <span>* Required fields</span>

        <div>
          <v-btn :disabled="task.busy.value" variant="text" @click="open = false">Cancel</v-btn>

          <v-btn
            color="primary"
            :disabled="!catalogue.metadata || task.conflict.value || titleTask.busy.value"
            form="person-editor"
            :loading="task.busy.value"
            type="submit"
            variant="flat"
          >{{ person ? 'Save changes' : 'Create person' }}<v-icon
            end
            icon="mdi-arrow-right"
          /></v-btn>
        </div>
      </div>
    </v-card>
  </v-dialog>
</template>
<script setup lang="ts">
  import type { MembershipType, PersonDetails, PersonSummary } from '../types'
  import { computed, reactive, ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { useUiStore } from '@/stores/ui'
  import { directoryApi } from '../api'
  import { churchToday } from '../presentation'
  import { useDirectoryStore } from '../store'
  import { useTask } from '../useTask'
  import TaskAlert from './TaskAlert.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ person?: PersonSummary | null }>()
  const emit = defineEmits<{ saved: [id: string], reloaded: [] }>()
  const catalogue = useDirectoryStore()
  const task = useTask()
  const titleTask = useTask()
  const ui = useUiStore()
  const baseline = ref<PersonSummary | null>(null)
  const formRef = ref<{ validate: () => Promise<{ valid: boolean }> }>()
  function empty (): PersonDetails {
    return {
      titleCode: null,
      firstName: '',
      lastName: '',
      dateOfBirth: null,
      gender: 'UNKNOWN',
      lifeStatus: 'UNKNOWN',
      dateOfDeath: null,
    }
  }
  const form = reactive<PersonDetails>(empty())
  const membership = ref<MembershipType>('VISITOR')
  const showTitle = ref(false)
  const title = reactive({ code: '', label: '' })
  const metadataError = ref('')
  const today = computed(() => churchToday(catalogue.metadata?.timeZone))
  const genderOptions = [
    { title: 'Not recorded', value: 'UNKNOWN' },
    { title: 'Female', value: 'FEMALE' },
    { title: 'Male', value: 'MALE' },
  ]
  const lifeOptions = [
    { title: 'Not recorded', value: 'UNKNOWN' },
    { title: 'Living', value: 'LIVING' },
    { title: 'Deceased', value: 'DECEASED' },
  ]
  const required = (value: unknown) => Boolean(String(value ?? '').trim()) || 'This field is required.'
  function pastDate (value: string) {
    return !value || value <= today.value || 'Choose a date that is not in the future.'
  }
  function deathDate (value: string) {
    return !value || !form.dateOfBirth || value >= form.dateOfBirth || 'This date must be on or after birth.'
  }
  function populate (person?: PersonSummary | null) {
    baseline.value = person ?? null
    Object.assign(form, person?.details ?? empty())
    membership.value = person?.membershipType ?? 'VISITOR'
    task.clear()
  }
  async function loadMetadata () {
    metadataError.value = ''
    try {
      await catalogue.loadMetadata()
    } catch (error) {
      metadataError.value = extractErrorMessage(error, 'Unable to load available titles and membership types.')
    }
  }
  watch(open, value => {
    if (value) {
      populate(props.person)
      showTitle.value = false
      titleTask.clear()
      if (!catalogue.metadata) void loadMetadata()
    }
  })
  async function reload () {
    if (!baseline.value) {
      task.clear()
      return
    }
    try {
      populate(await directoryApi.person(baseline.value.id))
      emit('reloaded')
    } catch (error) {
      task.error.value = extractErrorMessage(error, 'Unable to reload details.')
    }
  }
  async function reloadTitles () {
    await loadMetadata()
    titleTask.clear()
  }
  async function addTitle () {
    const result = await titleTask.run(() => directoryApi.addTitle(title.code.trim(), title.label.trim()))
    if (result) {
      form.titleCode = title.code.trim().toUpperCase()
      showTitle.value = false
      await loadMetadata()
    }
  }
  async function save () {
    if (!(await formRef.value?.validate())?.valid) return
    const person = {
      ...form,
      dateOfBirth: form.dateOfBirth || null,
      dateOfDeath: form.lifeStatus === 'DECEASED' ? form.dateOfDeath || null : null,
    }
    const result = await task.run(() => directoryApi.savePerson(person, membership.value, baseline.value))
    if (result) {
      open.value = false
      ui.notifySuccess(baseline.value ? 'Person updated.' : 'Person created.')
      emit('saved', result.value)
    }
  }
</script>
