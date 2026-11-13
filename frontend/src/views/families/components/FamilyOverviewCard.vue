<template>
  <article class="family-card">
    <header class="family-card__header">
      <div class="family-card__identity">
        <div aria-hidden="true" class="family-card__monogram">{{ initials }}</div>

        <div>
          <div class="family-card__eyebrow">HOUSEHOLD · {{ family.members.length }} {{ family.members.length === 1 ?
            'MEMBER' : 'MEMBERS' }}</div>

          <router-link class="family-card__name" :to="`/families/${family.id}`">{{ family.name }}</router-link>
        </div>
      </div>

      <v-btn
        :aria-label="`Open ${family.name}`"
        icon="mdi-arrow-top-right"
        :to="`/families/${family.id}`"
        variant="text"
      />
    </header>

    <div class="family-card__body">
      <section class="family-card__members">
        <div class="family-card__section-heading">
          <h3>Family members</h3>

          <v-btn
            v-if="canManage"
            color="primary"
            density="comfortable"
            prepend-icon="mdi-plus"
            size="small"
            variant="text"
            @click="$emit('add-member')"
          >Add member</v-btn>
        </div>

        <div v-if="family.members.length > 0" class="family-card__member-grid">
          <div v-for="member in family.members" :key="member.id" class="family-card__member">
            <router-link class="family-card__person" :to="`/people/${member.id}`">
              <div class="family-card__avatar">{{ member.firstName.charAt(0) }}{{ member.lastName.charAt(0) }}</div>

              <div class="family-card__person-copy">
                <strong>{{ member.firstName }} {{ member.lastName }}</strong>
                <span class="family-card__role">{{ label(member.role) }}</span>
              </div>
            </router-link>

            <v-btn
              v-if="canManage"
              :aria-label="family.primaryContact?.id === member.id ? `${member.firstName} is the primary contact` : `Make ${member.firstName} ${member.lastName} the primary contact`"
              :color="family.primaryContact?.id === member.id ? 'primary' : undefined"
              :disabled="busy"
              :icon="family.primaryContact?.id === member.id ? 'mdi-star' : 'mdi-star-outline'"
              size="small"
              variant="text"
              @click="$emit('primary-contact', member.id)"
            />
          </div>
        </div>

        <p v-else class="family-card__empty">Add the first member to start building this household.</p>
      </section>

      <aside class="family-card__household">
        <section>
          <div class="family-card__section-heading">
            <h3><v-icon icon="mdi-account-star-outline" size="17" /> Primary contact</h3>

            <v-btn
              v-if="canManage && (family.primaryContact)"
              aria-label="Clear primary contact"
              :disabled="busy"
              icon="mdi-close"
              size="x-small"
              variant="text"
              @click="$emit('primary-contact', null)"
            />
          </div>

          <div v-if="family.primaryContact" class="family-card__contact">
            <router-link :to="`/people/${family.primaryContact.id}`">{{ family.primaryContact.firstName }} {{
              family.primaryContact.lastName }}</router-link>

            <a v-if="family.primaryContact.phone" :href="`tel:${family.primaryContact.phone}`"><v-icon
              icon="mdi-phone-outline"
              size="15"
            /> {{ family.primaryContact.phone }}</a>

            <a v-if="family.primaryContact.email" :href="`mailto:${family.primaryContact.email}`"><v-icon
              icon="mdi-email-outline"
              size="15"
            /> {{ family.primaryContact.email }}</a>

            <span v-if="!family.primaryContact.phone && !family.primaryContact.email" class="family-card__empty">No
              contact details recorded.</span>
          </div>

          <p v-else class="family-card__empty">No primary contact selected. Use a member’s star to choose one.</p>
        </section>

        <section class="family-card__address-section">
          <div class="family-card__section-heading">
            <h3><v-icon icon="mdi-map-marker-outline" size="17" /> Addresses</h3>

            <v-btn
              v-if="canManage"
              aria-label="Add family address"
              icon="mdi-plus"
              size="x-small"
              variant="text"
              @click="$emit('add-address')"
            />
          </div>

          <div v-for="address in family.addresses" :key="address.id" class="family-card__address">
            <span class="family-card__address-type">{{ label(address.type) }}</span>
            <p>{{ address.line1 }}<template v-if="address.line2">, {{ address.line2 }}</template></p>
            <p>{{ [address.city, address.province, address.postalCode].filter(Boolean).join(', ') }}</p>
            <p>{{ address.country }}</p>
          </div>

          <p v-if="family.addresses.length === 0" class="family-card__empty">No family address recorded.</p>
        </section>
      </aside>
    </div>

    <section class="family-card__events">
      <div class="family-card__section-heading">
        <h3><v-icon icon="mdi-calendar-heart" size="18" />

          Upcoming events <span class="family-card__count">{{
            family.upcomingEvents.length }}</span></h3>

        <span class="family-card__event-caption">For members of this family</span>
      </div>

      <div v-if="family.upcomingEvents.length > 0" class="family-card__event-grid">
        <router-link
          v-for="event in family.upcomingEvents"
          :key="event.id"
          class="family-card__event"
          :to="`/life-events/${event.id}`"
        >
          <div class="family-card__date"><span>{{ eventMonth(event.eventDate) }}</span>

            <strong>{{
              eventDay(event.eventDate)
            }}</strong></div>

          <div class="family-card__event-copy">
            <strong>{{ event.isAnniversary ? (event.type === 'birth' ? 'Birthday' : `${label(event.type)} anniversary`) : label(event.type) }}</strong>
            <span>{{ participantNames(event.memberIds) }}</span>
            <span v-if="event.notes" class="family-card__notes">{{ event.notes }}</span>
          </div>

          <v-icon icon="mdi-arrow-right" size="17" />
        </router-link>
      </div>

      <p v-else class="family-card__empty">No upcoming events scheduled for this family.</p>
    </section>
  </article>
