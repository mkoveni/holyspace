<template>
  <v-text-field
    ref="field"
    v-bind="$attrs"
    :aria-expanded="open"
    aria-haspopup="dialog"
    :aria-label="label"
    class="date-time-selector"
    :clearable="clearable && !readonly"
    :disabled="disabled"
    :label="label"
    :model-value="dateTimeLabel(model)"
    prepend-inner-icon="mdi-calendar-clock-outline"
    readonly
    :rules="fieldRules"
    @click:clear="model = ''"
    @click:control="show"
    @keydown.enter.prevent="show"
    @keydown.space.prevent="show"
  />

  <v-dialog
    v-model="open"
    :aria-label="label.replace(/\s*\*$/, '')"
    max-width="740"
    scrollable
    @after-leave="restoreFocus"
  >
    <v-card class="date-time-panel">
      <header class="date-time-panel__header">
        <div>
          <span class="date-time-panel__eyebrow">
            MAKE TIME FOR WHAT MATTERS
          </span>

          <h2>{{ label.replace(/\s*\*$/, '') }}</h2>
          <p>Choose a day and a time.</p>
        </div>

        <v-btn
          aria-label="Close date and time picker"
          icon="mdi-close"
          type="button"
          variant="text"
          @click="open = false"
        />
      </header>

      <v-card-text class="date-time-panel__body">
        <div class="date-time-panel__calendar">
          <div class="date-time-panel__shortcuts">
            <v-btn
              v-for="(shortcut, index) in ['Today', 'Tomorrow']"
              :key="shortcut"
              size="small"
              type="button"
              variant="tonal"
              @click="chooseDay(index)"
            >
              {{ shortcut }}
            </v-btn>
          </div>

          <v-date-picker
            v-model="day"
            color="primary"
            :first-day-of-week="1"
            hide-header
            show-adjacent-months
            width="100%"
          />
        </div>

        <aside class="date-time-panel__time">
          <span class="date-time-panel__eyebrow">YOUR TIME</span>

          <div class="date-time-panel__clock">
            {{ hour }}
            <span>:</span>
            {{ minute }}
          </div>

          <div class="date-time-panel__inputs">
            <v-select
              v-model="hour"
              hide-details
              :items="hours"
              label="Hour"
            />

            <v-select
              v-model="minute"
              hide-details
              :items="minutes"
              label="Minute"
            />
          </div>

          <v-btn
            class="mt-4"
            prepend-icon="mdi-clock-outline"
            type="button"
            variant="text"
            @click="chooseNow"
          >
            Use current time
          </v-btn>

          <div class="date-time-panel__zone">
            <v-icon
              icon="mdi-earth"
              size="18"
            />

            <div>
              <strong>Local time</strong>
              <span>{{ timeZone }}</span>
            </div>
          </div>
        </aside>
      </v-card-text>

      <v-alert
        v-if="error"
        class="mx-6 mb-4"
        type="error"
        variant="tonal"
      >
        {{ error }}
      </v-alert>

      <footer class="date-time-panel__footer">
        <v-btn
          type="button"
          variant="text"
          @click="open = false"
        >
          Cancel
        </v-btn>

        <v-btn
          color="primary"
          :disabled="!day"
          type="button"
          variant="flat"
          @click="apply"
        >
          Use date & time
        </v-btn>
      </footer>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import {
    dateTimeLabel,
    localDateTimeValue,
    parseLocalDateTime,
  } from './date-time/value'

  defineOptions({ inheritAttrs: false })
  const model = defineModel<string>({ required: true })
  const props = withDefaults(
    defineProps<{
      label: string
      rules?: Array<(value: string) => boolean | string>
      disabled?: boolean
      readonly?: boolean
      clearable?: boolean
    }>(),
    { rules: () => [] },
  )
  const field = ref<{ $el: HTMLElement } | null>(null)
  const open = ref(false)
  const day = ref<Date | null>(null)
  const hour = ref('09')
  const minute = ref('00')
  const error = ref('')
  const hours = Array.from({ length: 24 }, (_, index) =>
    String(index).padStart(2, '0'),
  )
  const minutes = Array.from({ length: 60 }, (_, index) =>
    String(index).padStart(2, '0'),
  )
  const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone
  const fieldRules = computed(() => [
    ...props.rules.map(rule => () => rule(model.value)),
    () =>
      !model.value
      || !!parseLocalDateTime(model.value)
      || 'Choose a valid date and time.',
  ])

  function restoreFocus () {
    if (!props.disabled) field.value?.$el.querySelector('input')?.focus()
  }
  function show () {
    if (!props.disabled && !props.readonly) open.value = true
  }
  function setDraft (date: Date) {
    day.value = date
    hour.value = String(date.getHours()).padStart(2, '0')
    minute.value = String(date.getMinutes()).padStart(2, '0')
    error.value = ''
  }
  function chooseNow () {
    setDraft(new Date())
  }
  function chooseDay (offset: number) {
    const date = new Date()
    date.setDate(date.getDate() + offset)
    day.value = date
    error.value = ''
  }
  function apply () {
    if (!day.value) return
    const date = localDateTimeValue(day.value).slice(0, 10)
    const value = `${date}T${hour.value}:${minute.value}`
    if (!parseLocalDateTime(value)) {
      error.value
        = 'This time is unavailable in your time zone. Choose another time.'
      return
    }
    model.value = value
    open.value = false
  }
  watch(open, value => {
    if (value) setDraft(parseLocalDateTime(model.value) ?? new Date())
  })
