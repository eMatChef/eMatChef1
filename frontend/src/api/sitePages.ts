import apiClient from './apiClient'

export interface SitePagePayload {
  slug: string
  content: Record<string, unknown>
  updatedAt: string | null
}

export async function fetchPublicSitePages(): Promise<{ pages: SitePagePayload[] }> {
  const { data } = await apiClient.get<{ pages: SitePagePayload[] }>('/api/public/site-pages')
  return data
}

export async function getAdminSitePage(slug: string): Promise<SitePagePayload> {
  const { data } = await apiClient.get<SitePagePayload>(`/api/admin/site-pages/${encodeURIComponent(slug)}`)
  return data
}

export async function putAdminSitePage(slug: string, content: Record<string, unknown>): Promise<SitePagePayload> {
  const { data } = await apiClient.put<SitePagePayload>(`/api/admin/site-pages/${encodeURIComponent(slug)}`, {
    content,
  })
  return data
}
