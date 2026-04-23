/**
 * Liest eine verständliche Fehlermeldung aus einer Axios-/Fetch-artigen Exception.
 */
export function apiErrorMessage(err: unknown, fallback: string): string {
  const e = err as {
    message?: string
    code?: string
    response?: { status?: number; data?: unknown }
  }
  const data = e?.response?.data
  if (typeof data === 'string') {
    const t = data.trim().replace(/\s+/g, ' ')
    if (t) return t.length > 800 ? t.slice(0, 797) + '…' : t
  }
  if (data && typeof data === 'object') {
    const o = data as Record<string, unknown>
    for (const key of ['error', 'detail', 'message', 'title'] as const) {
      const v = o[key]
      if (typeof v === 'string' && v.trim()) {
        const t = v.trim()
        return t.length > 800 ? t.slice(0, 797) + '…' : t
      }
    }
  }
  if (e?.code === 'ECONNABORTED' || (typeof e?.message === 'string' && e.message.toLowerCase().includes('timeout'))) {
    return 'Zeitüberschreitung – der Server antwortet nicht rechtzeitig.'
  }
  if (e?.message === 'Network Error') {
    return 'Netzwerkfehler – API nicht erreichbar (CORS, falsche Basis-URL oder Server offline).'
  }
  const status = e?.response?.status
  if (typeof e?.message === 'string' && e.message && !/^Request failed with status code \d+$/.test(e.message)) {
    return e.message.length > 800 ? e.message.slice(0, 797) + '…' : e.message
  }
  if (status != null) {
    return `${fallback} (HTTP ${status})`
  }
  return fallback
}
