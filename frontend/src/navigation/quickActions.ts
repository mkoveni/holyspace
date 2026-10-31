import { isJavaApi } from '@/directory/backend'
export interface QuickAction {
  title: string
  description: string
  icon: string
  to: string
  permissions: string[]
}
export function visibleQuickActions (
  hasPermission: (permission: string) => boolean,
): QuickAction[] {
  const actions: QuickAction[] = [
    ...(isJavaApi
      ? [
          {
            title: 'Record gift',
            description: 'Record generosity already received',
            icon: 'mdi-gift-outline',
            to: '/giving?action=create',
            permissions: ['giving.manage'],
          },
          {
            title: 'Plan event',
            description: 'Prepare the next gathering',
            icon: 'mdi-calendar-plus',
            to: '/events?action=create',
            permissions: ['events.read', 'events.manage'],
          },
          {
            title: 'Plan serving shifts',
            description: 'Prepare your team’s next duty',
            icon: 'mdi-calendar-heart',
            to: '/volunteering/schedule?action=create',
            permissions: ['volunteering.read', 'volunteering.manage'],
          },
          {
            title: 'Add volunteer',
            description: 'Welcome someone into serving',
            icon: 'mdi-hand-heart-outline',
            to: '/volunteering?action=create',
            permissions: [
              'volunteering.read',
              'volunteering.manage',
              'people.read',
            ],
          },
          {
            title: 'Add person',
            description: 'Welcome someone to your community',
            icon: 'mdi-account-plus-outline',
            to: '/people?action=create',
            permissions: ['people.read', 'people.manage'],
          },
          {
            title: 'Add household',
            description: 'Bring a family’s records together',
            icon: 'mdi-home-plus-outline',
            to: '/families?action=create',
            permissions: ['people.read', 'people.manage'],
          },
          {
            title: 'Plan session',
            description: 'Prepare a children’s ministry session',
            icon: 'mdi-calendar-plus',
            to: '/children/sessions?action=create',
            permissions: ['children.read', 'children.manage'],
          },
          {
            title: 'Prepare guardian message',
            description: 'Start a reviewed guardian communication',
            icon: 'mdi-email-plus-outline',
            to: '/children/communications?action=create',
            permissions: [
              'children.safeguarding',
              'children.read',
              'people.read',
            ],
          },
        ]
      : [
          {
            title: 'Find a person',
            description: 'Open your people directory',
            icon: 'mdi-account-search-outline',
            to: '/people',
            permissions: ['people.read'],
          },
        ]),
    {
      title: 'Open check-in station',
      description: 'Welcome children to today’s sessions',
      icon: 'mdi-qrcode-scan',
      to: '/children-station',
      permissions: ['children.station'],
    },
  ]
  return actions.filter(action =>
    action.permissions.every(permission => hasPermission(permission)),
  )
}
