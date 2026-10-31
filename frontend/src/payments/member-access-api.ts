import { useApi } from '@/composables/useApi'
export interface MemberLink {
  accountId: string
  accountName: string
  personId: string
  personName: string
  active: boolean
  version: number
}
export const memberAccessApi = {
  access: async (offset = 0) =>
    (
      await useApi().get<{ links: MemberLink[], hasMore: boolean }>(
        '/api/member-access',
        { params: { offset, limit: 25 } },
      )
    ).data,
  link: async (account: string, body: object) =>
    useApi().put(`/api/member-access/${account}`, body),
}
