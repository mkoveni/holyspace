<template>
  <v-app>
    <a
      class="skip-link"
      href="#main-content"
    >Skip to content</a>

    <v-navigation-drawer
      v-model="drawer"
      class="app-drawer"
      :mobile-breakpoint="960"
      :temporary="!mdAndUp"
      width="248"
    >
      <div class="app-drawer__brand">
        <div class="brand-mark">
          <v-icon
            icon="mdi-church-outline"
            size="24"
          />
        </div>

        <div>
          <div class="app-drawer__wordmark">
            Divine Community
            <span />
          </div>

          <p>Space</p>
        </div>

        <v-btn
          v-if="!mdAndUp"
          aria-label="Close navigation"
          class="ml-auto"
          icon="mdi-close"
          size="small"
          variant="text"
          @click="drawer = false"
        />
      </div>

      <nav
        aria-label="Main navigation"
        class="app-drawer__nav"
      >
        <router-link
          v-for="item in navItems"
          :key="item.key"
          :aria-current="item.active ? 'page' : undefined"
          class="nav-link"
          :class="{ 'nav-link--active': item.active }"
          :to="item.to"
          @click="closeMobileNavigation"
        >
          <v-icon
            :icon="item.icon"
            size="20"
          />

          <span>{{ item.title }}</span>
        </router-link>
      </nav>

      <template #append>
        <div class="app-drawer__footer">
          <v-avatar
            class="app-drawer__avatar"
            size="36"
          >
            {{ initials }}
          </v-avatar>

          <div>
            <strong>{{ authStore.displayName }}</strong>
            <span>Signed in</span>
          </div>

          <v-btn
            aria-label="Sign out"
            icon="mdi-logout"
            size="small"
            variant="text"
            @click="handleLogout"
          />
        </div>
      </template>
    </v-navigation-drawer>

    <v-app-bar
      class="app-bar"
      flat
      height="64"
    >
      <v-app-bar-nav-icon
        :aria-expanded="drawer"
        aria-label="Toggle navigation"
        class="d-md-none"
        @click="drawer = !drawer"
      />

      <div class="app-bar__heading">
        <span class="app-bar__eyebrow">{{ activeSection }}</span>

        <v-toolbar-title class="app-bar__title">
          {{ pageTitle }}
        </v-toolbar-title>
      </div>

      <v-spacer />

      <button
        aria-label="Search pages, people and actions"
        class="app-bar__search"
        type="button"
        @click="paletteOpen = true"
      >
        <v-icon
          icon="mdi-magnify"
          size="18"
        />

        <span class="d-none d-sm-inline">Search or jump to…</span>
        <kbd class="d-none d-md-inline">{{ shortcut }}</kbd>
      </button>

      <QuickActions />

      <NotificationsMenu />

      <v-btn
        :aria-label="mode === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'"
        class="d-none d-sm-inline-flex ml-1"
        :icon="mode === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night'"
        variant="text"
        @click="toggleTheme"
      />

      <v-menu
        location="bottom end"
        :offset="10"
      >
        <template #activator="{ props: menuProps }">
          <v-btn
            v-bind="menuProps"
            aria-label="Account menu"
            class="app-bar__account"
            variant="text"
          >
            <v-avatar
              class="app-drawer__avatar"
              size="34"
            >
              {{ initials }}
            </v-avatar>

            <v-icon
              class="d-none d-sm-inline-flex ml-1"
              icon="mdi-chevron-down"
              size="16"
            />
          </v-btn>
        </template>

        <v-list
          class="app-account-menu"
          density="compact"
          min-width="230"
          rounded="xl"
        >
          <v-list-item
            prepend-icon="mdi-account-circle-outline"
            subtitle="Signed in"
            :title="authStore.displayName"
          />

          <v-divider class="my-2" />

          <v-list-item
            :prepend-icon="mode === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night'"
            :title="mode === 'dark' ? 'Light theme' : 'Dark theme'"
            @click="toggleTheme"
          />

          <v-list-item
            prepend-icon="mdi-cog-outline"
            title="Settings"
            to="/settings"
          />

          <v-list-item
            prepend-icon="mdi-logout"
            title="Sign out"
            @click="handleLogout"
          />
        </v-list>
      </v-menu>
    </v-app-bar>

    <v-main
      id="main-content"
      class="app-main"
    >
      <v-container
        class="workspace-content"
        fluid
      >
        <v-alert
          v-if="authStore.sessionError"
          class="mb-4"
          type="warning"
          variant="tonal"
        >
          {{ authStore.sessionError }}
          <v-btn
            class="ml-2"
            :loading="authStore.isBootstrapping"
            variant="text"
            @click="authStore.bootstrap()"
          >
            Retry
          </v-btn>
        </v-alert>

        <SectionTabs :area="currentArea" />

        <router-view v-slot="{ Component }">
          <transition
            mode="out-in"
            name="router-fade"
          >
            <component :is="Component" />
          </transition>
        </router-view>
      </v-container>
    </v-main>

    <CommandPalette v-model="paletteOpen" />
    <AppSnackbar />
    <ConfirmDialog />
  </v-app>
