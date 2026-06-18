import apiClient from './apiClient'

export interface DepartmentMaterialUsageItem {
  materialItemId: string
  materialName: string
  moveCount: number
  totalQuantity: number
}

export interface DepartmentMaterialUsageStats {
  departmentId: string
  from: string | null
  to: string | null
  items: DepartmentMaterialUsageItem[]
}

function mapItem(raw: Record<string, unknown>): DepartmentMaterialUsageItem {
  return {
    materialItemId: String(raw.material_item_id ?? ''),
    materialName: String(raw.material_name ?? ''),
    moveCount: Number(raw.move_count ?? 0),
    totalQuantity: Number(raw.total_quantity ?? 0),
  }
}

export async function getDepartmentMaterialUsageStats(
  departmentId: string,
  params?: { from?: string; to?: string; limit?: number },
): Promise<DepartmentMaterialUsageStats> {
  const { data } = await apiClient.get<Record<string, unknown>>(
    `/api/departments/${departmentId}/material-usage-stats`,
    { params },
  )
  const d = data ?? {}
  const itemsRaw = Array.isArray(d.items) ? d.items : []
  return {
    departmentId: String(d.department_id ?? departmentId),
    from: d.from != null ? String(d.from) : null,
    to: d.to != null ? String(d.to) : null,
    items: itemsRaw.map((row) => mapItem(row as Record<string, unknown>)),
  }
}
