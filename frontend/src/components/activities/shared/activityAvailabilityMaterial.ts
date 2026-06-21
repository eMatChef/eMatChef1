/**
 * Ein Treffer von GET /api/materials/available-for-period (camelCase).
 * Wird von der gemeinsamen Material-Suchkomponente und dem Erstell-Wizard genutzt.
 */
export interface ActivityPeriodAvailabilityMaterial {
  materialItemId: string
  name: string
  availableForPeriod: number
  totalStock: number
  /** In Phys.-Kombi-Kisten (nicht lose buchbar) */
  stockInPhysComboKisten?: number
  /** Als Referenz-Sack/Kiste einer phys. Kombo verknüpft (nicht lose buchbar) */
  stockAsLinkedRefContainer?: number
  /** In Lager-Behältern (buchbar, zählt zu «frei») */
  stockInStorageContainers?: number
  /** @deprecated stockInPhysComboKisten */
  stockInContainers?: number
  packSize: number | null
  packUnit: string | null
  isConsumable: boolean
  sourceDepartmentId?: string | null
  sourceDepartmentName?: string | null
  isJsMaterial?: boolean
  materialType?: 'physical' | 'physical_combo' | 'virtual_combo'
  /** Referenz-Behälter der phys. Kombo (Batch-Label / SN) */
  linkedContainerLabel?: string | null
  /** Verpackungseinheit des Behälter-Stammartikels (z. B. Sack, Kiste) */
  linkedContainerPackUnit?: string | null
  /** Virtuelle Kombo: Flaschenhals = min(floor(frei/menge)) über stock-Teile (= availableForPeriod). */
  comboBottleneck?: number
  /** Virtuelle Kombo: aufgelöste stock-Teile der Basis-Konfiguration. */
  comboStockComponents?: Array<{
    materialItemId: string
    name: string
    qtyPerCombo: number
    availableForPeriod: number
  }>
  /** In Reparatur (Batch-Status repair + offene Werkstatt-Tickets) — nicht buchbar */
  stockInRepair?: number
  /** Davon über Werkstatt-Ticket (Charge kann noch «active» sein) */
  stockInRepairFromWorkshop?: number
  /** Physisch «Am Event» (noch nicht retourniert/eingelagert) */
  stockIssuedOut?: number
  /** Komponente einer Phys.-Kombo-Stückliste (Anzeige «Teil von …») */
  partOfPhysicalCombos?: Array<{
    comboId: string
    comboName: string
    isContainer?: boolean
  }>
  /** Phys.-Kombo: Set-Einheiten in der eigenen Referenz-Kiste (nicht Stücklisten-Teile) */
  physicalComboSetsInOwnCrate?: number
}
