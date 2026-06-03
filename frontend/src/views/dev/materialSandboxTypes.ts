/** Shared mock types for material sandbox demos. */
export type SandboxComboComponent = {
  id: string
  name: string
  serial?: string | null
  qty: number
  assignment: 'fixed' | 'pool'
  assignment_label: string
}

export type SandboxMaterialRow = {
  id: string
  name: string
  manufacturer?: string
  is_container?: boolean
  is_food?: boolean
  is_consumable?: boolean
  is_js_material?: boolean
  is_combo?: boolean
  is_combo_draft?: boolean
  material_type?: 'physical_combo' | 'virtual_combo'
  material_type_label?: string
  open_loss_qty?: number
  category_parent?: string
  category_name?: string
  total_stock: number
  pack_size?: number
  pack_unit?: string
  issued_out: number
  repair_stock: number
  available: number
  components?: SandboxComboComponent[]
}

export type GroupSummaryLike = {
  items: readonly { raw?: SandboxMaterialRow; type?: string }[]
}
