<template>
  <v-dialog v-model="model" max-width="640" persistent>
    <v-card>
      <v-card-title>{{ isEdit ? 'Edit Life Event' : 'Record Life Event' }}</v-card-title>
      <v-divider />

      <v-form ref="formRef" @submit.prevent="handleSubmit">
        <v-card-text>
          <v-row>
            <v-col cols="12" sm="6">
              <v-select v-model="form.type" :items="eventTypes" label="Event type" :rules="[required]" />
            </v-col>

            <v-col cols="12" sm="6">
              <v-text-field v-model="form.eventDate" label="Event date" :rules="[required]" type="datetime-local" />
            </v-col>

            <v-col cols="12">
              <PeopleSearch
                v-model="form.participants"
                label="Participants"
                multiple
                :rules="[minOne]"
              />
            </v-col>

            <v-col v-for="(personId, index) in form.participants" :key="personId" cols="12" sm="6">
              <v-select
                :items="participantRoles"
                :label="`${peopleStore.byId(personId) ? `${peopleStore.byId(personId)?.firstName} ${peopleStore.byId(personId)?.lastName}` : 'Participant'} role`"
                :model-value="form.roles[personId] ?? defaultRole(index)"
                @update:model-value="form.roles[personId] = $event"
              />
            </v-col>

            <v-col cols="12">
              <v-textarea v-model="form.notes" label="Notes" rows="3" />
            </v-col>
          </v-row>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Cancel</v-btn>

          <v-btn color="primary" :loading="lifeEventsStore.isSaving" type="submit" variant="flat">
            {{ isEdit ? 'Save Changes' : 'Record Event' }}
          </v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import type { LifeEvent, LifeEventType, ParticipantRole } from '@/types/lifeEvent'
  import { computed, reactive, ref, watch } from 'vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import { lifeEventTypes, participantRoles } from '@/types/lifeEvent'

  const props = defineProps<{
    modelValue: boolean
    lifeEvent?: LifeEvent | null
  }>()

  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const lifeEventsStore = useLifeEventsStore()
  const peopleStore = usePeopleStore()
  const ui = useUiStore()
  const formRef = ref()

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const isEdit = computed(() => Boolean(props.lifeEvent))

  const eventTypes = lifeEventTypes

  const form = reactive({
    type: 'other' as LifeEventType,
    eventDate: '',
    participants: [] as string[],
    roles: {} as Record<string, ParticipantRole>,
    notes: '',
  })

  const required = (value: string) => Boolean(value) || 'This field is required.'
  const minOne = (value: string[]) => (value && value.length > 0) || 'Select at least one participant.'

  watch(() => props.modelValue, value => {
    if (value) {
      form.type = props.lifeEvent?.type ?? 'other'
      form.eventDate = props.lifeEvent?.eventDate ? toLocalDateTimeInput(props.lifeEvent.eventDate) : ''
      form.participants = (props.lifeEvent?.participants ?? []).map(participant => participant.personId)
      form.roles = Object.fromEntries((props.lifeEvent?.participants ?? []).map(participant => [participant.personId, participant.role]))
      form.notes = props.lifeEvent?.notes ?? ''
    }
  })

  function toLocalDateTimeInput (value: string) {
    const date = new Date(value)
    const offset = date.getTimezoneOffset()
    return new Date(date.getTime() - offset * 60_000).toISOString().slice(0, 16)
  }

  function defaultRole (index: number): ParticipantRole {
    if (form.type === 'marriage') return 'spouse'
    if (form.type === 'engagement') return index === 0 ? 'proposer' : 'recipient'
    return 'subject'
  }

  watch(() => form.type, () => {
    form.roles = {}
  }, { flush: 'sync' })

  function close () {
    model.value = false
  }

  async function handleSubmit () {
    if (lifeEventsStore.isSaving) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    const payload = {
      type: form.type,
      details: props.lifeEvent?.details ?? {},
      eventDate: new Date(form.eventDate).toISOString(),
      participants: form.participants.map((personId, index) => ({
        personId,
        role: form.roles[personId] ?? defaultRole(index),
      })),
      notes: form.notes || undefined,
    }

    try {
      if (isEdit.value && props.lifeEvent) {
        await lifeEventsStore.update(props.lifeEvent.id, payload)
        ui.notifySuccess('Life event updated successfully.')
      } else {
        await lifeEventsStore.create(payload)
        ui.notifySuccess('Life event recorded successfully.')
      }
      emit('saved')
      close()
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to save life event.')
    }
  }

</script>
