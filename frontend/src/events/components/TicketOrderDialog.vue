<template>
  <v-dialog
    max-width="640"
    :model-value="true"
    scrollable
    @update:model-value="emit('close')"
  >
    <v-card rounded="xl">
      <v-card-title class="pa-6">Ticket order</v-card-title>

      <v-card-text>
        <v-progress-linear v-if="loading" indeterminate />

        <v-alert
          v-if="error"
          class="mb-4"
          role="alert"
          type="error"
          variant="tonal"
        >
          {{ error }}
        </v-alert>

        <template v-if="order">
          <p class="mb-1">
            <strong>{{ order.buyerName ?? 'Buyer hidden' }}</strong>
            <span v-if="order.buyerEmail" class="text-medium-emphasis"> · {{ order.buyerEmail }}</span>
          </p>

          <p class="text-medium-emphasis">
            {{ order.seats }} seat(s) · {{ order.totalMinor === 0 ? 'Free' : formatRand(order.totalMinor) }}
            <span v-if="order.invoiceNumber"> · Invoice {{ order.invoiceNumber }}</span>
            <span v-for="n in order.creditNotes" :key="n.number"> · Credit note {{ n.number }} ({{ formatRand(n.totalMinor) }})</span>
          </p>

          <v-alert
            v-if="order.refundWaitingMinor > 0"
            class="mb-4"
            type="warning"
            variant="tonal"
          >
            A refund of {{ formatRand(order.refundWaitingMinor) }} was recorded and is waiting. Tick the tickets it covers and
            void them: when their prices add up to the refund it is matched and credited.
          </v-alert>

          <v-table density="compact">
            <thead>
              <tr>
                <th v-if="manage" scope="col"><span class="sr-only">Select</span></th>
                <th scope="col">Ticket</th>
                <th scope="col">Price</th>
                <th scope="col">Status</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="t in order.tickets" :key="t.id">
                <td v-if="manage">
                  <v-checkbox
                    v-model="selected"
                    :aria-label="`Select ${t.type} ticket`"
                    density="compact"
                    :disabled="t.status === 'VOID'"
                    hide-details
                    :value="t.id"
                  />
                </td>

                <td>{{ t.type }}<div class="text-medium-emphasis">{{ t.holder }}</div></td>
                <td>{{ t.priceMinor === 0 ? 'Free' : formatRand(t.priceMinor) }}</td>
                <td>{{ label(t) }}</td>
              </tr>
            </tbody>
          </v-table>

          <p v-if="selected.length > 0" class="mt-3">
            Selected: {{ formatRand(selectedMinor) }} of tickets. Voiding never moves money; refund the buyer in the Yoco dashboard.
          </p>
        </template>
      </v-card-text>

      <v-card-actions class="pa-6">
        <v-btn @click="emit('close')">Close</v-btn>
        <v-spacer />

        <v-btn
          v-if="manage && order && ['PAID', 'PARTIALLY_REFUNDED'].includes(order.status)"
          :loading="busy"
          variant="text"
          @click="resend"
        >
          Resend tickets email
        </v-btn>

        <v-btn
          v-if="manage"
          color="error"
          :disabled="selected.length === 0"
          :loading="busy"
          @click="voidSelected"
        >
          Void selected
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
  import type { StaffOrderDetail, StaffTicket } from '@/tickets/staff-api'
  import { computed, onMounted, ref } from 'vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { formatRand } from '@/payments/giving-amount'
  import { useAuthStore } from '@/stores/auth'
  import { useUiStore } from '@/stores/ui'
  import { ticketStaffApi } from '@/tickets/staff-api'

  const props = defineProps<{ occurrenceId: string, orderId: string }>()
  const emit = defineEmits<{ close: [], changed: [] }>()
  const auth = useAuthStore()
  const ui = useUiStore()
  const manage = computed(() => auth.hasPermission('events.tickets.manage'))
  const order = ref<StaffOrderDetail>()
  const selected = ref<string[]>([])
  const loading = ref(false)
  const busy = ref(false)
  const error = ref('')
  const selectedMinor = computed(() => (order.value?.tickets ?? []).filter(t => selected.value.includes(t.id)).reduce((sum, t) => sum + t.priceMinor, 0))

  const label = (t: StaffTicket) => t.status === 'VOID' ? (t.credited ? 'Void · credited' : 'Void · awaiting refund') : (t.status === 'CHECKED_IN' ? 'Checked in' : 'Valid')

  async function load () {
    loading.value = true
    try {
      order.value = await ticketStaffApi.order(props.occurrenceId, props.orderId)
      selected.value = []
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to load this order.')
    } finally {
      loading.value = false
    }
  }

  async function resend () {
    busy.value = true
    error.value = ''
    try {
      await ticketStaffApi.resend(props.occurrenceId, props.orderId)
      ui.notify('Tickets email queued.')
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to resend the tickets.')
    } finally {
      busy.value = false
    }
  }

  async function voidSelected () {
    const confirmed = await ui.confirmAction({
      title: 'Void these tickets?',
      message: 'The tickets stop working at the door. This does not refund the buyer.',
      confirmText: 'Void tickets',
      color: 'error',
    })
    if (!confirmed) return
    busy.value = true
    error.value = ''
    try {
      await ticketStaffApi.voidTickets(props.occurrenceId, props.orderId, selected.value)
      await load()
      emit('changed')
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to void these tickets.')
    } finally {
      busy.value = false
    }
  }

  onMounted(load)
</script>
