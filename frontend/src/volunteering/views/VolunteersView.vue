<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="MANY GIFTS. ONE COMMUNITY."
      subtitle="Know your volunteers, support their preparation and give people a sustainable place to serve."
      title="Make room for every gift."
    >
      <v-btn
        v-if="canManage && auth.hasPermission('people.read')"
        color="primary"
        prepend-icon="mdi-account-plus-outline"
        @click="registerOpen = true"
      >
        Add volunteer
      </v-btn>
    </DirectoryHeading>

    <ServingNavigation />

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-hand-heart-outline" />
        </span>

        <div>
          <strong>People first. Readiness made clear.</strong>

          <p>
            A volunteer can be planned before every requirement is complete.
            Only confirmed, ready assignments count toward qualified staffing.
          </p>
        </div>
      </div>
    </div>

    <v-alert
      v-if="error"
      class="my-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <section class="directory-surface serving-controls">
      <v-text-field
        v-model="search"
        hide-details
        label="Find a volunteer"
        prepend-inner-icon="mdi-magnify"
        @keyup.enter="apply"
      />

      <v-btn
        :loading="loading"
        variant="tonal"
        @click="apply"
      >
        Search
      </v-btn>

      <v-btn
        aria-label="Refresh volunteers"
        icon="mdi-refresh"
        variant="text"
        @click="load"
      />
    </section>

    <section class="serving-cards">
      <article
        v-for="person in people"
        :key="person.volunteer.id"
        class="directory-surface serving-card"
      >
        <div class="serving-card__heading">
          <span class="serving-icon">
            <v-icon icon="mdi-account-heart-outline" />
          </span>

          <v-chip
            :color="
              person.volunteer.status === 'ACTIVE' ? 'success' : 'warning'
            "
            size="small"
          >
            {{ person.volunteer.status }}
          </v-chip>
        </div>

        <h2>{{ person.name }}</h2>

        <p class="directory-muted">
          {{
            person.volunteer.accountId
              ? 'Online responses enabled'
              : 'Coordinator-managed volunteer'
          }}
        </p>

        <v-btn
          append-icon="mdi-arrow-top-right"
          variant="text"
          @click="open(person.volunteer.id)"
        >
          Open volunteer profile
        </v-btn>
      </article>

      <div
        v-if="!loading && people.length === 0"
        class="directory-surface serving-card"
      >
        <h2>A place to begin</h2>

        <p class="directory-muted">
          Add a person from People, then introduce them to a team.
        </p>
      </div>
    </section>

    <div class="serving-pagination">
      <span class="directory-muted mr-auto">{{ total }} volunteers</span>

      <v-btn
        :disabled="offset === 0 || loading"
        variant="text"
        @click="page(-25)"
      >
        Previous
      </v-btn>

      <v-btn
        :disabled="offset + 25 >= total || loading"
        variant="text"
        @click="page(25)"
      >
        Next
      </v-btn>
    </div>

    <ServingDialog
      v-model="registerOpen"
      save-label="Register volunteer"
      :submit="register"
      subtitle="Use an existing People record. A login is optional and never creates a second person."
      title="A place to serve"
      @saved="load"
    >
      <PersonPicker
        v-model="personId"
        label="Person *"
        :rules="[required]"
      />

      <v-autocomplete
        v-if="auth.hasPermission('identity.manage')"
        v-model="accountId"
        v-model:search="accountSearch"
        clearable
        hint="Choose the person's own account. It needs volunteering.serve permission for responses."
        item-title="label"
        item-value="id"
        :items="accounts"
        label="Optional login account"
        :loading="accountLoading"
        no-filter
        persistent-hint
      />
    </ServingDialog>

    <v-dialog
      v-model="profileOpen"
      max-width="960"
      scrollable
    >
      <v-card class="directory-form">
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">SERVING TOGETHER</span>
            <h2>{{ profile?.person.name || 'Volunteer profile' }}</h2>

            <p>
              Preparation, availability and a clear record of verified
              credentials.
            </p>
          </div>

          <v-btn
            aria-label="Close volunteer profile"
            icon="mdi-close"
            variant="text"
            @click="profileOpen = false"
          />
        </div>

        <v-card-text v-if="profile">
          <v-alert
            v-if="profileError"
            class="mb-4"
            type="error"
            variant="tonal"
          >
            {{ profileError }}
          </v-alert>

          <div class="d-flex flex-wrap ga-2 mb-5">
            <v-chip>{{ profile.person.volunteer.status }}</v-chip>

            <v-btn
              v-if="canManage"
              variant="tonal"
              @click="statusOpen = true"
            >
              Change status
            </v-btn>

            <v-btn
              v-if="canManage"
              variant="tonal"
              @click="editAvailability"
            >
              Edit availability
            </v-btn>

            <v-btn
              v-if="canVerify"
              variant="tonal"
              @click="credentialOpen = true"
            >
              Record credential
            </v-btn>
          </div>

          <v-btn
            v-if="canManage && auth.hasPermission('identity.manage')"
            class="mb-4"
            variant="text"
            @click="linkOpen = true"
          >
            Link or change login account
          </v-btn>

          <section class="serving-form-section">
            <h3>Verified credentials</h3>

            <div
              v-for="credential in profile.credentials"
              :key="credential.id"
              class="serving-row"
            >
              <div>
                <strong>{{ credential.type }}</strong>

                <p class="directory-muted">
                  {{ timeLabel(credential.validFrom) }} –
                  {{ timeLabel(credential.validUntil) }}
                </p>

                <v-chip
                  v-if="credential.revokedAt"
                  color="warning"
                  size="small"
                >
                  Revoked
                </v-chip>
              </div>

              <v-btn
                v-if="canVerify && !credential.revokedAt"
                color="error"
                variant="text"
                @click="startRevoke(credential.id)"
              >
                Revoke
              </v-btn>
            </div>

            <p
              v-if="profile.credentials.length === 0"
              class="directory-muted"
            >
              No credentials have been recorded. Evidence is kept outside
              ordinary profile views.
            </p>
          </section>

          <section class="serving-form-section">
            <h3>Usual availability</h3>

            <p
              v-for="(rule, index) in profile.weekly"
              :key="index"
              class="directory-muted mb-2"
            >
              {{ weekdays[rule.dayOfWeek - 1]?.title }} · {{ rule.startsAt }}–{{
                rule.endsAt
              }}
              · {{ rule.timeZone }}
              <br>
              {{ rule.validFrom }} until {{ rule.validUntil }} (exclusive)
            </p>

            <p
              v-if="profile.weekly.length === 0"
              class="directory-muted"
            >
              Availability has not been recorded.
            </p>

            <h3 class="mt-5">Dated exceptions</h3>

            <p
              v-for="(window, index) in profile.exceptions"
              :key="index"
              class="directory-muted mb-2"
            >
              {{ window.available ? 'Available' : 'Unavailable' }} ·
              {{ timeLabel(window.startsAt) }} – {{ timeLabel(window.endsAt) }}
            </p>
          </section>
        </v-card-text>

        <v-card-actions>
          <v-btn @click="profileOpen = false">Close</v-btn>

          <v-btn
            :loading="profileLoading"
            @click="refreshProfile"
          >
            Refresh profile
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ServingDialog
      v-model="linkOpen"
      :submit="linkAccount"
      subtitle="Choose their own active login. This does not grant serving permissions. Clear the selection to unlink the account."
      title="Connect the volunteer's account"
      @reload="refreshProfile"
      @saved="refreshProfile"
    >
      <v-autocomplete
        v-model="accountId"
        v-model:search="accountSearch"
        clearable
        item-title="label"
        item-value="id"
        :items="accounts"
        label="Login account"
        :loading="accountLoading"
        no-filter
      />
    </ServingDialog>

    <ServingDialog
      v-model="credentialOpen"
      save-label="Record credential"
      :submit="recordCredential"
      subtitle="Each credential has its own validity and revocation history."
      title="Evidence behind the readiness"
      @reload="refreshProfile"
      @saved="refreshProfile"
    >
      <v-select
        v-model="credentialType"
        item-title="name"
        item-value="id"
        :items="types"
        label="Credential type *"
        :rules="[required]"
      />

      <v-btn
        v-if="canVerify"
        class="mb-4"
        variant="text"
        @click="typeOpen = true"
      >
        Add credential type
      </v-btn>

      <DateTimeSelector
        v-model="credentialFrom"
        label="Valid from *"
      />

      <DateTimeSelector
        v-model="credentialUntil"
        label="Valid through *"
      />

      <v-textarea
        v-model="evidence"
        label="Verification evidence *"
        maxlength="500"
        :rules="[required]"
      />
    </ServingDialog>

    <ServingDialog
      v-model="typeOpen"
      :submit="createType"
      subtitle="Use a stable code so roles can require the same credential consistently."
      title="A shared credential catalogue"
      @saved="loadTypes"
    >
      <v-text-field
        v-model="typeCode"
        label="Code *"
        placeholder="child-protection"
        :rules="[required]"
      />

      <v-text-field
        v-model="typeName"
        label="Name *"
        :rules="[required]"
      />
    </ServingDialog>

    <ServingDialog
      v-model="revokeOpen"
      :submit="revoke"
      subtitle="Future readiness checks will stop counting this evidence."
      title="Revoke this credential"
      @reload="refreshProfile"
      @saved="refreshProfile"
    >
      <v-textarea
        v-model="reason"
        label="Reason *"
        maxlength="500"
        :rules="[required]"
      />
    </ServingDialog>

    <ServingDialog
      v-model="statusOpen"
      :submit="changeStatus"
      subtitle="Paused or archived volunteers remain in historical rosters but cannot be newly assigned."
      title="A change in season"
      @reload="refreshProfile"
      @saved="refreshProfile"
    >
      <v-select
        v-model="status"
        :items="['ACTIVE', 'PAUSED', 'ARCHIVED']"
        label="Volunteer status *"
        :rules="[required]"
      />
    </ServingDialog>

    <ServingDialog
      v-model="availabilityOpen"
      save-label="Save availability"
      :submit="saveAvailability"
      subtitle="Weekly availability uses the chosen time zone. Dated unavailable exceptions always take precedence."
      title="Make space, sustainably"
      @reload="refreshProfile"
      @saved="refreshProfile"
    >
      <div
        v-for="(rule, index) in weekly"
        :key="index"
        class="serving-form-section"
      >
        <div class="serving-grid">
          <v-select
            v-model="rule.dayOfWeek"
            item-title="title"
            item-value="value"
            :items="weekdays"
            label="Day *"
          />

          <v-text-field
            v-model="rule.timeZone"
            label="Time zone *"
            :rules="[required]"
          />

          <v-text-field
            v-model="rule.startsAt"
            label="From *"
            :rules="[required]"
            type="time"
          />

          <v-text-field
            v-model="rule.endsAt"
            label="Until *"
            :rules="[required]"
            type="time"
          />

          <DateSelector
            v-model="rule.validFrom"
            label="Effective from *"
          />

          <DateSelector
            v-model="rule.validUntil"
            label="Effective until (exclusive) *"
          />
        </div>

        <v-btn
          color="error"
          variant="text"
          @click="weekly.splice(index, 1)"
        >
          Remove rule
        </v-btn>
      </div>

      <v-btn
        class="mb-5"
        prepend-icon="mdi-plus"
        variant="tonal"
        @click="addWeekly"
      >
        Add weekly availability
      </v-btn>

      <h3 class="mb-3">Dated exceptions</h3>

      <div
        v-for="(window, index) in exceptions"
        :key="index"
        class="serving-form-section"
      >
        <DateTimeSelector
          v-model="window.startsAt"
          label="From *"
        />

        <DateTimeSelector
          v-model="window.endsAt"
          label="Until *"
        />

        <v-switch
          v-model="window.available"
          color="primary"
          label="Available during this exception"
        />

        <v-btn
          color="error"
          variant="text"
          @click="exceptions.splice(index, 1)"
        >
          Remove exception
        </v-btn>
      </div>

      <v-btn
        prepend-icon="mdi-plus"
        variant="tonal"
        @click="exceptions.push({ startsAt: '', endsAt: '', available: false })"
      >
        Add dated exception
      </v-btn>
    </ServingDialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { instant, localInput } from '@/children/presentation'
  import DateSelector from '@/components/common/DateSelector.vue'
  import DateTimeSelector from '@/components/common/DateTimeSelector.vue'
  import { useApi } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { useCreateAction } from '@/navigation/useCreateAction'
  import { useAuthStore } from '@/stores/auth'
  import {
    type CredentialType,
    type Person,
    type Profile,
    required,
    servingApi,
    servingError,
    timeLabel,
    type Weekly,
  } from '../api'
  import ServingDialog from '../components/ServingDialog.vue'
  import ServingNavigation from '../components/ServingNavigation.vue'
  import '@/directory/directory.scss'
  import '../serving.scss'
  const auth = useAuthStore(),
        route = useRoute(),
        router = useRouter()
  const canManage = computed(() => auth.hasPermission('volunteering.manage')),
        canVerify = computed(() =>
          auth.hasPermission('volunteering.credentials.manage'),
        )
  const people = ref<Person[]>([]),
        total = ref(0),
        offset = ref(0),
        search = ref(''),
        appliedSearch = ref(''),
        loading = ref(false),
        error = ref('')
  const linkOpen = ref(false)
  const registerOpen = ref(false),
        personId = ref(''),
        accountId = ref<string | null>(null),
        accountSearch = ref(''),
        accountLoading = ref(false),
        accounts = ref<{ id: string, label: string }[]>([])
  const profile = ref<Profile | null>(null),
        profileOpen = ref(false),
        profileLoading = ref(false),
        profileError = ref('')
  const types = ref<CredentialType[]>([]),
        typeOpen = ref(false),
        typeCode = ref(''),
        typeName = ref(''),
        credentialOpen = ref(false),
        credentialType = ref(''),
        credentialFrom = ref(localInput()),
        credentialUntil = ref(''),
        evidence = ref('')
  const revokeOpen = ref(false),
        revoking = ref(''),
        reason = ref(''),
        statusOpen = ref(false),
        status = ref('ACTIVE'),
        availabilityOpen = ref(false)
  const weekly = ref<Weekly[]>([]),
        exceptions = ref<
          { startsAt: string, endsAt: string, available: boolean }[]
        >([])
  const weekdays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ].map((title, index) => ({ title, value: index + 1 }))
  useCreateAction(
    () => canManage.value && auth.hasPermission('people.read'),
    () => {
      registerOpen.value = true
    },
  )
  let request = 0,
      accountRequest = 0
  async function load () {
    const current = ++request
    loading.value = true
    error.value = ''
    try {
      const result = await servingApi.volunteers(
        appliedSearch.value,
        offset.value,
      )
      if (current === request) {
        people.value = result.volunteers
        total.value = result.total
      }
    } catch (error_) {
      if (current === request) error.value = servingError(error_)
    } finally {
      if (current === request) loading.value = false
    }
  }
  function apply () {
    offset.value = 0
    appliedSearch.value = search.value
    void load()
  }
  function page (amount: number) {
    offset.value += amount
    void load()
  }
  async function linkAccount () {
    if (!profile.value) return
    await useApi().put(
      `/api/volunteering/volunteers/${profile.value.person.volunteer.id}/account`,
      {
        expectedVersion: profile.value.person.volunteer.version,
        accountId: accountId.value,
      },
    )
  }
  watch(linkOpen, open => {
    if (open) {
      accountId.value = profile.value?.person.volunteer.accountId || null
      accountSearch.value = ' '
    }
  })
  async function register () {
    const result = await servingApi.register({
      personId: personId.value,
      accountId: accountId.value,
    })
    await open(result.id)
  }
  async function open (id: string) {
    profileLoading.value = true
    profileError.value = ''
    try {
      profile.value = await servingApi.profile(id)
      status.value = profile.value.person.volunteer.status
      profileOpen.value = true
      await router.replace({ query: { volunteer: id } })
    } catch (error_) {
      error.value = servingError(error_)
    } finally {
      profileLoading.value = false
    }
  }
  async function refreshProfile () {
    if (profile.value) await open(profile.value.person.volunteer.id)
    await load()
  }
  async function loadTypes () {
    try {
      types.value = await servingApi.types()
    } catch (error_) {
      error.value = servingError(error_)
    }
  }
  async function createType () {
    await servingApi.createType({ code: typeCode.value, name: typeName.value })
  }
  async function recordCredential () {
    if (!profile.value) return
    await servingApi.credential(profile.value.person.volunteer.id, {
      expectedVersion: profile.value.person.volunteer.version,
      typeId: credentialType.value,
      validFrom: instant(credentialFrom.value),
      validUntil: instant(credentialUntil.value),
      evidence: evidence.value,
    })
  }
  function startRevoke (id: string) {
    revoking.value = id
    reason.value = ''
    revokeOpen.value = true
  }
  async function revoke () {
    if (!profile.value) return
    await servingApi.revokeCredential(profile.value.person.volunteer.id, {
      expectedVersion: profile.value.person.volunteer.version,
      credentialId: revoking.value,
      reason: reason.value,
    })
  }
  async function changeStatus () {
    if (!profile.value) return
    await servingApi.status(profile.value.person.volunteer.id, {
      expectedVersion: profile.value.person.volunteer.version,
      status: status.value,
    })
  }
  function editAvailability () {
    weekly.value = (profile.value?.weekly || []).map(rule => ({ ...rule }))
    exceptions.value = (profile.value?.exceptions || []).map(window => ({
      startsAt: localInput(new Date(window.startsAt)),
      endsAt: localInput(new Date(window.endsAt)),
      available: window.available,
    }))
    availabilityOpen.value = true
  }
  function addWeekly () {
    const from = new Date().toISOString().slice(0, 10)
    weekly.value.push({
      dayOfWeek: 7,
      startsAt: '08:00',
      endsAt: '13:00',
      timeZone: 'Africa/Johannesburg',
      validFrom: from,
      validUntil: `${Number(from.slice(0, 4)) + 1}${from.slice(4)}`,
    })
  }
  async function saveAvailability () {
    if (!profile.value) return
    await servingApi.availability(profile.value.person.volunteer.id, {
      expectedVersion: profile.value.person.volunteer.version,
      weekly: weekly.value,
      exceptions: exceptions.value.map(window => ({
        ...window,
        startsAt: instant(window.startsAt),
        endsAt: instant(window.endsAt),
      })),
    })
  }
  watch(accountSearch, async search => {
    if (
      !auth.hasPermission('identity.manage')
      || (!registerOpen.value && !linkOpen.value)
    )
      return
    const current = ++accountRequest
    accountLoading.value = true
    try {
      const result = await useApi().get<{
        items: {
          id: string
          displayName: string
          email: string
          status: string
        }[]
      }>('/api/users', { params: { search: search || '', limit: 25 } })
      if (current === accountRequest)
        accounts.value = result.data.items
          .filter(account => account.status.toUpperCase() === 'ACTIVE')
          .map(account => ({
            id: account.id,
            label: `${account.displayName} · ${account.email}`,
          }))
    } catch (error_) {
      error.value = servingError(error_)
    } finally {
      if (current === accountRequest) accountLoading.value = false
    }
  })
  watch(registerOpen, open => {
    if (open) accountSearch.value = ' '
  })
  onMounted(async () => {
    await Promise.all([load(), loadTypes()])
    if (typeof route.query.volunteer === 'string')
      await open(route.query.volunteer)
  })
</script>
