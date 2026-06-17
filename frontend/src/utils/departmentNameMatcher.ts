/**
 * Erkennt identische oder sehr ähnliche Department-Namen innerhalb einer Organisation
 * (z. B. «PFF 2027» vs «PFF27», «PFF-2027» vs «pff2027»).
 */
export function departmentNameCompactKey(name: string): string {
  return name
    .trim()
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, '')
}

export function departmentNameMatchKeys(name: string): string[] {
  const compact = departmentNameCompactKey(name)
  if (!compact) return []

  const keys = new Set<string>([compact])
  const match = compact.match(/^(\p{L}+)(\d+)$/u)
  if (match) {
    const letters = match[1]
    const digits = match[2]
    keys.add(letters + digits)
    if (digits.length === 4 && /^(19|20)\d{2}$/.test(digits)) {
      keys.add(letters + digits.slice(-2))
    }
  }

  return [...keys]
}

export function departmentNamesConflict(a: string, b: string): boolean {
  const keysA = new Set(departmentNameMatchKeys(a))
  const keysB = departmentNameMatchKeys(b)
  if (keysA.size === 0 || keysB.length === 0) return false
  return keysB.some((key) => keysA.has(key))
}
