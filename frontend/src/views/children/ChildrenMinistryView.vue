<template><div><v-btn v-if="auth.hasPermission('children.station')" to="/children-station" class="mb-4">Open check-in station</v-btn><WorkflowWorkspace :definition="childrenDefinition" /></div></template>
<script setup lang="ts">
import WorkflowWorkspace from '@/components/workflows/WorkflowWorkspace.vue'
import { childrenDefinition } from '@/workflows/children'
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
</script>
