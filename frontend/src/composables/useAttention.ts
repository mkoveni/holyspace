import { computed, ref } from 'vue'
import { useApi } from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'

export interface AttentionItem {
  id: string
  icon: string
  title: string
  detail: string
  to: string
  tone: 'error' | 'warning' | 'info'
}

interface FollowUp {
  id: string
  title: string
  personName: string
  status: string
  dueOn: string
  overdue: boolean
}

// Shared by the top-bar bell and the dashboard so the app fetches once.
const followUps = ref<FollowUp[]>([])
const loading = ref(false)
const loadedAt = ref(0)
const STALE_AFTER_MS = 60_000

/** Things that need a person's attention, built from data the app already exposes. */
export function useAttention () {
  const auth = useAuthStore()

  const items = computed<AttentionItem[]>(() => {
    const open = followUps.value.filter(task => !['completed', 'cancelled', 'closed'].includes(task.status))
    const overdue = open.filter(task => task.overdue)
    const result: AttentionItem[] = []
    if (overdue.length > 0) {
      result.push({
        id: 'followups-overdue',
        icon: 'mdi-clipboard-alert-outline',
        title: `${overdue.length} overdue follow-up${overdue.length === 1 ? '' : 's'}`,
        detail: overdue.slice(0, 2).map(task => `${task.title} · ${task.personName}`).join(', '),
        to: '/followups',
        tone: 'error',
      })
    }
    const dueSoon = open.length - overdue.length
    if (dueSoon > 0) {
      result.push({
        id: 'followups-open',
        icon: 'mdi-clipboard-check-outline',
        title: `${dueSoon} open follow-up${dueSoon === 1 ? '' : 's'}`,
        detail: 'Assigned to you or your team',
        to: '/followups',
        tone: 'info',
      })
    }
    return result
  })

  async function refresh (force = false) {
    if (!auth.hasPermission('followups.work') || loading.value) {
      return
    }
    if (!force && Date.now() - loadedAt.value < STALE_AFTER_MS) {
      return
    }
    loading.value = true
    try {
      followUps.value = (await useApi().get<{ items: FollowUp[] }>('/api/followups')).data.items
      loadedAt.value = Date.now()
    } catch {
      /* The bell is a convenience; failing quietly keeps the page usable. */
    } finally {
      loading.value = false
    }
  }

  return { items, loading, refresh, count: computed(() => items.value.length) }
}
