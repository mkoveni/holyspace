export interface UserAccount {
  id: string
  email?: string
  displayName?: string
  username: string
  personId: string | null
  status: string
  createdAt: string
  updatedAt: string
}

export interface CreateUserAccountPayload {
  email?: string
  displayName?: string
  username: string
  password: string
  personId?: string
}

export interface ChangePasswordPayload {
  password: string
}
