/**
 * Login-Vorbelegung für Demo-Konten (Dev/Staging). Die Konten selbst stehen in
 * backend/data/seeds/dev-demo/demo-accounts.json und auf docs.ematchef.ch → Entwicklung → Testumgebung.
 */
export const DEMO_LOGIN_PASSWORD = 'test!ematchef'

const SESSION_KEY = 'emc_demo_login'

/** Speichert nur die E-Mail — Passwort kommt aus dem festen Demo-Konto (kein Klartext in sessionStorage). */
export function stashDemoLogin(email: string): void {
  if (typeof sessionStorage === 'undefined') return
  const trimmed = email.trim()
  if (!trimmed) return
  sessionStorage.setItem(SESSION_KEY, JSON.stringify({ email: trimmed }))
}

export function consumeDemoLogin(): { email: string; password: string } | null {
  if (typeof sessionStorage === 'undefined') return null
  const raw = sessionStorage.getItem(SESSION_KEY)
  if (!raw) return null
  sessionStorage.removeItem(SESSION_KEY)
  try {
    const parsed = JSON.parse(raw) as { email?: unknown }
    const email = typeof parsed.email === 'string' ? parsed.email.trim() : ''
    if (!email) return null
    return { email, password: DEMO_LOGIN_PASSWORD }
  } catch {
    return null
  }
}
