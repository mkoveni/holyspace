import { ministryNavigation } from '@/children/navigation'
import { isJavaApi } from '@/directory/backend'
interface NavigationItem {
  title: string
  to: string
  permission?: string
  permissionsAny?: string[]
  permissionsAll?: string[]
}
interface NavigationGroup {
  title: string
  icon: string
  items: NavigationItem[]
}
const groups: NavigationGroup[] = [
  ...(isJavaApi
    ? [
        {
          title: 'Events',
          icon: 'mdi-calendar-star',
          items: [
            {
              title: 'Calendar & events',
              to: '/events',
              permission: 'events.read',
            },
            {
              title: 'Venues',
              to: '/events/venues',
              permission: 'events.read',
            },
            {
              title: 'Attendance reports',
              to: '/events/reports',
              permission: 'events.attendance.read',
            },
            {
              title: 'Email deliveries',
              to: '/events/email-deliveries',
              permission: 'events.communications.send',
            },
          ],
        },
      ]
    : []),
  {
    title: 'People & households',
    icon: 'mdi-account-group-outline',
    items: [
      { title: 'People', to: '/people', permission: 'people.read' },
      { title: 'Families', to: '/families', permission: 'families.read' },
      {
        title: 'Life Events',
        to: '/life-events',
        permission: 'life_events.read',
      },
    ],
  },
  ...(isJavaApi
    ? [
        {
          title: 'Groups & ministries',
          icon: 'mdi-account-group-outline',
          items: [
            {
              title: 'Groups',
              to: '/groups',
              permissionsAny: [
                'ministries.read',
                'ministries.manage',
                'ministries.lead',
              ],
            },
            {
              title: 'Ministries',
              to: '/ministries',
              permissionsAny: [
                'ministries.read',
                'ministries.manage',
                'ministries.lead',
              ],
            },
          ],
        },
      ]
    : [
        {
          title: 'Church life',
          icon: 'mdi-church',
          items: [
            ...(isJavaApi
              ? []
              : [
                  { title: 'Events', to: '/events', permission: 'events.read' },
                ]),
            {
              title: 'Attendance',
              to: '/attendance',
              permission: 'attendance.manage',
            },
            {
              title: 'Group workspace',
              to: '/groups',
              permission: 'ministries.lead',
            },
            {
              title: 'Ministries',
              to: '/ministries',
              permission: 'ministries.manage',
            },
          ],
        },
      ]),
  {
    title: 'Volunteering',
    icon: 'mdi-hand-heart-outline',
    items: [
      {
        title: 'Volunteers',
        to: '/volunteering',
        permission: isJavaApi ? 'volunteering.read' : 'volunteering.manage',
      },
      ...(isJavaApi
        ? [
            {
              title: 'Teams & roles',
              to: '/volunteering/teams',
              permission: 'volunteering.read',
            },
            {
              title: 'Serving schedule',
              to: '/volunteering/schedule',
              permission: 'volunteering.read',
            },
            {
              title: 'Reminder deliveries',
              to: '/volunteering/deliveries',
              permission: 'volunteering.communications.send',
            },
          ]
        : [
            {
              title: 'Volunteer invitations',
              to: '/volunteer-rota',
              permission: 'volunteering.manage',
            },
            {
              title: 'Service planning',
              to: '/service-plans',
              permission: 'service_planning.read',
            },
          ]),
      {
        title: isJavaApi ? 'My invitations' : 'Volunteer workspace',
        to: '/serve',
        permission: 'volunteering.serve',
      },
    ],
  },
  {
    title: 'Children Ministry',
    icon: 'mdi-sprout-outline',
    items: isJavaApi
      ? ministryNavigation(() => true).flatMap(group =>
          group.items.map(item => ({
            title: item.title,
            to: item.to,
            permissionsAny: item.permissionsAny,
          })),
        )
      : [
          {
            title: 'Children’s Ministry',
            to: '/children',
            permission: 'children.manage',
          },
          {
            title: 'Children’s station',
            to: '/children-station',
            permission: 'children.station',
          },
        ],
  },
  {
    title: 'Engagement & reports',
    icon: 'mdi-chart-box-outline',
    items: [
      {
        title: 'Reports and segments',
        to: '/reports',
        permission: 'reports.read',
      },
      {
        title: 'Follow-up tasks',
        to: '/followups',
        permission: 'followups.work',
      },
      { title: 'Forms', to: '/forms', permission: 'forms.read' },
      ...(isJavaApi
        ? [
            {
              title: 'Email templates',
              to: '/communications/templates',
              permission: 'communications.templates.read',
            },
          ]
        : []),
      {
        title: 'Communications',
        to: '/communications',
        permission: 'communications.manage',
      },
    ],
  },
  {
    title: 'Giving',
    icon: 'mdi-gift-outline',
    items: [
      {
        title: 'Gift ledger & funds',
        to: '/giving',
        permission: isJavaApi ? 'giving.read' : 'giving.manage',
      },
      ...(isJavaApi
        ? [
            {
              title: 'Pledges',
              to: '/giving/pledges',
              permission: 'giving.read',
            },
            {
              title: 'Statements & exports',
              to: '/giving/reports',
              permission: 'giving.read',
            },
            {
              title: 'Reconciliation',
              to: '/giving/reconciliations',
              permission: 'giving.read',
            },
          ]
        : []),
      {
        title: 'Online payments',
        to: '/payments',
        permission: 'payments.manage',
      },
    ],
  },
  {
    title: 'Accounts & access',
    icon: 'mdi-shield-account-outline',
    items: [
      {
        title: 'User Accounts',
        to: '/user-accounts',
        permission: 'identity.manage',
      },
      ...(isJavaApi
        ? [
            {
              title: 'Member access',
              to: '/administration/member-access',
              permissionsAll: ['identity.manage', 'people.manage'],
            },
            {
              title: 'Donor access',
              to: '/administration/donor-access',
              permissionsAll: ['identity.manage', 'giving.manage'],
            },
            {
              title: 'Payment settings',
              to: '/administration/payment-settings',
              permission: 'payments.configure',
            },
          ]
        : []),
      { title: 'Roles', to: '/roles', permission: 'identity.manage' },
      {
        title: 'Permissions',
        to: '/permissions',
        permission: 'identity.manage',
      },
    ],
  },
  {
    title: 'My account',
    icon: 'mdi-account-circle-outline',
    items: [
      { title: 'Account security', to: '/account-security' },
      { title: 'Settings', to: '/settings' },
      ...(isJavaApi ? [{ title: 'My giving', to: '/my-giving' }] : []),
    ],
  },
]
export function visibleNavigation (
  hasPermission: (permission: string) => boolean,
): NavigationGroup[] {
  const available = isJavaApi
    ? groups.map(group => ({
        ...group,
        items: group.items
          .filter(item =>
            [
              '/payments',
              '/my-giving',
              '/administration/donor-access',
              '/administration/member-access',
              '/administration/payment-settings',
              '/giving',
              '/giving/pledges',
              '/giving/reports',
              '/giving/reconciliations',
              '/groups',
              '/ministries',
              '/events',
              '/events/venues',
              '/events/reports',
              '/events/email-deliveries',
              '/volunteering',
              '/volunteering/schedule',
              '/volunteering/teams',
              '/volunteering/deliveries',
              '/serve',
              '/people',
              '/families',
              '/user-accounts',
              '/roles',
              '/permissions',
              '/settings',
              '/children',
              '/children-station',
              '/children/settings',
              '/children/session-series',
              '/children/team-planning',
              '/children/safeguarding-follow-up',
              '/children/reports',
              '/children/incidents',
              '/children/overview',
              '/children/sessions',
              '/children/email-deliveries',
              '/communications/templates',
              '/children/communications',
            ].includes(item.to),
          )
          .map(item =>
            item.to === '/children'
              ? { ...item, title: 'Programmes', permission: 'children.read' }
              : (item.to === '/families'
                  ? { ...item, title: 'Households', permission: 'people.read' }
                  : item),
          ),
      }))
    : groups
  return available
    .map(group => ({
      ...group,
      items: group.items.filter(
        item =>
          (!item.permission || hasPermission(item.permission))
          && (!item.permissionsAll
            || item.permissionsAll.every(permission =>
              hasPermission(permission),
            ))
            && (!item.permissionsAny
              || item.permissionsAny.some(permission =>
                hasPermission(permission),
              )),
      ),
    }))
    .filter(group => group.items.length > 0)
}
export function routeMatches (path: string, destination: string): boolean {
  return (
    path === destination
    || (destination !== '/' && path.startsWith(destination + '/'))
  )
}

