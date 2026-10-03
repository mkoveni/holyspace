export interface CreatedId {
  id: string
}

export interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[]>
}
