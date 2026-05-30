/**
 * Zentrale Helfer & Badges für Kombo-Materialien (Paket 7 — Cross-Cutting).
 *
 * Ersetzt den zuvor in `MaterialsView.vue` und `MaterialDetailView.vue` duplizierten
 * `isComboMaterial`-Helfer und vereinheitlicht die Badge-Emojis über alle Material-Typen.
 */

export interface ComboMaterialLike {
  material_type?: string | null
}

/** Emoji-Marker für die Material-Typen (überall konsistent verwenden). */
export const COMBO_BADGE = {
  /** Generischer „Set/Inhalt"-Marker (Kiste, gebuchte Kombo „wie Kiste"). */
  crate: '📦',
  /** Physische Kombo (feste Einheit, zusammen gelagert & ausgegeben). */
  physical: '🟦',
  /** Virtuelle Kombo (Teile bleiben einzeln). */
  virtual: '🟪',
  /** Konfigurierbar (virtuelle Kombo mit ≥ 1 Options-Gruppe). */
  configurable: '🧩',
} as const

export function isPhysicalCombo(m: ComboMaterialLike | null | undefined): boolean {
  return m?.material_type === 'physical_combo'
}

export function isVirtualCombo(m: ComboMaterialLike | null | undefined): boolean {
  return m?.material_type === 'virtual_combo'
}

export function isComboMaterial(m: ComboMaterialLike | null | undefined): boolean {
  return isPhysicalCombo(m) || isVirtualCombo(m)
}

/**
 * Liefert das passende Badge-Emoji für eine Material-Typ-Anzeige – oder `null`,
 * wenn es kein Kombo-Typ ist.
 *
 * `isConfigurable` (virtuelle Kombo mit Options-Gruppen) wird nur dort gesetzt,
 * wo die Information vorliegt (Material-Detail). In Aktivitäts-Listen fällt eine
 * virtuelle Kombo ohne diese Info auf 🟪 zurück.
 */
export function comboBadgeEmoji(opts: {
  materialType?: string | null
  isConfigurable?: boolean
}): string | null {
  if (opts.materialType === 'physical_combo') return COMBO_BADGE.physical
  if (opts.materialType === 'virtual_combo') {
    return opts.isConfigurable ? COMBO_BADGE.configurable : COMBO_BADGE.virtual
  }
  return null
}
