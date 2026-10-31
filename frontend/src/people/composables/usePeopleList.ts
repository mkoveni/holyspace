import { useApi } from "@/composables/useApi"
import type { Person } from "@/types/person";
import { ref } from "vue";

export function usePeopleList() {

    const http = useApi();

    const people = ref<Person[]>()

    const getPeopleList = async () => {
        const response = await http.get<Person[]>('/api/people')
        people.value = response.data
    }

    return {}
}