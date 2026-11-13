<template>
  <div>
    <PageHeader eyebrow="Workspace settings" subtitle="The services that help your church stay in touch." title="Settings" />

    <v-row>
      <v-col v-for="integration in integrations" :key="integration.title" cols="12" md="6">
        <v-card class="integration-card">
          <v-card-text>
            <div class="d-flex justify-space-between align-center mb-6"><span class="stat-icon-badge integration-icon"><v-icon :icon="integration.icon" /></span><v-chip size="small" variant="tonal">Coming soon</v-chip></div>
            <h2>{{ integration.title }}</h2>
            <p>{{ integration.description }}</p>
            <div class="integration-footer"><v-icon icon="mdi-clock-outline" size="16" /> Configuration will be available in a future release.</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script lang="ts" setup>
  import PageHeader from '@/components/common/PageHeader.vue'

  const integrations = [
    { title: 'Email delivery', icon: 'mdi-email-outline', description: 'Connect an email provider to reach your community with updates, reminders, and thoughtful messages.' },
    { title: 'SMS delivery', icon: 'mdi-message-text-outline', description: 'Keep important messages close at hand with text notifications for your members and families.' },
  ]
</script>

<style scoped>
.integration-icon { background: var(--workspace-tint); color: var(--workspace-blue); }
.integration-card h2 { font: 700 20px 'Plus Jakarta Sans', sans-serif; color: var(--workspace-ink); margin-bottom: 12px; }
.integration-card p { color: var(--workspace-muted); max-width: 420px; }
.integration-footer { display: flex; align-items: center; gap: 9px; border-top: 1px solid var(--workspace-border); padding-top: 20px; margin-top: 28px; color: var(--workspace-muted); font-size: 12px; }
</style>
