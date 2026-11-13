import { useApi } from '@/composables/useApi'

export type QuestionType = 'TEXT' | 'LONG_TEXT' | 'EMAIL' | 'NUMBER' | 'RATING' | 'DATE' | 'SINGLE_CHOICE' | 'MULTIPLE_CHOICE' | 'CHECKBOX'
export type Audience = 'PUBLIC' | 'MEMBERS' | 'STAFF' | 'GROUPS'
export type FormKind = 'DATA_COLLECTION' | 'QUIZ'
export type Identity = 'ANONYMOUS' | 'IDENTIFIED'

export const questionTypes: { value: QuestionType, title: string }[] = [
  { value: 'TEXT', title: 'Short text' },
  { value: 'LONG_TEXT', title: 'Long text' },
  { value: 'EMAIL', title: 'Email' },
  { value: 'NUMBER', title: 'Number' },
  { value: 'RATING', title: 'Rating (1 to 5)' },
  { value: 'DATE', title: 'Date' },
  { value: 'SINGLE_CHOICE', title: 'Single choice' },
  { value: 'MULTIPLE_CHOICE', title: 'Multiple choice' },
  { value: 'CHECKBOX', title: 'Yes / no checkbox' },
]

export interface Option { id: string, label: string }
/** A question as the respondent sees it (no answer key). */
export interface PublicQuestion { id: string, type: QuestionType, label: string, required: boolean, options: Option[] }
/** A question as staff edit it; `correct` and `points` only apply to quizzes. */
export interface EditQuestion extends PublicQuestion { correct?: string[], points?: number, tolerance?: number }

export interface FormPage {
  id: string
  title: string
  description: string
  kind: FormKind
  identity: Identity
  version: number
  questions: PublicQuestion[]
  open: boolean
  retentionMonths: number
  leaderboard: boolean
  challenge?: string
}
export interface SubmitResult { received: boolean, score: number | null, maxScore: number | null, answers: Record<string, unknown> | null }
export interface Leaderboard { entries: { rank: number, nickname: string, score: number, maxScore: number }[] }
export interface MemberFormSummary { id: string, title: string, description: string, kind: FormKind, identity: Identity, closesAt: string | null }

export interface FormSummaryRow { id: string, title: string, kind: FormKind, audience: Audience, identity: Identity, status: 'DRAFT' | 'PUBLISHED' | 'CLOSED', responseCount: number, version: number }
export interface FormDetail {
  id: string
  title: string
  description: string
  kind: FormKind
  audience: Audience
  identity: Identity
  status: 'DRAFT' | 'PUBLISHED' | 'CLOSED'
  opensAt: string | null
  closesAt: string | null
  maxResponses: number | null
  retentionMonths: number
  leaderboardEnabled: boolean
  leaderboardSize: number
  showScore: boolean
  showAnswers: boolean
  groupIds: string[]
  definition: { questions: EditQuestion[] }
  publishedVersion: number
  responseCount: number
  nextPurgeOn: string | null
  version: number
}
export interface FormBody {
  title: string
  description: string
  kind: FormKind
  audience: Audience
  identity: Identity
  opensAt: string | null
  closesAt: string | null
  maxResponses: number | null
  retentionMonths: number
  leaderboardEnabled: boolean
  leaderboardSize: number
  showScore: boolean
  showAnswers: boolean
  groupIds: string[]
  definition: { questions: EditQuestion[] }
}
export interface ResponseRow {
  id: string
  formVersion: number
  submittedOn: string
  submittedAt: string | null
  personId: string | null
  nickname: string | null
  score: number | null
  maxScore: number | null
  hidden: boolean
  answers: Record<string, unknown>
}
export interface ResponsePage { total: number, identified: boolean, items: ResponseRow[] }
export interface Summary { responses: number, hidden: boolean, minimumForAnonymous: number, questions: Record<string, unknown>[] }

export function newSubmissionKey (): string {
  return crypto.randomUUID()
}

/** Removes blank answers so an unanswered optional question is not sent. */
export function cleanAnswers (answers: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {}
  for (const [id, value] of Object.entries(answers)) {
    if (value == null || value === '') {
      continue
    }
    if (Array.isArray(value) && value.length === 0) {
      continue
    }
    out[id] = value
  }
  return out
}

