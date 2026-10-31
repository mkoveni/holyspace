export interface MinistryDestination {
  key: string
  title: string
  to: string
  icon: string
  permissionsAny: string[]
}
export interface MinistryNavigationGroup {
  title: string
  icon: string
  items: MinistryDestination[]
}
const destinations: MinistryNavigationGroup[] = [
  {
    title: 'Discover',
    icon: 'mdi-sprout-outline',
    items: [
      {
        key: 'overview',
        title: 'Overview',
        to: '/children/overview',
        icon: 'mdi-view-dashboard-outline',
        permissionsAny: [
          'children.read',
          'children.safeguarding',
          'children.incidents.manage',
        ],
      },
      {
        key: 'programmes',
        title: 'Programmes',
        to: '/children',
        icon: 'mdi-sprout-outline',
        permissionsAny: ['children.read'],
      },
    ],
  },
  {
    title: 'Session day',
    icon: 'mdi-calendar-heart',
    items: [
      {
        key: 'sessions',
        title: 'Sessions',
        to: '/children/sessions',
        icon: 'mdi-calendar-heart',
        permissionsAny: ['children.read'],
      },
      {
        key: 'station',
        title: 'Check-in station',
        to: '/children-station',
        icon: 'mdi-qrcode-scan',
        permissionsAny: ['children.station'],
      },
    ],
  },
  {
    title: 'Planning',
    icon: 'mdi-calendar-sync-outline',
    items: [
      {
        key: 'series',
        title: 'Recurring schedules',
        to: '/children/session-series',
        icon: 'mdi-calendar-sync-outline',
        permissionsAny: ['children.read'],
      },
      {
        key: 'team',
        title: 'Team planning',
        to: '/children/team-planning',
        icon: 'mdi-account-group-outline',
        permissionsAny: ['children.manage'],
      },
      {
        key: 'settings',
        title: 'Spaces & team',
        to: '/children/settings',
        icon: 'mdi-home-heart',
        permissionsAny: ['children.manage', 'children.safeguarding'],
      },
    ],
  },
  {
    title: 'Safeguarding',
    icon: 'mdi-shield-check-outline',
    items: [
      {
        key: 'follow-up',
        title: 'Safeguarding follow-up',
        to: '/children/safeguarding-follow-up',
        icon: 'mdi-shield-check-outline',
        permissionsAny: ['children.safeguarding'],
      },
      {
        key: 'incidents',
        title: 'Incident follow-up',
        to: '/children/incidents',
        icon: 'mdi-shield-alert-outline',
        permissionsAny: [
          'children.incidents.manage',
          'children.incidents.record',
        ],
      },
    ],
  },
  {
    title: 'Messages & reports',
    icon: 'mdi-message-text-outline',
    items: [
      {
        key: 'communications',
        title: 'Guardian communications',
        to: '/children/communications',
        icon: 'mdi-message-text-outline',
        permissionsAny: ['children.safeguarding'],
      },
      {
        key: 'delivery',
        title: 'Email delivery',
        to: '/children/email-deliveries',
        icon: 'mdi-email-check-outline',
        permissionsAny: ['children.safeguarding'],
      },
      {
        key: 'reports',
        title: 'Attendance & reports',
        to: '/children/reports',
        icon: 'mdi-chart-timeline-variant',
        permissionsAny: ['children.read'],
      },
    ],
  },
]
export function ministryNavigation (
  hasPermission: (permission: string) => boolean,
): MinistryNavigationGroup[] {
  return destinations
    .map(group => ({
      ...group,
      items: group.items.filter(item =>
        item.permissionsAny.some(permission => hasPermission(permission)),
      ),
    }))
    .filter(group => group.items.length)
}
