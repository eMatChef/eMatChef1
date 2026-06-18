/**
 * Gelber Hinweisbalken („Testumgebung“): bekannte *.ematchef.ch Dev-Hostnamen,
 * lokaler *.ematchef.test-Stack oder VITE_SHOW_DEV_BANNER=1.
 */
export function shouldShowDevEnvironmentBanner(): boolean {
  const flag = (import.meta.env.VITE_SHOW_DEV_BANNER || '').trim().toLowerCase()
  if (flag === '1' || flag === 'true' || flag === 'yes') {
    return true
  }

  if (typeof window === 'undefined') {
    return false
  }

  const h = window.location.hostname.toLowerCase()
  const devCheHosts = new Set([
    'dev.ematchef.ch',
    'app-dev.ematchef.ch',
    'qr-dev.ematchef.ch',
    'devices-dev.ematchef.ch',
  ])
  if (devCheHosts.has(h)) {
    return true
  }
  if (h.endsWith('.ematchef.test')) {
    return true
  }

  return false
}

/**
 * Dev-/Test-Umgebung (gleiche Logik wie der gelbe Hinweisbalken).
 */
export function isDevToolsEnvironment(): boolean {
  return shouldShowDevEnvironmentBanner()
}
