<template>
  <v-dialog v-model="ui.confirm.show" max-width="440" persistent>
    <v-card>
      <v-card-title class="text-h6">{{ ui.confirm.title }}</v-card-title>
      <v-card-text>{{ ui.confirm.message }}</v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="ui.resolveConfirm(false)">{{ ui.confirm.cancelText }}</v-btn>
        <v-btn :color="ui.confirm.color" variant="flat" @click="ui.resolveConfirm(true)">{{ ui.confirm.confirmText }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import { useUiStore } from '@/stores/ui'

  const ui = useUiStore()
</script>
