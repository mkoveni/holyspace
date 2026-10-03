<template>
  <div>
    <PageHeader subtitle="Organize households and track family membership" title="Families">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" variant="flat" @click="dialog = true">
          Add Family
        </v-btn>
      </template>
    </PageHeader>

    <v-card elevation="2">
      <v-card-text>
        <v-text-field
          v-model="search"
          clearable
          density="comfortable"
          hide-details
          label="Search by family name"
          prepend-inner-icon="mdi-magnify"
        />
      </v-card-text>

      <v-data-table :headers="headers" item-value="id" :items="filteredFamilies" :loading="familiesStore.isLoading">
        <template #item.name="{ item }">
          <router-link class="text-decoration-none text-primary-darken font-weight-medium" :to="`/families/${item.id}`">
            {{ item.name }}
          </router-link>
        </template>

        <template #item.createdAt="{ item }">
          {{ formatDate(item.createdAt) }}
        </template>

        <template #item.actions="{ item }">
          <v-btn icon="mdi-eye-outline" size="small" :to="`/families/${item.id}`" variant="text" />
        </template>
      </v-data-table>
    </v-card>

    <FamilyFormDialog v-model="dialog" @saved="familiesStore.fetchAll()" />
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { useFamiliesStore } from '@/stores/families'
  import { useUiStore } from '@/stores/ui'
  import FamilyFormDialog from './components/FamilyFormDialog.vue'

  const familiesStore = useFamiliesStore()
  const ui = useUiStore()

  const search = ref('')
  const dialog = ref(false)

  const headers = [
    { title: 'Family Name', key: 'name', sortable: false },
    { title: 'Created', key: 'createdAt' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ] as const

  const filteredFamilies = computed(() => familiesStore.families.filter(family =>
    !search.value || family.name.toLowerCase().includes(search.value.toLowerCase())))

  function formatDate (value: string) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  onMounted(async () => {
    try {
      await familiesStore.fetchAll()
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to load families.')
    }
  })
</script>
