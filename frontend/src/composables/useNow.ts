import { onBeforeUnmount, ref } from 'vue'

/** Keeps time-based UI rules current without reloading application data. */
export function useNow () {
  const now = ref(Date.now())
  const timer = setInterval(() => {
    now.value = Date.now()
  }, 1000)
  onBeforeUnmount(() => clearInterval(timer))
  return now
}
