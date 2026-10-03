export interface Family {
  id: string
  name: string
  createdAt: string
  updatedAt: string
}

export interface CreateFamilyPayload {
  name: string
}

export interface AddFamilyMemberPayload {
  personId: string
  role?: string
}