</script>

<style scoped lang="scss">
  .date-time-selector :deep(input) {
    cursor: pointer;
  }
  .date-time-panel {
    overflow: hidden;
  }
  .date-time-panel__header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 28px;
    background: var(--workspace-tint);
    border-bottom: 1px solid var(--workspace-border);
    h2 {
      margin: 8px 0;
      font-size: 1.5rem;
      font-weight: 600;
      letter-spacing: -0.05em;
    }
    p {
      margin: 0;
      color: var(--workspace-muted);
    }
  }
  .date-time-panel__eyebrow {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    color: var(--workspace-muted);
  }
  .date-time-panel__body {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
    padding: 0 !important;
  }
  .date-time-panel__calendar {
    padding: 20px 12px 8px;
    min-width: 0;
  }
  .date-time-panel__shortcuts {
    display: flex;
    gap: 8px;
    padding: 0 12px;
  }
  .date-time-panel__time {
    background: var(--workspace-soft);
    border-left: 1px solid var(--workspace-border);
    padding: 28px 20px;
    min-width: 0;
  }
  .date-time-panel__clock {
    font-size: 3.25rem;
    font-weight: 500;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.06em;
    margin: 14px 0 22px;
    color: var(--workspace-blue);
    span {
      opacity: 0.4;
      padding: 0 3px;
    }
  }
  .date-time-panel__inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .date-time-panel__zone {
    display: flex;
    gap: 10px;
    margin-top: 28px;
    color: var(--workspace-muted);
    font-size: 0.75rem;
    strong,
    span {
      display: block;
      overflow-wrap: anywhere;
    }
    strong {
      color: var(--workspace-ink);
      margin-bottom: 4px;
    }
  }
  .date-time-panel__footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 18px 24px;
    border-top: 1px solid var(--workspace-border);
  }
  @media (max-width: 600px) {
    .date-time-panel__body {
      grid-template-columns: minmax(0, 1fr);
    }
    .date-time-panel__header {
      padding: 20px;
    }
    .date-time-panel__time {
      border-left: 0;
      border-top: 1px solid var(--workspace-border);
      padding: 20px;
    }
    .date-time-panel__clock {
      font-size: 2.5rem;
      margin: 8px 0 16px;
    }
    .date-time-panel__zone {
      margin-top: 16px;
    }
    .date-time-panel__calendar {
      padding: 12px 4px 0;
    }
  }
</style>
