import { useApi } from "@/composables/useApi"
import type { Person } from "@/types/person";
import { ref } from "vue";

export function usePersonDetail() {

    const http = useApi();

    const person = ref<Person>()
    const isLoading = ref(false)

    async function getPerson(id: string) {
        isLoading.value = true
        try {

            const { data } = await http.get<Person>(`/api/people/${id}`)

            person.value = data

            return data
        } catch (error) {
            throw new Error('')
        } finally {
            isLoading.value = false
        }
    }

    return {}
}