</template>
<script lang="ts" setup>
  import { computed, onMounted, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useDisplay } from 'vuetify'
  import AppSnackbar from '@/components/common/AppSnackbar.vue'
  import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
  import { useAppTheme } from '@/composables/useAppTheme'
  import { activeAreaItem, visibleAreas } from '@/navigation/areas'
  import CommandPalette from '@/navigation/components/CommandPalette.vue'
  import NotificationsMenu from '@/navigation/components/NotificationsMenu.vue'
  import QuickActions from '@/navigation/components/QuickActions.vue'
  import SectionTabs from '@/navigation/components/SectionTabs.vue'
  import { routeMatches } from '@/navigation/workspace'
  import { useAuthStore } from '@/stores/auth'
  const { mdAndUp } = useDisplay()
  const drawer = ref(mdAndUp.value),
        paletteOpen = ref(false),
        route = useRoute(),
        router = useRouter(),
        authStore = useAuthStore(),
        { mode, init: initTheme, toggle: toggleTheme } = useAppTheme()
  const shortcut = /Mac|iPhone|iPad/.test(globalThis.navigator?.platform ?? '') ? '⌘K' : 'Ctrl K'
  const areas = computed(() =>
    visibleAreas(permission => authStore.hasPermission(permission)),
  )
  const homeItems = computed(() => [
    { key: 'home', title: 'Home', to: '/', icon: 'mdi-view-dashboard-outline' },
    ...(authStore.hasPermission('member.portal')
      ? [{ key: 'member', title: 'My church', to: '/member', icon: 'mdi-account-circle-outline' }]
      : []),
  ])
  const currentArea = computed(() =>
    areas.value.find(area => activeAreaItem(area, route.path) !== ''),
  )
  const navItems = computed(() => [
    ...homeItems.value.map(item => ({
      ...item,
      active: item.to === '/' ? route.path === '/' : routeMatches(route.path, item.to),
    })),
    ...areas.value.map(area => ({
      key: area.key,
      title: area.title,
      to: area.to,
      icon: area.icon,
      active: currentArea.value?.key === area.key,
    })),
  ])
  const activeSection = computed(
    () => currentArea.value?.title || (route.path === '/' ? 'Home' : 'Church workspace'),
  )
  watch(mdAndUp, wide => {
    drawer.value = wide
  })
  watch(() => route.path, closeMobileNavigation)
  onMounted(initTheme)
  const pageTitle = computed(() => (route.meta.title as string) ?? 'Divine Community Space')
  const initials = computed(() =>
    (authStore.displayName || 'A')
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map(part => part[0])
      .join('')
      .toUpperCase(),
  )
  function closeMobileNavigation () {
    if (!mdAndUp.value) drawer.value = false
  }
  async function handleLogout () {
    try {
      await authStore.logout()
    } catch {
      /* Local sign-out also succeeds while offline. */
    }
    await router.push({ name: 'login' })
  }
