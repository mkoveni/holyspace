/**
 * Person profile sub-resources: addresses, communication options, education
 * and employment history.
 *
 * Note: `GET /api/people/{personId}/profile` returns raw database rows (the
 * backend read model does not map these to camelCase like other endpoints),
 * so the response shapes below are intentionally snake_case to match reality.
 * The corresponding POST payloads (to create new entries) use camelCase, as
 * implemented by the controllers.
 */

export interface ProfileAddress {
  id: string
  person_id: string
  type: string
  line1: string
  line2: string | null
  city: string
  province: string | null
  postal_code: string | null
  country: string
  created_at: string
}

export interface AddAddressPayload {
  type: string
  line1: string
  line2?: string | null
  city: string
  province?: string | null
  postalCode?: string | null
  country: string
}

export interface ProfileCommunicationOption {
  id: string
  person_id: string
  channel: string
  value: string
  preferred: boolean | number
  created_at: string
}

export interface AddCommunicationOptionPayload {
  channel: string
  value: string
  preferred?: boolean
}

export interface ProfileEducationEntry {
  id: string
  person_id: string
  institution: string
  qualification: string
  start_date: string | null
  end_date: string | null
  field_of_study: string | null
  notes: string | null
  created_at: string
}

export interface AddEducationPayload {
  institution: string
  qualification: string
  startDate?: string | null
  endDate?: string | null
  fieldOfStudy?: string | null
  notes?: string | null
}

export interface ProfileEmploymentEntry {
  id: string
  person_id: string
  employer: string
  job_title: string
  start_date: string | null
  end_date: string | null
  industry: string | null
  work_email: string | null
  work_phone: string | null
  notes: string | null
  created_at: string
}

export interface AddEmploymentPayload {
  employer: string
  jobTitle: string
  startDate?: string | null
  endDate?: string | null
  industry?: string | null
  workEmail?: string | null
  workPhone?: string | null
  notes?: string | null
}

export interface PersonProfile {
  addresses: ProfileAddress[]
  communicationOptions: ProfileCommunicationOption[]
  education: ProfileEducationEntry[]
  employment: ProfileEmploymentEntry[]
}
