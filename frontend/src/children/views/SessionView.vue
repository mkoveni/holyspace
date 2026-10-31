<template>
  <div class="directory-workspace">
    <v-btn
      class="mb-3"
      prepend-icon="mdi-arrow-left"
      :to="station ? '/children-station' : '/children/sessions'"
      variant="text"
    >
      {{ station ? 'Check-in sessions' : 'All sessions' }}
    </v-btn>

    <DirectoryHeading
      eyebrow="EVERY ARRIVAL MATTERS"
      :subtitle="
        view
          ? `${view.roomName} · ${timeLabel(view.session.startsAt, view.timeZone)} · ${view.timeZone}`
          : 'Getting ready for a warm welcome.'
      "
      :title="view?.className || 'Session'"
    >
      <v-btn
        aria-label="Refresh session"
        icon="mdi-refresh"
        :loading="workspace.loading.value"
        variant="text"
        @click="refreshSession"
      />

      <v-btn
        v-if="canManage && view"
        color="primary"
        variant="flat"
        @click="statusEditor = true"
      >
        {{
          view.session.status === 'PLANNED'
            ? 'Open for arrivals'
            : 'Session status'
        }}
      </v-btn>

      <v-btn
        v-if="canManage && view"
        prepend-icon="mdi-check-all"
        variant="tonal"
        @click="scrollToCloseout"
      >
        Closeout review
      </v-btn>

      <v-btn
        v-if="canManage && view"
        prepend-icon="mdi-calendar-sync-outline"
        variant="tonal"
        @click="repeatEditor = true"
      >
        Repeat session
      </v-btn>

      <v-btn
        v-if="
          auth.hasPermission('children.incidents.record') ||
            auth.hasPermission('children.incidents.manage')
        "
        prepend-icon="mdi-shield-alert-outline"
        :to="{ path: '/children/incidents', query: { session: id } }"
        variant="tonal"
      >
        Incidents & follow-up
      </v-btn>
    </DirectoryHeading>

    <v-alert
      v-if="workspace.error.value"
      type="error"
      variant="tonal"
    >
      {{ workspace.error.value }}
      <v-btn
        variant="text"
        @click="load"
      >
        Retry
      </v-btn>
    </v-alert>

    <v-skeleton-loader
      v-if="workspace.loading.value && !view"
      type="article"
    />

    <template v-if="view">
      <div
        class="directory-banner"
        :class="{ 'directory-banner--sage': !view.staffingNeedsReview }"
      >
        <div>
          <span class="directory-banner__icon">
            <v-icon icon="mdi-shield-check-outline" />
          </span>

          <div>
            <strong>{{ statusLabel(view.session.status) }}</strong>

            <p>
              {{
                view.staffingNeedsReview
                  ? 'Review team presence and clearance before admitting more children.'
                  : `${view.occupancy} children attending · ${view.effectiveCapacity} effective capacity`
              }}
            </p>
          </div>
        </div>

        <span class="directory-banner__caption">
          {{ view.staff.filter((s) => s.present).length }} TEAM MEMBERS PRESENT
        </span>
      </div>

      <SessionDayOverview
        :blocked="
          rows.filter((child) => !child.ready && !child.attendance).length
        "
        :can-station="canStation"
        :expected="tab === 'expected'"
        :now="now"
        :ready="rows.filter((child) => child.ready && !child.attendance).length"
        :total="workspace.data.value?.day.total || 0"
        :view="view"
      />

      <div
        v-if="canStation"
        class="session-day-guide directory-surface children-section"
      >
        <span>
          <strong>1</strong>
          Welcome & check in
        </span>

        <v-icon
          icon="mdi-chevron-right"
          size="18"
        />

        <span>
          <strong>2</strong>
          Create & print pickup label
        </span>

        <v-icon
          icon="mdi-chevron-right"
          size="18"
        />

        <span>
          <strong>3</strong>
          Verify collector & complete handover
        </span>
      </div>

      <div class="children-split">
        <div>
          <section
            v-if="canStation"
            class="directory-surface"
          >
            <div class="directory-toolbar">
              <v-tabs
                v-model="tab"
                color="primary"
              >
                <v-tab value="expected">Expected children</v-tab>

                <v-tab value="attending">Attending</v-tab>

                <v-tab value="history">Collected</v-tab>
              </v-tabs>
            </div>

            <v-alert
              v-if="action.error.value"
              class="ma-4"
              type="error"
              variant="tonal"
            >
              {{ action.error.value }}
              <v-btn
                variant="text"
                @click="refreshSession"
              >
                Refresh
              </v-btn>
            </v-alert>

            <div class="directory-toolbar">
              <FamilyQrScanner
                kind="arrival"
                :resource-id="props.id"
                @scanned="reviewFamilyArrival"
              />

              <v-text-field
                v-model="search"
                aria-label="Search session children"
                class="directory-search"
                clearable
                density="compact"
                hide-details
                placeholder="Find a child in this session…"
                prepend-inner-icon="mdi-magnify"
              />
            </div>

            <div class="directory-table-scroll children-roster">
              <table class="directory-table">
                <thead>
                  <tr>
                    <th>Child</th>
                    <th>{{ tab === 'expected' ? 'Welcome' : 'Arrival' }}</th>
                    <th>Actions</th>
                  </tr>
                </thead>

                <tbody>
                  <tr
                    v-for="child in rows"
                    :key="child.attendance?.id || child.id"
                  >
                    <td>
                      <span class="directory-identity">
                        <span class="directory-avatar directory-avatar--1">
                          {{
                            child.name
                              .split(' ')
                              .map((n) => n[0])
                              .slice(0, 2)
                              .join('')
                          }}
                        </span>

                        <span>
                          <strong>{{ child.name }}</strong>

                          <small>
                            {{
                              child.attendance?.checkedOutAt
                                ? child.attendance.exceptionUsed
                                  ? 'Collected with supervisor review'
                                  : 'Safely collected'
                                : child.attendance
                                  ? 'With the team'
                                  : child.ready
                                    ? 'Prepared for this session'
                                    : 'Needs coordinator attention'
                            }}
                          </small>

                          <small
                            v-if="child.issues.length > 0"
                            class="session-day-mobile-issues"
                          >
                            {{ child.issues.join(' ') }}
                          </small>
                        </span>
                      </span>
                    </td>

                    <td class="directory-muted">
                      <ul
                        v-if="child.issues.length > 0"
                        class="session-day-issues"
                      >
                        <li
                          v-for="issue in child.issues"
                          :key="issue"
                        >
                          {{ issue }}
                        </li>
                      </ul>

                      <span v-else>
                        {{
                          child.attendance
                            ? timeLabel(
                              child.attendance.arrivedAt,
                              view.timeZone,
                            )
                            : child.ready
                              ? 'Requirements checked for this session'
                              : 'Review before arrival'
                        }}
                      </span>
                    </td>

                    <td>
                      <v-btn
                        v-if="!child.attendance"
                        :aria-describedby="
                          !canAdmit || !child.ready || action.busy.value
                            ? `arrival-reason-${child.id}`
                            : undefined
                        "
                        color="primary"
                        :disabled="
                          !canAdmit || !child.ready || action.busy.value
                        "
                        size="small"
                        variant="tonal"
                        @click="checkIn(child.id, child.name)"
                      >
                        Check in
                      </v-btn>

                      <small
                        v-if="
                          !child.attendance &&
                            (!canAdmit || !child.ready || action.busy.value)
                        "
                        :id="`arrival-reason-${child.id}`"
                        class="session-arrival-reason"
                      >
                        {{
                          action.busy.value
                            ? 'Saving an arrival…'
                            : arrivalReason ||
                              child.issues[0] ||
                              'Complete this child’s preparation first.'
                        }}
                      </small>

                      <v-btn
                        v-if="
                          !child.attendance &&
                            !child.ready &&
                            auth.hasPermission('children.read') &&
                            auth.hasPermission('people.read')
                        "
                        size="small"
                        :to="`/children/participants/${child.id}`"
                        variant="text"
                      >
                        Review readiness
                      </v-btn>

                      <template
                        v-if="
                          child.attendance && !child.attendance.checkedOutAt
                        "
                      >
                        <v-btn
                          size="small"
                          variant="text"
                          @click="openLabel(child.attendance, child.name)"
                        >
                          {{
                            child.attendance.labelIssued
                              ? 'Replace label'
                              : 'Issue label'
                          }}
                        </v-btn>

                        <v-btn
                          color="primary"
                          size="small"
                          variant="tonal"
                          @click="handover(child.attendance, child.name, false)"
                        >
                          Collect
                        </v-btn>

                        <v-menu>
                          <template #activator="{ props: menuProps }">
                            <v-btn
                              v-bind="menuProps"
                              :aria-label="`More actions for ${child.name}`"
                              icon="mdi-dots-horizontal"
                              size="small"
                              variant="text"
                            />
                          </template>

                          <v-list>
                            <v-list-item
                              v-if="auth.hasPermission('children.support')"
                              title="Care information"
                              @click="openSupport(child.attendance, child.name)"
                            />

                            <v-list-item
                              v-if="auth.hasPermission('children.supervise')"
                              title="Supervised collection"
                              @click="
                                handover(child.attendance, child.name, true)
                              "
                            />

                            <v-list-item
                              v-if="auth.hasPermission('children.supervise')"
                              title="Transfer to another session"
                              @click="
                                openTransfer(child.attendance, child.name)
                              "
                            />

                            <v-list-item
                              v-if="
                                auth.hasPermission('children.read') &&
                                  auth.hasPermission('people.read')
                              "
                              title="Ministry profile"
                              :to="`/children/participants/${child.id}`"
                            />
                          </v-list>
                        </v-menu>
                      </template>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <DirectoryEmpty
              v-if="rows.length === 0"
              :description="
                tab === 'expected'
                  ? 'A coordinator can enrol children and place them in this class.'
                  : 'Refresh the session to see the latest attendance.'
              "
              :title="
                search
                  ? 'No child matches this search'
                  : tab === 'expected'
                    ? 'A little room to grow'
                    : tab === 'attending'
                      ? 'Ready for the first hello'
                      : 'Safe handovers appear here'
              "
            />

            <footer class="directory-pagination">
              <span>
                {{ workspace.data.value?.day.total || 0 }} matching children ·
                100 per page
              </span>

              <div>
                <v-btn
                  :disabled="!pageOffset"
                  variant="text"
                  @click="pageOffset -= 100"
                >
                  Previous
                </v-btn>

                <v-btn
                  :disabled="
                    pageOffset + 100 >= (workspace.data.value?.day.total || 0)
                  "
                  variant="text"
                  @click="pageOffset += 100"
                >
                  Next
                </v-btn>
              </div>
            </footer>
          </section>

          <section
            v-else
            class="directory-surface children-section"
          >
            <h2>Attendance is restricted</h2>

            <p>
              Station permission is required to access children’s attendance and
              operate check-in.
            </p>
          </section>
        </div>

        <aside>
          <section class="directory-surface children-section">
            <div class="children-section__heading">
              <h2>The caring team</h2>

              <v-btn
                v-if="canManage"
                size="small"
                variant="text"
                @click="staffEditor = true"
              >
                Assign staff
              </v-btn>

              <v-btn
                v-if="canManage"
                size="small"
                variant="text"
                @click="openServing"
              >
                From serving plan
              </v-btn>
            </div>

            <div
              v-for="member in view.staff"
              :key="member.personId"
              class="children-list-row"
            >
              <div>
                <strong>{{ member.name }}</strong>

                <small>
                  {{ member.role === 'LEADER' ? 'Leader' : 'Assistant' }} ·
                  {{ member.present ? 'Present' : 'Not present' }}
                </small>

                <small
                  v-for="issue in member.issues"
                  :key="issue"
                  class="text-error"
                >
                  {{ issue }}
                </small>
              </div>

              <v-btn
                v-if="canManage && !terminal"
                :disabled="action.busy.value"
                size="small"
                variant="text"
                @click="presence(member.personId, member.role, !member.present)"
              >
                {{ member.present ? 'Mark absent' : 'Mark present' }}
              </v-btn>
            </div>

            <p
              v-if="view.staff.length === 0"
              class="children-notice"
            >
              Assign a qualified leader and team before opening this session.
            </p>
          </section>

          <section class="directory-surface children-section">
            <h2>Room to welcome</h2>

            <div class="children-stats">
              <div>
                <strong>{{ view.occupancy }}</strong>

                <small>Children attending</small>
              </div>

              <div>
                <strong>{{ view.effectiveCapacity }}</strong>

                <small>Effective capacity</small>
              </div>
            </div>

            <div class="children-capacity">
              <i
                :style="{
                  width: `${Math.min(100, (view.occupancy / Math.max(1, view.effectiveCapacity)) * 100)}%`,
                }"
              />
            </div>

            <p class="children-notice">
              Room seats, session limits and the qualified team present all
              contribute to capacity.
            </p>
          </section>
        </aside>
      </div>

      <SessionCloseoutPanel
        v-if="canManage"
        :key="id"
        :can-station="canStation"
        :revision="workspace.data.value?.day.checkedAt || ''"
        :view="view"
        @attending="reviewCloseout('attending')"
        @history="reviewCloseout('history')"
        @reload="load"
      />
    </template>

    <RecurringSessionDialog
      v-model="repeatEditor"
      :source="view || null"
      @created="openSeries"
      @reload="load"
    />

    <MinistryDialog
      v-model="statusEditor"
      :submit="saveStatus"
      subtitle="Open when the team is ready. Use Closeout review to finish a session."
      title="Set the tone for this session"
      @reload="load"
      @saved="load"
    >
      <v-alert
        v-if="newStatus === 'OPEN'"
        class="mb-5"
        type="info"
        variant="tonal"
      >
        Confirm a qualified, present team and review the expected children
        before opening.
        <p
          v-if="canStation && tab === 'expected'"
          class="mt-2"
        >
          This page:
          {{ rows.filter((child) => child.ready && !child.attendance).length }}
          prepared;
          {{ rows.filter((child) => !child.ready && !child.attendance).length }}
          need coordinator attention. Preparation is checked again at arrival.
        </p>

        <v-btn
          v-if="canStation && tab !== 'expected'"
          variant="text"
          @click="reviewExpectedChildren"
        >
          Review expected children
        </v-btn>
      </v-alert>

      <v-select
        v-model="newStatus"
        item-title="label"
        item-value="value"
        :items="statusOptions"
        label="Session status *"
        :rules="[required]"
      />

      <v-textarea
        v-model="reason"
        label="Reason *"
        maxlength="500"
        rows="3"
        :rules="[required]"
      />
    </MinistryDialog>

    <MinistryDialog
      v-model="servingOpen"
      :submit="importServing"
      :submit-disabled="!servingId"
      subtitle="Only confirmed, ready assignments linked to this session can be imported. Children Ministry clearance remains required."
      title="Bring the serving plan into the room"
      @reload="loadServing"
      @saved="load"
    >
      <v-alert
        v-if="servingError"
        class="mb-4"
        type="error"
        variant="tonal"
      >
        {{ servingError }}
      </v-alert>

      <v-progress-linear
        v-if="servingLoading"
        class="mb-4"
        color="primary"
        indeterminate
      />

      <v-select
        v-model="servingId"
        item-title="label"
        item-value="id"
        :items="servingOptions"
        label="Serving assignment *"
        :rules="[required]"
      />

      <div
        v-for="candidate in servingCandidates"
        :key="candidate.assignment.assignmentId"
        class="children-list-row"
      >
        <div>
          <strong>
            {{ candidate.assignment.name }} ·
            {{ candidate.assignment.responsibility }}
          </strong>

          <small
            v-for="issue in candidate.assignment.issues"
            :key="issue"
          >
            {{ issue }}
          </small>

          <small v-if="!candidate.workerRegistered">
            Register this person in Spaces &amp; team first.
          </small>
        </div>

        <v-chip
          :color="
            candidate.assignment.ready && candidate.workerRegistered
              ? 'success'
              : 'warning'
          "
          size="small"
        >
          {{
            candidate.assignment.ready && candidate.workerRegistered
              ? 'Ready to import'
              : 'Needs preparation'
          }}
        </v-chip>
      </div>

      <p
        v-if="!servingLoading && servingCandidates.length === 0"
        class="children-form-note"
      >
        No serving assignments are linked to this session. Choose this session
        when planning a shift in the serving schedule.
      </p>

      <v-switch
        v-model="servingPresent"
        color="primary"
        label="The worker is present"
      />
    </MinistryDialog>

    <MinistryDialog
      v-model="staffEditor"
      :submit="saveStaff"
      :submit-disabled="
        auth.hasPermission('children.safeguarding') &&
          !!workerId &&
          (staffLookupBusy || !!staffLookupError || !staffClearance)
      "
      subtitle="Assign a cleared team member and confirm their presence."
      title="A team prepared to care"
      @reload="load"
      @saved="load"
    >
      <PersonPicker
        v-if="auth.hasPermission('people.read')"
        v-model="workerId"
        age-category="ADULT"
        class="mb-5"
        label="Team member *"
        :rules="[required]"
        @selected="staffPerson = $event"
      />

      <p
        v-else
        class="children-form-note"
      >
        People directory access is needed to find a team member.
      </p>

      <v-alert
        v-if="staffLookupBusy"
        class="mb-4"
        type="info"
        variant="tonal"
      >
        Checking team registration…
      </v-alert>

      <v-alert
        v-else-if="staffLookupError"
        class="mb-4"
        type="error"
        variant="tonal"
      >
        {{ staffLookupError }}
        <v-btn
          variant="text"
          @click="checkStaffRegistration"
        >
          Retry team check
        </v-btn>
      </v-alert>

      <v-alert
        v-else-if="workerId && staffMissing"
        class="mb-4"
        type="warning"
        variant="tonal"
      >
        This person is in People but is not registered on the Children Ministry
        team yet. Record their linked account, screening and training before
        assigning them.
        <v-btn
          v-if="auth.hasPermission('children.safeguarding')"
          class="mt-3"
          variant="outlined"
          @click="clearanceEditor = true"
        >
          Record team clearance
        </v-btn>

        <p
          v-else
          class="mt-2"
        >
          Ask a safeguarding coordinator to record team clearance, then try
          again.
        </p>
      </v-alert>

      <v-alert
        v-else-if="staffClearance"
        class="mb-4"
        :type="clearanceCoversSession ? 'success' : 'warning'"
        variant="tonal"
      >
        {{
          clearanceCoversSession
            ? 'Children Ministry team registration and clearance are recorded.'
            : 'Team registration exists, but clearance needs review before this person can count toward session capacity.'
        }}
        <v-btn
          v-if="
            !clearanceCoversSession &&
              auth.hasPermission('children.safeguarding')
          "
          variant="text"
          @click="clearanceEditor = true"
        >
          Review team clearance
        </v-btn>
      </v-alert>

      <v-select
        v-model="role"
        :items="[
          { title: 'Leader', value: 'LEADER' },
          { title: 'Assistant', value: 'ASSISTANT' },
        ]"
        label="Role *"
        :rules="[required]"
      />

      <v-checkbox
        v-model="present"
        color="primary"
        label="This team member is present"
      />

      <p class="children-notice">
        Team registration is separate from a People profile. Role and presence
        are saved for this session.
      </p>
    </MinistryDialog>

    <SetupDialog
      v-if="auth.hasPermission('children.safeguarding')"
      v-model="clearanceEditor"
      :default-person="staffPerson || undefined"
      :locations="[]"
      mode="worker"
      @saved="checkStaffRegistration"
    />

    <MinistryDialog
      v-model="labelEditor"
      save-label="Create label"
      :submit="issueLabel"
      :subtitle="selectedName"
      :title="
        selected?.labelVersion
          ? 'Replace a pickup label'
          : 'Issue a pickup label'
      "
      @reload="reloadSelected"
    >
      <v-alert
        class="mb-5"
        type="info"
        variant="tonal"
      >
        The code is shown once. Print the label for the collector and keep it
        secure.
      </v-alert>

      <v-textarea
        v-if="selected?.labelVersion"
        v-model="reason"
        label="Reason for replacement *"
        maxlength="500"
        rows="3"
        :rules="[required]"
      />
    </MinistryDialog>

    <v-dialog
      v-model="labelPreview"
      max-width="560"
      persistent
      @update:model-value="clearLabel"
    >
      <v-card class="directory-form">
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">A SAFE HANDOVER</span>
            <h2>One little label. Peace of mind.</h2>
          </div>

          <v-btn
            aria-label="Close pickup label"
            icon="mdi-close"
            variant="text"
            @click="clearLabel"
          />
        </div>

        <v-card-text>
          <div
            v-if="issued"
            class="children-label"
          >
            <span class="directory-eyebrow">
              CHILDREN MINISTRY · COLLECTOR COPY
            </span>

            <h2>{{ selectedName }}</h2>
            <p>{{ view?.className }} · {{ view?.roomName }}</p>

            <img
              v-if="qr"
              alt="Pickup code QR label"
              :src="qr"
            >

            <code>{{ issued.pickupCode }}</code>

            <small>
              Label {{ issued.labelVersion }} · Expires
              {{ timeLabel(issued.expiresAt) }}
            </small>
          </div>

          <p class="children-notice">
            Keep this label with the authorized collector. Identity will still
            be checked at collection.
          </p>
        </v-card-text>

        <v-card-actions class="directory-form__footer">
          <v-btn
            variant="text"
            @click="clearLabel"
          >
            Done
          </v-btn>

          <v-btn
            color="primary"
            prepend-icon="mdi-printer-outline"
            variant="flat"
            @click="printLabel"
          >
            Print label
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <HandoverDialog
      v-model="handoverEditor"
      :attendance="selected"
      :child-name="selectedName"
      :supervised="supervised"
      @saved="load"
    />

    <MinistryDialog
      v-model="transferEditor"
      save-label="Transfer child"
      :submit="transfer"
      :subtitle="selectedName"
      title="A thoughtful change of room"
      @reload="reloadSelected"
      @saved="load"
    >
      <v-select
        v-model="targetSessionId"
        item-title="label"
        item-value="id"
        :items="destinations"
        label="Destination session *"
        :rules="[required]"
      />

      <v-textarea
        v-model="reason"
        label="Reason for transfer *"
        maxlength="500"
        :rules="[required]"
      />

      <p class="children-form-note">
        The destination needs current enrolment, placement, consent and
        available capacity. The old label will stop working; issue a new label
        in the destination.
      </p>
    </MinistryDialog>

    <v-dialog
      v-model="supportDialog"
      max-width="650"
      @update:model-value="support = null"
    >
      <v-card class="directory-form">
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">RESTRICTED CARE INFORMATION</span>
            <h2>{{ selectedName }}</h2>
          </div>

          <v-btn
            aria-label="Close care information"
            icon="mdi-close"
            variant="text"
            @click="closeSupport"
          />
        </div>

        <v-card-text>
          <v-skeleton-loader
            v-if="supportLoading"
            type="article"
          />

          <v-alert
            v-if="supportError"
            type="error"
            variant="tonal"
          >
            {{ supportError }}
          </v-alert>

          <template v-if="support">
            <h3>Allergies</h3>
            <p class="mb-5">{{ support.allergies || 'None recorded' }}</p>
            <h3>Care instructions</h3>

            <p class="mb-5">
              {{ support.careInstructions || 'None recorded' }}
            </p>

            <h3>Restricted notes</h3>
            <p class="mb-5">{{ support.restrictedNotes || 'None recorded' }}</p>

            <p class="children-notice">
              Review {{ dateLabel(support.reviewOn) }}. Access has been
              recorded.
            </p>
          </template>
        </v-card-text>
      </v-card>
    </v-dialog>

    <v-dialog
      max-width="540"
      :model-value="!!familyArrival"
      @update:model-value="familyArrival = null"
    >
      <v-card
        rounded="xl"
        title="Confirm child arrival"
      >
        <v-card-text>
          <h3>{{ familyArrival?.child.name }}</h3>

          <p class="directory-muted my-4">
            This reference helps find the child. Staff must confirm the child is
            present. Current consent, placement and safe capacity are checked
            when recording arrival.
          </p>

          <v-alert
            v-for="issue in familyArrival?.issues || []"
            :key="issue"
            class="mb-2"
            type="warning"
          >
            {{ issue }}
          </v-alert>

          <v-alert
            v-if="arrivalReason"
            type="info"
          >
            {{ arrivalReason }}
          </v-alert>
        </v-card-text>

        <v-card-actions>
          <v-btn @click="familyArrival = null">Cancel</v-btn>
          <v-spacer />

          <v-btn
            color="primary"
            :disabled="
              !!familyArrival?.issues.length || !canAdmit || action.busy.value
            "
            @click="confirmFamilyArrival"
          >
            Confirm child arrival
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar
      v-model="success"
      color="primary"
    >
      {{ successMessage }}
    </v-snackbar>
  </div>
