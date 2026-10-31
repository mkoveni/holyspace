import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
/** Consume a quick-action intent once, without discarding unrelated page filters. */
export function useCreateAction (allowed: () => boolean, open: () => void) {
  const route = useRoute(),
    router = useRouter()
  watch(
    () => route.query.action,
    action => {
      if (action !== 'create') {
        return
      }
      if (allowed()) {
        open()
      }
      void router.replace({
        query: { ...route.query, action: undefined },
        hash: route.hash,
      })
    },
    { immediate: true },
  )
}
