export interface AuthenticatedUser {
  id: string
  username: string
  personId: string | null
  status: string
  roles: string[]
  createdAt: string
  updatedAt: string
}

export interface LoginCredentials {
  username: string
  password: string
}

export interface LoginResponse {
  token: string
}
