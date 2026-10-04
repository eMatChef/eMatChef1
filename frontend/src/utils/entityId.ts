/** 12-stellige hex-ID wie backend `IdGenerator::isValid`. */
const ENTITY_ID_RE = /^[0-9a-f]{12}$/i

export function isValidEntityId(id: string | null | undefined): boolean {
  return typeof id === 'string' && ENTITY_ID_RE.test(id)
}
