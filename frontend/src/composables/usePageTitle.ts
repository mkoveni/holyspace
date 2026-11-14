import { type MaybeRefOrGetter, onBeforeUnmount, toValue, watchEffect } from 'vue'

export const APP_NAME = 'Divine Community Space'

/** Sets the browser tab title to "<page> · Divine Community Space" while the page is shown. */
export function usePageTitle (page: MaybeRefOrGetter<string | undefined>) {
  watchEffect(() => {
    const name = toValue(page)
    document.title = name ? `${name} · ${APP_NAME}` : APP_NAME
  })
  onBeforeUnmount(() => {
    document.title = APP_NAME
  })
}
