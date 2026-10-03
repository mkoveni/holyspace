<template>
  <v-app>
    <v-navigation-drawer
      v-model="drawer"
      class="app-drawer"
      color="primary"
      theme="dark"
      width="264"
    >
      <div class="d-flex align-center pa-5 ga-3">
        <div class="brand-mark">
          <v-icon color="primary" icon="mdi-church" size="22" />
        </div>

        <div>
          <div class="font-heading text-subtitle-1 font-weight-bold">Holyspace</div>
          <div class="text-caption app-drawer__subtitle">Church Management Portal</div>
        </div>
      </div>

      <v-divider class="app-drawer__divider" />

      <v-list class="app-drawer__list px-3 pt-3" density="comfortable" nav>
        <v-list-subheader class="app-drawer__subheader">Overview</v-list-subheader>

        <v-list-item
          v-for="item in overviewItems"
          :key="item.to"
          :active="isActive(item.to)"
          class="app-drawer__item mb-1"
          :prepend-icon="item.icon"
          rounded="sm"
          :title="item.title"
          :to="item.to"
        />

        <v-list-subheader class="app-drawer__subheader mt-3">People &amp; Life Events</v-list-subheader>

        <v-list-item
          v-for="item in peopleItems"
          :key="item.to"
          :active="isActive(item.to)"
          class="app-drawer__item mb-1"
          :prepend-icon="item.icon"
          rounded="sm"
          :title="item.title"
          :to="item.to"
        />

        <v-list-subheader class="app-drawer__subheader mt-3">Accounts &amp; Access</v-list-subheader>

        <v-list-item
          v-for="item in accessItems"
          :key="item.to"
          :active="isActive(item.to)"
          class="app-drawer__item mb-1"
          :prepend-icon="item.icon"
          rounded="sm"
          :title="item.title"
          :to="item.to"
        />

        <v-list-subheader class="app-drawer__subheader mt-3">System</v-list-subheader>

        <v-list-item
          v-for="item in systemItems"
          :key="item.to"
          :active="isActive(item.to)"
          class="app-drawer__item mb-1"
          :prepend-icon="item.icon"
          rounded="sm"
          :title="item.title"
          :to="item.to"
        />
      </v-list>

      <template #append>
        <div class="pa-4">
          <div class="app-drawer__footer-card">
            <v-icon color="accent" icon="mdi-shield-check-outline" size="20" />

            <div class="text-caption app-drawer__subtitle mt-2">
              Signed in as <strong class="text-white">{{ authStore.displayName }}</strong>
            </div>
          </div>
        </div>
      </template>
    </v-navigation-drawer>

    <v-app-bar class="app-bar" color="surface" flat height="72">
      <v-app-bar-nav-icon @click="drawer = !drawer" />

      <div>
        <v-toolbar-title class="font-heading font-weight-bold text-h6 mb-0 pb-0">
          {{ pageTitle }}
        </v-toolbar-title>

        <div class="text-caption text-medium-emphasis app-bar__eyebrow">
          {{ formattedDate }}
        </div>
      </div>

      <v-spacer />

      <v-btn class="mr-1" icon variant="text">
        <v-badge color="error" content="3" offset-x="2" offset-y="2">
          <v-icon icon="mdi-bell-outline" />
        </v-badge>
      </v-btn>

      <v-divider class="mx-2" length="28" vertical />

      <v-menu>
        <template #activator="{ props: menuProps }">
          <v-btn class="text-none app-bar__menu-btn" v-bind="menuProps" variant="text">
            <v-avatar class="mr-2" color="primary" size="36">
              <span class="text-white font-weight-bold">{{ initials }}</span>
            </v-avatar>

            <div class="d-none d-sm-flex flex-column align-start">
              <span class="text-body-2 font-weight-medium">{{ authStore.displayName }}</span>
              <span class="text-caption text-medium-emphasis">Administrator</span>
            </div>

            <v-icon class="ml-1" icon="mdi-chevron-down" size="18" />
          </v-btn>
        </template>

        <v-list density="compact" min-width="220" rounded="sm">
          <v-list-item prepend-icon="mdi-account-circle-outline" subtitle="Signed in" :title="authStore.displayName" />
          <v-divider class="my-1" />
          <v-list-item prepend-icon="mdi-cog-outline" title="Settings" to="/settings" />
          <v-list-item prepend-icon="mdi-logout" title="Sign out" @click="handleLogout" />
        </v-list>
      </v-menu>
    </v-app-bar>

    <v-main class="app-main">
      <v-container fluid>
        <router-view />
      </v-container>
    </v-main>

    <AppSnackbar />
    <ConfirmDialog />
  </v-app>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import AppSnackbar from '@/components/common/AppSnackbar.vue'
  import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
  import { useAuthStore } from '@/stores/auth'

  const drawer = ref(true)
  const route = useRoute()
  const router = useRouter()
  const authStore = useAuthStore()

  const overviewItems = [
    { title: 'Dashboard', to: '/', icon: 'mdi-view-dashboard-outline' },
  ]

  const peopleItems = [
    { title: 'People', to: '/people', icon: 'mdi-account-group-outline' },
    { title: 'Families', to: '/families', icon: 'mdi-home-heart' },
    { title: 'Life Events', to: '/life-events', icon: 'mdi-calendar-star' },
  ]

  const accessItems = [
    { title: 'User Accounts', to: '/user-accounts', icon: 'mdi-account-key-outline' },
    { title: 'Roles', to: '/roles', icon: 'mdi-shield-account-outline' },
    { title: 'Permissions', to: '/permissions', icon: 'mdi-key-chain-variant' },
  ]

  const systemItems = [
    { title: 'Settings', to: '/settings', icon: 'mdi-cog-outline' },
  ]

  const pageTitle = computed(() => (route.meta.title as string) ?? 'Holyspace')

  const formattedDate = computed(() => new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }))

  const initials = computed(() => {
    const name = authStore.displayName || 'A'
    return name.slice(0, 2).toUpperCase()
  })

  function isActive (to: string) {
    return to === '/' ? route.path === '/' : route.path.startsWith(to)
  }

  async function handleLogout () {
    authStore.logout()
    await router.push({ name: 'login' })
  }
</script>

<style scoped>
.brand-mark {
  width: 38px;
  height: 38px;
  border-radius: 6px;
  background-color: #fce9b9;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.app-drawer {
  border-right: none !important;
}

.app-drawer__divider {
  border-color: rgba(255, 255, 255, 0.12) !important;
}

.app-drawer__subtitle {
  opacity: 0.65;
}

.app-drawer__subheader {
  opacity: 0.55;
  font-size: 0.68rem;
  letter-spacing: 0.08em;
  font-weight: 700;
  text-transform: uppercase;
}

.app-drawer__item {
  color: rgba(255, 255, 255, 0.8);
  border-left: 2px solid transparent;
}

.app-drawer__item :deep(.v-icon) {
  opacity: 0.85;
}

.app-drawer__item.v-list-item--active {
  background: rgba(255, 255, 255, 0.1);
  border-left: 2px solid #fce9b9;
  color: #fff;
}

.app-drawer__item.v-list-item--active :deep(.v-icon) {
  opacity: 1;
  color: #fce9b9;
}

.app-drawer__footer-card {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  padding: 16px;
}

.app-bar {
  border-bottom: 1px solid #e4e8f1;
}

.app-bar__eyebrow {
  margin-top: -2px;
}

.app-bar__menu-btn {
  padding-inline: 8px;
}

.app-main {
  background: #f3f5fa;
}
</style>