export function navigationIcon (to: string): string {
  const icons: Record<string, string> = {
    '/administration/payment-settings': 'mdi-credit-card-settings-outline',
    '/giving': 'mdi-gift-outline',
    '/my-giving': 'mdi-hand-heart-outline',
    '/administration/member-access': 'mdi-account-check-outline',
    '/administration/donor-access': 'mdi-account-check-outline',
    '/giving/pledges': 'mdi-hand-heart-outline',
    '/giving/reports': 'mdi-file-chart-outline',
    '/giving/reconciliations': 'mdi-bank-check',
    '/groups': 'mdi-account-group-outline',
    '/ministries': 'mdi-church-outline',
    '/events': 'mdi-calendar-star',
    '/events/reports': 'mdi-chart-timeline-variant',
    '/events/email-deliveries': 'mdi-email-check-outline',
    '/events/venues': 'mdi-map-marker-outline',
    '/volunteering': 'mdi-hand-heart-outline',
    '/volunteering/schedule': 'mdi-calendar-heart',
    '/volunteering/teams': 'mdi-account-group-outline',
    '/volunteering/deliveries': 'mdi-email-check-outline',
    '/children/session-series': 'mdi-calendar-sync-outline',
    '/children/team-planning': 'mdi-account-group-outline',
    '/children/safeguarding-follow-up': 'mdi-shield-check-outline',
    '/children/reports': 'mdi-chart-timeline-variant',
    '/serve': 'mdi-email-heart-outline',
    '/people': 'mdi-account-outline',
    '/families': 'mdi-home-heart',
    '/children': 'mdi-sprout-outline',
    '/children/overview': 'mdi-view-dashboard-outline',
    '/children-station': 'mdi-qrcode-scan',
    '/children/sessions': 'mdi-calendar-heart',
    '/children/settings': 'mdi-home-outline',
    '/children/incidents': 'mdi-shield-alert-outline',
    '/children/communications': 'mdi-message-text-outline',
    '/children/email-deliveries': 'mdi-email-check-outline',
    '/communications/templates': 'mdi-email-edit-outline',
    '/user-accounts': 'mdi-account-key-outline',
    '/roles': 'mdi-account-group-outline',
    '/permissions': 'mdi-lock-outline',
    '/settings': 'mdi-cog-outline',
    '/account-security': 'mdi-shield-check-outline',
  }
  return icons[to] || 'mdi-circle-small'
}
