<template><v-btn class="mb-4" color="primary" to="/volunteer-rota">Invitations and responses</v-btn><WorkflowWorkspace :definition="volunteeringDefinition" /></template>
<script setup lang="ts">
  import WorkflowWorkspace from '@/components/workflows/WorkflowWorkspace.vue'
  import { volunteeringDefinition } from '@/workflows/volunteering'
</script>
