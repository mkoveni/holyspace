<template>
  <div>
    <v-btn class="mb-4" color="primary" to="/giving/reports">Statements and reports</v-btn>
    <v-btn v-if="auth.hasPermission('payments.manage')" class="ml-2 mb-4" to="/payments" variant="outlined">Online payments</v-btn>
    <WorkflowWorkspace :definition="givingDefinition" />

    <v-card class="mt-4">
      <v-card-title>View and print an acknowledgement</v-card-title>
      <v-card-text><v-select v-model="receiptId" :items="receipts" label="Acknowledgement" /></v-card-text>
      <v-card-actions><v-btn :disabled="!receiptId" :to="`/giving/receipts/${receiptId}`" variant="tonal">Open acknowledgement</v-btn></v-card-actions>
    </v-card>
  </div>
</template>
<script setup lang="ts">
  import { computed, ref } from 'vue'
  import WorkflowWorkspace from '@/components/workflows/WorkflowWorkspace.vue'
  import { useAuthStore } from '@/stores/auth'
  import { useWorkflowsStore } from '@/stores/workflows'
  import { givingDefinition } from '@/workflows/giving'
  const auth = useAuthStore()
  const store = useWorkflowsStore()
  const receiptId = ref<string | null>(null)
  const receipts = computed(() => (store.data.giving?.receipts ?? []).map(receipt => ({ title: `${receipt.receiptNumber}${receipt.void ? ' · VOID' : ''}`, value: receipt.id })))
</script>
