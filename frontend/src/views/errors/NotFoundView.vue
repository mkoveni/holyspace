<template>
  <v-app>
    <v-main class="not-found">
      <div class="not-found__card">
        <div class="detail-label">HOLYSPACE · PAGE NOT FOUND</div>
        <span class="not-found__number">404<span>.</span></span>
        <h1>A little off the path.</h1>
        <p>This page may have moved, or the address may be incorrect. Your workspace is just a click away.</p>
        <v-btn color="primary" prepend-icon="mdi-arrow-left" to="/" variant="flat">Back to dashboard</v-btn>
      </div>
    </v-main>
  </v-app>
</template>

<style scoped>
.not-found { display: grid; place-items: center; min-height: 100vh; padding: 24px; background: linear-gradient(115deg, #f7f5ef, #edf2fa); }
.not-found__card { padding: 48px 40px; max-width: 540px; border: 1px solid var(--workspace-border); border-radius: 24px; background: #fff; box-shadow: var(--workspace-shadow); }
.not-found__number { display: block; font: 700 96px 'Plus Jakarta Sans', sans-serif; color: #263c61; letter-spacing: -6px; }
.not-found__number > span { color: var(--workspace-gold); }
.not-found h1 { font: 700 25px 'Plus Jakarta Sans', sans-serif; color: var(--workspace-ink); }
.not-found p { color: var(--workspace-muted); font-size: 13px; line-height: 1.8; margin: 16px 0 28px; }
</style>
