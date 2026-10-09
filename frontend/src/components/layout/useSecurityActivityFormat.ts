import { useI18n } from 'vue-i18n'
import type { SecurityActivityEvent } from '@/api/profileSecurity'

const PREFIX = 'layout.profileModal.activity'

/** Gemeinsame Darstellung der Sicherheitsereignisse (Profil-Liste und Protokoll-Modal). */
export function useSecurityActivityFormat() {
  const { t, te, locale } = useI18n()

  const translateOr = (key: string, fallback: string) => (te(key) ? t(key) : fallback)

  function formatDate(value: string): string {
    return new Date(value).toLocaleString(locale.value, { dateStyle: 'short', timeStyle: 'short' })
  }

  function actionLabel(action: string): string {
    return translateOr(`${PREFIX}.events.${action}`, action)
  }

  function eventLabel(e: SecurityActivityEvent): string {
    const base = actionLabel(e.action)
    if (!e.detail) return base
    const detail = e.action.startsWith('external_identity_')
      ? translateOr(`${PREFIX}.providers.${e.detail}`, e.detail)
      : e.detail
    return `${base} (${detail})`
  }

  /** Nur vorhandene Angaben; historische Ereignisse ohne IP/Browser liefern entsprechend weniger. */
  function metaParts(e: SecurityActivityEvent): string[] {
    const parts: string[] = []
    if (e.auth_method) {
      parts.push(t(`${PREFIX}.meta.method`, { value: translateOr(`${PREFIX}.authMethods.${e.auth_method}`, e.auth_method) }))
    }
    if (e.mfa_source) {
      parts.push(t(`${PREFIX}.meta.mfa`, { value: translateOr(`${PREFIX}.mfaSources.${e.mfa_source}`, e.mfa_source) }))
    }
    const device = [e.browser, e.os].filter(Boolean).join(' / ')
    if (device) parts.push(t(`${PREFIX}.meta.device`, { value: device }))
    if (e.ip_address) parts.push(t(`${PREFIX}.meta.ip`, { value: e.ip_address }))
    if (e.changed_fields.length) {
      const fields = e.changed_fields.map((f) => translateOr(`${PREFIX}.fields.${f}`, f)).join(', ')
      parts.push(t(`${PREFIX}.meta.fields`, { fields }))
    }
    if (e.actor?.type === 'other') {
      parts.push(e.actor.name ? t(`${PREFIX}.meta.actor`, { name: e.actor.name }) : t(`${PREFIX}.meta.actorUnknown`))
    }
    return parts
  }

  return { formatDate, actionLabel, eventLabel, metaParts }
}
