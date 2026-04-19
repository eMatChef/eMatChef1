import apiClient from './apiClient'
import type { ActivityPeriodAvailabilityMaterial } from '@/components/activities/shared/activityAvailabilityMaterial'
import type { MaterialLookupAvailabilityContext } from '@/composables/useMaterialLookup'

function appendAvailabilityScopeToQuery(
  q: Record<string, string | number | boolean>,
  scope: Pick<
    MaterialLookupAvailabilityContext,
    'source' | 'internalScope' | 'singleDepartmentId' | 'includeGlobalJs'
  >,
) {
  if (scope.source) q.source = scope.source
  if (scope.source === 'internal' && scope.internalScope) q.internalScope = scope.internalScope
  if (scope.source === 'internal' && scope.internalScope === 'single' && scope.singleDepartmentId) {
    q.singleDepartmentId = scope.singleDepartmentId
  }
  if (scope.includeGlobalJs !== undefined) {
    q.includeGlobalJs = scope.includeGlobalJs ? 1 : 0
  }
}

/** Zeitraum-Verfügbarkeit für konkrete Material-IDs — `scope` wie bei der Materialsuche (Eigen / …), sonst weichen die Zahlen ab. */
export async function fetchMaterialsAvailableForPeriodByIds(params: {
  departmentId: string
  activityId?: string | null
  startDateIso: string | null
  endDateIso: string | null
  materialItemIds: string[]
  /** Gleiche Quelle wie ActivityMaterialAvailabilityLookup (source / internalScope / …) */
  scope?: Pick<
    MaterialLookupAvailabilityContext,
    'source' | 'internalScope' | 'singleDepartmentId' | 'includeGlobalJs'
  > | null
}): Promise<ActivityPeriodAvailabilityMaterial[]> {
  if (params.materialItemIds.length === 0) return []
  const q: Record<string, string | number | boolean> = {
    departmentId: params.departmentId,
    /** Genug Kopf für alle IDs (max. 50); nicht auf ids.length kürzen — vermeidet Randfälle mit LIMIT */
    limit: 50,
    materialItemIds: params.materialItemIds.join(','),
  }
  if (params.activityId) q.activityId = params.activityId
  if (params.startDateIso && params.endDateIso) {
    q.startDate = params.startDateIso
    q.endDate = params.endDateIso
  }
  if (params.scope) appendAvailabilityScopeToQuery(q, params.scope)
  const { data } = await apiClient.get<ActivityPeriodAvailabilityMaterial[]>('/api/materials/available-for-period', {
    params: q,
  })
  return Array.isArray(data) ? data : []
}
