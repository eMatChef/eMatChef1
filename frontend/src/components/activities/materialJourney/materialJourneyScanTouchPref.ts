export type MaterialJourneyScanTouchPref = 'camera' | 'type'

const SESSION_KEY = 'ematchef.materialJourney.scanTouchPref'

export function readMaterialJourneyScanTouchPref(): MaterialJourneyScanTouchPref | null {
  try {
    const value = sessionStorage.getItem(SESSION_KEY)
    return value === 'camera' || value === 'type' ? value : null
  } catch {
    return null
  }
}

export function writeMaterialJourneyScanTouchPref(
  pref: MaterialJourneyScanTouchPref | null,
): void {
  try {
    if (pref) sessionStorage.setItem(SESSION_KEY, pref)
    else sessionStorage.removeItem(SESSION_KEY)
  } catch {
    /* sessionStorage unavailable */
  }
}
