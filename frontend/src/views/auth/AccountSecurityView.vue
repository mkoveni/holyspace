<template>
  <div>
    <PageHeader title="Account security" subtitle="Protect your account with an authenticator and offline recovery codes" />
    <v-alert v-if="error" type="error" class="mb-4">{{ error }}</v-alert>
    <v-alert v-if="message" type="success" class="mb-4">{{ message }}</v-alert>
    <v-card class="workspace-panel" max-width="800">
      <p v-if="status?.enabled">Authenticator enabled. {{ status.verified ? 'This session is verified.' : 'Enter a new authenticator code to continue.' }}</p>
      <p v-else>Member portal access requires an authenticator. Enter your current password to start setup, then scan the QR code with your authenticator app.</p>
      <v-text-field v-if="!status?.enabled || status?.verified" v-model="password" label="Current password" type="password" autocomplete="current-password" />
      <v-btn v-if="status && !status.enabled && !setup" :loading="busy" @click="startSetup">Start authenticator setup</v-btn>
      <div v-if="setup"><p class="mt-4">In your authenticator app, choose Add account, then Scan QR code.</p><AuthenticatorSetupQr :uri="setup.uri" /><p>Can’t scan? Enter this manual setup key instead (keep private):</p><code class="setup-secret">{{ setup.secret }}</code><p>Account: {{ auth.user?.username }} · issuer: Holyspace · time-based · 30 seconds · SHA1 · 6 digits</p><p>Setup expires after ten minutes. Save your recovery codes after enabling.</p></div>
      <v-text-field v-if="setup || (status?.enabled && !status.verified)" v-model="code" label="Authenticator code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="mt-3" />
      <v-btn v-if="setup" :loading="busy" :disabled="code.length !== 6" @click="enable">Enable authenticator</v-btn>
      <v-btn v-if="status?.enabled && !status.verified" :loading="busy" :disabled="code.length !== 6" @click="verify">Verify authenticator</v-btn>
      <template v-if="status?.enabled && status.verified"><p class="my-3">{{ status.remainingRecoveryCodes }} recovery codes remain. Replacing them revokes all previous codes and other sessions.</p><v-btn :loading="busy" @click="replaceCodes">Replace recovery codes</v-btn></template>
    </v-card>
    <v-card v-if="recoveryCodes.length" class="workspace-panel" max-width="800"><h2>Save these recovery codes now</h2><p>Each code is a secret that can recover your account. Store them offline. They will not be shown again.</p><ul class="ml-5 my-4"><li v-for="item in recoveryCodes" :key="item"><code>{{ item }}</code></li></ul><v-btn @click="downloadCodes">Download recovery codes</v-btn><v-checkbox v-model="savedCodes" label="I stored my recovery codes safely" /><v-btn :disabled="!savedCodes" @click="continueToWorkspace">Continue</v-btn></v-card>
    <v-btn v-else-if="status?.verified && auth.hasPermission('member.portal')" to="/member" class="mb-4">Open member portal</v-btn>
    <v-card v-if="status && (!status.enabled || status.verified)" class="workspace-panel" max-width="800"><h2>Change password</h2><p>Changing your password signs out all sessions, including this one.</p><v-text-field v-model="newPassword" label="New password (at least 12 characters)" type="password" autocomplete="new-password" /><v-btn :loading="busy" :disabled="newPassword.length < 12 || !password" @click="changePassword">Change password</v-btn></v-card>
  </div>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import AuthenticatorSetupQr from '@/components/common/AuthenticatorSetupQr.vue'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage, useApi } from '@/composables/useApi'
const auth = useAuthStore(), route = useRoute(), router = useRouter()
const status = ref<{enabled:boolean,verified:boolean,remainingRecoveryCodes:number} | null>(null)
const setup = ref<{secret:string,uri:string} | null>(null), recoveryCodes = ref<string[]>([]), savedCodes = ref(false)
const password = ref(''), newPassword = ref(''), code = ref(''), busy = ref(false), error = ref(''), message = ref('')
async function load () {status.value = (await useApi().get('/api/account-security')).data}
async function action (operation:() => Promise<void>) {if (busy.value) return; busy.value = true; error.value = ''; message.value = ''; try {await operation()} catch (e) {error.value = extractErrorMessage(e, 'Unable to complete account security operation.')} finally {busy.value = false; code.value = ''}}
async function takeToken (token:string) {auth.setToken(token); await auth.fetchCurrentUser(); await load()}
async function startSetup () {await action(async () => {setup.value = (await useApi().post('/api/account-security/setup',{password:password.value})).data})}
async function enable () {await action(async () => {const result = (await useApi().post<{token:string,recoveryCodes:string[]}>('/api/account-security/enable',{password:password.value,code:code.value})).data; setup.value = null; password.value = ''; recoveryCodes.value = result.recoveryCodes; savedCodes.value = false; await takeToken(result.token)})}
async function verify () {await action(async () => {const result = (await useApi().post<{token:string}>('/api/account-security/verify',{code:code.value})).data; await takeToken(result.token); await continueToWorkspace()})}
async function replaceCodes () {await action(async () => {const result = (await useApi().post<{token:string,recoveryCodes:string[]}>('/api/account-security/recovery-codes',{password:password.value})).data; recoveryCodes.value = result.recoveryCodes; savedCodes.value = false; password.value = ''; await takeToken(result.token)})}
async function changePassword () {await action(async () => {await useApi().post('/api/account-security/password',{password:password.value,newPassword:newPassword.value}); auth.logout(); await router.replace('/login')})}
async function continueToWorkspace () {recoveryCodes.value = []; const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') && !route.query.redirect.startsWith('//') && !route.query.redirect.startsWith('/account-security') ? route.query.redirect : auth.hasPermission('member.portal') && !auth.hasPermission('people.read') ? '/member' : '/'; await router.replace(redirect)}
function downloadCodes () {const blob = new Blob(['Holyspace recovery codes for ' + auth.user?.username + '\nStore offline. Each code can recover the account.\n\n' + recoveryCodes.value.join('\n')],{type:'text/plain'}); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'holyspace-recovery-codes.txt'; a.click(); URL.revokeObjectURL(url)}
onMounted(() => action(load))
</script>