</template>
<script setup lang="ts">
  import type { FamilyQr } from '../familyQr'
  import type {
    AttendanceView,
    LabelIssue,
    MinistrySession,
    SupportRecord,
    Worker,
  } from '../types'
  import type { PersonSummary } from '@/directory/types'
  import axios from 'axios'
  import QRCode from 'qrcode'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { useNow } from '@/composables/useNow'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { useTask } from '@/directory/useTask'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import { arrivalBlocker } from '../arrivalAvailability'
  import FamilyQrScanner from '../components/FamilyQrScanner.vue'
  import HandoverDialog from '../components/HandoverDialog.vue'
  import MinistryDialog from '../components/MinistryDialog.vue'
  import RecurringSessionDialog from '../components/RecurringSessionDialog.vue'
  import SessionCloseoutPanel from '../components/SessionCloseoutPanel.vue'
  import SessionDayOverview from '../components/SessionDayOverview.vue'
  import SetupDialog from '../components/SetupDialog.vue'
  import { dateLabel, required, statusLabel, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const props = defineProps<{ id: string }>()
  const auth = useAuthStore()
  const now = useNow()
  const route = useRoute()
  const router = useRouter()
  const repeatEditor = ref(false)
  watch(
    () => props.id,
    () => {
      repeatEditor.value = false
    },
  )
  function openSeries (id: string) {
    void router.push({
      path: '/children/session-series',
      query: { series: id },
    })
  }
  const station = computed(
    () => route.query.station === '1' || !auth.hasPermission('children.read'),
  )
  const canManage = computed(() => auth.hasPermission('children.manage'))
  const canStation = computed(() => auth.hasPermission('children.station'))
  const tab = ref('expected')
  const search = ref('')
  const appliedSearch = ref('')
  let searchTimer: ReturnType<typeof setTimeout> | undefined
  const pageOffset = ref(0)
  const action = useTask({ preserveConflictMessage: true })
  const statusEditor = ref(false)
  interface ServingCandidate {
    assignment: {
      assignmentId: string
      personId: string
      name: string
      responsibility: string
      ready: boolean
      issues: string[]
    }
    workerRegistered: boolean
  }
  const servingOpen = ref(false),
        servingId = ref(''),
        servingPresent = ref(false),
        servingLoading = ref(false),
        servingError = ref(''),
        servingCandidates = ref<ServingCandidate[]>([])
  const servingOptions = computed(() =>
    servingCandidates.value
      .filter(item => item.assignment.ready && item.workerRegistered)
      .map(item => ({
        id: item.assignment.assignmentId,
        label: `${item.assignment.name} · ${item.assignment.responsibility}`,
      })),
  )
  async function loadServing () {
    servingLoading.value = true
    servingError.value = ''
    servingId.value = ''
    try {
      servingCandidates.value = (
        await useApi().get<ServingCandidate[]>(
          `/api/children/sessions/${props.id}/serving-candidates`,
        )
      ).data
    } catch (error) {
      servingError.value = extractErrorMessage(error)
    } finally {
      servingLoading.value = false
    }
  }
  function openServing () {
    servingOpen.value = true
    servingPresent.value = false
    void loadServing()
  }
  async function importServing () {
    const selected = servingCandidates.value.find(
      item => item.assignment.assignmentId === servingId.value,
    )
    if (!view.value || !selected)
      throw new Error('Choose a ready serving assignment.')
    await useApi().post(`/api/children/sessions/${props.id}/serving-staff`, {
      expectedVersion: view.value.session.version,
      assignmentId: servingId.value,
      responsibility: selected.assignment.responsibility,
      present: servingPresent.value,
    })
  }
  const staffEditor = ref(false)
  const labelEditor = ref(false)
  const labelPreview = ref(false)
  const handoverEditor = ref(false)
  const transferEditor = ref(false)
  const supportDialog = ref(false)
  const newStatus = ref<MinistrySession['status']>('OPEN')
  const reason = ref('')
  const workerId = ref<string | null>(null)
  const staffPerson = ref<PersonSummary | null>(null)
  const clearanceEditor = ref(false)
  const staffClearance = ref<Worker | null>(null)
  const staffMissing = ref(false)
  const staffLookupBusy = ref(false)
  const staffLookupError = ref('')
  let staffLookupSequence = 0
  const clearanceCoversSession = computed(() => {
    const worker = staffClearance.value
    const session = view.value?.session
    return (
      !!worker
      && !!session
      && !worker.revokedAt
      && Date.parse(worker.verifiedFrom) <= Date.now()
      && Date.parse(worker.screenedUntil) >= Date.parse(session.endsAt)
      && Date.parse(worker.trainedUntil) >= Date.parse(session.endsAt)
    )
  })
  const role = ref<'LEADER' | 'ASSISTANT'>('ASSISTANT')
  const present = ref(false)
  const selected = ref<AttendanceView | null>(null)
  const selectedName = ref('')
  const supervised = ref(false)
  const issued = ref<LabelIssue | null>(null)
  const qr = ref('')
  const targetSessionId = ref('')
  const destinations = ref<{ id: string, label: string }[]>([])
  const support = ref<SupportRecord | null>(null)
  const supportLoading = ref(false)
  const supportError = ref('')
  const success = ref(false)
  const successMessage = ref('')
  const arrivalRequests = new Map<string, string>()
  let alive = true
  let scopeSequence = 0
  let supportSequence = 0
  const workspace = useLoad(async () => {
    const [view, day] = await Promise.all([
      childrenApi.session(props.id, station.value),
      canStation.value
        ? childrenApi.sessionDay(
          props.id,
          tab.value.toUpperCase(),
          appliedSearch.value,
          pageOffset.value,
        )
        : Promise.resolve({ children: [], total: 0, checkedAt: '' }),
    ])
    return {
      view,
      day,
      attendance: day.children.flatMap(child =>
        child.attendance ? [child.attendance] : [],
      ),
    }
  }, null)
  const view = computed(() => workspace.data.value?.view)
  const terminal = computed(() =>
    ['CLOSED', 'CANCELLED'].includes(view.value?.session.status || ''),
  )
  const arrivalReason = computed(() =>
    view.value
      ? arrivalBlocker(view.value, now.value)
      : 'Wait for the session to load.',
  )
  const canAdmit = computed(() => !arrivalReason.value)
  const statusOptions = ['OPEN', 'NEEDS_REVIEW', 'CANCELLED'].map(value => ({
    value,
    label: statusLabel(value),
  }))
  const rows = computed(() =>
    (workspace.data.value?.day.children || []).map(child => ({
      id: child.childId,
      name: child.name,
      attendance: child.attendance,
      ready: child.ready,
      issues: child.issues,
    })),
  )
  async function load () {
    await workspace.load()
    for (const record of workspace.data.value?.attendance || [])
      arrivalRequests.delete(record.childId)
  }
  const familyArrival = ref<{
    child: { id: string, name: string }
    issues: string[]
  } | null>(null)
  async function reviewFamilyArrival (reference: FamilyQr) {
    if (!('childId' in reference)) return
    const sessionId = props.id
    const result = await action.run(() =>
      useApi().get<{ child: { id: string, name: string }, issues: string[] }>(
        `/api/children/sessions/${props.id}/arrival-reference/${reference.childId}`,
      ),
    )
    if (result && props.id === sessionId) familyArrival.value = result.value.data
  }
  async function confirmFamilyArrival () {
    if (
      !familyArrival.value
      || familyArrival.value.issues.length > 0
      || !canAdmit.value
    )
      return
    const child = familyArrival.value.child
    familyArrival.value = null
    await checkIn(child.id, child.name)
  }
  async function checkIn (childId: string, childName: string) {
    if (!arrivalRequests.has(childId))
      arrivalRequests.set(childId, crypto.randomUUID())
    const result = await action.run(() =>
      childrenApi.checkIn({
        sessionId: props.id,
        childId,
        requestId: arrivalRequests.get(childId)!,
      }),
    )
    if (result) {
      arrivalRequests.delete(childId)
      successMessage.value = 'Child checked in. Issue their pickup label next.'
      success.value = true
      tab.value = 'attending'
      await load()
      openLabel(result.value, childName)
    }
  }
  async function presence (
    personId: string,
    staffRole: string,
    isPresent: boolean,
  ) {
    const current = view.value
    if (!current) return
    const result = await action.run(() =>
      childrenApi.staff({
        sessionId: props.id,
        expectedVersion: current.session.version,
        workerId: personId,
        role: staffRole as 'LEADER' | 'ASSISTANT',
        present: isPresent,
      }),
    )
    if (result) await load()
  }
  async function checkStaffRegistration () {
    const sequence = ++staffLookupSequence
    staffClearance.value = null
    staffMissing.value = false
    staffLookupError.value = ''
    staffLookupBusy.value = false
    if (
      !staffEditor.value
      || !workerId.value
      || !auth.hasPermission('children.safeguarding')
    )
      return
    staffLookupBusy.value = true
    try {
      const value = await childrenApi.worker(workerId.value)
      if (sequence === staffLookupSequence) staffClearance.value = value
    } catch (error) {
      if (sequence !== staffLookupSequence) return
      if (axios.isAxiosError(error) && error.response?.status === 404)
        staffMissing.value = true
      else
        staffLookupError.value = extractErrorMessage(
          error,
          'Unable to check team registration.',
        )
    } finally {
      if (sequence === staffLookupSequence) staffLookupBusy.value = false
    }
  }
  watch(workerId, checkStaffRegistration)
  async function saveStaff () {
    if (!workerId.value || !view.value) throw new Error('Choose a team member.')
    if (staffLookupBusy.value)
      throw new Error('Wait for the team registration check to finish.')
    if (staffMissing.value && auth.hasPermission('children.safeguarding'))
      throw new Error(
        'Record Children Ministry team clearance for this person first.',
      )
    try {
      await childrenApi.staff({
        sessionId: props.id,
        expectedVersion: view.value.session.version,
        workerId: workerId.value,
        role: role.value,
        present: present.value,
      })
    } catch (error) {
      if (
        axios.isAxiosError(error)
        && error.response?.status === 404
        && error.response.data?.message === 'Worker was not found.'
      ) {
        staffMissing.value = true
        throw new Error(
          'This person needs Children Ministry team registration. Ask a safeguarding coordinator to record their linked account, screening and training first.',
        )
      }
      throw error
    }
  }
  function scrollToCloseout () {
    document
      .querySelector('.closeout-panel')
      ?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
  function reviewCloseout (target: string) {
    search.value = ''
    appliedSearch.value = ''
    pageOffset.value = 0
    tab.value = target
    document
      .querySelector('.children-roster')
      ?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
  async function saveStatus () {
    if (!view.value) return
    await childrenApi.status({
      sessionId: props.id,
      expectedVersion: view.value.session.version,
      status: newStatus.value,
      reason: reason.value,
    })
  }
  function choose (value: AttendanceView, name: string) {
    selected.value = value
    selectedName.value = name
    reason.value = ''
  }
  function openLabel (value: AttendanceView, name: string) {
    choose(value, name)
    labelEditor.value = true
  }
  async function issueLabel () {
    const scope = scopeSequence
    if (!selected.value) return
    const value = await childrenApi.issueLabel({
      attendanceId: selected.value.id,
      expectedVersion: selected.value.version,
      replacementReason: reason.value,
    })
    if (!alive || scope !== scopeSequence) return
    issued.value = value
    try {
      const image = await QRCode.toDataURL(value.pickupCode, {
        width: 240,
        margin: 2,
      })
      if (!alive || scope !== scopeSequence) return
      qr.value = image
    } catch {
      if (!alive || scope !== scopeSequence) return
      qr.value = ''
    }
    if (alive && scope === scopeSequence) labelPreview.value = true
    await load()
  }
  function reviewExpectedChildren () {
    statusEditor.value = false
    tab.value = 'expected'
    refreshSession()
  }
  function refreshSession () {
    action.clear()
    void load()
  }
  function closeSupport () {
    supportSequence++
    supportDialog.value = false
    support.value = null
  }
  function clearLabel () {
    labelPreview.value = false
    issued.value = null
    qr.value = ''
  }
  function printLabel () {
    window.print()
  }
  function handover (value: AttendanceView, name: string, exception: boolean) {
    choose(value, name)
    supervised.value = exception
    handoverEditor.value = true
  }
  async function reloadSelected () {
    if (selected.value)
      selected.value = await childrenApi.attendance(selected.value.id)
    await load()
  }
  async function openTransfer (value: AttendanceView, name: string) {
    choose(value, name)
    targetSessionId.value = ''
    const current = view.value
    if (!current) return
    const result = await action.run(() =>
      childrenApi.sessions(
        new Date(Date.now() - 86_400_000).toISOString(),
        new Date(Date.now() + 86_400_000).toISOString(),
        0,
        true,
      ),
    )
    if (result) {
      destinations.value = result.value
        .filter(v => v.session.id !== props.id && v.session.status === 'OPEN')
        .map(v => ({
          id: v.session.id,
          label: `${v.className} · ${v.roomName}`,
        }))
      transferEditor.value = true
    }
  }
  async function transfer () {
    if (!selected.value) return
    await childrenApi.transfer({
      attendanceId: selected.value.id,
      expectedVersion: selected.value.version,
      targetSessionId: targetSessionId.value,
      reason: reason.value,
    })
  }
  async function openSupport (value: AttendanceView, name: string) {
    const sequence = ++supportSequence
    choose(value, name)
    support.value = null
    supportError.value = ''
    supportDialog.value = true
    supportLoading.value = true
    try {
      const record = await childrenApi.support(value.childId, props.id)
      if (
        alive
        && sequence === supportSequence
        && supportDialog.value
        && selected.value?.id === value.id
      )
        support.value = record
    } catch (error) {
      if (alive)
        supportError.value = extractErrorMessage(
          error,
          'No current care information is available.',
        )
    } finally {
      if (alive) supportLoading.value = false
    }
  }
  watch(statusEditor, value => {
    if (value) {
      newStatus.value = 'OPEN'
      reason.value = ''
    }
  })
  watch(staffEditor, value => {
    staffLookupSequence++
    staffMissing.value = false
    staffClearance.value = null
    staffLookupError.value = ''
    staffLookupBusy.value = false
    if (!value) clearanceEditor.value = false
    if (value) {
      staffPerson.value = null
      workerId.value = null
      role.value = 'ASSISTANT'
      present.value = false
    }
  })
  watch(search, value => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      appliedSearch.value = (value || '').trim()
    }, 300)
  })
  watch(appliedSearch, () => {
    if (pageOffset.value) pageOffset.value = 0
    else void load()
  })
  watch(tab, () => {
    clearTimeout(searchTimer)
    pageOffset.value = 0
    search.value = ''
    appliedSearch.value = ''
    void load()
  })
  watch(pageOffset, load)
  watch(
    () => props.id,
    () => {
      scopeSequence++
      supportSequence++
      clearLabel()
      support.value = null
      selected.value = null
      arrivalRequests.clear()
      clearTimeout(searchTimer)
      search.value = ''
      appliedSearch.value = ''
      pageOffset.value = 0
      staffEditor.value = false
      clearanceEditor.value = false
      labelEditor.value = false
      handoverEditor.value = false
      transferEditor.value = false
      supportDialog.value = false
      action.clear()
      void load()
    },
    { immediate: true },
  )
  watch(
    () => auth.token,
    () => {
      scopeSequence++
      supportSequence++
      clearLabel()
      support.value = null
      supportDialog.value = false
    },
  )
  onBeforeUnmount(() => {
    staffLookupSequence++
    clearTimeout(searchTimer)
    alive = false
    clearLabel()
    support.value = null
    arrivalRequests.clear()
  })