</template>

<script lang="ts" setup>
  import type { FamilyOverview } from '@/types/family'
  import { computed } from 'vue'

  const props = defineProps<{ family: FamilyOverview, busy?: boolean, canManage?: boolean }>()
  defineEmits<{ 'add-member': [], 'add-address': [], 'primary-contact': [personId: string | null] }>()
  const initials = computed(() => props.family.name.split(/\s+/).filter(Boolean).slice(0, 2).map(word => word.charAt(0)).join(''))
  const label = (value: string) => value.charAt(0).toUpperCase() + value.slice(1)
  const eventDate = (value: string) => new Date(`${value.slice(0, 10)}T12:00:00`)
  const eventMonth = (value: string) => eventDate(value).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })
  const eventDay = (value: string) => eventDate(value).getDate()
  const participantNames = (ids: string[]) => props.family.members.filter(member => ids.includes(member.id)).map(member => `${member.firstName} ${member.lastName}`).join(', ')

</script>

<style scoped>
.family-card {
  overflow: hidden;
  border: 1px solid var(--workspace-border);
  border-radius: 20px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 8px 30px #172a4510;
}

.family-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 26px 30px;
  border-bottom: 1px solid var(--workspace-border);
}

.family-card__identity {
  display: flex;
  align-items: center;
  gap: 18px;
  min-width: 0;
}

.family-card__monogram {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 58px;
  height: 58px;
  background: var(--workspace-tint);
  color: var(--workspace-blue);
  border: 1px solid var(--workspace-tint-border);
  border-radius: 18px;
  font: 700 22px 'Plus Jakarta Sans', sans-serif;
}

