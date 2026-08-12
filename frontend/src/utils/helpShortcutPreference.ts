/** localStorage: floating Hilfe-Button ausgeblendet (pro Profil). Default: sichtbar. */

import { ref } from 'vue'

const STORAGE_PREFIX = 'help_shortcut_hidden_'

/** Reaktiv für AppLayout ↔ Hilfeseite (gleiche Modul-Instanz). */
export const helpShortcutHiddenTick = ref(0)

function storageKey(profileId: string): string {
  return `${STORAGE_PREFIX}${profileId || 'anon'}`
}

export function isHelpShortcutHidden(profileId: string): boolean {
  if (!profileId) return false
  try {
    return localStorage.getItem(storageKey(profileId)) === '1'
  } catch {
    return false
  }
}

export function setHelpShortcutHidden(profileId: string, hidden: boolean): void {
  if (!profileId) return
  try {
    if (hidden) {
      localStorage.setItem(storageKey(profileId), '1')
    } else {
      localStorage.removeItem(storageKey(profileId))
    }
  } catch {
    /* ignore quota / private mode */
  }
  helpShortcutHiddenTick.value += 1
}

export function isHelpShortcutVisible(profileId: string): boolean {
  return !isHelpShortcutHidden(profileId)
}
