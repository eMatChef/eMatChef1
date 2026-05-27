/**
 * Anzeige für Lager-Behälter (phys. Kombo, Kiste/Tasche/Sack …).
 * Nutzt pack_unit des Behälter-Stammartikels; sonst neutral «Behälter».
 */

const PACK_UNIT_TO_STORAGE_NOUN: Record<string, string> = {
  kiste: 'Kiste',
  karton: 'Karton',
  sack: 'Sack',
  tasche: 'Tasche',
  fass: 'Fass',
  rolle: 'Rolle',
  palette: 'Palette',
  paket: 'Paket',
  set: 'Set',
  bündel: 'Bündel',
  bundel: 'Bündel',
}

/** Klein geschriebener Schlüssel → Anzeige-Wort (z. B. «Sack»). */
export function storageContainerNounFromPackUnit(packUnit: string | null | undefined): string {
  const key = String(packUnit ?? '')
    .trim()
    .toLowerCase()
  if (!key) return 'Behälter'
  return PACK_UNIT_TO_STORAGE_NOUN[key] ?? packUnit!.trim()
}

/** Emoji-Hinweis in Materialsuche (nicht für alle Fälle semantisch perfekt). */
export function storageContainerIconFromPackUnit(packUnit: string | null | undefined): string {
  const key = String(packUnit ?? '')
    .trim()
    .toLowerCase()
  if (key === 'sack' || key === 'tasche') return '🎒'
  if (key === 'fass') return '🛢️'
  if (key === 'kiste' || key === 'karton' || key === 'palette') return '📦'
  return '📦'
}

export interface PhysicalComboStorageHintParams {
  packUnit?: string | null
  containerLabel?: string | null
  comboName?: string | null
}

/**
 * Zusatztext «… im Lager» für physische Kombos.
 * Mit Label nur wenn es vom Kombi-Namen abweicht.
 */
export function physicalComboStorageHintSuffix(params: PhysicalComboStorageHintParams): string {
  const noun = storageContainerNounFromPackUnit(params.packUnit)
  const label = String(params.containerLabel ?? '').trim()
  const combo = String(params.comboName ?? '').trim()
  if (label && label !== combo) {
    return ` (${noun} «${label}» im Lager)`
  }
  return ` (${noun} im Lager)`
}
