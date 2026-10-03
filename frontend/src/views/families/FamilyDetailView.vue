<template>
  <div v-if="family">
    <PageHeader :subtitle="`Created ${formatDate(family.createdAt)}`" :title="family.name">
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="$router.push('/families')">Back</v-btn>

        <v-btn prepend-icon="mdi-map-marker-plus-outline" variant="tonal" @click="addressDialog = true">
          Add Address
        </v-btn>

        <v-btn color="primary" prepend-icon="mdi-account-plus-outline" variant="flat" @click="memberDialog = true">
          Add Member
        </v-btn>
      </template>
    </PageHeader>

    <v-card>
      <v-card-title>Members</v-card-title>
      <v-divider />

      <v-list v-if="members.length > 0" lines="two">
        <v-list-item
          v-for="member in members"
          :key="member.id"
          :subtitle="`${member.role} · Member since ${formatDate(member.membershipCreatedAt)}`"
          :title="`${member.firstName} ${member.lastName}`"
          :to="`/people/${member.id}`"
        >
          <template #prepend>
            <v-avatar color="primary-lighten-1">
              <span class="text-secondary font-weight-bold">{{ member.firstName.charAt(0) }}{{ member.lastName.charAt(0) }}</span>
            </v-avatar>
          </template>
        </v-list-item>
      </v-list>

      <v-card-text v-else class="text-medium-emphasis">
        No members in this family yet.
      </v-card-text>
    </v-card>

    <v-alert class="mt-4" density="comfortable" type="info" variant="tonal">
      Family addresses can be added above, but the API does not yet expose an endpoint to
      list a family's addresses, so they aren't shown here.
    </v-alert>

    <AddFamilyMemberDialog
      v-model="memberDialog"
      :family-id="family.id"
      @saved="peopleStore.fetchAll()"
    />

    <FamilyAddressFormDialog v-model="addressDialog" :family-id="family.id" />
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
  import FamilyAddressFormDialog from './components/FamilyAddressFormDialog.vue'

  const props = defineProps<{ id: string }>()

  const familiesStore = useFamiliesStore()
  const peopleStore = usePeopleStore()
  const ui = useUiStore()

  const memberDialog = ref(false)
  const addressDialog = ref(false)

  const family = computed(() => familiesStore.current)
  const members = computed(() => familiesStore.peopleByFamily[props.id] ?? [])

  function formatDate (value: string) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  async function load () {
    try {
      await familiesStore.fetchOne(props.id)
      await familiesStore.fetchFamilyPeople(props.id)
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
