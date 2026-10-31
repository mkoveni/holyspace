export const personGenders = ['male', 'female', 'other', 'unspecified'] as const
export type PersonGender = typeof personGenders[number]

export const membershipStatuses = ['prospect', 'active', 'inactive', 'transferred', 'deceased'] as const
export type MembershipStatus = typeof membershipStatuses[number]

export interface Person {
  id: string
  firstName: string
  lastName: string
  dateOfBirth: string | null
  gender: PersonGender
  email: string | null
  phone: string | null
  status: MembershipStatus
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

export type UpdatePersonPayload = Required<CreatePersonPayload>

export interface ChangePersonStatusPayload {
  status: MembershipStatus
}
