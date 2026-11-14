<template>
  <v-btn
    prepend-icon="mdi-file-document-outline"
    variant="text"
    @click="show"
  >
    Invoice details
  </v-btn>

  <v-dialog
    v-model="open"
    max-width="620"
    scrollable
  >
    <v-card rounded="xl">
      <v-card-title class="pa-6">Invoice details</v-card-title>

      <v-card-text>
        <p class="text-medium-emphasis mb-4">
          These details appear on every ticket invoice and credit note. Documents already issued never change: a new set
          applies from the date you choose, and VAT is added to invoices only from that date.
        </p>

        <v-alert
          v-if="error"
          class="mb-4"
          role="alert"
          type="error"
          variant="tonal"
        >
          {{ error }}
        </v-alert>

        <v-progress-linear v-if="loading" indeterminate />

        <v-form @submit.prevent="save">
          <v-text-field v-model="form.legalName" label="Legal name *" maxlength="150" />
          <v-text-field v-model="form.address" label="Address" maxlength="300" />

          <div class="d-flex ga-3 flex-wrap">
            <v-text-field v-model="form.registrationNumber" label="Registration number" maxlength="40" />
            <v-text-field v-model="form.npoNumber" label="NPO number" maxlength="40" />
          </div>

          <v-checkbox v-model="form.vatRegistered" label="The church is registered for VAT" />

          <v-text-field
            v-if="form.vatRegistered"
            v-model="form.vatNumber"
            label="VAT number *"
            maxlength="20"
          />

          <v-text-field
            v-model="form.effectiveFrom"
            hint="Must be after the date of the current details."
            label="Takes effect on *"
            persistent-hint
            type="date"
          />
        </v-form>

        <p v-if="current" class="text-medium-emphasis mt-4">
          Current: {{ current.legalName }} (from {{ current.effectiveFrom }}), {{ current.vatRegistered ? `VAT registered ${current.vatNumber}` : 'not VAT registered' }}.
        </p>
      </v-card-text>

      <v-card-actions class="pa-6">
        <v-btn @click="open = false">Close</v-btn>
        <v-spacer />

        <v-btn
          color="primary"
          :disabled="!ready"
          :loading="busy"
          @click="save"
        >
          Save from this date
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
  import type { InvoiceSettings } from '@/tickets/staff-api'
  import { computed, reactive, ref } from 'vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useUiStore } from '@/stores/ui'
  import { ticketStaffApi } from '@/tickets/staff-api'

  const ui = useUiStore()
  const open = ref(false)
  const loading = ref(false)
  const busy = ref(false)
  const error = ref('')
  const current = ref<InvoiceSettings>()
  const form = reactive({ legalName: '', address: '', registrationNumber: '', npoNumber: '', vatNumber: '', vatRegistered: false, vatRateBp: 1500, effectiveFrom: '' })
  const ready = computed(() => form.legalName.trim() !== '' && form.effectiveFrom !== '' && (!form.vatRegistered || form.vatNumber.trim() !== ''))

  async function show () {
    open.value = true
    loading.value = true
    error.value = ''
    try {
      const [latest] = await ticketStaffApi.invoiceSettings()
      current.value = latest
      if (latest) {
        Object.assign(form, { ...latest, effectiveFrom: new Date().toISOString().slice(0, 10) })
      }
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to load the invoice details.')
    } finally {
      loading.value = false
    }
  }

  async function save () {
    if (!ready.value || busy.value) return
    busy.value = true
    error.value = ''
    try {
      await ticketStaffApi.changeInvoiceSettings({ ...form, legalName: form.legalName.trim() })
      ui.notify('Invoice details saved.')
      open.value = false
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to save the invoice details.')
    } finally {
      busy.value = false
    }
  }
</script>
