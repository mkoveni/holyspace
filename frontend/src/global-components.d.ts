import type PeopleSearch from '@/components/common/PeopleSearch.vue'

export {}

declare module 'vue' {
  export interface GlobalComponents {
    PeopleSearch: typeof PeopleSearch
  }
}
