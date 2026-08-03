/**
 * Build-injected app version for UI (user-menu footer).
 * Hostpoint builds set VITE_APP_VERSION / VITE_APP_GIT_SHA; local falls back to package-aligned defaults.
 */

const rawVersion = (import.meta.env.VITE_APP_VERSION || '4.0.1').trim()
const rawSha = (import.meta.env.VITE_APP_GIT_SHA || '').trim()

/** Marketing-style label: 4.0.1 → v4.01 (major + zero-padded minor*10+patch when minor < 10). */
export function formatMarketingVersion(semver: string): string {
  const m = /^(\d+)\.(\d+)\.(\d+)$/.exec(semver)
  if (!m) {
    return semver.startsWith('v') ? semver : `v${semver}`
  }
  const major = m[1]
  const minor = Number(m[2])
  const patch = Number(m[3])
  // 4.0.1 → 4.01; 4.1.0 → 4.10; 4.1.2 → 4.12
  const rest = String(minor * 10 + patch).padStart(2, '0')
  return `v${major}.${rest}`
}

export const appVersion = formatMarketingVersion(rawVersion)

export const appGitSha = rawSha.length >= 7 ? rawSha.slice(0, 7) : rawSha

/** e.g. "v4.01 · a1b2c3d" or "v4.01" when no SHA (local). */
export const appVersionLabel = appGitSha ? `${appVersion} · ${appGitSha}` : appVersion
