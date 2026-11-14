<template>
  <section class="directory-surface event-section">
    <div class="d-flex ga-3 align-center flex-wrap mb-4">
      <div class="mr-auto">
        <span class="directory-eyebrow">PUBLIC TICKETS</span>
        <h2 class="mt-2 mb-0">Ticket sales</h2>
      </div>

      <v-btn
        :loading="loading"
        prepend-icon="mdi-refresh"
        variant="text"
        @click="load"
      >
        Refresh
      </v-btn>
    </div>

    <v-alert
      v-if="error"
      class="mb-4"
      role="alert"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <template v-if="sales">
      <div class="d-flex ga-3 align-center flex-wrap mb-4">
        <v-chip :color="sales.enabled ? 'success' : undefined">{{ sales.enabled ? 'On sale' : 'Not on sale' }}</v-chip>

        <v-btn
          v-if="manage"
          color="primary"
          :loading="busy"
          variant="tonal"
          @click="toggle"
        >
          {{ sales.enabled ? 'Close sales' : 'Open sales' }}
        </v-btn>

        <span class="text-medium-emphasis">Up to {{ sales.maxPerOrder }} tickets per order. Buyers pay on Yoco and need no account.</span>
      </div>

      <v-table density="compact">
        <thead>
          <tr>
            <th scope="col">Ticket type</th>
            <th scope="col">Price</th>
            <th scope="col">Cap</th>
            <th scope="col">Status</th>
            <th v-if="manage" scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="t in sales.ticketTypes" :key="t.id">
            <td>{{ t.name }}<div v-if="t.description" class="text-medium-emphasis">{{ t.description }}</div></td>
            <td>{{ t.priceMinor === 0 ? 'Free' : formatRand(t.priceMinor) }}</td>
            <td>{{ t.quantityCap ?? 'No cap' }}</td>
            <td>{{ t.active ? 'Active' : 'Hidden' }}</td>

            <td v-if="manage">
              <v-btn
                size="small"
                variant="text"
                @click="edit(t)"
              >
                Edit
              </v-btn>
            </td>
          </tr>

          <tr v-if="sales.ticketTypes.length === 0">
            <td colspan="5">No ticket types yet. Add one (for example General or VIP) before opening sales.</td>
          </tr>
        </tbody>
      </v-table>

      <v-form
        v-if="manage"
        class="mt-4"
        @submit.prevent="save"
      >
        <h3>{{ form.id ? 'Edit ticket type' : 'Add a ticket type' }}</h3>

        <div class="d-flex ga-3 flex-wrap">
          <v-text-field
            v-model="form.name"
            label="Name *"
            maxlength="80"
            style="min-width: 180px"
          />

          <v-text-field
            v-model="form.price"
            :error-messages="priceMessage"
            inputmode="decimal"
            label="Price (ZAR) *"
            prefix="R"
            style="max-width: 160px"
          />

          <v-text-field
            v-model="form.cap"
            inputmode="numeric"
            label="Cap (optional)"
            style="max-width: 160px"
          />
        </div>

        <v-text-field
          v-model="form.description"
          label="Description (optional)"
          maxlength="300"
        />

        <v-checkbox v-model="form.active" label="Offered to the public" />

        <v-btn
          color="primary"
          :disabled="!formReady"
          :loading="busy"
          type="submit"
        >
          {{ form.id ? 'Save changes' : 'Add ticket type' }}
        </v-btn>

        <v-btn
          v-if="form.id"
          class="ml-2"
          variant="text"
          @click="reset"
        >
          Cancel
        </v-btn>
      </v-form>

      <h3 class="mt-6">Orders</h3>

      <v-table v-if="orders.length > 0" density="compact">
        <thead>
          <tr>
            <th scope="col">Placed</th>
            <th scope="col">Buyer</th>
            <th scope="col">Seats</th>
            <th scope="col">Total</th>
            <th scope="col">Status</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="o in orders" :key="o.id">
            <td>{{ new Date(o.createdAt).toLocaleString('en-ZA') }}</td>
            <td>{{ o.buyerName ?? 'Hidden' }}<div v-if="o.buyerEmail" class="text-medium-emphasis">{{ o.buyerEmail }}</div></td>
            <td>{{ o.seats }}</td>
            <td>{{ o.totalMinor === 0 ? 'Free' : formatRand(o.totalMinor) }}</td>
            <td>{{ statusLabel(o.status) }}</td>
          </tr>
        </tbody>
      </v-table>

      <p v-else class="text-medium-emphasis">No orders yet.</p>
    </template>
  </section>
