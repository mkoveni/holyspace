<template>
  <div class="my-4">
    <img v-if="source" :src="source" alt="Authenticator setup QR code" width="320" height="320" class="authenticator-qr" />
    <v-progress-circular v-else-if="loading" indeterminate aria-label="Generating authenticator QR code" />
    <v-alert v-else-if="failed" type="warning" variant="tonal">
      Unable to display the QR code. Use the manual setup key below, or try again.
      <v-btn variant="text" @click="generate">Retry QR code</v-btn>
    </v-alert>
  </div>
</template>
<script setup lang="ts">
import { onScopeDispose, ref, watch } from 'vue'
import { authenticatorQr } from '@/security/authenticatorQr'

const props = defineProps<{uri:string}>()
const source = ref(''), loading = ref(false), failed = ref(false)
let generation = 0
async function generate () {
  const current = ++generation
  source.value = ''; failed.value = false; loading.value = true
  try {
    const result = await authenticatorQr(props.uri)
    if (current === generation) source.value = result
  } catch {
    if (current === generation) failed.value = true
  } finally {
    if (current === generation) loading.value = false
  }
}
watch(() => props.uri, generate, {immediate:true})
onScopeDispose(() => {++generation; source.value = ''})
</script>
<style scoped>
.authenticator-qr {display:block;max-width:100%;height:auto;background:#fff;image-rendering:pixelated}
</style>
