import type { Material } from '@/api/materials'

export type MaterialWizardPrefillSkipField = 'name' | 'category'

export interface MaterialWizardPrefillDraft {
  creationMode: 'individual' | 'physical_combo' | 'virtual_combo'
  material_type: 'physical' | 'physical_combo' | 'virtual_combo'
  tracking_type: '' | 'serialized' | 'bulk'
  storage_address_id: string
  description: string
  manufacturer: string
  model: string
  color: string
  weight: string
  size_length: string
  size_width: string
  size_height: string
  reference_purchase_unit_chf: number | null
  sale_price: number | null
  min_stock: number | null
  pack_size: number | null
  pack_unit: string
  pack_sale_price_chf: number | null
  pack_weight: string
  pack_size_length: string
  pack_size_width: string
  pack_size_height: string
  is_consumable: boolean
  is_food: boolean
  rental_price_day: string
  rental_price_week: string
  rental_price_month: string
  rental_deposit: string
  rental_lead_days: number | null
  rental_max_days: number | null
  rental_external_allowed: boolean
  rental_scope: string
  rental_requires_approval: boolean
  rental_notes: string
  unit_price: number
  notes: string
  stock_unit: 'Stk' | 'm'
}

export function buildMaterialWizardPrefillFromMaterial(
  material: Material,
  options?: {
    skip?: MaterialWizardPrefillSkipField[]
    forceTrackingType?: 'bulk' | 'serialized'
    sourceNote?: string
  },
): MaterialWizardPrefillDraft {
  const skip = new Set(options?.skip ?? ['name', 'category'])
  const packUnit = (material.pack_unit || '').trim()
  const isMeter = packUnit.toLowerCase() === 'm'
  const trackingType =
    options?.forceTrackingType
    ?? (material.tracking_type === 'serialized' ? 'serialized' : 'bulk')

  let creationMode: MaterialWizardPrefillDraft['creationMode'] = 'individual'
  let materialType: MaterialWizardPrefillDraft['material_type'] = 'physical'
  if (material.material_type === 'physical_combo') {
    creationMode = 'physical_combo'
    materialType = 'physical_combo'
  } else if (material.material_type === 'virtual_combo') {
    creationMode = 'virtual_combo'
    materialType = 'virtual_combo'
  }

  const refPurchase = material.reference_purchase_unit_chf
  const salePrice = material.sale_price

  return {
    creationMode,
    material_type: materialType,
    tracking_type: trackingType,
    storage_address_id: material.storage_address?.id || '',
    description: (material.description || '').trim(),
    manufacturer: (material.manufacturer || '').trim(),
    model: (material.model || '').trim(),
    color: (material.color || '').trim(),
    weight: (material.weight || '').trim(),
    size_length: (material.size_length || '').trim(),
    size_width: (material.size_width || '').trim(),
    size_height: (material.size_height || '').trim(),
    reference_purchase_unit_chf:
      refPurchase != null && String(refPurchase).trim() !== '' ? Number(refPurchase) : null,
    sale_price: salePrice != null && String(salePrice).trim() !== '' ? Number(salePrice) : null,
    min_stock: material.min_stock ?? null,
    pack_size: material.pack_size ?? null,
    pack_unit: packUnit,
    pack_sale_price_chf:
      material.pack_sale_price_chf != null && String(material.pack_sale_price_chf).trim() !== ''
        ? Number(material.pack_sale_price_chf)
        : null,
    pack_weight: (material.pack_weight || '').trim(),
    pack_size_length: (material.pack_size_length || '').trim(),
    pack_size_width: (material.pack_size_width || '').trim(),
    pack_size_height: (material.pack_size_height || '').trim(),
    is_consumable: !!material.is_consumable,
    is_food: !!material.is_food,
    rental_price_day: (material.rental_price_day || '').trim(),
    rental_price_week: (material.rental_price_week || '').trim(),
    rental_price_month: (material.rental_price_month || '').trim(),
    rental_deposit: (material.rental_deposit || '').trim(),
    rental_lead_days: material.rental_lead_days ?? null,
    rental_max_days: material.rental_max_days ?? null,
    rental_external_allowed: !!material.rental_external_allowed,
    rental_scope: (material.rental_scope || '').trim(),
    rental_requires_approval: !!material.rental_requires_approval,
    rental_notes: (material.rental_notes || '').trim(),
    unit_price:
      refPurchase != null && String(refPurchase).trim() !== '' ? Number(refPurchase) : 0,
    notes: options?.sourceNote?.trim() || '',
    stock_unit: isMeter ? 'm' : 'Stk',
  }
}
