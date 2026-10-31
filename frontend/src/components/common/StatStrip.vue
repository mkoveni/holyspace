<template>
  <dl class="stat-strip">
    <div v-for="item in items" :key="item.label" class="stat-strip__item">
      <dt>{{ item.label }}</dt>
      <dd>{{ item.value }}</dd>
    </div>
  </dl>
</template>

<script lang="ts" setup>
  defineProps<{ items: { label: string, value: number | string }[] }>()
</script>
