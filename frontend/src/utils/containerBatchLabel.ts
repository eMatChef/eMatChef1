import type { ContainerBatch } from '@/api/storageLocations'

/**
 * Einheitliche Beschriftung für Kisten/Taschen in Selects – gleiche Logik wie
 * der Dropdown „Kiste/Tasche“ im Tab „Inhalt Kiste/Tasche“ (MaterialDetailView).
 *
 * Reihenfolge: Lagerplatz (Fach, optional „Regal / Fach“) · Primärbezeichnung · Seriennummer · Materialname
 */
export function formatContainerBatchSelectLabel(cb: ContainerBatch): string {
  const d = (cb.display_label || '').trim()
  if (d) return d

  const rackName = (cb.rack?.name || '').trim()
  const slotName = (cb.slot?.name || '').trim()
  const label = (cb.label || '').trim()
  const serial = (cb.serial_number || '').trim()
  const materialName = (cb.material_name || '').trim()

  const lead = label || serial || materialName || 'Kiste/Tasche'
  const serialSuffix = serial && serial !== lead ? ` · ${serial}` : ''
  const materialSuffix = materialName && materialName !== lead ? ` · ${materialName}` : ''

  // Wie MaterialDetailView: zuerst Fachname (z. B. „A3 ·“), sonst Regal, sonst „Regal / Fach“
  let locationPrefix = ''
  if (slotName) {
    locationPrefix = `${slotName} · `
  } else if (rackName) {
    locationPrefix = `${rackName} · `
  }

  return `${locationPrefix}${lead}${serialSuffix}${materialSuffix}`
}

/** Inhaltsvorschau aus API (max. 2 Zeilen + „+N weitere“) für Kisten-Dropdowns */
export function formatContainerBatchContentPreviewSuffix(cb: ContainerBatch): string {
  const lines = cb.content_preview || []
  const more = cb.content_preview_more ?? 0
  if (lines.length === 0) return ''
  const parts = lines.map((l) => `${l.material_name} (${l.qty} Stk.)`)
  const extra = more > 0 ? `, +${more} weitere` : ''
  return ` — ${parts.join(', ')}${extra}`
}

/** Eine Zeile für die native Select-Option: Bezeichnung + optional Inhalt */
export function formatContainerBatchOptionFullLabel(cb: ContainerBatch): string {
  return `${formatContainerBatchSelectLabel(cb)}${formatContainerBatchContentPreviewSuffix(cb)}`
}
