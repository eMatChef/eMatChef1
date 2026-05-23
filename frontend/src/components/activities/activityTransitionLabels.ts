import type { Composer } from 'vue-i18n'

/** Aktions-Label für Workflow-Buttons (übersetzt; Fallback: API-Label vom Backend). */
export function activityTransitionActionLabel(
  targetStatus: string,
  currentStatus: string | undefined | null,
  t: Composer['t'],
  te: Composer['te'],
  apiFallback?: string,
): string {
  const from = currentStatus === 'issued' ? 'at_event' : (currentStatus ?? '')

  if (from === 'submitted' && targetStatus === 'packing') {
    const key = 'activities.transitionActions.packingFromSubmitted'
    if (te(key)) return t(key)
  }
  if (from === 'approved' && targetStatus === 'submitted') {
    const key = 'activities.transitionActions.submittedFromApproved'
    if (te(key)) return t(key)
  }
  if (from === 'packed' && targetStatus === 'packing') {
    const key = 'activities.transitionActions.packingFromPacked'
    if (te(key)) return t(key)
  }
  if (from === 'at_event' && targetStatus === 'packed') {
    const key = 'activities.transitionActions.packedFromAtEvent'
    if (te(key)) return t(key)
  }
  if (from === 'returned' && targetStatus === 'at_event') {
    const key = 'activities.transitionActions.atEventFromReturned'
    if (te(key)) return t(key)
  }

  const key = `activities.transitionActions.${targetStatus}`
  if (te(key)) return t(key)

  const fallback = (apiFallback ?? '').trim()
  return fallback || targetStatus
}
