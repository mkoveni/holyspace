import type { Person } from '@/types/person';
export type PersonSearchPerson = Pick<Person, 'id' | 'firstName' | 'lastName' | 'email' | 'phone' | 'dateOfBirth'>;
export declare function personSearchItem(person: PersonSearchPerson): {
    value: string;
    title: string;
    subtitle: string;
    search: string;
};
export declare function matchesPerson(value: string, query: string, item?: {
    raw?: {
        search?: string;
    };
}): boolean;
