<template>
  <v-dialog
    v-model="open"
    max-width="800"
    scrollable
  >
    <v-card class="directory-form">
      <div class="directory-form__header">
        <div>
          <span class="directory-eyebrow">EVERY STEP ACCOUNTED FOR</span>
          <h2>Attendance journey</h2>

          <p>
            {{ workspace.data.value?.visits[0]?.childName || childName }} ·
            complete room history for this attendance record
          </p>
        </div>

        <v-btn
          aria-label="Close attendance journey"
          icon="mdi-close"
          variant="text"
          @click="open = false"
        />
      </div>

      <v-card-text>
        <v-alert
          v-if="workspace.error.value"
          type="error"
          variant="tonal"
        >
          {{ workspace.error.value }}
          <v-btn
            variant="text"
            @click="workspace.load"
          >
            Retry
          </v-btn>
        </v-alert>

        <v-progress-linear
          v-if="workspace.loading.value"
          color="primary"
          indeterminate
        />

        <ol class="attendance-journey">
          <li
            v-for="visit in workspace.data.value?.visits || []"
            :key="visit.id"
          >
            <span class="directory-eyebrow">
              {{ visit.programme }} · {{ visit.location }}
            </span>

            <h3>{{ visit.ministryClass }} · {{ visit.room }}</h3>
            <p>Arrived {{ timeLabel(visit.arrivedAt, visit.timeZone) }}</p>

            <p v-if="visit.leftAt">
              {{
                visit.outcome === 'TRANSFERRED'
                  ? 'Transferred out'
                  : 'Collected'
              }}
              {{ timeLabel(visit.leftAt, visit.timeZone) }}
            </p>

            <v-chip
              v-else
              color="primary"
              size="small"
              variant="tonal"
            >
              Still attending
            </v-chip>

            <p v-if="visit.supervisorCollection">
              Supervisor label exception · identity and pickup authority
              verified
            </p>

            <small class="directory-muted">{{ visit.timeZone }}</small>

            <div>
              <v-btn
                size="small"
                :to="`/children/sessions/${visit.sessionId}`"
                variant="text"
              >
                Open session
              </v-btn>
            </div>
          </li>
        </ol>

        <div class="journey-paging">
          <v-btn
            :disabled="!offset || workspace.loading.value"
            variant="text"
            @click="offset -= 50"
          >
            Earlier steps
          </v-btn>

          <span>
            {{ workspace.data.value?.summary.visits || 0 }} room visits
          </span>

          <v-btn
            :disabled="
              workspace.loading.value ||
                offset + 50 >= (workspace.data.value?.summary.visits || 0)
            "
            variant="text"
            @click="offset += 50"
          >
            Later steps
          </v-btn>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>
<script setup lang="ts">
  import type { AttendanceReport } from '../types'
  import { ref, watch } from 'vue'
  import { childrenApi } from '../api'
  import { timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ attendanceId: string, childName: string }>()
  const offset = ref(0)
  const workspace = useLoad<AttendanceReport | null>(
    () => childrenApi.attendanceTimeline(props.attendanceId, offset.value),
    null,
  )
  watch(
    () => [open.value, props.attendanceId],
    () => {
      offset.value = 0
      workspace.data.value = null
      if (open.value) void workspace.load()
    },
  )
  watch(offset, () => {
    if (open.value) void workspace.load()
  })
</script>
<style scoped>
  .attendance-journey {
    list-style: none;
    padding: 0 0 0 18px;
    margin: 20px 0;
    border-left: 2px solid #dce7e3;
  }
  .attendance-journey li {
    position: relative;
    padding: 0 0 28px 14px;
  }
  .attendance-journey li::before {
    content: '';
    position: absolute;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #6f8c80;
    left: -24px;
    top: 5px;
  }
  .attendance-journey h3 {
    margin: 8px 0;
  }
  .journey-paging {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }
</style>
