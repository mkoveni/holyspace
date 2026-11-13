<template>
  <div class="question-fields">
    <fieldset v-for="q in questions" :key="q.id" class="question-fields__item">
      <legend>{{ q.label }}<span v-if="q.required" aria-hidden="true"> *</span></legend>

      <v-radio-group v-if="q.type === 'SINGLE_CHOICE'" v-model="model[q.id]" :aria-label="q.label" hide-details>
        <v-radio v-for="o in q.options" :key="o.id" :label="o.label" :value="o.id" />
      </v-radio-group>

      <div v-else-if="q.type === 'MULTIPLE_CHOICE'">
        <v-checkbox
          v-for="o in q.options"
          :key="o.id"
          v-model="model[q.id]"
          density="compact"
          hide-details
          :label="o.label"
          :value="o.id"
        />
      </div>

      <v-checkbox
        v-else-if="q.type === 'CHECKBOX'"
        v-model="model[q.id]"
        density="compact"
        hide-details
        label="Yes"
      />

      <v-rating
        v-else-if="q.type === 'RATING'"
        v-model.number="model[q.id] as number"
        :aria-label="q.label"
        hover
        length="5"
      />

      <v-textarea
        v-else-if="q.type === 'LONG_TEXT'"
        v-model="model[q.id]"
        :aria-label="q.label"
        auto-grow
        maxlength="4000"
        rows="3"
        variant="outlined"
      />

      <v-text-field
        v-else
        v-model="model[q.id]"
        :aria-label="q.label"
        maxlength="500"
        :type="inputType(q.type)"
        variant="outlined"
      />
    </fieldset>
  </div>
</template>

<script lang="ts" setup>
  import type { PublicQuestion, QuestionType } from '@/forms/api'

  defineProps<{ questions: PublicQuestion[] }>()
  const model = defineModel<Record<string, unknown>>({ required: true })

  function inputType (type: QuestionType): string {
    return { EMAIL: 'email', NUMBER: 'number', DATE: 'date' }[type as 'EMAIL' | 'NUMBER' | 'DATE'] ?? 'text'
  }
</script>

<style scoped>
.question-fields__item { border: 0; padding: 0; margin: 0 0 20px; }
.question-fields__item legend { font-weight: 600; margin-bottom: 6px; }
</style>
