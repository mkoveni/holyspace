<template><div><v-btn v-if="auth.hasPermission('children.station')" class="mb-4" to="/children-station">Open check-in station</v-btn><WorkflowWorkspace :definition="childrenDefinition" /></div></template>
<script setup lang="ts">
  import WorkflowWorkspace from '@/components/workflows/WorkflowWorkspace.vue'
  import { useAuthStore } from '@/stores/auth'
  import { childrenDefinition } from '@/workflows/children'
  const auth = useAuthStore()
</script>
