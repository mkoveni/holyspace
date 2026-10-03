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

/** A person as returned by `GET /api/families/{familyId}/people`. */
export interface FamilyMember {
  id: string
  firstName: string
  lastName: string
  dateOfBirth: string | null
  gender: string
  email: string | null
  phone: string | null
  role: string
  membershipCreatedAt: string
}

/** A family as returned by `GET /api/people/{personId}/families`. */
export interface PersonFamily {
  id: string
  name: string
  role: string
  membershipCreatedAt: string
  createdAt: string
  updatedAt: string
}

export interface AddFamilyAddressPayload {
  type: string
  line1: string
  line2?: string | null
  city: string
  province?: string | null
  postalCode?: string | null
  country: string
}
