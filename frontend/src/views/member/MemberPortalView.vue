<template>
  <div>
    <div class="member-controls">
      <PageHeader title="My church" eyebrow="Your church community" subtitle="A personal space for your details, households, registrations and giving.">
        <template #actions><v-btn color="primary" prepend-icon="mdi-hand-heart-outline" to="/member/payments">Giving and tickets</v-btn><v-btn variant="outlined" prepend-icon="mdi-shield-lock-outline" to="/account-security">Account security</v-btn><v-btn v-if="auth.hasPermission('volunteering.serve')" color="primary" prepend-icon="mdi-hand-heart-outline" to="/serve">My volunteering</v-btn></template>
      </PageHeader>
      <v-alert v-if="error" type="error" class="mb-4">{{ error }}<v-btn @click="load" variant="text">Reload</v-btn></v-alert>
      <v-progress-linear v-if="busy && !workspace" indeterminate color="primary" aria-label="Loading your church records" />
      <template v-if="workspace">
        <dl class="stat-strip">
          <div class="stat-strip__item"><div><dt>My households</dt><dd>{{ workspace.families.length }}</dd></div><v-icon icon="mdi-home-heart" color="primary" /></div>
          <div class="stat-strip__item"><div><dt>Active registrations</dt><dd>{{ workspace.registrations.filter(booking => booking.status !== 'cancelled').length }}</dd></div><v-icon icon="mdi-calendar-check-outline" color="primary" /></div>
          <div class="stat-strip__item"><div><dt>My profile</dt><dd class="member-profile-name">{{ workspace.profile.firstName }}</dd></div><v-icon icon="mdi-account-outline" color="primary" /></div>
        </dl>
        <div class="member-grid">
          <v-card class="workspace-panel">
            <div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-account-outline" /><div><h2>Personal details</h2><p>{{ workspace.profile.firstName }} {{ workspace.profile.lastName }}</p></div></div></div>
            <dl class="detail-field mb-5"><dt>Date of birth</dt><dd>{{ workspace.profile.dateOfBirth ?? 'Not recorded' }}</dd></dl>
            <v-text-field v-model="email" label="My email" type="email" maxlength="254" />
            <v-text-field v-model="phone" label="My phone" maxlength="40" />
            <v-btn color="primary" :loading="busy" @click="saveContact">Save contact details</v-btn>
            <p class="workspace-note">Ask church staff to correct names, birth dates or household membership. Contact updates do not change communication consent.</p>
          </v-card>
          <v-card class="workspace-panel">
            <div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-home-heart" /><div><h2>My households</h2><p>Your place in the church family.</p></div></div></div>
            <div v-if="!workspace.families.length" class="workspace-empty"><v-icon icon="mdi-home-outline" size="36" /><p>No household membership recorded.</p></div>
            <div v-for="(family,index) in workspace.families" :key="index" class="workspace-record"><div><strong>{{ family.name }}</strong><p>{{ family.memberCount }} members</p></div><v-chip color="primary">{{ family.yourRole }}</v-chip></div>
          </v-card>
        </div>
        <v-card class="workspace-panel">
          <div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-calendar-check-outline" /><div><h2>My registrations</h2><p>Your event bookings and waitlist places.</p></div></div></div>
          <div v-if="!workspace.registrations.length" class="workspace-empty"><v-icon icon="mdi-calendar-blank-outline" size="36" /><p>No registrations recorded.</p></div>
          <v-list><v-list-item v-for="booking in workspace.registrations" :key="booking.id" :title="booking.occurrence?.title ?? 'Event unavailable'" :subtitle="eventDate(booking.occurrence?.startsAt)"><template #append><v-chip :color="booking.status === 'cancelled' ? undefined : 'primary'">{{ booking.status }}</v-chip><v-btn v-if="booking.status !== 'cancelled'" variant="text" :disabled="busy" @click="cancellation = booking">Cancel my registration</v-btn></template></v-list-item></v-list>
          <p class="workspace-note">Shows the latest 100 registrations. Ask staff about earlier history or booking a new event.</p>
        </v-card>
      </template>
      <v-card v-if="workspace" class="workspace-panel">
        <div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-file-document-outline" /><div><h2>My giving statement</h2><p>View and print an acknowledgement of your giving.</p></div></div></div>
        <div class="workspace-form-row"><v-text-field v-model="from" label="Statement from" type="date" /><v-text-field v-model="to" label="Statement to" type="date" /></div>
        <div class="workspace-actions"><v-btn color="primary" :loading="busy" @click="loadStatement">Load my statement</v-btn><v-btn v-if="statement" variant="outlined" prepend-icon="mdi-printer-outline" @click="print">Print statement</v-btn></div>
        <p class="workspace-note">Choose a period of at most 367 days. Only gifts recorded against your linked person appear here.</p>
      </v-card>
    </div>
    <v-card v-if="statement" class="workspace-panel member-statement">
      <div class="panel-heading"><div><div class="page-header__eyebrow">Giving acknowledgement</div><h2>Giving statement · {{ statement.donor?.name }}</h2><p>{{ statement.from }} – {{ statement.to }}</p></div><v-icon icon="mdi-church" size="32" color="primary" /></div>
      <p>{{ statement.notice }}</p>
      <v-table><thead><tr><th>Date</th><th>Fund</th><th>Type</th><th>Amount</th></tr></thead><tbody><tr v-for="entry in statement.entries" :key="entry.id"><td>{{ entry.date }}</td><td>{{ entry.fundName }}</td><td>{{ entry.type }}</td><td>{{ money(entry.amountMinor,entry.currency) }}</td></tr></tbody></v-table>
      <div v-for="total in statement.totals" :key="total.currency" class="workspace-record"><span>Net giving</span><strong>{{ money(total.netAmountMinor,total.currency) }}</strong></div>
      <p v-if="!statement.entries.length" class="workspace-empty">No giving entries in this period.</p>
    </v-card>
    <v-dialog :model-value="Boolean(cancellation)" max-width="500" persistent><v-card title="Cancel my registration"><v-card-text>This releases your booking and may promote another person from the waitlist. Cancelling a paid booking does not refund its payment; ask church staff about a refund. Continue?</v-card-text><v-card-actions><v-btn :disabled="busy" @click="cancellation = null">Keep registration</v-btn><v-btn :loading="busy" @click="cancel">Confirm cancellation</v-btn></v-card-actions></v-card></v-dialog>
  </div>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage, useApi } from '@/composables/useApi'
