<template>
  <iframe
    class="email-preview"
    sandbox=""
    :srcdoc="document"
    :title="title"
  />
</template>
<script setup lang="ts">
  import { computed } from 'vue'
  const props = withDefaults(defineProps<{ html: string, title?: string }>(), {
    title: 'Email preview',
  })
  const document = computed(
    () =>
      `<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline';"><style>body{margin:0;padding:32px;font:15px/1.7 Arial,sans-serif;color:#273c37;background:#fff;overflow-wrap:anywhere}h2,h3{line-height:1.3;color:#173d32}a{color:#1b6b55}blockquote{border-left:3px solid #c7d9ce;margin:16px 0;padding-left:16px}p{margin:0 0 16px}</style></head><body>${props.html}</body></html>`,
  )
</script>
<style scoped>
  .email-preview {
    width: 100%;
    min-height: 340px;
    border: 1px solid var(--workspace-border);
    border-radius: 16px;
    background: white;
  }
</style>
