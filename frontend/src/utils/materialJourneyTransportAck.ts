const KEY_PREFIX = 'ematchef.mj.transportAck.'

function storageKey(activityId: string): string {
  return `${KEY_PREFIX}${activityId}`
}

/** Transport hin abgeschlossen — «Am Anlass» ist der aktive Schritt (Logistics, Status packed). */
export function isTransportOutAcknowledged(activityId: string): boolean {
  if (!activityId) return false
  try {
    return sessionStorage.getItem(storageKey(activityId)) === '1'
  } catch {
    return false
  }
}

export function acknowledgeTransportOut(activityId: string): void {
  if (!activityId) return
  try {
    sessionStorage.setItem(storageKey(activityId), '1')
  } catch {
    /* ignore quota / private mode */
  }
}

export function clearTransportOutAck(activityId: string): void {
  if (!activityId) return
  try {
    sessionStorage.removeItem(storageKey(activityId))
  } catch {
    /* ignore */
  }
}
