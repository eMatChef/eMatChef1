import type { ContainerBatch } from '@/api/storageLocations'

/**
 * Einheitliche Beschriftung für Kisten/Taschen in Selects – gleiche Logik wie
 * der Dropdown „Kiste/Tasche“ im Tab „Inhalt Kiste/Tasche“ (MaterialDetailView).
 *
 * Reihenfolge: Lagerplatz (Fach, optional „Regal / Fach“) · Seriennummer · Materialname · Label (wenn gesetzt)
 */
export function formatContainerBatchSelectLabel(cb: ContainerBatch): string {
  const rackName = (cb.rack?.name || '').trim()
  const slotName = (cb.slot?.name || '').trim()
  const label = (cb.label || '').trim()
  const serial = (cb.serial_number || '').trim()
  const materialName = (cb.material_name || '').trim()

  let locationPrefix = ''
  if (slotName) {
    locationPrefix = `${slotName} · `
  } else if (rackName) {
    locationPrefix = `${rackName} · `
  }

  const parts: string[] = []
  const primary = serial || label || materialName || 'Kiste/Tasche'
  parts.push(primary)

  if (materialName && materialName !== primary) {
    parts.push(materialName)
  }

  if (label && label !== primary && label !== materialName) {
    parts.push(label)
  }

  return `${locationPrefix}${parts.join(' — ')}`
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

/** Referenz-Kiste einer physischen Kombo (material.linked_container_batch). */
export interface LinkedContainerBatchRef {
  id: string
  material_id: string
  label: string | null
  serial_number: string | null
  material_name: string
  display_label: string
}

export function containerBatchFromLinkedRef(linked: LinkedContainerBatchRef): ContainerBatch {
  return {
    id: linked.id,
    material_id: linked.material_id,
    serial_number: linked.serial_number,
    label: linked.label,
    material_name: linked.material_name,
    display_label: linked.display_label,
    rack_id: '',
    slot_id: null,
    rack: null,
    slot: null,
  }
}

/** Dropdown-Zeile: Kombi-Name + Kisten-Batch (API listet verknüpfte Kisten nicht). */
export function formatPhysicalComboLinkedContainerLabel(comboName: string, cb: ContainerBatch): string {
  const name = comboName.trim()
  const batchPart = formatContainerBatchOptionFullLabel(cb)
  return name ? `${name} — ${batchPart}` : batchPart
}
