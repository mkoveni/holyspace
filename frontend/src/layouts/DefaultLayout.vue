<template>
  <v-app>
    <v-navigation-drawer
      v-model="drawer"
      class="app-drawer"
      :mobile-breakpoint="960"
      :temporary="!mdAndUp"
      width="252"
    >
      <div class="app-drawer__brand">
        <div class="brand-mark">
          <v-icon
            icon="mdi-church-outline"
            size="25"
          />
        </div>

        <div>
          <div class="app-drawer__wordmark">
            Divine Community Space
            <span />
          </div>

          <p>A place for your community</p>
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

      <v-list
        v-model:opened="openedGroups"
        aria-label="Main navigation"
        class="app-drawer__list"
        density="comfortable"
        nav
        open-strategy="single"
        slim
      >
        <v-list-subheader class="app-drawer__label">
          YOUR WORKSPACE
        </v-list-subheader>

        <v-list-item
          v-for="item in homeItems"
          :key="item.to"
          :active="isActive(item.to)"
          :aria-current="isActive(item.to) ? 'page' : undefined"
          class="app-drawer__item app-drawer__home"
          exact
          :prepend-icon="item.icon"
          :title="item.title"
          :to="item.to"
          @click="closeMobileNavigation"
        />

        <div class="app-drawer__rule" />

        <v-list-group
          v-for="group in navigationGroups"
          :key="group.title"
          class="app-drawer__group"
          :value="group.title"
        >
          <template #activator="{ props }">
            <v-list-item
              v-bind="props"
              class="app-drawer__section"
              :class="{
                'app-drawer__section--current': group.items.some((item) =>
                  routeMatches(route.path, item.to),
                ),
              }"
              :prepend-icon="group.icon"
              :title="group.title"
            />
          </template>

          <v-list-item
            v-for="item in group.items"
            :key="item.to"
            :active="isActive(item.to)"
            :aria-current="isActive(item.to) ? 'page' : undefined"
            class="app-drawer__item app-drawer__child"
            exact
            :prepend-icon="navigationIcon(item.to)"
            :title="item.title"
            :to="item.to"
            @click="closeMobileNavigation"
          />
        </v-list-group>
      </v-list>

      <template #append>
        <div class="app-drawer__footer">
          <v-avatar
            class="app-drawer__avatar"
            size="34"
          >
            {{ initials }}
          </v-avatar>

          <div>
            <strong>{{ authStore.displayName }}</strong>

            <span>
              <i />
              Your church workspace
            </span>
          </div>

          <v-btn
            aria-label="Workspace settings"
            icon="mdi-cog-outline"
            size="small"
            to="/settings"
            variant="text"
          />
        </div>
      </template>
    </v-navigation-drawer>

    <v-app-bar
      class="app-bar"
      flat
      height="68"
    >
      <v-app-bar-nav-icon
        :aria-expanded="drawer"
        aria-label="Toggle navigation"
        @click="drawer = !drawer"
      />

      <div class="app-bar__heading">
        <span class="app-bar__eyebrow">{{ activeSection }}</span>

        <v-toolbar-title class="app-bar__title">
          {{ pageTitle }}
        </v-toolbar-title>
      </div>

      <v-spacer />
      <span class="app-bar__date">{{ formattedDate }}</span>
      <QuickActions />

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

    <v-main class="app-main">
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

        <router-view />
      </v-container>
    </v-main>

    <AppSnackbar />
    <ConfirmDialog />
  </v-app>
