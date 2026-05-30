import type { Address } from '@/api/addresses'

/** Kurzlabel fürs Suchfeld nach Auswahl (ohne «Name — Adresse»-Duplikat). */
export function formatAddressSelectionLabel(a: Address): string {
  const name = (a.name || a.company || a.street_line || '').trim()
  if (name) return name
  return (a.full_address || [a.postal_code, a.city].filter(Boolean).join(' ') || a.id).trim()
}

export function formatAddressOption(a: Address): string {
  const head = formatAddressSelectionLabel(a)
  const tail = (a.full_address || [a.postal_code, a.city].filter(Boolean).join(' ')).trim()
  if (!tail || tail.toLowerCase() === head.toLowerCase()) return head
  if (head && tail.toLowerCase().startsWith(head.toLowerCase())) return tail
  if (head && tail) return `${head} — ${tail}`
  return tail || head || a.id
}

export function addressMatchesQuery(a: Address, q: string): boolean {
  const hay = [
    a.type,
    a.type_label,
    a.name,
    a.company,
    a.street,
    a.street_line,
    a.city,
    a.postal_code,
    a.full_address,
    a.email,
    a.phone,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
  return hay.includes(q)
}

export interface GroupedAddressSearchResults {
  primary: Address[]
  other: Address[]
  showDivider: boolean
  totalCount: number
}

/** Suchtreffer mit `primaryType` zuerst, danach übrige Adresstypen. */
export function groupDepartmentAddressesForSearch(
  addresses: Address[],
  query: string,
  primaryType: string,
  options?: { maxPrimary?: number; maxOther?: number },
): GroupedAddressSearchResults {
  const q = query.trim().toLowerCase()
  const filtered = q ? addresses.filter((a) => addressMatchesQuery(a, q)) : addresses

  const maxPrimary = options?.maxPrimary ?? 20
  const maxOther = options?.maxOther ?? 20

  const byLabel = (a: Address, b: Address) =>
    formatAddressOption(a).localeCompare(formatAddressOption(b), 'de')

  const primary = filtered.filter((a) => a.type === primaryType).sort(byLabel).slice(0, maxPrimary)
  const other = filtered.filter((a) => a.type !== primaryType).sort(byLabel).slice(0, maxOther)

  return {
    primary,
    other,
    /** Trenner vor «Andere Standorte», sobald es Nicht-Primär-Treffer gibt */
    showDivider: other.length > 0,
    totalCount: primary.length + other.length,
  }
}