</script>

<style scoped>
.session-arrival-reason {
  display: block;
  margin-top: 8px;
  color: #946021;
  font-size: 0.75rem;
  line-height: 1.5;
  white-space: normal;
  overflow-wrap: anywhere;
}
.session-day-guide {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 24px;
  padding: 18px 24px;
  color: #69798e;
  font-size: 0.8rem;
}
.session-day-guide span {
  display: flex;
  align-items: center;
  gap: 8px;
}
.session-day-guide strong {
  display: inline-grid;
  place-items: center;
  border-radius: 50%;
  width: 26px;
  height: 26px;
  background: #eef2fa;
  color: #2c4d94;
}
.session-day-mobile-issues {
  display: none;
}
@media (max-width: 600px) {
  .session-day-mobile-issues {
    display: block;
    color: #946021;
    white-space: normal;
  }
}
.session-day-issues {
  padding-left: 16px;
  min-width: 150px;
  color: #946021;
  font-size: 0.78rem;
  line-height: 1.6;
}
.children-roster :deep(.v-btn) {
  min-height: 44px;
}
.children-roster .directory-table {
  width: 100%;
  table-layout: fixed;
  white-space: normal;
}
.children-roster .directory-table th,
.children-roster .directory-table td {
  white-space: normal;
  overflow-wrap: anywhere;
}
.children-roster .directory-table th:first-child {
  width: 35%;
}
.children-roster .directory-table th:nth-child(2) {
  width: 38%;
}
.children-roster .directory-table th:last-child {
  width: 27%;
}
.children-roster .directory-table td:last-child .v-btn {
  margin: 3px 0;
  max-width: 100%;
}
@media (max-width: 600px) {
  .children-roster .directory-table th:first-child {
    width: 55%;
  }
  .children-roster .directory-table th:last-child {
    width: 45%;
  }
}
</style>
