<template>
  <div class="directory-workspace">
    <router-link
      class="directory-back"
      to="/people"
    >
      <v-icon
        icon="mdi-arrow-left"
        size="17"
      />
      Back to people
    </router-link>

    <v-alert
      v-if="loadError"
      class="mb-5"
      role="alert"
      type="error"
      variant="tonal"
    >
      {{ loadError }}
      <v-btn
        variant="text"
        @click="load"
      >
        Retry
      </v-btn>
    </v-alert>

    <v-skeleton-loader
      v-if="loading && !person"
      type="article, list-item-three-line@3"
    />

    <template v-if="person">
      <header class="directory-profile-hero">
        <span class="directory-avatar directory-avatar--large">
          {{ initials(person) }}
        </span>

        <div class="directory-profile-hero__identity">
          <span class="directory-eyebrow">PERSON PROFILE</span>
          <h1>{{ titledName }}</h1>

          <div class="directory-inline">
            <span
              class="directory-badge"
              :class="`directory-badge--${person.membershipType.toLowerCase()}`"
            >
              <i />
              {{ membershipLabels[person.membershipType] }}
            </span>

            <span class="directory-muted">
              {{ ageLabels[person.ageCategory] }}
            </span>

            <span
              v-if="person.archivedAt"
              class="directory-badge"
            >
              Archived
            </span>
          </div>
        </div>

        <v-btn
          v-if="canManage"
          :disabled="loading || Boolean(loadError)"
          prepend-icon="mdi-pencil-outline"
          variant="outlined"
          @click="editor = true"
        >
          Edit profile
        </v-btn>
      </header>

      <TaskAlert
        :conflict="task.conflict.value"
        :error="task.error.value"
        @reload="reload"
      />

      <div class="directory-profile-grid">
        <aside>
          <section class="directory-surface directory-panel">
            <div class="directory-panel__title">
              <h2>The essentials</h2>

              <v-icon
                icon="mdi-account-outline"
                size="20"
              />
            </div>

            <dl class="directory-facts">
              <div>
                <dt>Date of birth</dt>
                <dd>{{ dateLabel(person.details.dateOfBirth) }}</dd>
              </div>

              <div>
                <dt>Gender</dt>

                <dd>
                  {{
                    person.details.gender === 'UNKNOWN'
                      ? 'Not recorded'
                      : person.details.gender === 'FEMALE'
                        ? 'Female'
                        : 'Male'
                  }}
                </dd>
              </div>

              <div>
                <dt>Life status</dt>

                <dd>
                  {{
                    person.details.lifeStatus === 'UNKNOWN'
                      ? 'Not recorded'
                      : person.details.lifeStatus === 'LIVING'
                        ? 'Living'
                        : 'Deceased'
                  }}
                </dd>
              </div>

              <div v-if="person.details.dateOfDeath">
                <dt>Date of death</dt>
                <dd>{{ dateLabel(person.details.dateOfDeath) }}</dd>
              </div>
            </dl>

            <div class="directory-footnote">
              <v-icon
                icon="mdi-sprout-outline"
                size="17"
              />

              <span>
                Age group on {{ dateLabel(person.ageAsOf) }}.
                <br>
                {{
                  person.ageCategory === 'UNKNOWN'
                    ? 'Add a date of birth to identify their age group.'
                    : 'Age groups update as people grow.'
                }}
              </span>
            </div>
          </section>

          <section class="directory-surface directory-panel mt-5">
            <div class="directory-panel__title">
              <h2>Households</h2>

              <v-icon
                icon="mdi-home-heart"
                size="20"
              />
            </div>

            <router-link
              v-for="member in currentHouseholds"
              :key="member.id"
              class="directory-connection"
              :to="`/families/${member.householdId}`"
            >
              <span class="directory-mini-icon">
                <v-icon icon="mdi-home-outline" />
              </span>

              <span>
                <strong>
                  {{
                    homes[member.householdId]?.household.name ||
                      'View household'
                  }}
                </strong>

                <small>
                  {{
                    member.primary ? 'Primary household' : 'Household member'
                  }}
                </small>
              </span>

              <v-icon
                icon="mdi-chevron-right"
                size="18"
              />
            </router-link>

            <p
              v-if="currentHouseholds.length === 0"
              class="directory-muted"
            >
              No current household memberships.
            </p>

            <router-link
              v-if="canManage"
              class="directory-text-link mt-4"
              to="/families"
            >
              Manage households
              <v-icon
                icon="mdi-arrow-top-right"
                size="16"
              />
            </router-link>
          </section>

          <section
            v-if="canManage"
            class="directory-archive"
          >
            <h3>
              {{
                person.archivedAt
                  ? 'Restore this person'
                  : 'Keep the directory current'
              }}
            </h3>

            <p>
              {{
                person.archivedAt
                  ? 'Return this profile to the active directory.'
                  : 'Archiving hides this person from default searches and keeps their history.'
              }}
            </p>

            <v-btn
              :disabled="!canChange || task.busy.value"
              :prepend-icon="
                person.archivedAt
                  ? 'mdi-archive-arrow-up-outline'
                  : 'mdi-archive-outline'
              "
              variant="text"
              @click="archive"
            >
              {{ person.archivedAt ? 'Restore person' : 'Archive person' }}
            </v-btn>
          </section>
        </aside>

        <div>
          <div class="directory-detail-toolbar">
            <v-tabs
              v-model="tab"
              color="primary"
            >
              <v-tab value="connections">Contact & connections</v-tab>

              <v-tab value="history">History</v-tab>
            </v-tabs>
          </div>

          <v-alert
            v-if="referenceError"
            class="mb-5"
            type="warning"
            variant="tonal"
          >
            {{ referenceError }}
            <v-btn
              variant="text"
              @click="load"
            >
              Retry
            </v-btn>
          </v-alert>

          <template v-if="tab === 'connections'">
            <section class="directory-surface directory-panel">
              <div class="directory-panel__title">
                <div>
                  <span class="directory-eyebrow">STAY IN TOUCH</span>
                  <h2>Contact details</h2>
                </div>

                <v-btn
                  v-if="canManage && !person.archivedAt"
                  :disabled="!canChange"
                  prepend-icon="mdi-plus"
                  variant="text"
                  @click="openEntry('contact')"
                >
                  Add contact
                </v-btn>
              </div>

              <div
                v-for="contact in person.contacts.filter(
                  (item) => !item.endedAt,
                )"
                :key="contact.id"
                class="directory-entry"
              >
                <span class="directory-mini-icon">
                  <v-icon
                    :icon="
                      contact.kind === 'EMAIL'
                        ? 'mdi-email-outline'
                        : 'mdi-phone-outline'
                    "
                  />
                </span>

                <div class="directory-entry__body">
                  <a
                    :href="`${contact.kind === 'EMAIL' ? 'mailto:' : 'tel:'}${contact.value}`"
                  >
                    {{ contact.value }}
                  </a>

                  <small>
                    {{
                      contact.kind === 'EMAIL'
                        ? 'Email address'
                        : 'Phone number'
                    }}
                    <span
                      v-if="contact.primary"
                      class="directory-preferred"
                    >
                      Preferred
                    </span>
                  </small>
                </div>

                <v-btn
                  v-if="canManage"
                  :aria-label="`End contact ${contact.value}`"
                  :disabled="!canChange || task.busy.value"
                  icon="mdi-minus-circle-outline"
                  size="small"
                  variant="text"
                  @click="endContact(contact.id)"
                />
              </div>

              <p
                v-if="!person.contacts.some((item) => !item.endedAt)"
                class="directory-quiet-empty"
              >
                No contact details yet. A small detail makes it easier to stay
                connected.
              </p>
            </section>

            <section class="directory-surface directory-panel mt-5">
              <div class="directory-panel__title">
                <div>
                  <span class="directory-eyebrow">PLACES & BELONGING</span>
                  <h2>Addresses</h2>
                </div>

                <v-btn
                  v-if="canManage && !person.archivedAt"
                  :disabled="!canChange"
                  prepend-icon="mdi-plus"
                  variant="text"
                  @click="openEntry('address')"
                >
                  Add address
                </v-btn>
              </div>

              <div
                v-for="address in person.addresses.filter(
                  (item) => !item.endedAt,
                )"
                :key="address.id"
                class="directory-entry"
              >
                <span class="directory-mini-icon">
                  <v-icon icon="mdi-map-marker-outline" />
                </span>

                <address class="directory-entry__body">
                  <strong>{{ address.line1 }}</strong>

                  <small>
                    {{
                      [
                        address.line2,
                        address.city,
                        address.region,
                        address.postalCode,
                        address.countryCode,
                      ]
                        .filter(Boolean)
                        .join(', ')
                    }}
                  </small>
                </address>

                <v-btn
                  v-if="canManage"
                  aria-label="End address"
                  :disabled="!canChange || task.busy.value"
                  icon="mdi-minus-circle-outline"
                  size="small"
                  variant="text"
                  @click="endAddress(address.id)"
                />
              </div>

              <p
                v-if="!person.addresses.some((item) => !item.endedAt)"
                class="directory-quiet-empty"
              >
                No address recorded.
              </p>
            </section>

            <section class="directory-surface directory-panel mt-5">
              <div class="directory-panel__title">
                <div>
                  <span class="directory-eyebrow">THE PEOPLE AROUND THEM</span>
                  <h2>Relationships</h2>
                </div>

                <v-btn
                  v-if="canManage && !person.archivedAt"
                  :disabled="!canChange"
                  prepend-icon="mdi-plus"
                  variant="text"
                  @click="relationshipEditor = true"
                >
                  Add relationship
                </v-btn>
              </div>

              <div
                v-for="relationship in relationships.filter(
                  (item) => !item.validTo,
                )"
                :key="relationship.id"
                class="directory-entry"
              >
                <span class="directory-mini-icon">
                  <v-icon icon="mdi-account-heart-outline" />
                </span>

                <div class="directory-entry__body">
                  <small>{{ relationLabels[relationship.type] }}</small>

                  <router-link :to="`/people/${relationship.relatedPersonId}`">
                    {{ nameFor(relationship.relatedPersonId) }}
                  </router-link>
                </div>

                <v-btn
                  v-if="canManage"
                  aria-label="End relationship"
                  :disabled="!canChange || task.busy.value"
                  icon="mdi-minus-circle-outline"
                  size="small"
                  variant="text"
                  @click="endRelationship(relationship)"
                />
              </div>

              <p
                v-if="!relationships.some((item) => !item.validTo)"
                class="directory-quiet-empty"
              >
                Family connections will appear here as you record them.
              </p>

              <div class="directory-footnote">
                <v-icon
                  icon="mdi-shield-heart-outline"
                  size="18"
                />

                <span>
                  Relationships do not grant permission to collect a child.
                </span>
              </div>
            </section>
          </template>

          <section
            v-else
            class="directory-surface directory-panel"
          >
            <div class="directory-panel__title">
              <div>
                <span class="directory-eyebrow">A RECORD OF CONNECTIONS</span>
                <h2>Previous details</h2>
              </div>

              <v-icon icon="mdi-history" />
            </div>

            <div
              v-for="contact in person.contacts.filter((item) => item.endedAt)"
              :key="contact.id"
              class="directory-history-row"
            >
              <span class="directory-history-dot" />

              <div>
                <strong>{{ contact.value }}</strong>

                <small>Contact ended {{ dateLabel(contact.endedAt) }}</small>
              </div>
            </div>

            <div
              v-for="address in person.addresses.filter((item) => item.endedAt)"
              :key="address.id"
              class="directory-history-row"
            >
              <span class="directory-history-dot" />

              <div>
                <strong>{{ address.line1 }}, {{ address.city }}</strong>

                <small>Address ended {{ dateLabel(address.endedAt) }}</small>
              </div>
            </div>

            <div
              v-for="relationship in relationships.filter(
                (item) => item.validTo,
              )"
              :key="relationship.id"
              class="directory-history-row"
            >
              <span class="directory-history-dot" />

              <div>
                <strong>
                  {{ relationLabels[relationship.type] }}
                  {{ nameFor(relationship.relatedPersonId) }}
                </strong>

                <small>
                  {{ dateLabel(relationship.validFrom) }} —
                  {{ dateLabel(relationship.validTo) }}
                </small>
              </div>
            </div>

            <div
              v-for="membership in memberships.filter((item) => item.validTo)"
              :key="membership.id"
              class="directory-history-row"
            >
              <span class="directory-history-dot" />

              <div>
                <router-link :to="`/families/${membership.householdId}`">
                  {{
                    homes[membership.householdId]?.household.name ||
                      'Previous household'
                  }}
                </router-link>

                <small>
                  Membership ended {{ dateLabel(membership.validTo) }}
                </small>
              </div>
            </div>

            <p
              v-if="!hasHistory"
              class="directory-quiet-empty"
            >
              No previous details. Ended contacts, addresses, and connections
              are kept here.
            </p>
          </section>
        </div>
      </div>

      <PersonParticipation
        v-if="auth.hasPermission('ministries.read')"
        :person-id="person.id"
      />

      <PersonEditor
        v-model="editor"
        :person="person"
        @reloaded="load"
        @saved="load"
      />

      <ContactAddressEditor
        v-model="entryEditor"
        :mode="entryMode"
        :person="person"
        @reload="reload"
        @saved="load"
      />

      <AssociationEditor
        v-model="relationshipEditor"
        kind="relationship"
        :person="person"
        @reload="reload"
        @saved="load"
      />
    </template>
  </div>
