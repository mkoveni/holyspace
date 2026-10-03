export type PersonGender = 'male' | 'female' | 'unspecified'

export interface Person {
  id: string
  firstName: string
  lastName: string
  dateOfBirth: string | null
  gender: PersonGender
  email: string | null
  phone: string | null
  status: string
  createdAt: string
  updatedAt: string
}

export interface CreatePersonPayload {
  firstName: string
  lastName: string
  dateOfBirth?: string | null
  gender?: PersonGender
  email?: string | null
  phone?: string | null
}

export type UpdatePersonPayload = Partial<CreatePersonPayload>

export interface ChangePersonStatusPayload {
  status: string
}
