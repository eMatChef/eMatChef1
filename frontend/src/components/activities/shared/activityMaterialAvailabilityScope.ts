import type { MaterialLookupAvailabilityContext } from '@/composables/useMaterialLookup'

export type MaterialScopeTab = 'own' | 'all' | 'single' | 'js'

/**
 * Gleiche Quellenlogik wie in ActivityMaterialAvailabilityLookup (Eigen / Partner),
 * damit „Frei im Zeitraum“ in der Liste mit der Suche übereinstimmt.
 * J&S-Material ist ein separates Projekt und wird in der Suche nicht angeboten.
 */
export function materialLookupContextForScopeTab(
  base: Pick<MaterialLookupAvailabilityContext, 'departmentId' | 'activityId' | 'startDate' | 'endDate' | 'limit'>,
  tab: MaterialScopeTab,
  hasPartners: boolean,
  singlePartnerDepartmentId: string | null,
): MaterialLookupAvailabilityContext {
  const ctx: MaterialLookupAvailabilityContext = { ...base }
  if (tab === 'js') {
    ctx.source = 'js'
    ctx.includeGlobalJs = true
  } else if (tab === 'own') {
    ctx.source = 'internal'
    ctx.internalScope = 'own'
  } else if (tab === 'all' && hasPartners) {
    ctx.source = 'internal'
    ctx.internalScope = 'both'
  } else if (tab === 'single' && singlePartnerDepartmentId && hasPartners) {
    ctx.source = 'internal'
    ctx.internalScope = 'single'
    ctx.singleDepartmentId = singlePartnerDepartmentId
  } else {
    ctx.source = 'internal'
    ctx.internalScope = 'own'
  }
  return ctx
}
