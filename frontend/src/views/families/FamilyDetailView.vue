<template>
  <div v-if="family">
    <PageHeader :subtitle="`Created ${formatDate(family.createdAt)}`" :title="family.name">
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="$router.push('/families')">Back</v-btn>

        <v-btn color="primary" prepend-icon="mdi-account-plus-outline" variant="flat" @click="memberDialog = true">
          Add Member
        </v-btn>
      </template>
    </PageHeader>

    <v-alert class="mb-4" type="info" variant="tonal">
      Family membership listing is not yet exposed by the API. Members added during this
      session are shown below; refresh will clear this local view until a listing endpoint is added.
    </v-alert>

    <v-card elevation="2">
      <v-card-title>Members</v-card-title>
      <v-divider />

      <v-list v-if="members.length > 0">
        <v-list-item
          v-for="member in members"
          :key="member.personId"
          :subtitle="member.role"
          :title="personName(member.personId)"
          :to="`/people/${member.personId}`"
        >
          <template #prepend>
            <v-icon color="primary" icon="mdi-account-outline" />
          </template>
        </v-list-item>
      </v-list>

      <v-card-text v-else class="text-medium-emphasis">
        No members added yet during this session.
      </v-card-text>
    </v-card>

    <AddFamilyMemberDialog
      v-model="memberDialog"
      :family-id="family.id"
      @saved="peopleStore.fetchAll()"
    />
  </div>

  <v-skeleton-loader v-else type="article" />
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref, watch } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { useFamiliesStore } from '@/stores/families'
  import { usePeopleStore } from '@/stores/people'
  import { useUiStore } from '@/stores/ui'
  import AddFamilyMemberDialog from './components/AddFamilyMemberDialog.vue'

  const props = defineProps<{ id: string }>()

  const familiesStore = useFamiliesStore()
  const peopleStore = usePeopleStore()
  const ui = useUiStore()

  const memberDialog = ref(false)

  const family = computed(() => familiesStore.current)
  const members = computed(() => familiesStore.membersByFamily[props.id] ?? [])

  function personName (id: string) {
    const person = peopleStore.byId(id)
    return person ? `${person.firstName} ${person.lastName}` : 'Unknown person'
  }

  function formatDate (value: string) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  async function load () {
    try {
      await familiesStore.fetchOne(props.id)
      if (peopleStore.people.length === 0) {
        await peopleStore.fetchAll()
      }
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to load family.')
    }
  }

  watch(() => props.id, load)

  onMounted(load)
</script>
