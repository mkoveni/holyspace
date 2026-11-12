<template>
  <section class="directory-surface event-section">
    <div class="d-flex ga-3 align-center flex-wrap mb-4">
      <div class="mr-auto">
        <span class="directory-eyebrow">FIRST-TIME VISITORS</span>
        <h2 class="mt-2 mb-0">Visitor check-in</h2>
      </div>

      <v-btn
        v-if="visitorsRead"
        :loading="loading"
        prepend-icon="mdi-refresh"
        variant="text"
        @click="load"
      >
        Refresh
      </v-btn>
    </div>

    <v-alert
      v-if="error"
      class="mb-4"
      role="alert"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <div v-if="manage" class="visit-link mb-6">
      <template v-if="link?.enabled && link.token">
        <canvas
          ref="canvas"
          aria-label="QR code that opens the visitor check-in form"
          class="visit-link__qr"
          role="img"
        />

        <div class="visit-link__body">
          <p class="mb-2">
            Show or print this code at the door. Visitors scan it with their
            phone, no app or account needed. It works from two hours before the
            gathering until it ends.
          </p>

          <code class="visit-link__url">{{ url }}</code>

          <div class="d-flex ga-2 flex-wrap mt-3">
            <v-btn prepend-icon="mdi-content-copy" variant="tonal" @click="copy">
              {{ copied ? 'Copied' : 'Copy link' }}
            </v-btn>

            <v-btn prepend-icon="mdi-printer-outline" variant="tonal" @click="print">
              Print code
            </v-btn>

            <v-btn
              :disabled="busy"
              prepend-icon="mdi-autorenew"
              variant="tonal"
              @click="change('ROTATE')"
            >
              New code
            </v-btn>

            <v-btn
              color="error"
              :disabled="busy"
              prepend-icon="mdi-link-off"
              variant="text"
              @click="change('DISABLE')"
            >
              Turn off
            </v-btn>
          </div>

          <p class="directory-muted mt-3 mb-0">
            "New code" stops every earlier printout or shared link from working.
          </p>

          <div class="visit-link__messages mt-4">
            <strong>Welcome and follow-up emails</strong>

            <p class="directory-muted mb-2">
              <template v-if="link.messages">
                On. Visitors who give an email address get a welcome message
                now and a thank-you the next morning, using the "Events ·
                Welcome" and "Events · Following up" templates in
                Communications. Removing a visitor cancels what is still waiting.
              </template>

              <template v-else>
                Off. Turning this on approves the two Communications templates
                to be emailed to visitors who gave their email address and agreed
                to be contacted.
              </template>
            </p>

            <v-btn
              v-if="link.messages"
              :disabled="busy"
              prepend-icon="mdi-email-off-outline"
              variant="tonal"
              @click="change('MESSAGES_OFF')"
            >
              Turn off emails
            </v-btn>

            <v-btn
              v-else-if="canSend"
              :disabled="busy"
              prepend-icon="mdi-email-check-outline"
              variant="tonal"
              @click="change('MESSAGES_ON')"
            >
              Turn on emails
            </v-btn>

            <span v-else class="directory-muted">
              Ask someone who can approve event communications to turn this on.
            </span>
          </div>
        </div>
      </template>

      <template v-else>
        <p class="mb-3">
          Visitor check-in is off for this gathering. Turning it on needs
          registration to be open, because visitors are booked at the door like
          any walk-in.
        </p>

        <v-btn
          color="primary"
          :disabled="busy"
          prepend-icon="mdi-qrcode"
          @click="change('ENABLE')"
        >
          Turn on visitor check-in
        </v-btn>
      </template>
    </div>

    <template v-if="visitorsRead">
      <div class="d-flex ga-3 align-center flex-wrap mb-3">
        <h3 class="mr-auto mb-0">
          Visitors who checked in
          <v-chip v-if="page && page.pending > 0" class="ml-2" color="warning" size="small">
            {{ page.pending }} to review
          </v-chip>
        </h3>

        <v-btn-toggle
          v-model="filter"
          density="compact"
          divided
          mandatory
          variant="outlined"
          @update:model-value="apply"
        >
          <v-btn value="ALL">All</v-btn>
          <v-btn value="PENDING">To review</v-btn>
          <v-btn value="CONFIRMED">Confirmed</v-btn>
          <v-btn value="REMOVED">Removed</v-btn>
        </v-btn-toggle>
      </div>

      <p v-if="page && page.visitors.length === 0" class="directory-muted">
        No visitors have checked themselves in yet.
      </p>

      <v-table v-else-if="page" density="comfortable">
        <thead>
          <tr>
            <th>Name</th>
            <th>Contact</th>
            <th>Heard about us</th>
            <th>Checked in</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="visitor in page.visitors" :key="visitor.registrationId">
            <td>
              {{ visitor.fullName }}
              <v-chip v-if="visitor.firstTime" class="ml-1" color="primary" size="x-small">First time</v-chip>
              <span v-else class="directory-muted"> (returning)</span>
              <div v-if="visitor.partyAdults" class="directory-muted">{{ visitor.partyAdults }} adults</div>

              <ul v-if="visitor.children.length > 0" class="visitor-children">
                <li v-for="child in visitor.children" :key="child.id">
                  <v-icon icon="mdi-human-child" size="16" />
                  {{ child.firstName }}, {{ age(child.birthDate) }}

                  <v-chip
                    v-if="child.handoverStatus !== 'PENDING'"
                    class="ml-1"
                    size="x-small"
                  >
                    {{ child.handoverStatus === 'HANDED_OVER' ? "With Children's Ministry" : 'Dismissed' }}
                  </v-chip>

                  <template v-else-if="manage && visitor.reviewStatus !== 'REMOVED'">
                    <v-btn
                      class="ml-1"
                      :disabled="busy"
                      size="x-small"
                      variant="tonal"
                      @click="handOver(child, 'HANDED_OVER')"
                    >
                      Handed to Children's Ministry
                    </v-btn>

                    <v-btn
                      :disabled="busy"
                      size="x-small"
                      variant="text"
                      @click="handOver(child, 'DISMISSED')"
                    >
                      Dismiss
                    </v-btn>
                  </template>

                  <span v-else class="directory-muted"> waiting</span>
                </li>
              </ul>
            </td>

            <td>
              <div v-if="visitor.mobile">{{ visitor.mobile }}</div>
              <div v-if="visitor.email">{{ visitor.email }}</div>
            </td>

            <td>{{ howHeard(visitor.howHeard) }}</td>
            <td>{{ dateLabel(visitor.checkedInAt) }}</td>

            <td>
              <v-chip :color="statusColor(visitor.reviewStatus)" size="small">
                {{ statusLabel(visitor.reviewStatus) }}
              </v-chip>

              <div v-if="visitor.attendanceStatus === 'WAITLISTED'" class="directory-muted">Waitlisted</div>
            </td>

            <td class="text-right">
              <template v-if="manage && visitor.reviewStatus !== 'REMOVED'">
                <v-btn
                  v-if="visitor.reviewStatus === 'PENDING'"
                  :disabled="busy"
                  size="small"
                  variant="tonal"
                  @click="review(visitor, 'CONFIRM')"
                >
                  Confirm
                </v-btn>

                <v-btn
                  v-if="canConvert"
                  class="ml-1"
                  :disabled="busy"
                  size="small"
                  variant="tonal"
                  @click="convert(visitor)"
                >
                  Make member
                </v-btn>

                <v-btn
                  class="ml-1"
                  color="error"
                  :disabled="busy"
                  size="small"
                  variant="text"
                  @click="review(visitor, 'REMOVE')"
                >
                  Remove
                </v-btn>
              </template>
            </td>
          </tr>
        </tbody>
      </v-table>

      <div v-if="page && page.total > 25" class="d-flex ga-2 justify-end mt-3">
        <v-btn :disabled="offset === 0" variant="text" @click="move(-25)">Previous</v-btn>
        <v-btn :disabled="offset + 25 >= page.total" variant="text" @click="move(25)">Next</v-btn>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
  import QRCode from 'qrcode'
  import { computed, nextTick, onMounted, ref, watch } from 'vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    dateLabel,
    errorMessage,
    eventApi,
    type VisitLink,
    type VisitLinkAction,
    type Visitor,
    type VisitorChild,
    type VisitorPage,
  } from '../api'

  const props = defineProps<{ occurrenceId: string }>()
  const emit = defineEmits<{ changed: [] }>()
  const auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('events.attendance.manage')),
        canConvert = computed(() => manage.value && auth.hasPermission('people.manage')),
        visitorsRead = computed(() => auth.hasPermission('events.visitors.read')),
        canSend = computed(() => auth.hasPermission('events.communications.send'))
  const link = ref<VisitLink>(),
        page = ref<VisitorPage>(),
        filter = ref('ALL'),
        offset = ref(0),
        loading = ref(false),
        busy = ref(false),
        copied = ref(false),
        error = ref(''),
        canvas = ref<HTMLCanvasElement>()
  const url = computed(() =>
    link.value?.token ? `${window.location.origin}/visit/${link.value.token}` : '',
  )
  const sources: Record<string, string> = {
    FRIEND_OR_FAMILY: 'Friend or family',
    SOCIAL_MEDIA: 'Social media',
    WEBSITE: 'Website',
    PASSING_BY: 'Passing by',
    EVENT: 'An event',
    OTHER: 'Other',
  }
  const howHeard = (value: string | null) => (value ? sources[value] ?? value : '')
  function statusLabel (status: Visitor['reviewStatus']) {
    return ({ PENDING: 'To review', CONFIRMED: 'Confirmed', REMOVED: 'Removed' })[status]
  }
  function statusColor (status: Visitor['reviewStatus']) {
    return ({ PENDING: 'warning', CONFIRMED: 'success', REMOVED: 'default' })[status]
  }

  async function load () {
    loading.value = true
    try {
      const [nextLink, nextPage] = await Promise.all([
        manage.value ? eventApi.visitLink(props.occurrenceId) : undefined,
        visitorsRead.value
          ? eventApi.visitors(props.occurrenceId, filter.value === 'ALL' ? undefined : filter.value, offset.value)
          : undefined,
      ])
      link.value = nextLink
      page.value = nextPage
      error.value = ''
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      loading.value = false
    }
  }
  function apply () {
    offset.value = 0
    load()
  }
  function move (by: number) {
    offset.value += by
    load()
  }
  async function change (action: VisitLinkAction) {
    if (action === 'ROTATE' && !window.confirm('Replace the code? Printed copies and shared links will stop working.')) return
    busy.value = true
    try {
      link.value = await eventApi.configureVisitLink(props.occurrenceId, action)
      error.value = ''
      emit('changed')
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      busy.value = false
    }
  }
  function age (birthDate: string) {
    const born = new Date(`${birthDate}T00:00:00`)
    const now = new Date()
    let years = now.getFullYear() - born.getFullYear()
    if (now < new Date(now.getFullYear(), born.getMonth(), born.getDate())) years -= 1
    if (years >= 2) return `${years} years`
    const months = years * 12 + now.getMonth() - born.getMonth() - (now.getDate() < born.getDate() ? 1 : 0)
    return `${Math.max(months, 0)} months`
  }
  async function handOver (child: VisitorChild, decision: 'HANDED_OVER' | 'DISMISSED') {
    busy.value = true
    try {
      await eventApi.handOverVisitorChild(child.id, decision)
      error.value = ''
      await load()
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      busy.value = false
    }
  }
  async function review (visitor: Visitor, decision: 'CONFIRM' | 'REMOVE') {
    let reason = 'Confirmed by the welcome team'
    if (decision === 'REMOVE') {
      const entered = window.prompt(`Why remove ${visitor.fullName}? This also voids their attendance.`)
      if (!entered?.trim()) return
      reason = entered.trim()
    }
    busy.value = true
    try {
      await eventApi.reviewVisitor(visitor.registrationId, {
        decision,
        reason,
        expectedAttendanceVersion: visitor.attendanceVersion ?? undefined,
      })
      error.value = ''
      await load()
      emit('changed')
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      busy.value = false
    }
  }
  async function convert (visitor: Visitor) {
    const invite = !!visitor.email && auth.hasPermission('events.communications.send')
      && window.confirm(`Make ${visitor.fullName} a member? Press OK to also email them an invitation to the app, or Cancel to choose again.`)
    if (!invite && !window.confirm(`Make ${visitor.fullName} a member without an app invitation?`)) return
    busy.value = true
    try {
      await eventApi.convertVisitor(visitor.registrationId, invite)
      error.value = ''
      await load()
      emit('changed')
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      busy.value = false
    }
  }
  async function copy () {
    try {
      await navigator.clipboard.writeText(url.value)
      copied.value = true
      setTimeout(() => {
        copied.value = false
      }, 2000)
    } catch {
      error.value = 'Copying is not available here. Select the link and copy it by hand.'
    }
  }
  function print () {
    const image = canvas.value?.toDataURL('image/png')
    if (!image) return
    const sheet = window.open('', '_blank', 'width=700,height=900')
    if (!sheet) {
      error.value = 'Allow pop-ups to print the code.'
      return
    }
    const doc = sheet.document
    doc.title = 'Visitor check-in'
    const body = doc.body
    body.style.cssText = 'font-family:sans-serif;text-align:center;padding:48px'
    const heading = doc.createElement('h1')
    heading.textContent = 'First time with us?'
    const hint = doc.createElement('p')
    hint.textContent = 'Scan this code with your phone camera to check in.'
    const picture = doc.createElement('img')
    picture.src = image
    picture.style.cssText = 'width:420px;height:420px'
    body.append(heading, hint, picture)
    picture.addEventListener('load', () => {
      sheet.print()
    })
  }
  async function draw () {
    await nextTick()
    if (canvas.value && url.value) {
      await QRCode.toCanvas(canvas.value, url.value, { width: 220, margin: 1 })
    }
  }
  watch(url, draw)
  onMounted(async () => {
    await load()
    await draw()
  })
</script>

<style scoped>
.visit-link {
  display: flex;
  gap: 24px;
  align-items: flex-start;
  flex-wrap: wrap;
}
.visit-link__qr {
  width: 220px;
  height: 220px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: #fff;
}
.visit-link__body {
  flex: 1 1 280px;
  min-width: 0;
}
.visit-link__messages {
  padding-top: 12px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.visitor-children {
  margin: 4px 0 0;
  padding: 0;
  list-style: none;
  font-size: 0.85rem;
}
.visit-link__url {
  display: block;
  overflow-wrap: anywhere;
  font-size: 0.8rem;
}
</style>
