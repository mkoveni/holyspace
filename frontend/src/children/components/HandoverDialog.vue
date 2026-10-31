<template>
  <MinistryDialog
    v-model="open"
    save-label="Confirm collection"
    :submit="save"
    :subtitle="childName"
    :title="supervised ? 'A carefully verified handover' : 'A safe handover'"
    @reload="reload"
    @saved="$emit('saved')"
  >
    <v-alert
      v-if="loadError"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ loadError }}
      <v-btn
        variant="text"
        @click="reload"
      >
        Retry
      </v-btn>
    </v-alert>

    <v-select
      v-model="collectorId"
      item-title="name"
      item-value="collectorId"
      :items="collectors"
      label="Authorized collector *"
      :loading="loading"
      no-data-text="No current collector authorization. A safeguarding coordinator must review permissions."
      :rules="[required]"
    />

    <FamilyQrScanner
      v-if="!supervised && current"
      kind="collection"
      :resource-id="current.id"
      @scanned="scanCollection"
    />

    <v-text-field
      v-if="!supervised"
      v-model="code"
      autocapitalize="off"
      autocomplete="off"
      class="mb-4"
      hint="Scan the label using a keyboard scanner, or enter the printed code."
      label="Pickup code *"
      persistent-hint
      :rules="[required]"
      spellcheck="false"
    />

    <v-textarea
      v-else
      v-model="reason"
      label="Reason for supervised collection *"
      maxlength="500"
      rows="3"
      :rules="[required]"
    />

    <v-checkbox
      v-model="verified"
      color="primary"
      label="I have checked this collector’s identity in person"
      :rules="[
        (v) => v === true || 'Verify the collector’s identity before handover.',
      ]"
    />

    <div class="children-form-note">
      {{
        supervised
          ? 'A supervisor can resolve a lost-label handover. Collector authorization and identity checks are still required.'
          : 'Match the adult in front of you to the authorized collector. A pickup code alone is not proof of identity.'
      }}
    </div>
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type { FamilyQr } from '../familyQr'
  import type { AttendanceView, PickupCollector } from '../types'
  import { onBeforeUnmount, ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { childrenApi } from '../api'
  import { required } from '../presentation'
  import FamilyQrScanner from './FamilyQrScanner.vue'
  import MinistryDialog from './MinistryDialog.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{
    attendance: AttendanceView | null
    childName: string
    supervised: boolean
  }>()
  defineEmits<{ saved: [] }>()
  const current = ref<AttendanceView | null>(null)
  const collectors = ref<PickupCollector[]>([])
  const collectorId = ref('')
  const verified = ref(false)
  const code = ref('')
  const reason = ref('')
  const loading = ref(false)
  const loadError = ref('')
  let requestId = ''
  let sequence = 0
  async function reload () {
    const version = ++sequence
    if (!props.attendance) return
    loadError.value = ''
    loading.value = true
    verified.value = false
    try {
      const [record, adults] = await Promise.all([
        childrenApi.attendance(props.attendance.id),
        childrenApi.collectors(props.attendance.id),
      ])
      if (version === sequence) {
        current.value = record
        collectors.value = adults
        if (!adults.some(a => a.collectorId === collectorId.value))
          collectorId.value = ''
      }
    } catch (error) {
      if (version === sequence) loadError.value = extractErrorMessage(error)
    } finally {
      if (version === sequence) loading.value = false
    }
  }
  watch(open, value => {
    code.value = ''
    reason.value = ''
    verified.value = false
    if (value) {
      requestId = crypto.randomUUID()
      collectorId.value = ''
      current.value = null
      collectors.value = []
      void reload()
    } else {
      sequence++
      current.value = null
      collectors.value = []
    }
  })
  watch(collectorId, () => {
    verified.value = false
  })
  onBeforeUnmount(() => {
    sequence++
    code.value = ''
  })
  async function scanCollection (reference: FamilyQr) {
    if (!('pickupCode' in reference)) return
    const attendanceId = current.value?.id
    verified.value = false
    await reload()
    if (open.value && current.value?.id === attendanceId && !loadError.value)
      code.value = reference.pickupCode
  }
  async function save () {
    if (!current.value || loading.value || !verified.value)
      throw new Error(
        'Load the current record and confirm the collector’s identity.',
      )
    const body = {
      attendanceId: current.value.id,
      expectedVersion: current.value.version,
      requestId,
      collectorId: collectorId.value,
      identityVerified: true,
    }
    try {
      return props.supervised
        ? await childrenApi.supervisorCheckout({
          ...body,
          reason: reason.value,
        })
        : await childrenApi.checkout({ ...body, pickupCode: code.value })
    } catch (error) {
      try {
        current.value = await childrenApi.attendance(body.attendanceId)
      } catch {
        current.value = null
      }
      code.value = ''
      throw error
    }
  }
</script>
