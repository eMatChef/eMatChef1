/**
 * Fächer, die als Kisten-/Taschen-Lagerplätze angelegt sind (Name).
 * Diese gehören in den Modus „Kiste/Tasche“, nicht in „Gestell/Fach“.
 */
export function isContainerNamedStorageSlot(slotName: string): boolean {
  const n = (slotName || '').trim()
  if (!n) return false
  return /^KISTE/i.test(n) || /^TASCHE/i.test(n) || /^Tasche\b/i.test(n)
}
