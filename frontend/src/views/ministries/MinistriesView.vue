<template><v-btn class="mb-4" color="primary" to="/groups">Group meetings and leader workspace</v-btn><WorkflowWorkspace :definition="ministriesDefinition" /></template>
<script setup lang="ts">
  import WorkflowWorkspace from '@/components/workflows/WorkflowWorkspace.vue'
  import { ministriesDefinition } from '@/workflows/ministries'
</script>
