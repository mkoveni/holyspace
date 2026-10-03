/**
 * router/index.ts
 *
 * Application routes. Protected routes are nested under the Default layout
 * and require a valid auth session; the login route uses the Auth layout.
 */
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { public: true },
      children: [
        {
          path: '',
          name: 'login-form',
          component: () => import('@/views/auth/LoginView.vue'),
        },
      ],
    },
    {
      path: '/',
      component: () => import('@/layouts/DefaultLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
          meta: { title: 'Dashboard' },
        },
        {
          path: 'people',
          name: 'people',
          component: () => import('@/views/people/PeopleListView.vue'),
          meta: { title: 'People' },
        },
        {
          path: 'people/:id',
          name: 'person-detail',
          component: () => import('@/views/people/PersonDetailView.vue'),
          props: true,
          meta: { title: 'Person Detail' },
        },
        {
          path: 'families',
          name: 'families',
          component: () => import('@/views/families/FamiliesListView.vue'),
          meta: { title: 'Families' },
        },
        {
          path: 'families/:id',
          name: 'family-detail',
          component: () => import('@/views/families/FamilyDetailView.vue'),
          props: true,
          meta: { title: 'Family Detail' },
        },
        {
          path: 'life-events',
          name: 'life-events',
          component: () => import('@/views/life-events/LifeEventsListView.vue'),
          meta: { title: 'Life Events' },
        },
        {
          path: 'life-events/:id',
          name: 'life-event-detail',
          component: () => import('@/views/life-events/LifeEventDetailView.vue'),
          props: true,
          meta: { title: 'Life Event Detail' },
        },
        {
          path: 'user-accounts',
          name: 'user-accounts',
          component: () => import('@/views/identity/UserAccountsListView.vue'),
          meta: { title: 'User Accounts' },
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('@/views/identity/RolesListView.vue'),
          meta: { title: 'Roles' },
        },
        {
          path: 'permissions',
          name: 'permissions',
          component: () => import('@/views/identity/PermissionsListView.vue'),
          meta: { title: 'Permissions' },
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/views/settings/SettingsView.vue'),
          meta: { title: 'Settings' },
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/errors/NotFoundView.vue'),
      meta: { public: true },
    },
  ],
})

router.beforeEach(async to => {
  const authStore = useAuthStore()

  if (authStore.token && !authStore.user && !authStore.isBootstrapping) {
    await authStore.bootstrap()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.name === 'login-form' && authStore.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
