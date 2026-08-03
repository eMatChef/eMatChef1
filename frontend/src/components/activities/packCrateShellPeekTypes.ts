export interface PackCrateShellPeekLine {
  id: string
  materialName: string
  quantity: number
  /** material_item.id für Kistencheck / Lager / Meldungen */
  materialItemId?: string | null
  /** Seriennummer / Label der erwarteten Charge (Sichtprüfung) */
  serialHint?: string | null
  /** Nach Kistencheck: Status der Zeile (ok, loss, extra, …) */
  checkStatus?: string | null
  /** Soll zum Zeitpunkt des Checks */
  sollQty?: number | null
  /** Gezählt in der Kiste (vor Nachlegen) */
  countedQty?: number | null
  /** Aus Lager in die Kiste nachgelegt */
  replenishQty?: number | null
}

export interface PackCrateShellPeekSection {
  subsectionKey: string
  title: string
  lines: PackCrateShellPeekLine[]
}
