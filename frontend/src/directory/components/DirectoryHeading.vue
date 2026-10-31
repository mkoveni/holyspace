<template>
  <header class="directory-heading">
    <div>
      <div class="directory-eyebrow"><span class="directory-spark" />{{ eyebrow }}</div>
      <h1>{{ title }}</h1>
      <p>{{ subtitle }}</p>
    </div>

    <div class="directory-heading__actions"><slot /></div>
  </header>
</template>
<script setup lang="ts">
  defineProps<{ eyebrow: string, title: string, subtitle: string }>()
</script>