</template>
<script setup lang="ts">
  import type {
    HouseholdMembership,
    HouseholdProfile,
    PersonProfile,
    Relationship,
  } from '../types'
  import axios from 'axios'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import PersonParticipation from '@/communities/components/PersonParticipation.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  import { useUiStore } from '@/stores/ui'
  import { directoryApi } from '../api'
  import AssociationEditor from '../components/AssociationEditor.vue'
  import ContactAddressEditor from '../components/ContactAddressEditor.vue'
  import PersonEditor from '../components/PersonEditor.vue'
  import TaskAlert from '../components/TaskAlert.vue'
  import {
    ageLabels,
    dateLabel,
    fullName,
    initials,
    membershipLabels,
    relationLabels,
  } from '../presentation'
  import { personReferences } from '../references'
  import { useDirectoryStore } from '../store'
  import { useTask } from '../useTask'
  const props = defineProps<{ id: string }>()
  const person = ref<PersonProfile | null>(null)
  const relationships = ref<Relationship[]>([])
  const memberships = ref<HouseholdMembership[]>([])
  const people = ref<Record<string, PersonProfile>>({})
  const homes = ref<Record<string, HouseholdProfile>>({})
  const loading = ref(false)
  const loadError = ref('')
  const referenceError = ref('')
  const tab = ref('connections')
  const editor = ref(false)
  const entryEditor = ref(false)
  const relationshipEditor = ref(false)
  const entryMode = ref<'contact' | 'address'>('contact')
  const task = useTask()
  const catalogue = useDirectoryStore()
  const auth = useAuthStore()
  const ui = useUiStore()
  const canManage = computed(() => auth.hasPermission('people.manage'))
  const canChange = computed(
    () =>
      canManage.value
      && !loading.value
      && !loadError.value
      && !task.conflict.value,
  )
  const titledName = computed(() =>
    person.value
      ? [
        catalogue.metadata?.titles.find(
          title => title.code === person.value?.details.titleCode,
        )?.label,
        fullName(person.value),
      ]
        .filter(Boolean)
        .join(' ')
      : '',
  )
  const currentHouseholds = computed(() =>
    memberships.value.filter(member => !member.validTo),
  )
  const hasHistory = computed(
    () =>
      person.value?.contacts.some(item => item.endedAt)
      || person.value?.addresses.some(item => item.endedAt)
      || relationships.value.some(item => item.validTo)
      || memberships.value.some(item => item.validTo),
  )
  function nameFor (id: string) {
    return people.value[id] ? fullName(people.value[id]) : 'View person'
  }
  function openEntry (mode: 'contact' | 'address') {
    entryMode.value = mode
    entryEditor.value = true
  }
  let controller: AbortController | null = null
  async function load () {
    controller?.abort()
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    loadError.value = ''
    referenceError.value = ''
    try {
      const [profile, links, members] = await Promise.all([
        directoryApi.person(props.id, signal),
        directoryApi.relationships(props.id, signal),
        directoryApi.personHouseholds(props.id, signal),
      ])
      if (signal.aborted) return
      person.value = profile
      relationships.value = links
      memberships.value = members
      try {
        if (!catalogue.metadata) await catalogue.loadMetadata()
        const [references, households] = await Promise.all([
          personReferences(
            links.map(link => link.relatedPersonId),
            signal,
          ),
          Promise.all(
            [...new Set(members.map(member => member.householdId))].map(
              id => directoryApi.household(id, signal),
            ),
          ),
        ])
        if (!signal.aborted) {
          people.value = references
          homes.value = Object.fromEntries(
            households.map(home => [home.household.id, home]),
          )
        }
      } catch (error) {
        if (!signal.aborted && !axios.isCancel(error))
          referenceError.value
            = 'Some connection details could not be loaded. Retry to see their names.'
      }
    } catch (error) {
      if (!signal.aborted && !axios.isCancel(error))
        loadError.value = extractErrorMessage(
          error,
          'Unable to load this person.',
        )
    } finally {
      if (!signal.aborted) loading.value = false
    }
  }
  async function reload () {
    task.clear()
    await load()
  }
  watch(
    () => props.id,
    () => {
      person.value = null
      people.value = {}
      homes.value = {}
      task.clear()
      editor.value = false
      entryEditor.value = false
      relationshipEditor.value = false
      void load()
    },
    { immediate: true },
  )
  onBeforeUnmount(() => controller?.abort())
  async function change (operation: () => Promise<void>, message: string) {
    const result = await task.run(operation)
    if (result) {
      ui.notifySuccess(message)
      await load()
    }
  }
  async function archive () {
    if (!person.value) return
    const current = person.value
    if (
      await ui.confirmAction({
        title: current.archivedAt
          ? 'Restore this person?'
          : 'Archive this person?',
        message: current.archivedAt
          ? 'Their profile will return to the directory.'
          : 'Their history and connections will be preserved. They will be hidden from default directory searches.',
        confirmText: current.archivedAt ? 'Restore person' : 'Archive person',
      })
    )
      await change(
        () => directoryApi.archive(current),
        current.archivedAt ? 'Person restored.' : 'Person archived.',
      )
  }
  async function endContact (id: string) {
    const current = person.value
    if (current && (await confirmEnd('End this contact method?')))
      await change(() => directoryApi.endContact(current, id), 'Contact ended.')
  }
  async function endAddress (id: string) {
    const current = person.value
    if (current && (await confirmEnd('End this address?')))
      await change(() => directoryApi.endAddress(current, id), 'Address ended.')
  }
  async function endRelationship (relationship: Relationship) {
    if (await confirmEnd('End this relationship?'))
      await change(
        () => directoryApi.endRelationship(relationship),
        'Relationship ended.',
      )
  }
  function confirmEnd (title: string) {
    return ui.confirmAction({
      title,
      message:
        'This takes effect today. The previous details will remain in history.',
      confirmText: 'End now',
    })
  }
</script>
