/**
 * Session-Diagnostik – nur in Development aktiv.
 * Hilft bei der Analyse von "Sitzung abgelaufen"-Problemen.
 * In Produktion: keine Logs, keine Performance-Auswirkung.
 */
const isDev = import.meta.env.DEV

export type SessionEvent =
  | { type: '401_RECEIVED'; url: string; method: string }
  | { type: 'REFRESH_START' }
  | { type: 'REFRESH_SUCCESS' }
  | { type: 'REFRESH_FAILED'; status?: number; message?: string }
  | { type: 'SESSION_EXPIRED_TRIGGERED'; reason: string }
  | { type: 'NO_REFRESH_TOKEN' }
  | { type: 'REFRESH_MUTEX_WAIT' }
  | { type: 'REFRESH_MUTEX_ACQUIRED' }
  | { type: 'REFRESH_MUTEX_RELEASED' }

function ts(): string {
  return new Date().toISOString().slice(11, 23)
}

export function logSessionEvent(event: SessionEvent): void {
  if (!isDev) return
  const prefix = `[Session ${ts()}]`
  switch (event.type) {
    case '401_RECEIVED':
      console.warn(prefix, '401 von', event.method, event.url)
      break
    case 'REFRESH_START':
      console.info(prefix, 'Token-Refresh wird versucht...')
      break
    case 'REFRESH_SUCCESS':
      console.info(prefix, 'Token-Refresh erfolgreich')
      break
    case 'REFRESH_FAILED':
      console.error(prefix, 'Token-Refresh fehlgeschlagen:', event.status, event.message)
      break
    case 'SESSION_EXPIRED_TRIGGERED':
      console.error(prefix, 'Session-Expired-Handler ausgelöst:', event.reason)
      break
    case 'NO_REFRESH_TOKEN':
      console.warn(prefix, 'Kein Refresh-Token vorhanden')
      break
    case 'REFRESH_MUTEX_WAIT':
      console.info(prefix, 'Warte auf laufenden Refresh...')
      break
    case 'REFRESH_MUTEX_ACQUIRED':
      console.info(prefix, 'Refresh-Mutex erworben')
      break
    case 'REFRESH_MUTEX_RELEASED':
      console.info(prefix, 'Refresh-Mutex freigegeben')
      break
    default:
      break
  }
}
