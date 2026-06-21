import type { ActivityPackItem } from '@/api/activityPackItems'

export type StorageLookupResult = {
  entity_type: 'storage_address' | 'storage_rack' | 'storage_slot'
  entity_id?: string
  name: string
  label: string
  storage_address_name?: string | null
  rack_name?: string | null
}

function norm(value: string | null | undefined): string {
  return (value ?? '').trim().toLowerCase()
}

export function parseStorageLookupData(raw: Record<string, unknown>): StorageLookupResult | null {
  const entityType = String(raw.entity_type ?? '')
  if (
    entityType !== 'storage_address' &&
    entityType !== 'storage_rack' &&
    entityType !== 'storage_slot'
  ) {
    return null
  }

  const name = String(raw.name ?? '').trim()
  const label = String(raw.label ?? raw.name ?? '').trim()
  if (!name && !label) return null

  return {
    entity_type: entityType,
    entity_id: raw.entity_id != null ? String(raw.entity_id) : undefined,
    name: name || label,
    label: label || name,
    storage_address_name:
      raw.storage_address_name != null ? String(raw.storage_address_name) : null,
    rack_name: raw.rack_name != null ? String(raw.rack_name) : null,
  }
}

export function packItemMatchesStorageLookup(
  pi: ActivityPackItem,
  lookup: StorageLookupResult,
): boolean {
  const addr = norm(pi.storageAddressName)
  const rack = norm(pi.storageRackName)
  const slot = norm(pi.storageSlotName)
  const locName = norm(lookup.name)
  const locLabel = norm(lookup.label)

  switch (lookup.entity_type) {
    case 'storage_address':
      return locName !== '' && addr === locName
    case 'storage_rack':
      if (locName !== '' && rack === locName) return true
      // Fallback: Label «Hauptlager · Regal B» vs. storageRackName «Regal B»
      if (locLabel !== '' && rack !== '' && (locLabel === rack || locLabel.endsWith(` · ${rack}`))) {
        return true
      }
      return false
    case 'storage_slot': {
      const rackName = norm(lookup.rack_name)
      return rackName !== '' && locName !== '' && rack === rackName && slot === locName
    }
    default:
      return false
  }
}

/** Fach-/Regal-Hinweis pro Zeile nach Scan-Typ (Gestell vs. ganzer Standort). */
export function packItemShelfLineLocationLabel(
  pi: ActivityPackItem,
  lookup: StorageLookupResult,
): string {
  const slot = pi.storageSlotName?.trim()
  const rack = pi.storageRackName?.trim()
  if (lookup.entity_type === 'storage_slot') return ''
  if (lookup.entity_type === 'storage_rack') return slot ?? ''
  if (lookup.entity_type === 'storage_address') {
    if (rack && slot) return `${rack} · ${slot}`
    return rack || slot || ''
  }
  return ''
}
