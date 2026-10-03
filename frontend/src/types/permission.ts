export interface Permission {
  id: string
  code: string
  description?: string
}

export interface CreatePermissionPayload {
  code: string
  description?: string
}
