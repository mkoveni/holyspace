import { useApi } from '@/composables/useApi'
export interface EmailTemplate {
  id: string
  key: string
  name: string
  subject: string
  html: string
  variables: string[]
  status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED'
  version: number
  publishedVersion: number | null
  updatedAt: string
}
export interface TemplateDraft {
  key: string
  name: string
  subject: string
  html: string
  variables: string[]
}
export interface RenderedEmail {
  subject: string
  html: string
}
export interface TemplateRevision extends RenderedEmail {
  version: number
  variables: string[]
  publishedAt: string
}
const root = '/api/communications/templates'
export const templatesApi = {
  async list (search = '', offset = 0) {
    return (
      await useApi().get<{ templates: EmailTemplate[], total: number }>(root, {
        params: { search, limit: 25, offset },
      })
    ).data
  },
  async get (id: string) {
    return (await useApi().get<EmailTemplate>(`${root}/${id}`)).data
  },
  async create (draft: TemplateDraft) {
    return (await useApi().post<{ id: string }>(root, draft)).data
  },
  async update (id: string, draft: TemplateDraft, expectedVersion: number) {
    return (
      await useApi().put<{ id: string }>(`${root}/${id}`, {
        ...draft,
        expectedVersion,
      })
    ).data
  },
  async change (
    id: string,
    action: 'publish' | 'archive',
    expectedVersion: number,
  ) {
    await useApi().post(`${root}/${id}/${action}`, { expectedVersion })
  },
  async preview (draft: TemplateDraft, values: Record<string, string>) {
    return (
      await useApi().post<RenderedEmail>(`${root}/preview`, {
        subject: draft.subject,
        html: draft.html,
        variables: draft.variables,
        values,
      })
    ).data
  },
  async revisions (id: string, offset = 0) {
    return (
      await useApi().get<TemplateRevision[]>(`${root}/${id}/revisions`, {
        params: { limit: 25, offset },
      })
    ).data
  },
}
