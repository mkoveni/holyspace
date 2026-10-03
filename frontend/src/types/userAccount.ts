export interface UserAccount {
  id: string
  username: string
  personId: string | null
  status: string
  createdAt: string
  updatedAt: string
}

export interface CreateUserAccountPayload {
  username: string
  password: string
  personId?: string
}

export interface ChangePasswordPayload {
  password: string
}
