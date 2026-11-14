<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="GIVING · ONLINE GIVING"
      subtitle="Let anyone give online, no account needed. Finish the steps below, then share the link."
      title="Set up online giving."
    />

    <GivingNavigation />

    <v-alert v-if="error" class="mb-4" type="error" variant="tonal">{{ error }}</v-alert>

    <v-alert
      v-if="!loading"
      class="mb-5"
      :icon="live ? 'mdi-check-circle-outline' : 'mdi-progress-wrench'"
      :type="live ? 'success' : 'info'"
      variant="tonal"
    >
      <strong v-if="live">Online giving is live.</strong>
      <strong v-else>Not live yet.</strong>
      <span v-if="live"> People can give at the link in step 3.</span>
      <span v-else> {{ remaining }}</span>
    </v-alert>

    <!-- Step 1: provider -->
    <section class="directory-surface giving-section mb-5">
      <div class="step-heading">
        <span class="step-number" :class="{ 'step-number--done': providerReady }">
          <v-icon v-if="providerReady" icon="mdi-check" size="18" />
          <template v-else>1</template>
        </span>

        <div>
          <h2>Connect a payment provider</h2>
          <p class="directory-muted">Payments are taken on Yoco's secure page. Card details never reach the church.</p>
        </div>
      </div>

      <div v-if="providers" class="step-body">
        <v-alert v-if="providers.encryptionReady === false" class="mb-3" type="warning" variant="tonal">
          Credential storage isn't ready on the server. Ask whoever runs the system to set the payments key.
        </v-alert>

        <div v-for="p in providers.providers" :key="p.settings.provider" class="step-row">
          <strong class="text-capitalize">{{ p.settings.provider }}</strong>

          <v-chip :color="p.settings.enabled ? 'success' : undefined" size="small" variant="tonal">
            {{ p.settings.enabled ? `Enabled · ${p.settings.mode}` : 'Not enabled' }}
          </v-chip>
        </div>

        <v-btn class="mt-3" color="primary" to="/administration/payment-settings" variant="tonal">
          {{ providerReady ? 'Manage provider settings' : 'Connect a provider' }}
        </v-btn>
      </div>

      <p v-else class="directory-muted mt-2">
        {{ canConfigure ? 'Loading…' : 'Only an administrator with payment settings access can connect a provider. Ask them to do this step.' }}
      </p>
    </section>

    <!-- Step 2: funds -->
    <section class="directory-surface giving-section mb-5">
      <div class="step-heading">
        <span class="step-number" :class="{ 'step-number--done': fundsReady }">
          <v-icon v-if="fundsReady" icon="mdi-check" size="18" />
          <template v-else>2</template>
        </span>

        <div>
          <h2>Choose which funds people can give to</h2>
          <p class="directory-muted">Only funds you switch on appear on the public page. Switching one off doesn't affect gifts already made.</p>
        </div>
      </div>

      <div class="step-body">
        <p v-if="funds.length === 0 && !loading" class="directory-muted">
          There are no funds yet. <router-link to="/giving">Create a fund in the gift ledger</router-link>, then come back.
        </p>

        <article v-for="fund in funds" :key="fund.id" class="step-row">
          <div>
            <strong>{{ fund.name }}</strong>
            <p v-if="fund.description" class="directory-muted">{{ fund.description }}</p>
            <small v-if="!fund.active">Inactive fund</small>
          </div>

          <v-switch
            color="primary"
            :disabled="working || (!fund.active && !fund.enabled)"
            hide-details
            :label="fund.enabled ? 'Offered online' : 'Not offered'"
            :model-value="fund.enabled"
            @update:model-value="toggle(fund)"
          />
        </article>
      </div>
    </section>

    <!-- Step 3: link -->
    <section class="directory-surface giving-section">
      <div class="step-heading">
        <span class="step-number" :class="{ 'step-number--done': live }">
          <v-icon v-if="live" icon="mdi-check" size="18" />
          <template v-else>3</template>
        </span>

        <div>
          <h2>Share your giving link</h2>
          <p class="directory-muted">Put it on your website, in the service notes or on a screen. People give without signing in, and get a confirmation by email.</p>
        </div>
      </div>

      <div class="step-body link-row">
        <div class="link-main">
          <v-text-field
            append-inner-icon="mdi-content-copy"
            density="comfortable"
            hide-details
            label="Public giving link"
            :model-value="link"
            readonly
            @click:append-inner="copy"
          />

          <div class="mt-3 link-actions">
            <v-btn prepend-icon="mdi-content-copy" variant="tonal" @click="copy">{{ copied ? 'Copied' : 'Copy link' }}</v-btn>

            <v-btn
              append-icon="mdi-open-in-new"
              :href="link"
              rel="noopener"
              target="_blank"
              variant="text"
            >Open the page</v-btn>
          </div>

          <p v-if="!live" class="directory-muted mt-3">The page shows "not available" until steps 1 and 2 are done.</p>
        </div>

        <img
          v-if="qr"
          alt="QR code for the giving link"
          class="link-qr"
          height="160"
          :src="qr"
          width="160"
        >
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
  import type { OnlineFund } from '@/payments/donor-api'
  import type { Settings } from '@/payments/settings-api'
  import QRCode from 'qrcode'
  import { computed, onMounted, ref } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import GivingNavigation from '@/giving/components/GivingNavigation.vue'
  import { donorApi } from '@/payments/donor-api'
  import { publicGiving } from '@/payments/public-giving'
  import { paymentSettingsApi } from '@/payments/settings-api'
  import { useAuthStore } from '@/stores/auth'
  import '@/giving/giving.css'

  const auth = useAuthStore()
  const loading = ref(true),
        working = ref(false),
        error = ref(''),
        copied = ref(false),
        funds = ref<OnlineFund[]>([]),
        providers = ref<Settings>(),
        qr = ref(''),
        publicOffer = ref<{ funds: number, providers: number }>()
  const canConfigure = computed(() => auth.hasPermission('payments.configure'))
  const link = computed(() => `${window.location.origin}/give`)
  const providerReady = computed(() =>
    publicOffer.value ? publicOffer.value.providers > 0 : Boolean(providers.value?.providers.some(p => p.settings.enabled)),
  )
  const fundsReady = computed(() => funds.value.some(f => f.enabled && f.active))
  // "Live" is what visitors actually get from the public endpoint, not what we think the settings mean.
  const live = computed(() => Boolean(publicOffer.value && publicOffer.value.funds > 0 && publicOffer.value.providers > 0))
  const remaining = computed(() => {
    const todo = [
      providerReady.value ? '' : 'connect a payment provider (step 1)',
      fundsReady.value ? '' : 'switch on at least one fund (step 2)',
    ].filter(Boolean)
    if (todo.length === 0) return 'The public page is refreshing; reload in a moment.'
    return `To go live: ${todo.join(' and ')}.`
  })

  async function load () {
    loading.value = true
    error.value = ''
    try {
      const [catalogue, offer, settings] = await Promise.all([
        donorApi.onlineFunds(),
        publicGiving.options().catch(() => undefined),
        canConfigure.value ? paymentSettingsApi.get().catch(() => undefined) : Promise.resolve(undefined),
      ])
      funds.value = catalogue.funds
      providers.value = settings
      publicOffer.value = offer ? { funds: offer.funds.length, providers: offer.providers.length } : undefined
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      loading.value = false
    }
  }

  async function toggle (fund: OnlineFund) {
    if (working.value) return
    working.value = true
    error.value = ''
    try {
      await donorApi.fund(fund)
      await load()
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      working.value = false
    }
  }

  async function copy () {
    try {
      await navigator.clipboard.writeText(link.value)
      copied.value = true
      setTimeout(() => {
        copied.value = false
      }, 2000)
    } catch {
      error.value = 'Copy failed. Select the link and copy it manually.'
    }
  }

  onMounted(async () => {
    qr.value = await QRCode.toDataURL(link.value, { margin: 1, width: 320 }).catch(() => '')
    await load()
  })
</script>

<style scoped>
.step-heading {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}
.step-heading h2 {
  margin: 0 0 4px;
  font-size: 1.1rem;
}
.step-number {
  display: inline-flex;
  flex: none;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  font-weight: 700;
  background: rgba(var(--v-theme-on-surface), 0.1);
}
.step-number--done {
  color: rgb(var(--v-theme-on-success));
  background: rgb(var(--v-theme-success));
}
.step-body {
  margin: 12px 0 0 44px;
}
.step-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}
.link-row {
  display: flex;
  gap: 24px;
  align-items: flex-start;
}
.link-main {
  flex: 1;
  min-width: 0;
}
.link-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.link-qr {
  flex: none;
  border-radius: 8px;
  background: var(--workspace-paper);
}
@media (max-width: 600px) {
  .step-body {
    margin-left: 0;
  }
  .link-row {
    flex-direction: column-reverse;
  }
}
</style>
