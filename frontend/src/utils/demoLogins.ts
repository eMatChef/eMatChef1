/**
 * Feste Demo-Accounts für den gelben Dev-Banner (app:create-role-users / app:dev-demo:reset).
 * Nur auf Dev/Staging-Hosts anzeigen — nie Produktiv-Credentials.
 */
export type DemoLogin = {
  email: string
  password: string
  label: string
  role: string
}

export const DEMO_LOGIN_PASSWORD = 'test'

export const DEMO_LOGINS: DemoLogin[] = [
  { email: 'superadmin@ematchef.ch', password: DEMO_LOGIN_PASSWORD, label: 'Superadmin', role: 'sa' },
  { email: 'organisationschef@ematchef.ch', password: DEMO_LOGIN_PASSWORD, label: 'Organisationschef', role: 'org' },
  { email: 'suborgchef@ematchef.ch', password: DEMO_LOGIN_PASSWORD, label: 'Suborgchef', role: 'sub' },
  { email: 'matwart@ematchef.ch', password: DEMO_LOGIN_PASSWORD, label: 'Materialchef', role: 'mw' },
  { email: 'depchef@ematchef.ch', password: DEMO_LOGIN_PASSWORD, label: 'Departmentchef', role: 'dc' },
  { email: 'leader1@ematchef.ch', password: DEMO_LOGIN_PASSWORD, label: 'Leader 1', role: 'l1' },
  { email: 'leader2@ematchef.ch', password: DEMO_LOGIN_PASSWORD, label: 'Leader 2', role: 'l2' },
  { email: 'leader3@ematchef.ch', password: DEMO_LOGIN_PASSWORD, label: 'Leader 3', role: 'l3' },
  { email: 'user@ematchef.ch', password: DEMO_LOGIN_PASSWORD, label: 'User', role: 'u' },
]

const SESSION_KEY = 'emc_demo_login'

export function stashDemoLogin(email: string, password: string): void {
  if (typeof sessionStorage === 'undefined') return
  sessionStorage.setItem(SESSION_KEY, JSON.stringify({ email, password }))
}

export function consumeDemoLogin(): { email: string; password: string } | null {
  if (typeof sessionStorage === 'undefined') return null
  const raw = sessionStorage.getItem(SESSION_KEY)
  if (!raw) return null
  sessionStorage.removeItem(SESSION_KEY)
  try {
    const parsed = JSON.parse(raw) as { email?: unknown; password?: unknown }
    const email = typeof parsed.email === 'string' ? parsed.email.trim() : ''
    const password = typeof parsed.password === 'string' ? parsed.password : ''
    if (!email || !password) return null
    return { email, password }
  } catch {
    return null
  }
}