</script>
<style scoped>
  .skip-link {
    position: absolute;
    left: 12px;
    top: -48px;
    z-index: 3000;
    padding: 8px 14px;
    border-radius: 8px;
    background: var(--workspace-blue);
    color: #fff;
    transition: top 0.15s;
  }
  .skip-link:focus {
    top: 12px;
  }
  .app-drawer {
    background: var(--nav-bg) !important;
    border-right: 0 !important;
    color: var(--nav-ink);
  }
  .app-drawer__brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 24px 20px 20px;
  }
  .brand-mark {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(145deg, #c8b07a, #a58b55);
    color: #1b2a41;
    flex-shrink: 0;
  }
  .app-drawer__wordmark {
    display: flex;
    align-items: center;
    gap: 6px;
    font:
      700 16px 'Plus Jakarta Sans',
      sans-serif;
    letter-spacing: -0.2px;
    color: #fff;
  }
  .app-drawer__wordmark span {
    width: 5px;
    height: 5px;
    background: var(--workspace-gold);
    border-radius: 50%;
    margin-top: 8px;
  }
  .app-drawer__brand p {
    font-size: 12px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--nav-muted);
    margin-top: 2px;
  }
  .app-drawer__nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px 12px 16px;
  }
  .nav-link {
    position: relative;
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 44px;
    padding: 0 14px;
    border-radius: 12px;
    color: var(--nav-ink);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition:
      background-color 0.18s ease,
      color 0.18s ease;
  }
  .nav-link .v-icon {
    color: var(--nav-muted);
    transition: color 0.18s ease;
  }
  .nav-link:hover {
    background: #ffffff0d;
    color: #fff;
  }
  .nav-link--active {
    background: var(--nav-active-bg);
    color: #fff;
    font-weight: 600;
  }
  .nav-link--active .v-icon {
    color: var(--workspace-gold);
  }
  .nav-link--active::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 10px;
    bottom: 10px;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: var(--workspace-gold);
  }
  .nav-link:focus-visible {
    outline: 2px solid var(--workspace-gold);
    outline-offset: 2px;
  }
  .app-drawer__footer {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    border-top: 1px solid var(--nav-border);
    color: var(--nav-ink);
  }
  .app-drawer__footer > div:nth-child(2) {
    min-width: 0;
    flex: 1;
  }
  .app-drawer__footer strong {
    display: block;
    color: #fff;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .app-drawer__footer span {
    font-size: 12px;
    color: var(--nav-muted);
  }
  .app-drawer__avatar {
    background: var(--workspace-tint);
    color: var(--workspace-blue);
    font-size: 12px;
    font-weight: 700;
    border: 1px solid var(--workspace-tint-border);
  }
  .app-bar {
    border-bottom: 1px solid var(--workspace-border);
    background: var(--workspace-paper) !important;
    padding-inline: 6px 12px;
  }
  .app-bar__heading {
    min-width: 0;
    margin-left: 8px;
  }
  .app-bar__eyebrow {
    display: block;
    color: var(--workspace-gold);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    margin-bottom: 2px;
  }
  .app-bar__title {
    font:
      700 17px 'Plus Jakarta Sans',
      sans-serif;
    color: var(--workspace-ink);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .app-bar__search {
    display: flex;
    align-items: center;
    gap: 10px;
    height: 40px;
    min-width: 40px;
    justify-content: center;
    padding: 0 14px;
    margin-right: 12px;
    border: 1px solid var(--workspace-border);
    border-radius: 12px;
    background: var(--workspace-soft);
    color: var(--workspace-muted);
    font-size: 14px;
    cursor: pointer;
    transition:
      border-color 0.18s ease,
      box-shadow 0.18s ease;
  }
  .app-bar__search:hover {
    border-color: var(--workspace-blue);
    box-shadow: 0 0 0 3px var(--workspace-tint);
  }
  @media (min-width: 600px) {
    .app-bar__search {
      min-width: 240px;
      justify-content: flex-start;
    }
    .app-bar__search kbd {
      margin-left: auto;
    }
  }
  .app-bar__search kbd {
    padding: 1px 7px;
    border: 1px solid var(--workspace-border);
    border-radius: 6px;
    background: var(--workspace-paper);
    font: 600 11px 'Manrope', sans-serif;
  }
  .app-bar__account {
    min-width: 48px;
    padding-inline: 6px;
    margin-left: 4px;
  }
  .app-account-menu {
    border: 1px solid var(--workspace-border);
    padding: 8px;
  }
  .app-main {
    background: var(--workspace-background);
  }
  @media (max-width: 599px) {
    .app-bar {
      padding-inline: 0 4px;
    }
    .app-bar__heading {
      flex: 1;
      margin-right: 8px;
    }
    .app-bar__title {
      font-size: 15px;
      max-width: 40vw;
    }
    .app-bar__search {
      margin-right: 6px;
      padding: 0;
      width: 40px;
    }
  }
</style>
