/**
 * vue-i18n/@intlify: unescaped `@` triggers INVALID_LINKED_FORMAT (SyntaxError code 10).
 * Linked messages (`@:key`, `@.modifier:key`) stay untouched.
 */
export function escapeLiteralAtSignInMessage(text: string): string {
  if (!text.includes('@')) return text
  if (text.includes("{'@'}")) return text
  if (text.includes('@:') || text.includes('@.')) return text
  return text.replace(/@/g, "{'@'}")
}

export function escapeLiteralAtSignInMessages<T>(messages: T): T {
  return deepWalk(messages) as T
}

function deepWalk(value: unknown): unknown {
  if (typeof value === 'string') {
    return escapeLiteralAtSignInMessage(value)
  }
  if (Array.isArray(value)) {
    return value.map(deepWalk)
  }
  if (value && typeof value === 'object') {
    const out: Record<string, unknown> = {}
    for (const [key, child] of Object.entries(value)) {
      out[key] = deepWalk(child)
    }
    return out
  }
  return value
}