</template>
<script lang="ts" setup>
  import { computed, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useDisplay } from 'vuetify'
  import AppSnackbar from '@/components/common/AppSnackbar.vue'
  import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
  import QuickActions from '@/navigation/components/QuickActions.vue'
  import {
    navigationIcon,
    routeMatches,
    visibleNavigation,
  } from '@/navigation/workspace'
  import { useAuthStore } from '@/stores/auth'
  const { mdAndUp } = useDisplay()
  const drawer = ref(mdAndUp.value),
        route = useRoute(),
        router = useRouter(),
        authStore = useAuthStore()
  const homeItems = computed(() => [
    {
      title: 'Community',
      to: '/',
      icon: 'mdi-view-dashboard-outline',
    },
    ...(authStore.hasPermission('member.portal')
      ? [
        {
          title: 'My church',
          to: '/member',
          icon: 'mdi-account-circle-outline',
        },
      ]
      : []),
  ])
  const navigationGroups = computed(() =>
    visibleNavigation(permission => authStore.hasPermission(permission)).map(
      group => ({
        ...group,
        title: {
          'Engagement & reports': 'Communications',
          'Accounts & access': 'Administration',
          'My account': 'Preferences',
        }[group.title] || group.title,
      }),
    ),
  )
  const activeDestination = computed(() =>
    [
      ...homeItems.value,
      ...navigationGroups.value.flatMap(group => group.items),
    ]
      .filter(item => routeMatches(route.path, item.to))
      .filter(
        item =>
          item.to !== '/children'
          || route.path === '/children'
          || /^\/children\/(?:programmes|participants)\//.test(route.path),
      )
      .reduce(
        (best, item) => (item.to.length > best.length ? item.to : best),
        '',
      ),
  )
  const activeSection = computed(
    () =>
      navigationGroups.value.find(group =>
        group.items.some(item => routeMatches(route.path, item.to)),
      )?.title || 'Church workspace',
  )
  const openedGroups = ref<string[]>([])
  watch(mdAndUp, wide => {
    drawer.value = wide
  })
  watch(
    [() => route.path, navigationGroups],
    () => {
      const active = navigationGroups.value.find(group =>
        group.items.some(item => routeMatches(route.path, item.to)),
      )
      openedGroups.value = active
        ? [active.title]
        : openedGroups.value.filter(title =>
          navigationGroups.value.some(group => group.title === title),
        )
      closeMobileNavigation()
    },
    { immediate: true },
  )
  const pageTitle = computed(() => (route.meta.title as string) ?? 'Divine Community Space')
  const formattedDate = computed(() =>
    new Date().toLocaleDateString(undefined, {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
    }),
  )
  const initials = computed(() =>
    (authStore.displayName || 'A')
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map(part => part[0])
      .join('')
      .toUpperCase(),
  )
  function isActive (to: string) {
    return to === activeDestination.value
  }
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
  .app-drawer {
    background: #fbfcf9;
    border-right: 1px solid #e2e8df !important;
  }
  .app-drawer__brand {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 24px 18px 22px;
  }
  .brand-mark {
    display: grid;
    place-items: center;
    width: 42px;
    height: 46px;
    border-radius: 15px;
    background: #315642;
    color: #f4f3df;
    flex-shrink: 0;
  }
  .app-drawer__wordmark {
    display: flex;
    align-items: center;
    gap: 6px;
    font:
      700 20px 'Plus Jakarta Sans',
      sans-serif;
    letter-spacing: -0.8px;
    color: #2e4637;
  }
  .app-drawer__wordmark span {
    width: 5px;
    height: 5px;
    background: #b8a47c;
    border-radius: 50%;
    margin-top: 10px;
  }
  .app-drawer__brand p {
    font-size: 9px;
    color: #86927f;
    margin-top: 4px;
  }
  .app-drawer__list {
    --v-list-prepend-gap: 12px;
    --v-list-slim-spacer-width: 8px;
    padding: 0 12px 18px;
  }
  .app-drawer__label {
    min-height: 32px;
    color: #96a18d;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 1.7px;
    padding-inline: 12px;
  }
  .app-drawer__rule {
    margin: 18px 12px 12px;
    height: 1px;
    background: #e5eadf;
  }
  .app-drawer__item {
    color: #6b7b69;
    border-radius: 11px !important;
    min-height: 42px;
    margin-bottom: 3px;
    position: relative;
  }
  .app-drawer__item :deep(.v-list-item-title) {
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0;
  }
  .app-drawer__item :deep(.v-icon) {
    font-size: 18px;
    color: #86977f;
  }
  .app-drawer__item :deep(.v-list-item__overlay),
  .app-drawer__section :deep(.v-list-item__overlay) {
    display: none;
  }
  .app-drawer__item.v-list-item--active {
    background: #e8efe4;
    color: #31543d;
  }
  .app-drawer__item.v-list-item--active :deep(.v-list-item-title) {
    font-weight: 700;
  }
  .app-drawer__item.v-list-item--active :deep(.v-icon) {
    color: #436b4b;
  }
  .app-drawer__item.v-list-item--active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 12px;
    bottom: 12px;
    width: 3px;
    background: #6c8c60;
    border-radius: 0 3px 3px 0;
  }
  .app-drawer__section {
    min-height: 46px;
    border-radius: 10px;
    color: #5e715d;
    margin-top: 3px;
    padding-inline: 12px !important;
  }
  .app-drawer__section :deep(.v-list-item-title) {
    font-size: 11px;
    font-weight: 650;
  }
  .app-drawer__section :deep(.v-icon) {
    font-size: 18px;
    color: #8e9d84;
  }
  .app-drawer__section :deep(.v-list-item__append .v-icon) {
    font-size: 15px;
  }
  .app-drawer__section--current {
    color: #32543c;
  }
  .app-drawer__child {
    padding-inline-start: 22px !important;
    padding-inline-end: 8px !important;
  }
  .app-drawer__child :deep(.v-list-item__prepend > .v-icon) {
    margin-inline-end: 0;
  }
  .app-drawer__footer {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 15px 14px;
    border-top: 1px solid #e4e9de;
    background: #f5f7f0;
  }
  .app-drawer__footer > div:nth-child(2) {
    min-width: 0;
    flex: 1;
  }
  .app-drawer__footer strong {
    display: block;
    color: #4c6048;
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .app-drawer__footer > div:nth-child(2) > span {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 9px;
    color: #8c9881;
    margin-top: 4px;
  }
  .app-drawer__footer > div:nth-child(2) > span > i {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #8fa67b;
  }
  .app-drawer__avatar {
    background: #e8eee1;
    color: #54704c;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid #dbe4d1;
  }
  .app-bar {
    border-bottom: 1px solid #e5e9e1;
    background: #fff;
    padding-inline: 6px 12px;
  }
  .app-bar__heading {
    min-width: 0;
    margin-left: 4px;
  }
  .app-bar__eyebrow {
    display: block;
    color: #8b9988;
    font-size: 9px;
    letter-spacing: 1.1px;
    margin-bottom: 3px;
  }
  .app-bar__title {
    font:
      600 14px 'Plus Jakarta Sans',
      sans-serif;
    color: #344b3b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .app-bar__date {
    color: #93a08a;
    font-size: 11px;
    margin-right: 24px;
  }
  .app-bar__account {
    min-width: 48px;
    padding-inline: 6px;
    margin-left: 12px;
  }
  .app-account-menu {
    border: 1px solid #e5e9e1;
    padding: 8px;
  }
  .app-main {
    background: var(--workspace-background);
  }
  @media (max-width: 959px) {
    .app-drawer__brand {
      padding: 20px 12px;
      gap: 8px;
    }
    .app-drawer__wordmark {
      font-size: 18px;
    }
    .brand-mark {
      width: 34px;
      height: 40px;
    }
    .app-bar__date {
      display: none;
    }
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
      font-size: 12px;
      max-width: 46vw;
    }
    .app-bar__eyebrow {
      font-size: 8px;
    }
    .app-bar__account {
      margin-left: 4px;
      min-width: 40px;
    }
  }
</style>
