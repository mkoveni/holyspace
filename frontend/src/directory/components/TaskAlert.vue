<template>
  <v-alert
    v-if="error"
    class="mb-5"
    role="alert"
    :type="conflict ? 'warning' : 'error'"
    variant="tonal"
  >
    {{ error }}
    <v-btn
      v-if="conflict"
      class="mt-2"
      size="small"
      variant="outlined"
      @click="$emit('reload')"
    >Reload latest details</v-btn>
  </v-alert>
</template>
<script setup lang="ts">
  defineProps<{ error: string, conflict?: boolean }>()
  defineEmits<{ reload: [] }>()
</script>
