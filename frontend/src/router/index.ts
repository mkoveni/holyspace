import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router'
/**
 * router/index.ts
 *
 * Application routes. Protected routes are nested under the Default layout
 * and require a valid auth session; the login route uses the Auth layout.
 */
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { supportedRoutes } from './supportedRoutes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/forms/public/:token',
      name: 'public-form',
      component: () => import('@/forms/views/PublicFormView.vue'),
      props: true,
      meta: { public: true },
    },
    {
      path: '/visit/:token',
      name: 'public-visit',
      component: () => import('@/views/events/PublicVisitView.vue'),
      props: true,
      meta: { public: true },
    },
    {
      path: '/tickets',
      name: 'public-tickets-wallet',
      component: () => import('@/tickets/views/TicketWalletView.vue'),
      meta: { public: true },
    },
    {
      path: '/tickets/events',
      name: 'public-tickets-events',
      component: () => import('@/tickets/views/PublicEventsView.vue'),
      meta: { public: true },
    },
    {
      path: '/tickets/events/:id',
      name: 'public-tickets-event',
      component: () => import('@/tickets/views/PublicEventView.vue'),
      meta: { public: true },
    },
    {
      path: '/tickets/thanks',
      name: 'public-tickets-thanks',
      component: () => import('@/tickets/views/PublicTicketsThanksView.vue'),
      meta: { public: true },
    },
    {
      path: '/give',
      name: 'public-give',
      component: () => import('@/payments/views/PublicGiveView.vue'),
      meta: { public: true },
    },
    {
      path: '/give/thanks',
      name: 'public-give-thanks',
      component: () => import('@/payments/views/PublicGiveThanksView.vue'),
      meta: { public: true },
    },
    {
      path: '/recover',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { public: true },
      children: [
        {
          path: '',
          component: () => import('@/views/auth/AccountRecoveryView.vue'),
        },
      ],
    },
    {
      path: '/login',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { public: true },
      children: [
        {
          path: '',
          name: 'login',
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
          path: 'account-security',
          name: 'account-security',
          component: () => import('@/views/auth/AccountSecurityView.vue'),
          meta: { title: 'Account security' },
        },
        {
          path: 'reports',
          name: 'reports',
          component: () => import('@/views/reporting/ReportsView.vue'),
          meta: { title: 'Reports and segments', permission: 'reports.read' },
        },
        {
          path: 'followups',
          name: 'followups',
          component: () => import('@/views/reporting/FollowupsView.vue'),
          meta: { title: 'Follow-up tasks', permission: 'followups.work' },
        },
        {
          path: 'my-giving',
          name: 'my-giving',
          component: () => import('@/payments/views/MyGivingView.vue'),
          meta: { title: 'My giving' },
        },
        {
          path: 'administration/member-access',
          name: 'member-access',
          component: () =>
            import('@/payments/views/MemberAccessView.vue'),
          meta: {
            title: 'Member access',
            permissionsAll: ['identity.manage', 'people.manage'],
          },
        },
        {
          path: 'administration/donor-access',
          name: 'donor-access',
          component: () => import('@/payments/views/DonorAccessView.vue'),
          meta: {
            title: 'Donor access',
            permissionsAll: ['identity.manage', 'giving.manage'],
          },
        },
        {
          path: 'administration/payment-settings',
          name: 'payment-settings',
          component: () =>
            import('@/payments/views/PaymentSettingsView.vue'),
          meta: {
            title: 'Payment settings',
            permission: 'payments.configure',
          },
        },
        {
          path: 'payments/settings',
          redirect: { name: 'payment-settings' },
        },
        {
          path: 'payments',
          name: 'payments',
          component: () => import('@/payments/views/PaymentsView.vue'),
          meta: { title: 'Online payments', permission: 'payments.manage' },
        },
        {
          path: 'member/payments',
          name: 'member-payments',
          component: () => import('@/views/payments/PaymentsView.vue'),
          props: { member: true },
          meta: { title: 'Giving and tickets', permission: 'member.portal' },
        },
        {
          path: 'member',
          name: 'member',
          component: () => import('@/views/member/MemberPortalView.vue'),
          meta: { title: 'My church', permission: 'member.portal' },
        },
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
          meta: { title: 'Dashboard' },
        },
        {
          path: 'people',
          name: 'people',
          component: () => import('@/directory/views/PeopleDirectory.vue'),
          meta: { title: 'People', permission: 'people.read' },
        },
        {
          path: 'people/:id',
          name: 'person-detail',
          component: () => import('@/directory/views/PersonProfileView.vue'),
          props: true,
          meta: { title: 'Person Detail', permission: 'people.read' },
        },
        {
          path: 'families',
          name: 'families',
          component: () => import('@/directory/views/HouseholdDirectory.vue'),
          meta: {
            title: 'Households',
            permission: 'people.read',
          },
        },
        {
          path: 'families/:id',
          name: 'family-detail',
          component: () => import('@/directory/views/HouseholdProfileView.vue'),
          props: true,
          meta: {
            title: 'Household',
            permission: 'people.read',
          },
        },
        {
          path: 'volunteering/deliveries',
          name: 'serving-deliveries',
          component: () =>
            import('@/volunteering/views/DeliveriesView.vue'),
          meta: {
            title: 'Volunteer reminder deliveries',
            permission: 'volunteering.communications.send',
          },
        },
        {
          path: 'volunteering/teams',
          name: 'serving-teams',
          component: () => import('@/volunteering/views/TeamsView.vue'),
          meta: {
            title: 'Serving teams',
            permission: 'volunteering.read',
          },
        },
        {
          path: 'volunteering/schedule',
          name: 'serving-schedule',
          component: () =>
            import('@/volunteering/views/ScheduleView.vue'),
          meta: {
            title: 'Serving schedule',
            permission: 'volunteering.read',
          },
        },
        {
          path: 'volunteering',
          name: 'volunteering',
          component: () => import('@/volunteering/views/VolunteersView.vue'),
          meta: {
            title: 'Volunteering',
            permission: 'volunteering.read',
          },
        },
        {
          path: 'children-station',
          name: 'children-station',
          component: () => import('@/children/views/SessionsView.vue'),
          props: { station: true },
          meta: {
            title: 'Children’s check-in station',
            permission: 'children.station',
          },
        },
        {
          path: 'children',
          name: 'children',
          component: () => import('@/children/views/ProgrammesView.vue'),
          meta: {
            title: 'Children’s Ministry',
            permission: 'children.read',
          },
        },
        {
          path: 'children/programmes/:id',
          name: 'children-programme',
          component: () => import('@/children/views/ProgrammeView.vue'),
          props: true,
          meta: {
            title: 'Children Ministry',
            permission: 'children.read',
          },
        },
        {
          path: 'children/participants/:id',
          name: 'children-participant',
          component: () => import('@/children/views/ParticipantView.vue'),
          props: true,
          meta: {
            title: 'Children Ministry',
            permission: 'children.read',
          },
        },
        {
          path: 'children/sessions',
          name: 'children-sessions',
          component: () => import('@/children/views/SessionsView.vue'),
          props: true,
          meta: {
            title: 'Children Ministry',
            permission: 'children.read',
          },
        },
        {
          path: 'children/sessions/:id',
          name: 'children-session',
          component: () => import('@/children/views/SessionView.vue'),
          props: true,
          meta: {
            title: 'Children Ministry',
            permissionsAny: ['children.read', 'children.station'],
          },
        },
        {
          path: 'children/reports',
          name: 'children-reports',
          component: () =>
            import('@/children/views/AttendanceReportsView.vue'),
          meta: {
            title: 'Attendance & reports',
            permission: 'children.read',
          },
        },
        {
          path: 'children/session-series',
          name: 'children-session-series',
          component: () =>
            import('@/children/views/SessionSeriesView.vue'),
          meta: {
            title: 'Recurring schedules',
            permission: 'children.read',
          },
        },
        {
          path: 'children/team-planning',
          name: 'children-team-planning',
          component: () =>
            import('@/children/views/TeamPlanningView.vue'),
          meta: { title: 'Team planning', permission: 'children.manage' },
        },
        {
          path: 'children/safeguarding-follow-up',
          name: 'children-safeguarding-follow-up',
          component: () =>
            import('@/children/views/SafeguardingFollowUpView.vue'),
          meta: {
            title: 'Safeguarding follow-up',
            permission: 'children.safeguarding',
          },
        },
        {
          path: 'children/incidents',
          name: 'children-incidents',
          component: () => import('@/children/views/IncidentsView.vue'),
          meta: {
            title: 'Incident follow-up',
            permissionsAny: [
              'children.incidents.record',
              'children.incidents.manage',
            ],
          },
        },
        {
          path: 'children/communications',
          name: 'children-communications',
          component: () =>
            import('@/children/views/GuardianCommunicationsView.vue'),
          meta: {
            title: 'Guardian communications',
            permission: 'children.safeguarding',
          },
        },
        {
          path: 'children/overview',
          name: 'children-overview',
          component: () =>
            import('@/children/views/MinistryOverviewView.vue'),
          meta: {
            title: 'Ministry overview',
            permissionsAny: [
              'children.read',
              'children.safeguarding',
              'children.incidents.manage',
            ],
          },
        },
        {
          path: 'children/email-deliveries',
          name: 'children-email-deliveries',
          component: () =>
            import('@/children/views/EmailDeliveriesView.vue'),
          meta: {
            title: 'Email delivery',
            permission: 'children.safeguarding',
          },
        },
        {
          path: 'children/settings',
          name: 'children-settings',
          component: () => import('@/children/views/SettingsView.vue'),
          props: true,
          meta: {
            title: 'Children Ministry',
            permissionsAny: ['children.manage', 'children.safeguarding'],
          },
        },
        {
          path: 'giving/pledges',
          name: 'giving-pledges',
          component: () => import('@/giving/views/PledgesView.vue'),
          meta: { title: 'Pledges', permission: 'giving.read' },
        },
        {
          path: 'giving/online',
          name: 'giving-online',
          component: () => import('@/giving/views/OnlineGivingSetupView.vue'),
          meta: { title: 'Online giving', permission: 'giving.manage' },
        },
        {
          path: 'giving/reconciliations',
          name: 'giving-reconciliations',
          component: () =>
            import('@/giving/views/ReconciliationView.vue'),
          meta: { title: 'Reconciliation', permission: 'giving.read' },
        },
        {
          path: 'giving',
          name: 'giving',
          component: () => import('@/giving/views/GivingView.vue'),
          meta: {
            title: 'Giving',
            permission: 'giving.read',
          },
        },
        {
          path: 'giving/reports',
          name: 'giving-reports',
          component: () => import('@/giving/views/GivingReportsView.vue'),
          meta: {
            title: 'Giving reports',
            permission: 'giving.read',
          },
        },
        {
          path: 'giving/receipts/:id',
          name: 'giving-receipt',
          component: () => import('@/giving/views/AcknowledgementView.vue'),
          props: true,
          meta: {
            title: 'Acknowledgement',
            permission: 'giving.read',
          },
        },
        {
          path: 'attendance',
          name: 'attendance',
          component: () => import('@/views/attendance/AttendanceView.vue'),
          meta: { title: 'Attendance', permission: 'attendance.manage' },
        },
        {
          path: 'volunteer-rota',
          name: 'volunteer-rota',
          component: () => import('@/views/volunteering/VolunteerRotaView.vue'),
          meta: {
            title: 'Volunteer invitations',
            permission: 'volunteering.manage',
          },
        },
        {
          path: 'serve',
          name: 'volunteer-workspace',
          component: () => import('@/volunteering/views/OwnScheduleView.vue'),
          meta: {
            title: 'Volunteer workspace',
            permission: 'volunteering.serve',
          },
        },
        {
          path: 'service-plans',
          name: 'service-planning',
          component: () =>
            import('@/views/service-planning/ServicePlansView.vue'),
          meta: {
            title: 'Service planning',
            permission: 'service_planning.read',
          },
        },
        {
          path: 'service-plans/:id',
          name: 'service-plan',
          component: () =>
            import('@/views/service-planning/ServicePlansView.vue'),
          props: true,
          meta: {
            title: 'Service order',
            permissionsAny: ['service_planning.read', 'volunteering.serve'],
          },
        },
        {
          path: 'groups',
          name: 'group-workspace',
          component: () => import('@/communities/views/CommunityDirectoryView.vue'),
          meta: {
            title: 'Groups',
            permissionsAny: ['ministries.read', 'ministries.manage', 'ministries.lead'],
          },
        },
        {
          path: 'ministries',
          name: 'ministries',
          component: () => import('@/communities/views/CommunityDirectoryView.vue'),
          meta: {
            title: 'Ministries',
            permissionsAny: ['ministries.read', 'ministries.manage', 'ministries.lead'],
          },
        },
        {
          path: 'communities/:id',
          name: 'community-workspace',
          component: () =>
            import('@/communities/views/CommunityWorkspaceView.vue'),
          meta: {
            title: 'Community workspace',
            permissionsAny: [
              'ministries.manage',
              'ministries.read',
              'ministries.lead',
            ],
          },
        },
        {
          path: 'forms',
          name: 'forms',
          component: () => import('@/forms/views/FormsView.vue'),
          meta: { title: 'Forms', permission: 'forms.read' },
        },
        {
          path: 'forms/new',
          name: 'form-new',
          component: () => import('@/forms/views/FormEditorView.vue'),
          meta: { title: 'Create form', permission: 'forms.manage' },
        },
        {
          path: 'forms/:id',
          name: 'form-edit',
          component: () => import('@/forms/views/FormEditorView.vue'),
          props: true,
          meta: { title: 'Edit form', permission: 'forms.manage' },
        },
        {
          path: 'forms/:id/results',
          name: 'form-results',
          component: () => import('@/forms/views/FormResultsView.vue'),
          props: true,
          meta: { title: 'Form results', permission: 'forms.read' },
        },
        {
          path: 'member/forms',
          name: 'member-forms',
          component: () => import('@/forms/views/MemberFormsView.vue'),
          meta: { title: 'Forms and quizzes', permission: 'member.portal' },
        },
        {
          path: 'member/forms/:id',
          name: 'member-form',
          component: () => import('@/forms/views/MemberFormView.vue'),
          props: true,
          meta: { title: 'Form', permission: 'member.portal' },
        },
        {
          path: 'communications/templates',
          name: 'communication-templates',
          component: () =>
            import('@/communications/views/TemplatesView.vue'),
          meta: {
            title: 'Email templates',
            permission: 'communications.templates.read',
          },
        },
        {
          path: 'communications',
          name: 'communications',
          component: () =>
            import('@/views/communications/CommunicationsView.vue'),
          meta: {
            title: 'Communications',
            permission: 'communications.manage',
          },
        },
        {
          path: 'events/reports',
          name: 'event-attendance-reports',
          component: () =>
            import('@/events/views/AttendanceReportView.vue'),
          meta: {
            title: 'Event attendance reports',
            permission: 'events.attendance.read',
          },
        },
        {
          path: 'events/email-deliveries',
          name: 'event-email-deliveries',
          component: () => import('@/events/views/DeliveriesView.vue'),
          meta: {
            title: 'Event email deliveries',
            permission: 'events.communications.send',
          },
        },
        {
          path: 'events/venues',
          name: 'event-venues',
          component: () => import('@/events/views/VenuesView.vue'),
          meta: { title: 'Event venues', permission: 'events.read' },
        },
        {
          path: 'events/occurrences/:id',
          name: 'event-occurrence',
          component: () => import('@/events/views/OccurrenceView.vue'),
          meta: { title: 'Event occurrence', permission: 'events.read' },
        },
        {
          path: 'events',
          name: 'events',
          component: () => import('@/events/views/EventsView.vue'),
          meta: { title: 'Events', permission: 'events.read' },
        },
        {
          path: 'events/:id',
          name: 'event-detail',
          component: () => import('@/events/views/EventDetailView.vue'),
          props: true,
          meta: { title: 'Event Details', permission: 'events.read' },
        },
        {
          path: 'life-events',
          name: 'life-events',
          component: () => import('@/views/life-events/LifeEventsListView.vue'),
          meta: { title: 'Life Events', permission: 'life_events.read' },
        },
        {
          path: 'life-events/:id',
          name: 'life-event-detail',
          component: () =>
            import('@/views/life-events/LifeEventDetailView.vue'),
          props: true,
          meta: { title: 'Life Event Detail', permission: 'life_events.read' },
        },
        {
          path: 'user-accounts',
          name: 'user-accounts',
          component: () => import('@/views/identity/UserAccountsListView.vue'),
          meta: { title: 'User Accounts', permission: 'identity.manage' },
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('@/views/identity/RolesListView.vue'),
          meta: { title: 'Roles', permission: 'identity.manage' },
        },
        {
          path: 'permissions',
          name: 'permissions',
          component: () => import('@/views/identity/PermissionsListView.vue'),
          meta: { title: 'Permissions', permission: 'identity.manage' },
        },
        {
          path: 'forbidden',
          name: 'forbidden',
          component: () => import('@/views/errors/ForbiddenView.vue'),
          meta: { title: 'Access denied' },
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

function resolveAccountDestination (
  to: RouteLocationNormalized,
  authStore: ReturnType<typeof useAuthStore>,
): RouteLocationRaw | undefined {
  if (to.name === 'dashboard' && authStore.user) {
    const destinations = [
      ['people.read', 'people'],
      ['identity.manage', 'user-accounts'],
      ['children.read', 'children'],
      ['children.station', 'children-station'],
      ['children.safeguarding', 'children-safeguarding-follow-up'],
      ['children.incidents.manage', 'children-incidents'],
      ['children.incidents.record', 'children-incidents'],
    ]
    return {
      name:
        destinations.find(([permission]) =>
          authStore.hasPermission(permission!),
        )?.[1] || 'my-giving',
    }
  }
  if (!supportedRoutes.has(String(to.name))) {
    return { name: 'not-found', params: { pathMatch: ['unavailable'] } }
  }

  if (
    !to.meta.public
    && to.name !== 'account-security'
    && authStore.user?.mfaEnabled
    && !authStore.user.mfaVerified
  ) {
    return { name: 'account-security', query: { redirect: to.fullPath } }
  }
  if (
    ['member', 'member-payments'].includes(String(to.name))
    && authStore.hasPermission('member.portal')
    && !authStore.user?.personId
  ) {
    return { name: 'dashboard' }
  }
  if (
    ['member', 'member-payments'].includes(String(to.name))
    && authStore.hasPermission('member.portal')
    && !authStore.user?.mfaEnabled
  ) {
    return { name: 'account-security', query: { redirect: to.fullPath } }
  }
  if (
    to.name === 'dashboard'
    && authStore.user?.personId
    && authStore.hasPermission('member.portal')
    && !authStore.hasPermission('people.read')
  ) {
    return { name: 'member' }
  }
}

router.beforeEach(async to => {
  const authStore = useAuthStore()

  if (!to.meta.public && authStore.token && !authStore.user) {
    await authStore.bootstrap()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  const accountDestination = resolveAccountDestination(to, authStore)
  if (accountDestination) {
    return accountDestination
  }

  if (
    Array.isArray(to.meta.permissionsAny)
    && authStore.user
    && !to.meta.permissionsAny.some(
      permission =>
        typeof permission === 'string' && authStore.hasPermission(permission),
    )
  ) {
    return { name: 'forbidden' }
  }

  if (
    Array.isArray(to.meta.permissionsAll)
    && !to.meta.permissionsAll.every(
      permission =>
        typeof permission === 'string' && authStore.hasPermission(permission),
    )
  ) {
    return { name: 'forbidden' }
  }

  if (
    to.meta.permission
    && authStore.user
    && !authStore.hasPermission(String(to.meta.permission))
  ) {
    return { name: 'forbidden' }
  }

  if (to.name === 'login' && authStore.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