.family-card__eyebrow {
  color: var(--workspace-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.4px;
  margin-bottom: 4px;
}

.family-card__name {
  color: var(--workspace-ink);
  text-decoration: none;
  font: 700 23px 'Plus Jakarta Sans', sans-serif;
  overflow-wrap: anywhere;
}

.family-card__body {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(260px, 1fr);
}

.family-card__members {
  padding: 24px 30px;
}

.family-card__section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.family-card h3 {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  color: var(--workspace-ink);
}

.family-card__member-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.family-card__member {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
  padding: 12px;
  border: 1px solid var(--workspace-border);
  border-radius: 12px;
}

.family-card__person {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  text-decoration: none;
  color: var(--workspace-ink);
}

.family-card__avatar {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--workspace-tint);
  font-size: 12px;
  font-weight: 700;
  color: var(--workspace-muted);
}

.family-card__person-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 5px;
  min-width: 0;
}

.family-card__person-copy strong {
  font-size: 14px;
  overflow-wrap: anywhere;
}

.family-card__role {
  padding: 2px 7px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 5px;
  color: var(--workspace-muted);
  background: var(--workspace-tint);
}

.family-card__household {
  padding: 24px 30px;
  border-left: 1px solid var(--workspace-border);
  background: #fafbfdf0;
}

.family-card__contact {
  display: flex;
  flex-direction: column;
  gap: 7px;
  font-size: 13px;
}

.family-card__contact a {
  text-decoration: none;
  color: var(--workspace-muted);
  overflow-wrap: anywhere;
}

.family-card__contact a:first-child {
  color: var(--workspace-blue);
  font-size: 14px;
  font-weight: 700;
}

.family-card__address-section {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--workspace-border);
}

.family-card__address+.family-card__address {
  margin-top: 16px;
}

.family-card__address {
  font-size: 13px;
  line-height: 1.65;
  color: var(--workspace-muted);
}

.family-card__address-type {
  display: inline-block;
  color: var(--workspace-muted);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .6px;
  margin-bottom: 5px;
}

.family-card__empty {
  color: var(--workspace-muted);
  font-size: 13px;
  line-height: 1.7;
}

.family-card__events {
  padding: 22px 30px 26px;
  border-top: 1px solid var(--workspace-border);
}

.family-card__event-caption {
  color: var(--workspace-muted);
  font-size: 12px;
}

.family-card__count {
  display: inline-grid;
  place-items: center;
  min-width: 20px;
  height: 20px;
  border-radius: 6px;
  color: var(--workspace-blue);
  background: var(--workspace-tint);
  font-size: 11px;
}

.family-card__event-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.family-card__event {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  color: var(--workspace-muted);
  padding: 12px 14px;
  background: var(--workspace-soft);
  border: 1px solid var(--workspace-border);
  border-radius: 12px;
}

.family-card__date {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  min-width: 62px;
  min-height: 56px;
  background: white;
  border: 1px solid var(--workspace-border);
  border-radius: 9px;
  color: var(--workspace-blue);
}

.family-card__date span {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.family-card__date strong {
  font: 700 23px 'Plus Jakarta Sans', sans-serif;
}

.family-card__event-copy {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.family-card__event-copy strong {
  font-size: 14px;
  color: var(--workspace-ink);
}

.family-card__event-copy span {
  font-size: 12px;
  overflow-wrap: anywhere;
}

.family-card__notes {
  white-space: pre-wrap;
}

.family-card a:hover {
  color: var(--workspace-blue);
}

.family-card a:focus-visible {
  outline: 2px solid var(--workspace-blue);
  outline-offset: 3px;
  border-radius: 4px;
}

@media (max-width: 1100px) {
  .family-card__member-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 700px) {
  .family-card__body {
    grid-template-columns: 1fr;
  }

  .family-card__household {
    border-left: 0;
    border-top: 1px solid var(--workspace-border);
  }

  .family-card__header,
  .family-card__members,
  .family-card__household,
  .family-card__events {
    padding: 20px;
  }

  .family-card__event-grid {
    grid-template-columns: 1fr;
  }

  .family-card__event-caption {
    display: none;
  }

  .family-card__name {
    font-size: 20px;
  }
}
</style>