</template>

<script setup lang="ts">
  import type { PublicSales, StaffTicketOrder, StaffTicketType } from '@/tickets/staff-api'
  import { computed, onMounted, reactive, ref } from 'vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { formatRand } from '@/payments/giving-amount'
  import { useAuthStore } from '@/stores/auth'
  import { priceToMinor, ticketStaffApi } from '@/tickets/staff-api'

  const props = defineProps<{ occurrenceId: string }>()
  const auth = useAuthStore()
  const manage = computed(() => auth.hasPermission('events.tickets.manage'))
  const sales = ref<PublicSales>()
  const orders = ref<StaffTicketOrder[]>([])
  const loading = ref(false)
  const busy = ref(false)
  const error = ref('')
  const blank = () => ({ id: '', version: 0, name: '', price: '', cap: '', description: '', active: true, sortOrder: 0 })
  const form = reactive(blank())

  const priceMessage = computed(() => form.price && priceToMinor(form.price) === null ? 'Use 0 for free, or at least R5.00' : '')
  const capOk = computed(() => form.cap === '' || (/^\d{1,5}$/.test(form.cap) && Number(form.cap) >= 1))
  const formReady = computed(() => form.name.trim() !== '' && priceToMinor(form.price) !== null && capOk.value)

  const LABELS: Record<string, string> = {
    RESERVED: 'Awaiting payment',
    PAID: 'Paid',
    EXPIRED: 'Expired',
    RELEASED: 'Test payment',
    LATE_PAID_REVIEW: 'Paid late: refund it',
    PARTIALLY_REFUNDED: 'Partly refunded',
    REFUNDED: 'Refunded',
    REFUND_REVIEW: 'Needs refund review',
  }
  const statusLabel = (status: string) => LABELS[status] ?? status

  function reset () {
    Object.assign(form, blank())
  }

  function edit (t: StaffTicketType) {
    Object.assign(form, {
      id: t.id,
      version: t.version,
      name: t.name,
      price: (t.priceMinor / 100).toFixed(2),
      cap: t.quantityCap === null ? '' : String(t.quantityCap),
      description: t.description,
      active: t.active,
      sortOrder: t.sortOrder,
    })
  }

  async function load () {
    loading.value = true
    error.value = ''
    try {
      sales.value = await ticketStaffApi.sales(props.occurrenceId)
      orders.value = auth.hasPermission('events.tickets.read') ? await ticketStaffApi.orders(props.occurrenceId) : []
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to load ticket sales.')
    } finally {
      loading.value = false
    }
  }

  async function run (action: () => Promise<void>) {
    busy.value = true
    error.value = ''
    try {
      await action()
      await load()
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to save this change.')
    } finally {
      busy.value = false
    }
  }

  function toggle () {
    return run(async () => {
      if (!sales.value) return
      await ticketStaffApi.configure(props.occurrenceId, {
        expectedVersion: sales.value.version,
        enabled: !sales.value.enabled,
        opensAt: sales.value.opensAt,
        closesAt: sales.value.closesAt,
        maxPerOrder: sales.value.maxPerOrder,
      })
    })
  }

  function save () {
    return run(async () => {
      const priceMinor = priceToMinor(form.price)
      if (priceMinor === null) return
      await ticketStaffApi.saveType(props.occurrenceId, {
        id: form.id || undefined,
        version: form.id ? form.version : undefined,
        name: form.name.trim(),
        description: form.description.trim(),
        priceMinor,
        quantityCap: form.cap === '' ? null : Number(form.cap),
        sortOrder: form.sortOrder,
        active: form.active,
      })
      reset()
    })
  }

  onMounted(load)
</script>
