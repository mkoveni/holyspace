<template>
  <FormDialog
    ref="formRef"
    v-model="model"
    :loading="lifeEventsStore.isSaving"
    size="md"
    :submit-label="isEdit ? 'Save Changes' : 'Record Event'"
    :title="isEdit ? 'Edit Life Event' : 'Record Life Event'"
    @cancel="close"
    @submit="handleSubmit"
  >
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

  </FormDialog>
</template>

<script lang="ts" setup>
  import type { LifeEvent, LifeEventType, ParticipantRole } from '@/types/lifeEvent'
  import { computed, reactive, ref, watch } from 'vue'
  import FormDialog from '@/components/common/FormDialog.vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useLifeEventsStore } from '@/stores/lifeEvents'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import { lifeEventTypes, participantRoles } from '@/types/lifeEvent'
  import { required } from '@/utils/rules'

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
      ui.notifyError(extractErrorMessage(error, 'Unable to save life event.'))
    }
  }

</script>
