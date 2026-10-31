<template><WorkflowWorkspace :definition="attendanceDefinition" /></template>
<script setup lang="ts">
  import WorkflowWorkspace from '@/components/workflows/WorkflowWorkspace.vue'
  import { attendanceDefinition } from '@/workflows/attendance'
</script>
