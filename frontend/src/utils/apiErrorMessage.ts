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

/**
 * Grossanlass: Der Server lehnt Buchung oder Ausgabe ab, weil eine Charge überbucht wäre oder die Menge nicht vorhanden ist
 * (HTTP 409, `code: availability_conflict`). Die Meldung steht in `error` und ist für Benutzer lesbar.
 */
export function isAvailabilityConflict(err: unknown): boolean {
  const response = (err as { response?: { status?: number; data?: { code?: unknown } } })?.response
  return response?.status === 409 && response.data?.code === 'availability_conflict'
}
