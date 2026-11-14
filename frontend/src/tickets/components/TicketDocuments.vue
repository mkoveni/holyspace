<template>
  <section class="tk-card tk-doc">
    <div class="tk-row">
      <h2 class="mb-0">{{ docs.title }}</h2>

      <v-btn
        class="tk-doc__print"
        prepend-icon="mdi-printer-outline"
        size="small"
        variant="text"
        @click="print"
      >Print or save as PDF</v-btn>
    </div>

    <p class="tk-muted mb-3">{{ docs.number }} · {{ docs.issuedOn }}</p>

    <p class="mb-3">
      <strong>{{ docs.issuer.legalName }}</strong><br>
      <span v-if="docs.issuer.address">{{ docs.issuer.address }}<br></span>
      <span v-if="docs.issuer.registrationNumber">Reg. {{ docs.issuer.registrationNumber }}<br></span>
      <span v-if="docs.issuer.npoNumber">NPO {{ docs.issuer.npoNumber }}<br></span>
      <span v-if="docs.issuer.vatNumber">VAT {{ docs.issuer.vatNumber }}</span>
    </p>

    <p class="mb-3">Billed to <strong>{{ docs.buyer.name }}</strong></p>

    <v-table density="compact">
      <thead>
        <tr>
          <th scope="col">Description</th>
          <th class="text-right" scope="col">Qty</th>
          <th class="text-right" scope="col">Amount</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="(l, i) in docs.lines" :key="i">
          <td>{{ l.description }}</td>
          <td class="text-right">{{ l.quantity }}</td>
          <td class="text-right">{{ formatRand(l.lineMinor) }}</td>
        </tr>

        <tr v-if="docs.vatMinor > 0">
          <td colspan="2">Includes VAT at {{ docs.vatRateBp / 100 }}%</td>
          <td class="text-right">{{ formatRand(docs.vatMinor) }}</td>
        </tr>

        <tr class="tk-total">
          <td colspan="2">Total paid</td>
          <td class="text-right">{{ formatRand(docs.totalMinor) }}</td>
        </tr>
      </tbody>
    </v-table>

    <div v-for="n in docs.creditNotes" :key="n.id" class="mt-4">
      <h3>Credit note {{ n.number }}</h3>
      <p class="tk-muted mb-1">{{ n.issuedOn }}</p>

      <v-table density="compact">
        <tbody>
          <tr v-for="(l, i) in n.lines" :key="i">
            <td>{{ l.description }}</td>
            <td class="text-right">−{{ formatRand(l.lineMinor) }}</td>
          </tr>

          <tr v-if="n.vatMinor > 0">
            <td>Includes VAT</td>
            <td class="text-right">{{ formatRand(n.vatMinor) }}</td>
          </tr>

          <tr class="tk-total">
            <td>Refunded</td>
            <td class="text-right">−{{ formatRand(n.totalMinor) }}</td>
          </tr>
        </tbody>
      </v-table>
    </div>
  </section>
</template>

<script setup lang="ts">
  import type { TicketDocuments } from '@/tickets/public-tickets'
  import { formatRand } from '@/tickets/public-tickets'

  defineProps<{ docs: TicketDocuments }>()

  function print () {
    window.print()
  }
</script>

<style>
@media print {
  .tk-page { background: #fff !important; }
  .tk-page .tk-card:not(.tk-doc), .tk-page .tk-brand, .tk-doc__print, .tk-page .tk-muted.text-center { display: none !important; }
  .tk-doc { box-shadow: none !important; border: 0 !important; }
}
</style>
