import apiClient from './apiClient'

export interface PackSessionViewer {
  userId: string
  displayName: string
  shelf: string | null
  containerId: string | null
  journeyStep: string | null
  lastSeenAt: string
}

function mapViewer(raw: Record<string, unknown>): PackSessionViewer {
  return {
    userId: String(raw.user_id ?? ''),
    displayName: String(raw.display_name ?? ''),
    shelf: raw.shelf != null ? String(raw.shelf) : null,
    containerId: raw.container_id != null ? String(raw.container_id) : null,
    journeyStep: raw.journey_step != null ? String(raw.journey_step) : null,
    lastSeenAt: String(raw.last_seen_at ?? ''),
  }
}

export async function getPackSessionPresence(activityId: string): Promise<PackSessionViewer[]> {
  const { data } = await apiClient.get<{ viewers?: Record<string, unknown>[] }>(
    `/api/activities/${activityId}/pack-session/presence`,
  )
  const list = Array.isArray(data?.viewers) ? data.viewers : []
  return list.map((row) => mapViewer(row as Record<string, unknown>))
}

export async function patchPackSessionPresence(
  activityId: string,
  body: {
    shelf?: string | null
    containerId?: string | null
    journeyStep?: string | null
  },
): Promise<PackSessionViewer[]> {
  const payload: Record<string, unknown> = {}
  if (body.shelf !== undefined) payload.shelf = body.shelf
  if (body.containerId !== undefined) payload.container_id = body.containerId
  if (body.journeyStep !== undefined) payload.journey_step = body.journeyStep

  const { data } = await apiClient.patch<{ viewers?: Record<string, unknown>[] }>(
    `/api/activities/${activityId}/pack-session/presence`,
    payload,
  )
  const list = Array.isArray(data?.viewers) ? data.viewers : []
  return list.map((row) => mapViewer(row as Record<string, unknown>))
}