interface Booking {id:string,status:string,version:number,occurrence:{title:string,startsAt:string} | null}
interface Workspace {profile:{firstName:string,lastName:string,dateOfBirth:string | null,email:string | null,phone:string | null,revision:string},families:{name:string,yourRole:string,memberCount:number}[],registrations:Booking[]}
interface Statement {from:string,to:string,donor:{name:string} | null,notice:string,entries:{id:string,date:string,fundName:string,type:string,amountMinor:number,currency:string}[],totals:{currency:string,netAmountMinor:number}[]}
const auth = useAuthStore(), workspace = ref<Workspace | null>(null), statement = ref<Statement | null>(null), cancellation = ref<Booking | null>(null)
const email = ref(''), phone = ref(''), from = ref(new Date().getUTCFullYear() + '-01-01'), to = ref(new Date().toISOString().slice(0,10)), busy = ref(false), error = ref('')
async function action (operation:() => Promise<void>) {if (busy.value) return; busy.value = true; error.value = ''; try {await operation()} catch (e) {error.value = extractErrorMessage(e, 'Unable to load or update your records.')} finally {busy.value = false}}
async function refresh () {workspace.value = (await useApi().get<Workspace>('/api/member')).data; email.value = workspace.value.profile.email ?? ''; phone.value = workspace.value.profile.phone ?? ''}
async function load () {workspace.value = null; statement.value = null; await action(refresh)}
async function saveContact () {await action(async () => {await useApi().put('/api/member/contact',{email:email.value || null,phone:phone.value || null,expectedRevision:workspace.value?.profile.revision}); await refresh()})}
async function cancel () {await action(async () => {await useApi().post('/api/member/registrations/' + cancellation.value?.id + '/cancel',{expectedVersion:cancellation.value?.version}); cancellation.value = null; await refresh()})}
async function loadStatement () {statement.value = null; await action(async () => {statement.value = (await useApi().get<Statement>('/api/member/giving',{params:{from:from.value,to:to.value}})).data})}
function money (minor:number,currency:string) {const formatter = new Intl.NumberFormat(undefined,{style:'currency',currency}); return formatter.format(minor / 10 ** (formatter.resolvedOptions().maximumFractionDigits ?? 2))}
function eventDate (value:string | undefined) {if (!value) return 'Date unavailable'; const date = new Date(value); return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(date)}
function print () {window.print()}
onMounted(load)
</script>
<style scoped>.member-grid {display:grid;grid-template-columns:1.2fr 1fr;gap:24px}.member-profile-name {font-size:18px!important;overflow-wrap:anywhere}@media(max-width:800px){.member-grid{grid-template-columns:1fr;gap:0}}</style>
<style>@media print {.member-controls,.v-navigation-drawer,.v-app-bar,.v-overlay-container{display:none!important}.v-main{padding:0!important}.member-statement{box-shadow:none!important}.member-statement .v-table__wrapper{overflow:visible!important}.member-statement th,.member-statement td{text-align:left;padding:3mm}}</style>
