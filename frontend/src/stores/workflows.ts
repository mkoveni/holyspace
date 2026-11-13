import type { OperationalRecord, WorkflowAuditPage } from '@/types/workflow'
import { isAxiosError } from 'axios'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

export const useWorkflowsStore = defineStore('workflows', () => {
  const data = ref<Record<string, Record<string, OperationalRecord[]>>>({})
  const isSaving = ref(false)
  async function load (context: string, params: Record<string, unknown> = {}) {
    try {
      data.value[context] = (await useApi().get<Record<string, OperationalRecord[]>>(`/api/${context}`, { params })).data
    } catch (error) {
      throw new Error(extractErrorMessage(error, `Unable to load ${context}.`), { cause: error })
    }
  }
  async function save (path: string, payload: Record<string, unknown>, method: 'POST' | 'PUT' = 'POST', version?: number) {
    isSaving.value = true
    try {
      return (await useApi().request({ url: path, method, data: payload, ...(version === undefined ? {} : { headers: { 'If-Match': `"${version}"` } }) })).data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to save.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }
  async function audit (context: string, id: string, before: number | null = null): Promise<WorkflowAuditPage> {
    try {
      const params = { limit: 25, ...(before === null ? {} : { before }) }
      try {
        return (await useApi().get<WorkflowAuditPage>(`/api/audit/${context}/${id}`, { params })).data
      } catch (error) {
        if (!isAxiosError(error) || error.response?.status !== 501) {
          throw error
        }
        return (await useApi().get<WorkflowAuditPage>(`/api/workflows/${context}/${id}/audit`, { params })).data
      }
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load change history.'), { cause: error })
    }
  }
  function reset () {
    data.value = {}
  }
  return { data, isSaving, load, save, audit, reset }
})
