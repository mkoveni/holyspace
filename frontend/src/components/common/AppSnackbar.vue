<template>
  <v-snackbar v-model="ui.snackbar.show" :color="ui.snackbar.color" location="top right" :timeout="ui.snackbar.timeout">
    {{ ui.snackbar.text }}
    <template #actions>
      <v-btn icon="mdi-close" size="small" variant="text" @click="ui.snackbar.show = false" />
    </template>
  </v-snackbar>
</template>

<script lang="ts" setup>
  import { useUiStore } from '@/stores/ui'

  const ui = useUiStore()
</script>
