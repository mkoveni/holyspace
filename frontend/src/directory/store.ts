import type { PeopleMetadata } from './types'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { directoryApi } from './api'
export const useDirectoryStore = defineStore('javaDirectory', () => {
  const metadata = ref<PeopleMetadata | null>(null)
  let generation = 0
  async function loadMetadata () {
    const started = generation
    const result = await directoryApi.metadata()
    if (started === generation) {
      metadata.value = result
    }
    return result
  }
  function reset () {
    generation++
    metadata.value = null
  }
  return { metadata, loadMetadata, reset }
})
