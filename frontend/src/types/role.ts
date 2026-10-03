export interface Role {
  id: string
  name: string
  description?: string
}

export interface CreateRolePayload {
  name: string
  description?: string
}
