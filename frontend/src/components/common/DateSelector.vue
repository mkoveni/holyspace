<template>
  <v-text-field
    v-bind="$attrs"
    :aria-expanded="open"
    aria-haspopup="dialog"
    :aria-label="label"
    :label="label"
    :model-value="model ? dateLabel(model) : ''"
    prepend-inner-icon="mdi-calendar-outline"
    readonly
    @click:control="show"
    @keydown.enter.prevent="show"
    @keydown.space.prevent="show"
  />

  <v-dialog
    v-model="open"
    :aria-label="label"
    max-width="390"
  >
    <v-card class="directory-form">
      <div class="directory-form__header">
        <div>
          <span class="directory-eyebrow">MAKE TIME FOR WHAT MATTERS</span>
          <h2>{{ label }}</h2>
        </div>

        <v-btn
          aria-label="Close date picker"
          icon="mdi-close"
          variant="text"
          @click="open = false"
        />
      </div>

      <v-date-picker
        v-model="selected"
        color="primary"
        hide-header
        show-adjacent-months
        width="100%"
      />

      <v-card-actions>
        <v-btn
          variant="text"
          @click="selected = new Date()"
        >
          Today
        </v-btn>

        <v-spacer />

        <v-btn
          variant="text"
          @click="open = false"
        >
          Cancel
        </v-btn>

        <v-btn
          color="primary"
          :disabled="!selected"
          variant="flat"
          @click="save"
        >
          Use date
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script setup lang="ts">
  import { ref } from 'vue'
  function dateLabel (value: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
      new Date(value + 'T12:00:00'),
    )
  }
  defineOptions({ inheritAttrs: false })
  defineProps<{ label: string }>()
  const model = defineModel<string>({ required: true })
  const open = ref(false)
  const selected = ref<Date | null>(null)
  function show () {
    selected.value = model.value
      ? new Date(model.value + 'T12:00:00')
      : new Date()
    open.value = true
  }
  function save () {
    const date = selected.value
    if (!date) return
    model.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
    open.value = false
  }
</script>