/** Whether every required question has an answer (the server checks again). */
export function missingRequired (questions: PublicQuestion[], answers: Record<string, unknown>): string | null {
  for (const q of questions) {
    if (!q.required) {
      continue
    }
    const value = answers[q.id]
    const empty = value === '' || value === null || value === undefined || (Array.isArray(value) && value.length === 0)
    if (empty || (q.type === 'CHECKBOX' && value !== true)) {
      return q.label
    }
  }
  return null
}

/** A starting point for a new question of the given type. */
export function blankQuestion (type: QuestionType = 'TEXT'): EditQuestion {
  const choice = ['SINGLE_CHOICE', 'MULTIPLE_CHOICE'].includes(type)
  return {
    id: 'q' + crypto.randomUUID().replaceAll('-', '').slice(0, 10),
    type,
    label: '',
    required: false,
    options: choice ? [{ id: 'a', label: '' }, { id: 'b', label: '' }] : [],
  }
}

const base = '/api/forms'
export const staffForms = {
  async list (limit = 50, offset = 0) {
    return (await useApi().get<{ items: FormSummaryRow[] }>(base, { params: { limit, offset } })).data.items
  },
  async get (id: string) {
    return (await useApi().get<FormDetail>(`${base}/${id}`)).data
  },
  async create (body: FormBody, requestId = crypto.randomUUID()) {
    return (await useApi().post<{ id: string }>(base, { ...body, requestId })).data.id
  },
  async update (id: string, body: FormBody, expectedVersion: number) {
    await useApi().put(`${base}/${id}`, { ...body, expectedVersion })
  },
  async publish (id: string, expectedVersion: number) {
    return (await useApi().post<{ token: string | null }>(`${base}/${id}/publish`, { expectedVersion })).data
  },
  async close (id: string, expectedVersion: number) {
    await useApi().post(`${base}/${id}/close`, { expectedVersion })
  },
  async reopen (id: string, expectedVersion: number) {
    await useApi().post(`${base}/${id}/reopen`, { expectedVersion })
  },
  async unpublish (id: string, expectedVersion: number) {
    await useApi().post(`${base}/${id}/unpublish`, { expectedVersion })
  },
  async responses (id: string, limit = 50, offset = 0) {
    return (await useApi().get<ResponsePage>(`${base}/${id}/responses`, { params: { limit, offset } })).data
  },
  async summary (id: string) {
    return (await useApi().get<Summary>(`${base}/${id}/summary`)).data
  },
  async hide (id: string, responseId: string) {
    await useApi().post(`${base}/${id}/responses/${responseId}/hide`)
  },
  /** The CSV text of every response. */
  async exportCsv (id: string) {
    return (await useApi().get<string>(`${base}/${id}/export`, { responseType: 'text', transformResponse: r => r })).data
  },
}

export const memberForms = {
  async list () {
    return (await useApi().get<{ items: MemberFormSummary[] }>('/api/member/forms')).data.items
  },
  async get (id: string) {
    return (await useApi().get<FormPage>(`/api/member/forms/${id}`)).data
  },
  async submit (id: string, body: { answers: Record<string, unknown>, formVersion: number, submissionKey: string, nickname?: string }) {
    return (await useApi().post<SubmitResult>(`/api/member/forms/${id}/responses`, body)).data
  },
  async leaderboard (id: string) {
    return (await useApi().get<Leaderboard>(`/api/member/forms/${id}/leaderboard`)).data
  },
}

export const publicForms = {
  async get (token: string) {
    return (await useApi().get<FormPage>(`/api/public/forms/${encodeURIComponent(token)}`)).data
  },
  async submit (token: string, body: Record<string, unknown>) {
    return (await useApi().post<SubmitResult>(`/api/public/forms/${encodeURIComponent(token)}`, body)).data
  },
  async leaderboard (token: string) {
    return (await useApi().get<Leaderboard>(`/api/public/forms/${encodeURIComponent(token)}/leaderboard`)).data
  },
}
