/**
 * Ein Treffer von GET /api/materials/available-for-period (camelCase).
 * Wird von der gemeinsamen Material-Suchkomponente und dem Erstell-Wizard genutzt.
 */
export interface ActivityPeriodAvailabilityMaterial {
  materialItemId: string
  name: string
  availableForPeriod: number
  totalStock: number
  packSize: number | null
  packUnit: string | null
  isConsumable: boolean
  sourceDepartmentId?: string | null
  sourceDepartmentName?: string | null
}
