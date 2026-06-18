const STORAGE_KEY = 'packUiPreference'

export type PackUiPreference = 'journey' | 'legacy'

export function readPackUiPreferenceFromQuery(query: Record<string, unknown>): PackUiPreference | null {
  const raw = query.packUi
  const value = Array.isArray(raw) ? String(raw[0] ?? '') : typeof raw === 'string' ? raw : ''
  if (value === 'legacy') return 'legacy'
  if (value === 'journey') return 'journey'
  return null
}

export function getStoredPackUiPreference(): PackUiPreference | null {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored === 'legacy' || stored === 'journey') return stored
  } catch {
    /* ignore */
  }
  return null
}

export function resolvePackUiPreference(query: Record<string, unknown>): PackUiPreference {
  return readPackUiPreferenceFromQuery(query) ?? getStoredPackUiPreference() ?? 'journey'
}

export function setStoredPackUiPreference(pref: PackUiPreference): void {
  try {
    localStorage.setItem(STORAGE_KEY, pref)
  } catch {
    /* ignore */
  }
}

export function legacyPackUiQuery(): { packUi: 'legacy' } {
  return { packUi: 'legacy' }
}
