<template><WorkflowWorkspace :definition="communicationsDefinition" /></template>
<script setup lang="ts">
  import WorkflowWorkspace from '@/components/workflows/WorkflowWorkspace.vue'
  import { communicationsDefinition } from '@/workflows/communications'
</script>
