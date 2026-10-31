<template>
  <div>
    <PageHeader eyebrow="People & community" subtitle="Members, contact details, and shared milestones" :title="family?.name ?? 'Family'">
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" to="/families" variant="text">Family directory</v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="loadError" class="mb-4" type="error" variant="tonal">
      {{ loadError }}
      <v-btn :loading="isLoading" variant="text" @click="load">Retry</v-btn>
    </v-alert>

    <v-skeleton-loader v-if="isLoading" type="article, list-item-avatar-three-line, article" />

    <PortalImage v-if="family && !isLoading" target="family" :owner-id="family.id" label="Family picture" :editable="access.hasPermission('families.manage')" />

    <FamilyOverviewCard
      v-if="family && !isLoading"
      :busy="Boolean(familiesStore.savingContactId)"
      :family="family"
      :can-manage="access.hasPermission('families.manage')"
      @add-address="addressDialog = true"
      @add-member="memberDialog = true"
      @primary-contact="setContact"
    />

    <AddFamilyMemberDialog v-if="family && memberDialog" v-model="memberDialog" :family-id="family.id" />
    <FamilyAddressFormDialog v-if="family && addressDialog" v-model="addressDialog" :family-id="family.id" />
  </div>
</template>

<script lang="ts" setup>
import PortalImage from '@/components/common/PortalImage.vue'
  import { computed, onMounted, ref, watch } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { useAuthStore } from '@/stores/auth'
  import { useFamiliesStore } from '@/stores/families'
  import { useUiStore } from '@/stores/ui'
  import AddFamilyMemberDialog from './components/AddFamilyMemberDialog.vue'
  import FamilyAddressFormDialog from './components/FamilyAddressFormDialog.vue'
  import FamilyOverviewCard from './components/FamilyOverviewCard.vue'

  const props = defineProps<{ id: string }>()
  const access = useAuthStore()
  const familiesStore = useFamiliesStore()
  const ui = useUiStore()
  const memberDialog = ref(false)
  const addressDialog = ref(false)
  const isLoading = ref(false)
  const loadError = ref('')
  const family = computed(() => familiesStore.overview?.id === props.id ? familiesStore.overview : null)

  async function setContact (personId: string | null) {
    try {
      await familiesStore.setPrimaryContact(props.id, personId)
      ui.notifySuccess(personId ? 'Primary contact updated.' : 'Primary contact cleared.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to update primary contact.')
    }
  }

  async function load () {
    isLoading.value = true
    loadError.value = ''
    memberDialog.value = false
    addressDialog.value = false
    try {
      await familiesStore.fetchOverview(props.id)
    } catch (error) {
      loadError.value = error instanceof Error ? error.message : 'Unable to load family.'
    } finally {
      isLoading.value = false
    }
  }

  watch(() => props.id, load)
  onMounted(load)
</script>
