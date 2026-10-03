<template>
  <div>
    <PageHeader subtitle="Configure integrations used across the portal" title="Settings" />

    <v-row>
      <v-col cols="12" md="6">
        <v-card elevation="2">
          <v-card-title><v-icon class="mr-2" icon="mdi-email-outline" />Email (SMTP / API)</v-card-title>
          <v-divider />

          <v-card-text>
            <v-alert type="info" variant="tonal">
              Email provider configuration is planned for an upcoming bounded context and is not
              yet exposed by the API. This section will let administrators configure SMTP
              credentials or transactional email API providers (e.g. SendGrid, Mailgun, SES).
            </v-alert>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card elevation="2">
          <v-card-title><v-icon class="mr-2" icon="mdi-message-text-outline" />SMS Providers</v-card-title>
          <v-divider />

          <v-card-text>
            <v-alert type="info" variant="tonal">
              SMS provider configuration (e.g. Twilio, Vonage) is planned for an upcoming bounded
              context and is not yet exposed by the API.
            </v-alert>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script lang="ts" setup>
  import PageHeader from '@/components/common/PageHeader.vue'
</script>
