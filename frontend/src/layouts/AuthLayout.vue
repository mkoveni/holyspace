<template>
  <v-app>
    <v-main class="auth-main">
      <div class="auth-shell">
        <section class="auth-visual d-none d-md-flex">
          <div class="auth-brand"><span><v-icon icon="mdi-church" size="24" /></span><strong>Divine Community Space</strong></div>

          <div class="auth-copy">
            <div class="auth-eyebrow">PEOPLE. FAMILIES. SHARED MOMENTS.</div>
            <h1>A closer view of<br>your community<span>.</span></h1>
            <p>A thoughtful space for your church’s people, households, and milestones. Everything you need to stay connected.</p>

            <div aria-hidden="true" class="auth-household">
              <div class="auth-household__header"><span class="auth-household__icon"><v-icon icon="mdi-home-heart" /></span><div><small>YOUR COMMUNITY</small><strong>Connected by care</strong></div></div>
              <div class="auth-household__row"><v-icon icon="mdi-account-group-outline" /><span>People &amp; families</span><v-icon icon="mdi-check-circle-outline" size="17" /></div>
              <div class="auth-household__row"><v-icon icon="mdi-calendar-heart" /><span>Life’s meaningful moments</span><v-icon icon="mdi-check-circle-outline" size="17" /></div>
              <div class="auth-household__row"><v-icon icon="mdi-account-star-outline" /><span>The right person to reach</span><v-icon icon="mdi-check-circle-outline" size="17" /></div>
            </div>
          </div>

          <div class="auth-footer">CHURCH MANAGEMENT, WITH CLARITY</div>
        </section>

        <section class="auth-form-panel">
          <div class="auth-form-panel__inner"><router-view /></div>
        </section>
      </div>
    </v-main>

    <AppSnackbar />
  </v-app>
</template>

<script lang="ts" setup>
  import { onMounted } from 'vue'
  import { useTheme } from 'vuetify'
  import AppSnackbar from '@/components/common/AppSnackbar.vue'

  // Sign-in screens are designed for the light theme only; the saved preference returns after sign-in.
  const theme = useTheme()
  onMounted(() => {
    document.documentElement.dataset.theme = 'light'
    theme.change('divineCommunitySpaceLight')
  })
</script>

<style scoped>
.auth-main { background: #f5f6f8; }
.auth-shell { display: flex; min-height: 100vh; }
.auth-visual { flex: 0 0 48%; padding: 42px clamp(32px, 5vw, 80px); flex-direction: column; justify-content: space-between; background: linear-gradient(135deg, #f7f5ef, #eaf0fb); border-right: 1px solid #e1e5eb; }
.auth-brand { display: flex; align-items: center; gap: 13px; color: var(--workspace-ink); font: 700 20px 'Plus Jakarta Sans', sans-serif; }
.auth-brand > span { display: grid; place-items: center; width: 44px; height: 44px; color: var(--workspace-blue); background: #fff; border: 1px solid var(--workspace-tint-border); border-radius: 14px; }
.auth-copy { padding-block: 48px; }
.auth-eyebrow, .auth-footer { font-size: 11px; font-weight: 800; letter-spacing: 1.6px; color: #718096; }
.auth-copy h1 { font: 700 clamp(30px, 3.3vw, 48px)/1.2 'Plus Jakarta Sans', sans-serif; letter-spacing: -1.6px; color: var(--workspace-ink); margin: 20px 0; }
.auth-copy h1 span { color: #b29a66; }
.auth-copy > p { max-width: 420px; color: var(--workspace-muted); font-size: 14px; line-height: 1.8; }
.auth-household { margin-top: 32px; padding: 24px; background: #ffffffb8; border: 1px solid #e1e6ef; border-radius: 20px; max-width: 400px; box-shadow: var(--workspace-shadow); }
.auth-household__header { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
.auth-household__icon { display: grid; place-items: center; width: 44px; height: 44px; border: 1px solid var(--workspace-tint-border); border-radius: 13px; background: var(--workspace-tint); color: var(--workspace-blue); }
.auth-household__header small { display: block; font-size: 11px; letter-spacing: 1px; color: #718096; margin-bottom: 5px; }
.auth-household__header strong { font: 700 15px 'Plus Jakarta Sans', sans-serif; color: var(--workspace-ink); }
.auth-household__row { display: flex; align-items: center; gap: 12px; border-top: 1px solid var(--workspace-border); padding: 14px 0; color: var(--workspace-muted); font-size: 13px; }
.auth-household__row:last-child { padding-bottom: 0; }
.auth-household__row > span { flex: 1; }
.auth-household__row > .v-icon { font-size: 18px; color: #8092b1; }
.auth-form-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 32px; }
.auth-form-panel__inner { width: 100%; max-width: 460px; padding: 38px; border: 1px solid var(--workspace-border); border-radius: 22px; background: #fff; box-shadow: var(--workspace-shadow); }
@media (max-width: 600px) { .auth-form-panel { padding: 20px 12px; } .auth-form-panel__inner { padding: 28px 24px; } }
</style>
