<template>
  <v-app>
    <v-main class="auth-main">
      <div class="auth-shell">
        <div class="auth-visual gradient-brand backdrop-noise d-none d-md-flex">
          <div class="auth-visual__content">
            <div class="auth-visual__brand">
              <div class="auth-visual__brand-mark">
                <v-icon color="secondary" icon="mdi-church" size="26" />
              </div>

              <span class="font-heading text-h6 font-weight-bold text-white">Holyspace</span>
            </div>

            <div class="auth-visual__copy">
              <p class="text-overline text-accent mb-2">Church Management Portal</p>

              <h1 class="font-heading text-h3 font-weight-bold text-white mb-4">
                Shepherd your congregation with clarity.
              </h1>

              <p class="text-body-1 text-white auth-visual__subtitle">
                Manage families, life events, volunteers and giving in a single,
                beautifully organized workspace built for your church staff.
              </p>
            </div>

            <v-row class="auth-visual__stats" no-gutters>
              <v-col cols="4">
                <div class="text-h5 font-heading font-weight-bold text-white">1</div>
                <div class="text-caption text-white auth-visual__stat-label">Unified Platform</div>
              </v-col>

              <v-col cols="4">
                <div class="text-h5 font-heading font-weight-bold text-white">360°</div>
                <div class="text-caption text-white auth-visual__stat-label">Member Insight</div>
              </v-col>

              <v-col cols="4">
                <div class="text-h5 font-heading font-weight-bold text-white">24/7</div>
                <div class="text-caption text-white auth-visual__stat-label">Secure Access</div>
              </v-col>
            </v-row>
          </div>
        </div>

        <div class="auth-form-panel">
          <div class="auth-form-panel__inner">
            <router-view />
          </div>
        </div>
      </div>
    </v-main>

    <AppSnackbar />
  </v-app>
</template>

<script lang="ts" setup>
  import AppSnackbar from '@/components/common/AppSnackbar.vue'
</script>

<style scoped>
.auth-main {
  background: #f3f5fa;
}

.auth-shell {
  display: flex;
  min-height: 100vh;
}

.auth-visual {
  position: relative;
  flex: 0 0 44%;
  max-width: 560px;
  padding: 56px 48px;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
}

.auth-visual__content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 100%;
}

.auth-visual__brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.auth-visual__brand-mark {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background-color: #fce9b9;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.auth-visual__copy {
  margin: 48px 0;
}

.auth-visual__subtitle {
  opacity: 0.78;
  max-width: 380px;
  line-height: 1.6;
}

.auth-visual__stats {
  border-top: 1px solid rgba(255, 255, 255, 0.16);
  padding-top: 24px;
}

.auth-visual__stat-label {
  opacity: 0.68;
}

.auth-form-panel {
  flex: 1 1 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.auth-form-panel__inner {
  width: 100%;
  max-width: 420px;
}
</style>
