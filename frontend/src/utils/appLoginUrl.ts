/**
 * App-Instanz (Login) vs. öffentliche Hauptdomain – siehe VITE_APP_ORIGIN / VITE_MAIN_SITE_ORIGIN.
 */
export function getAppLoginPath(): string {
  return '/login'
}

function inferAppOriginFromCurrentHost(): string {
  if (typeof window === 'undefined') return ''
  const protocol = window.location.protocol || 'http:'
  const host = window.location.hostname.toLowerCase()

  // Fallback NUR lokal: Produktion soll strikt über VITE_APP_ORIGIN laufen.
  if (host === 'localhost' || host === '127.0.0.1') {
    return `${protocol}//app.ematchef.test`
  }
  if (host === 'ematchef.test') {
    return `${protocol}//app.ematchef.test`
  }
  if (!host.endsWith('.test') && !host.endsWith('.localhost')) {
    return ''
  }
  if (host.startsWith('app.')) {
    return `${protocol}//${host}`
  }
  if (host.startsWith('qr.')) {
    return `${protocol}//app.${host.slice(3)}`
  }
  return `${protocol}//app.${host}`
}

function resolveAppOrigin(): string {
  const appOrigin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')
  if (appOrigin) return appOrigin
  return inferAppOriginFromCurrentHost()
}

function inferMainSiteOriginFromCurrentHost(): string {
  if (typeof window === 'undefined') return ''
  const protocol = window.location.protocol || 'http:'
  const host = window.location.hostname.toLowerCase()

  if (host === 'localhost' || host === '127.0.0.1') {
    return `${protocol}//ematchef.test`
  }
  if (host.startsWith('qr.')) {
    return `${protocol}//${host.slice(3)}`
  }
  if (host.startsWith('app.')) {
    return `${protocol}//${host.slice(4)}`
  }
  if (host === 'ematchef.test' || host.endsWith('.ematchef.test')) {
    return `${protocol}//ematchef.test`
  }
  if (host === 'ematchef.ch' || host === 'www.ematchef.ch') {
    return `${protocol}//ematchef.ch`
  }
  return ''
}

function resolveMainSiteOrigin(): string {
  const configured = getMainSiteOrigin()
  if (configured) return configured
  return inferMainSiteOriginFromCurrentHost()
}

/**
 * Ziel-Origin für Login/App von der QR-Subdomain: Hauptdomain (ematchef.*), nicht app.*.
 * Sonst wie bisher die App-Instanz (app.*).
 */
export function resolvePublicLinkOrigin(): string {
  if (isQrPublicHost()) {
    return resolveMainSiteOrigin()
  }
  return resolveAppOrigin()
}

export function getAppLoginTarget(): string {
  const origin = resolvePublicLinkOrigin()
  if (origin && typeof window !== 'undefined') {
    try {
      const u = new URL(origin)
      return `${u.origin}/login`
    } catch {
      /* ignore */
    }
  }
  return '/login'
}

export function getAppEntryTarget(): string {
  const origin = resolvePublicLinkOrigin()
  if (origin && typeof window !== 'undefined') {
    try {
      const u = new URL(origin)
      return `${u.origin}/`
    } catch {
      /* ignore */
    }
  }
  return '/'
}

export function getMainSiteOrigin(): string {
  return (import.meta.env.VITE_MAIN_SITE_ORIGIN || '').trim().replace(/\/$/, '')
}

import { isDevicesHost } from '@/utils/devicesHost'

const qrPublicHost = (import.meta.env.VITE_QR_PUBLIC_HOST || '').trim().toLowerCase()

/** Öffentliche QR-/Info-Hostnames (z. B. qr.localhost, qr.ematchef.ch). */
export function isQrPublicHost(): boolean {
  if (!qrPublicHost || typeof window === 'undefined') return false
  return window.location.hostname.toLowerCase() === qrPublicHost
}

export function isAppOrigin(): boolean {
  const appOrigin = resolveAppOrigin()
  if (!appOrigin || typeof window === 'undefined') return false
  try {
    const u = new URL(appOrigin)
    return window.location.origin === u.origin
  } catch {
    return false
  }
}

/**
 * Nach manuellem Logout: App-Instanz (z. B. app.localhost) immer Login, keine Marketing-Startseite.
 * Haupt-/Marketing-Origin → Startseite „/“.
 */
export function getPostLogoutPath(): string {
  if (isDevicesHost() || isAppOrigin()) {
    return '/login'
  }
  return '/'
}
