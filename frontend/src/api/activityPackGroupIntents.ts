import apiClient from './apiClient'

export interface ActivityPackGroupIntent {
  id: string
  activityId: string
  label: string | null
  createdByUserId: string
  createdAt: string
  resolvedAt: string | null
  resolvedContainerId: string | null
  packItemIds: string[]
  memberCount: number
}

function mapIntent(raw: Record<string, unknown>): ActivityPackGroupIntent {
  const packItemIdsRaw = Array.isArray(raw.pack_item_ids) ? raw.pack_item_ids : []
  return {
    id: String(raw.id ?? ''),
    activityId: String(raw.activity_id ?? ''),
    label: raw.label != null ? String(raw.label) : null,
    createdByUserId: String(raw.created_by_user_id ?? ''),
    createdAt: String(raw.created_at ?? ''),
    resolvedAt: raw.resolved_at != null ? String(raw.resolved_at) : null,
    resolvedContainerId:
      raw.resolved_container_id != null ? String(raw.resolved_container_id) : null,
    packItemIds: packItemIdsRaw.map((id) => String(id)),
    memberCount: Number(raw.member_count ?? packItemIdsRaw.length),
  }
}

export async function getPackGroupIntents(activityId: string): Promise<ActivityPackGroupIntent[]> {
  const { data } = await apiClient.get<Record<string, unknown>[]>(
    `/api/activities/${activityId}/pack-group-intents`,
  )
  const list = Array.isArray(data) ? data : []
  return list.map((row) => mapIntent(row as Record<string, unknown>))
}

export async function postPackGroupIntent(
  activityId: string,
  body: { pack_item_ids: string[]; label?: string | null },
): Promise<ActivityPackGroupIntent> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-group-intents`,
    body,
  )
  return mapIntent((data ?? {}) as Record<string, unknown>)
}

export async function postResolvePackGroupIntent(
  activityId: string,
  intentId: string,
  body: { container_id: string },
): Promise<ActivityPackGroupIntent> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-group-intents/${intentId}/resolve`,
    body,
  )
  return mapIntent((data ?? {}) as Record<string, unknown>)
}
