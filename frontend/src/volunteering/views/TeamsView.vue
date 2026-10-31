<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="BELONG. GROW. SERVE."
      subtitle="Bring people together around a shared calling, with clear roles and dated approvals."
      title="Teams with purpose."
    >
      <v-btn
        v-if="manage"
        color="primary"
        prepend-icon="mdi-plus"
        @click="createOpen = true"
      >
        Create team
      </v-btn>
    </DirectoryHeading>

    <ServingNavigation />

    <v-alert
      v-if="error"
      class="my-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <div class="serving-cards mt-6">
      <article
        v-for="team in teams"
        :key="team.id"
        class="directory-surface serving-card"
      >
        <div class="serving-card__heading">
          <span class="serving-icon">
            <v-icon icon="mdi-account-group-outline" />
          </span>

          <v-chip size="small">
            {{ team.active ? 'Active' : 'Archived' }}
          </v-chip>
        </div>

        <h2>{{ team.name }}</h2>

        <p class="directory-muted">
          People, responsibilities and preparation in one place.
        </p>

        <v-btn
          append-icon="mdi-arrow-top-right"
          variant="text"
          @click="open(team.id)"
        >
          Open team
        </v-btn>
      </article>

      <article
        v-if="teams.length === 0 && !loading"
        class="directory-surface serving-card"
      >
        <h2>Build your first team</h2>

        <p class="directory-muted">
          Start with a team, then add roles and welcome volunteers.
        </p>
      </article>
    </div>

    <div class="serving-pagination">
      <span class="directory-muted mr-auto">{{ total }} teams</span>

      <v-btn
        :disabled="!offset || loading"
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
      v-model="createOpen"
      :submit="create"
      subtitle="Give your team a clear, welcoming name."
      title="A shared calling"
      @saved="load"
    >
      <v-text-field
        v-model="name"
        label="Team name *"
        maxlength="150"
        :rules="[required]"
      />
    </ServingDialog>

    <v-dialog
      v-model="detailOpen"
      max-width="1000"
      scrollable
    >
      <v-card class="directory-form">
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">TEAM WORKSPACE</span>
            <h2>{{ detail?.team.name }}</h2>

            <p>
              Membership opens the door. Role approval establishes
              responsibility.
            </p>
          </div>

          <v-btn
            aria-label="Close team"
            icon="mdi-close"
            variant="text"
            @click="detailOpen = false"
          />
        </div>

        <v-card-text v-if="detail">
          <div
            v-if="manage"
            class="d-flex flex-wrap ga-2 mb-5"
          >
            <v-btn
              variant="tonal"
              @click="editTeam"
            >
              Edit team
            </v-btn>

            <v-btn
              variant="tonal"
              @click="startRole"
            >
              Add role
            </v-btn>

            <v-btn
              variant="tonal"
              @click="joinOpen = true"
            >
              Add member
            </v-btn>

            <v-btn
              variant="tonal"
              @click="approveOpen = true"
            >
              Approve role
            </v-btn>
          </div>

          <section class="serving-form-section">
            <h3>Roles & requirements</h3>

            <div
              v-for="role in detail.roles"
              :key="role.id"
              class="serving-row"
            >
              <div>
                <strong>{{ role.name }}</strong>

                <p class="directory-muted">
                  {{
                    role.childrenRole === 'NONE'
                      ? 'General serving role'
                      : 'Children Ministry · ' + role.childrenRole
                  }}
                </p>

                <div class="d-flex flex-wrap ga-1 mt-2">
                  <v-chip
                    v-for="typeId in role.requiredCredentials"
                    :key="typeId"
                    size="small"
                  >
                    {{ types.find((type) => type.id === typeId)?.name }}
                  </v-chip>
                </div>
              </div>

              <v-btn
                v-if="manage"
                variant="text"
                @click="editRole(role)"
              >
                Edit role
              </v-btn>
            </div>

            <p
              v-if="detail.roles.length === 0"
              class="directory-muted"
            >
              Add a role before planning shifts.
            </p>
          </section>

          <section class="serving-form-section">
            <h3>Team members</h3>

            <div
              v-for="member in detail.memberships"
              :key="member.id"
              class="serving-row"
            >
              <div>
                <strong>{{ names[member.volunteerId] || 'Volunteer' }}</strong>

                <p class="directory-muted">
                  {{ timeLabel(member.validFrom) }} –
                  {{ timeLabel(member.validUntil) }}
                </p>

                <v-chip
                  v-if="member.revokedAt"
                  size="small"
                >
                  Revoked
                </v-chip>
              </div>

              <v-btn
                v-if="manage && !member.revokedAt"
                color="error"
                variant="text"
                @click="startRevoke(member.volunteerId, member.id, false)"
              >
                Revoke membership
              </v-btn>
            </div>

            <p
              v-if="detail.memberships.length === 0"
              class="directory-muted"
            >
              No memberships yet.
            </p>
          </section>

          <section class="serving-form-section">
            <h3>Role approvals</h3>

            <div
              v-for="approval in detail.approvals"
              :key="approval.id"
              class="serving-row"
            >
              <div>
                <strong>
                  {{ names[approval.volunteerId] || 'Volunteer' }} ·
                  {{
                    detail.roles.find((role) => role.id === approval.roleId)
                      ?.name
                  }}
                </strong>

                <p class="directory-muted">
                  {{ timeLabel(approval.validFrom) }} –
                  {{ timeLabel(approval.validUntil) }}
                </p>

                <v-chip
                  v-if="approval.revokedAt"
                  size="small"
                >
                  Revoked
                </v-chip>
              </div>

              <v-btn
                v-if="manage && !approval.revokedAt"
                color="error"
                variant="text"
                @click="startRevoke(approval.volunteerId, approval.id, true)"
              >
                Revoke approval
              </v-btn>
            </div>

            <p
              v-if="detail.approvals.length === 0"
              class="directory-muted"
            >
              Approvals are specific to a role and validity period.
            </p>
          </section>
        </v-card-text>

        <v-card-actions>
          <v-btn @click="refresh">Refresh team</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ServingDialog
      v-model="editOpen"
      :submit="saveTeam"
      subtitle="Archiving retains the team's history and stops new planning."
      title="Care for this team"
      @reload="refresh"
      @saved="refresh"
    >
      <v-text-field
        v-model="name"
        label="Team name *"
        :rules="[required]"
      />

      <v-switch
        v-model="active"
        color="primary"
        label="Active team"
      />
    </ServingDialog>

    <ServingDialog
      v-model="roleOpen"
      :submit="createRole"
      subtitle="Choose the credentials this role needs. Children responsibilities are explicit."
      :title="
        editingRole ? 'Refine the responsibility' : 'Give the role clarity'
      "
      @reload="refresh"
      @saved="refresh"
    >
      <v-text-field
        v-model="roleName"
        label="Role name *"
        :rules="[required]"
      />

      <v-select
        v-model="childrenRole"
        :disabled="!!editingRole"
        :items="[
          { title: 'General serving', value: 'NONE' },
          { title: 'Leader', value: 'LEADER' },
          { title: 'Assistant', value: 'ASSISTANT' },
        ]"
        label="Children responsibility"
      />

      <v-switch
        v-if="editingRole"
        v-model="roleActive"
        color="primary"
        label="Active role"
      />

      <v-select
        v-model="requirements"
        chips
        item-title="name"
        item-value="id"
        :items="types"
        label="Required credentials"
        multiple
      />
    </ServingDialog>

    <ServingDialog
      v-model="joinOpen"
      :submit="join"
      subtitle="A membership is dated independently of role approvals."
      title="Welcome to the team"
      @reload="refresh"
      @saved="refresh"
    >
      <VolunteerPicker v-model="volunteer" />

      <DateTimeSelector
        v-model="from"
        label="From *"
      />

      <DateTimeSelector
        v-model="until"
        label="Until (exclusive) *"
      />
    </ServingDialog>

    <ServingDialog
      v-model="approveOpen"
      :submit="approve"
      subtitle="This approval applies only to the selected role and dates."
      title="Approve a responsibility"
      @reload="refresh"
      @saved="refresh"
    >
      <VolunteerPicker v-model="volunteer" />

      <v-select
        v-model="roleId"
        item-title="name"
        item-value="id"
        :items="detail?.roles || []"
        label="Role *"
        :rules="[required]"
      />

      <DateTimeSelector
        v-model="from"
        label="From *"
      />

      <DateTimeSelector
        v-model="until"
        label="Until (exclusive) *"
      />
    </ServingDialog>

    <ServingDialog
      v-model="revokeOpen"
      :submit="revoke"
      subtitle="This takes effect immediately. The historical record is retained."
      title="End this approval"
      @reload="refresh"
      @saved="refresh"
    >
      <v-alert
        type="warning"
        variant="tonal"
      >
        Future readiness will stop counting this
        {{ revoking.roleApproval ? 'role approval' : 'membership' }}.
      </v-alert>
    </ServingDialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { instant, localInput } from '@/children/presentation'
  import DateTimeSelector from '@/components/common/DateTimeSelector.vue'
  import { useApi } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    type CredentialType,
    required,
    type Role,
    servingApi,
    servingError,
    type Team,
    type TeamDetails,
    timeLabel,
  } from '../api'
  import ServingDialog from '../components/ServingDialog.vue'
  import ServingNavigation from '../components/ServingNavigation.vue'
  import VolunteerPicker from '../components/VolunteerPicker.vue'
  import '@/directory/directory.scss'
  import '../serving.scss'
  const auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('volunteering.manage'))
  const teams = ref<Team[]>([]),
        total = ref(0),
        offset = ref(0),
        loading = ref(false),
        error = ref(''),
        detail = ref<TeamDetails | null>(null),
        types = ref<CredentialType[]>([]),
        names = ref<Record<string, string>>({})
  const createOpen = ref(false),
        detailOpen = ref(false),
        editOpen = ref(false),
        roleOpen = ref(false),
        joinOpen = ref(false),
        approveOpen = ref(false),
        revokeOpen = ref(false)
  const editingRole = ref(''),
        roleActive = ref(true)
  const name = ref(''),
        active = ref(true),
        roleName = ref(''),
        childrenRole = ref('NONE'),
        requirements = ref<string[]>([]),
        volunteer = ref(''),
        roleId = ref(''),
        from = ref(localInput()),
        until = ref(''),
        revoking = ref({ volunteerId: '', recordId: '', roleApproval: false })
  async function load () {
    loading.value = true
    error.value = ''
    try {
      const result = await servingApi.teams(offset.value)
      teams.value = result.teams
      total.value = result.total
    } catch (error_) {
      error.value = servingError(error_)
    } finally {
      loading.value = false
    }
  }
  function page (amount: number) {
    offset.value += amount
    void load()
  }
  async function open (id: string) {
    try {
      detail.value = await servingApi.team(id)
      detailOpen.value = true
      const ids = [
        ...new Set(
          [...detail.value.memberships, ...detail.value.approvals].map(
            item => item.volunteerId,
          ),
        ),
      ]
      await Promise.all(
        ids.map(async id => {
          const profile = await servingApi.profile(id)
          names.value[id] = profile.person.name
        }),
      )
    } catch (error_) {
      error.value = servingError(error_)
    }
  }
  async function refresh () {
    if (detail.value) await open(detail.value.team.id)
    await load()
  }
  async function create () {
    await servingApi.createTeam({ name: name.value })
  }
  function editTeam () {
    if (!detail.value) return
    name.value = detail.value.team.name
    active.value = detail.value.team.active
    editOpen.value = true
  }
  async function saveTeam () {
    if (detail.value)
      await servingApi.updateTeam(detail.value.team.id, {
        expectedVersion: detail.value.team.version,
        name: name.value,
        active: active.value,
      })
  }
  function startRole () {
    editingRole.value = ''
    roleName.value = ''
    childrenRole.value = 'NONE'
    requirements.value = []
    roleActive.value = true
    roleOpen.value = true
  }
  function editRole (role: Role) {
    editingRole.value = role.id
    roleName.value = role.name
    childrenRole.value = role.childrenRole
    requirements.value = [...role.requiredCredentials]
    roleActive.value = role.active
    roleOpen.value = true
  }
  async function createRole () {
    if (!detail.value) return
    const body = {
      expectedVersion: detail.value.team.version,
      name: roleName.value,
      childrenRole: childrenRole.value,
      requiredCredentials: requirements.value,
      active: roleActive.value,
    }
    await (editingRole.value
      ? useApi().put(
        `/api/volunteering/teams/${detail.value.team.id}/roles/${editingRole.value}`,
        body,
      )
      : servingApi.role(detail.value.team.id, body))
  }
  async function join () {
    if (detail.value)
      await servingApi.join(detail.value.team.id, {
        expectedVersion: detail.value.team.version,
        volunteerId: volunteer.value,
        validFrom: instant(from.value),
        validUntil: instant(until.value),
      })
  }
  async function approve () {
    if (detail.value)
      await servingApi.approve(detail.value.team.id, {
        expectedVersion: detail.value.team.version,
        volunteerId: volunteer.value,
        roleId: roleId.value,
        validFrom: instant(from.value),
        validUntil: instant(until.value),
      })
  }
  function startRevoke (
    volunteerId: string,
    recordId: string,
    roleApproval: boolean,
  ) {
    revoking.value = { volunteerId, recordId, roleApproval }
    revokeOpen.value = true
  }
  async function revoke () {
    if (detail.value)
      await servingApi.revoke(detail.value.team.id, {
        expectedVersion: detail.value.team.version,
        ...revoking.value,
      })
  }
  onMounted(async () => {
    await load()
    try {
      types.value = await servingApi.types()
    } catch (error_) {
      error.value = servingError(error_)
    }
  })
</script>
