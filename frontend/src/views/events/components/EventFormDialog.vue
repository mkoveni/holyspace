<template>
  <v-dialog v-model="model" max-width="720" persistent>
    <v-card>
      <v-card-title>{{ event ? 'Edit event details' : 'Create event' }}</v-card-title>

      <v-form ref="formRef" @submit.prevent="save">
        <v-card-text>
          <v-alert v-if="error" class="mb-4" type="error" variant="tonal">{{ error }}</v-alert>
          <v-text-field v-model="form.title" label="Title" maxlength="200" :rules="[required]" />
          <v-select v-model="form.type" :items="eventTypes" label="Event type" />
          <v-textarea v-model="form.description" label="Description" maxlength="5000" rows="2" />

          <template v-if="!event">
            <v-text-field v-model="form.location" label="Location" maxlength="255" :rules="[required]" />
            <v-autocomplete v-model="form.timezone" :items="timezones" label="Timezone" :rules="[required]" />
            <p class="text-caption mb-3">Enter times in the selected timezone. Weekly events keep the same local time.</p>

            <v-row>
              <v-col cols="12" sm="6"><v-text-field v-model="form.startsAt" label="Starts" :rules="[required]" type="datetime-local" /></v-col>
              <v-col cols="12" sm="6"><v-text-field v-model="form.endsAt" label="Ends" :rules="[required, endAfterStart]" type="datetime-local" /></v-col>
            </v-row>

            <v-select v-model="form.recurrence" :items="[{ title: 'One-off', value: 'once' }, { title: 'Weekly', value: 'weekly' }]" label="Schedule" />

            <v-text-field
              v-if="form.recurrence === 'weekly'"
              v-model.number="form.occurrenceCount"
              label="Number of occurrences"
              max="52"
              min="1"
              :rules="[validCount]"
              type="number"
            />
          </template>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn :disabled="store.isSaving" @click="model = false">Cancel</v-btn>
          <v-btn color="primary" :loading="store.isSaving" type="submit" variant="flat">{{ event ? 'Save details' : 'Create event' }}</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>
<script setup lang="ts">
  import type { ChurchEvent, CreateEventPayload } from '@/types/event'
  import { computed, reactive, ref, watch } from 'vue'
  import { useEventsStore } from '@/stores/events'
  import { eventTypes } from '@/types/event'

  const props = defineProps<{ modelValue: boolean, event?: ChurchEvent | null }>()
  const emit = defineEmits<{ 'update:model-value': [boolean], 'saved': [string] }>()
  const store = useEventsStore()
  const model = computed({ get: () => props.modelValue, set: value => emit('update:model-value', value) })
  const formRef = ref()
  const error = ref('')
  const timezones = [...new Set(['Africa/Johannesburg', 'UTC', ...Intl.supportedValuesOf('timeZone')])]
  const form = reactive<CreateEventPayload>({ title: '', type: 'service', description: null, startsAt: '', endsAt: '', location: '', timezone: 'Africa/Johannesburg', recurrence: 'once', occurrenceCount: 1 })
  const required = (value: string) => Boolean(value?.trim()) || 'This field is required.'
  const endAfterStart = (value: string) => value > form.startsAt || 'End must be after start.'
  const validCount = (value: number) => (Number.isInteger(value) && value >= 1 && value <= 52) || 'Choose 1 to 52 occurrences.'
  watch(() => props.modelValue, open => {
    if (!open) return
    error.value = ''
    Object.assign(form, { title: props.event?.title ?? '', type: props.event?.type ?? 'service', description: props.event?.description ?? null, startsAt: '', endsAt: '', location: '', timezone: 'Africa/Johannesburg', recurrence: 'once', occurrenceCount: 1 })
    formRef.value?.resetValidation()
  })
  async function save () {
    if (store.isSaving || !(await formRef.value.validate()).valid) return
    error.value = ''
    try {
      let id: string
      if (props.event) {
        id = props.event.id
        await store.update(id, { title: form.title, type: form.type, description: form.description })
      } else {
        id = await store.create({ ...form, occurrenceCount: form.recurrence === 'once' ? 1 : form.occurrenceCount })
      }
      model.value = false
      emit('saved', id)
    } catch (error_) {
      error.value = error_ instanceof Error ? error_.message : 'Unable to save event.'
    }
  }
</script>
