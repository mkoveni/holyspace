import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'

export type SnackbarColor = 'success' | 'error' | 'warning' | 'info'

interface ConfirmOptions {
  title: string
  message: string
  confirmText?: string
  cancelText?: string
  color?: string
}

export const useUiStore = defineStore('ui', () => {
  const snackbar = reactive({
    show: false,
    text: '',
    color: 'success' as SnackbarColor,
    timeout: 4000,
  })

  const confirm = reactive({
    show: false,
    title: '',
    message: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    color: 'primary',
  })

  const isLoading = ref(false)
  let confirmResolve: ((value: boolean) => void) | null = null

  function notify (text: string, color: SnackbarColor = 'success', timeout = 4000) {
    snackbar.show = true
    snackbar.text = text
    snackbar.color = color
    snackbar.timeout = timeout
  }

  function notifySuccess (text: string) {
    notify(text, 'success')
  }

  function notifyError (text: string) {
    notify(text, 'error', 6000)
  }

  function confirmAction (options: ConfirmOptions): Promise<boolean> {
    confirm.show = true
    confirm.title = options.title
    confirm.message = options.message
    confirm.confirmText = options.confirmText ?? 'Confirm'
    confirm.cancelText = options.cancelText ?? 'Cancel'
    confirm.color = options.color ?? 'primary'
    return new Promise(resolve => {
      confirmResolve = resolve
    })
  }

  function resolveConfirm (result: boolean) {
    confirmResolve?.(result)
    confirm.show = false
  }

  function setLoading (value: boolean) {
    isLoading.value = value
  }

  return {
    snackbar,
    confirm,
    isLoading,
    notify,
    notifySuccess,
    notifyError,
    confirmAction,
    resolveConfirm,
    setLoading,
  }
})
