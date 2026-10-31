<template>
  <v-data-table
    class="family-directory-table"
    density="compact"
    :headers="headers"
    item-value="id"
    :items="rows"
    :items-per-page="25"
    :items-per-page-options="[10, 25, 50, 100]"
    :loading="loading"
  >
    <template #item.name="{ item }">
      <router-link class="table-identity" :to="`/families/${item.id}`">
        <span class="table-monogram">{{ initials(item.name) }}</span>
        <span><strong>{{ item.name }}</strong><small>View household</small></span>
      </router-link>
    </template>

    <template #item.memberCount="{ item }">
      <div class="table-primary">{{ item.memberCount }} {{ item.memberCount === 1 ? 'member' : 'members' }}</div>
      <div class="table-secondary" :title="memberNames(item)">{{ item.members.slice(0, 2).map(member => member.firstName).join(', ') }}{{ item.memberCount > 2 ? ` +${item.memberCount - 2}` : '' }}</div>
    </template>

    <template #item.contactName="{ item }">
      <template v-if="item.primaryContact">
        <router-link class="table-primary table-link" :to="`/people/${item.primaryContact.id}`">{{ item.contactName }}</router-link>
        <div class="table-secondary">{{ item.primaryContact.phone || item.primaryContact.email || 'No contact details' }}</div>
      </template>

      <span v-else class="table-muted">Not selected</span>
    </template>

    <template #item.addressLabel="{ item }">
      <template v-if="item.displayAddress">
        <div class="table-primary">{{ item.displayAddress.line1 }}</div>
        <div class="table-secondary">{{ [item.displayAddress.city, item.displayAddress.province].filter(Boolean).join(', ') }}{{ item.addresses.length > 1 ? ` · +${item.addresses.length - 1} addresses` : '' }}</div>
      </template>

      <span v-else class="table-muted">No address</span>
    </template>

    <template #item.eventCount="{ item }">
      <router-link v-if="item.upcomingEvents[0]" class="table-event" :to="`/life-events/${item.upcomingEvents[0].id}`">
        <span class="table-event__dot" />
        <div><strong>{{ item.upcomingEvents[0].isAnniversary ? (item.upcomingEvents[0].type === 'birth' ? 'Birthday' : `${item.upcomingEvents[0].type} anniversary`) : item.upcomingEvents[0].type }}</strong><small>{{ formatDate(item.upcomingEvents[0].eventDate) }}{{ item.eventCount > 1 ? ` · +${item.eventCount - 1} events` : '' }}</small></div>
      </router-link>

      <span v-else class="table-muted">None scheduled</span>
    </template>

    <template #item.actions="{ item }">
      <v-btn
        :aria-label="`Open ${item.name}`"
        icon="mdi-arrow-top-right"
        size="small"
        :to="`/families/${item.id}`"
        variant="text"
      />
    </template>

    <template #no-data>
      <div class="workspace-empty"><v-icon icon="mdi-home-heart" size="32" /><p>No families to display. Add a household or try another search.</p></div>
    </template>
  </v-data-table>
</template>

<script lang="ts" setup>
  import type { FamilyOverview } from '@/types/family'
  import { computed } from 'vue'

  const props = defineProps<{ families: FamilyOverview[], loading: boolean }>()
  const headers = [
    { title: 'Household', key: 'name' },
    { title: 'Members', key: 'memberCount' },
    { title: 'Primary contact', key: 'contactName' },
    { title: 'Address', key: 'addressLabel' },
    { title: 'Upcoming', key: 'eventCount' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
  ] as const
  const rows = computed(() => props.families.map(family => {
    const displayAddress = family.addresses.find(address => address.type === 'residential') ?? family.addresses[0]
    return {
      ...family,
      memberCount: family.members.length,
      eventCount: family.upcomingEvents.length,
      contactName: family.primaryContact ? `${family.primaryContact.firstName} ${family.primaryContact.lastName}` : '',
      addressLabel: displayAddress ? `${displayAddress.line1} ${displayAddress.city}` : '',
      displayAddress,
    }
  }))
  const initials = (name: string) => name.split(/\s+/).filter(Boolean).slice(0, 2).map(word => word.charAt(0)).join('')
  const memberNames = (family: FamilyOverview) => family.members.map(member => `${member.firstName} ${member.lastName} (${member.role})`).join(', ')
  const formatDate = (value: string) => new Date(`${value.slice(0, 10)}T12:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
</script>

<style scoped>
.family-directory-table :deep(table) { min-width: 900px; }
.family-directory-table :deep(td) { height: 72px !important; }
.table-event { display: flex; align-items: center; gap: 9px; color: var(--workspace-ink); text-decoration: none; }
.table-event__dot { width: 6px; height: 6px; border-radius: 50%; background: var(--workspace-gold); flex-shrink: 0; }
.table-event strong { font-size: 12px; font-weight: 600; text-transform: capitalize; }
.table-event small { display: block; font-size: 11px; color: var(--workspace-muted); margin-top: 4px; }
</style>
