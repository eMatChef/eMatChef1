import apiClient from './apiClient'

export interface FcalIntegrationStatus {
  fcalApiKeyConfigured: boolean
  authSessionLimitPerMinute: number
  authRefreshLimitPerMinute: number
  autologout: {
    timeoutMs: number
    warningMs: number
    activityThrottleMs: number
    refreshIntervalMs: number
    activityEvents: string
  }
}

export async function getFcalIntegration(): Promise<FcalIntegrationStatus> {
  const { data } = await apiClient.get<{
    fcal_api_key_configured: boolean
    auth_session_limit_per_minute: number
    auth_refresh_limit_per_minute: number
    autologout?: {
      timeout_ms?: number
      warning_ms?: number
      activity_throttle_ms?: number
      refresh_interval_ms?: number
      activity_events?: string[]
    }
  }>('/api/admin/integrations/fcal')
  return {
    fcalApiKeyConfigured: data.fcal_api_key_configured,
    authSessionLimitPerMinute: Number(data.auth_session_limit_per_minute || 120),
    authRefreshLimitPerMinute: Number(data.auth_refresh_limit_per_minute || 30),
    autologout: {
      timeoutMs: Number(data.autologout?.timeout_ms || 1800000),
      warningMs: Number(data.autologout?.warning_ms || 180000),
      activityThrottleMs: Number(data.autologout?.activity_throttle_ms || 5000),
      refreshIntervalMs: Number(data.autologout?.refresh_interval_ms || 1500000),
      activityEvents: String((data.autologout?.activity_events || ['click', 'keydown', 'scroll']).join(',')),
    },
  }
}

/** Nur Superadmin. Leerer String entfernt den gespeicherten Key. */
export async function saveFcalIntegration(
  fcalApiKey: string,
  authSessionLimitPerMinute: number,
  authRefreshLimitPerMinute: number,
  autologout: {
    timeoutMs: number
    warningMs: number
    activityThrottleMs: number
    refreshIntervalMs: number
    activityEvents: string
  }
): Promise<FcalIntegrationStatus> {
  const { data } = await apiClient.put<{
    fcal_api_key_configured: boolean
    auth_session_limit_per_minute: number
    auth_refresh_limit_per_minute: number
    autologout?: {
      timeout_ms?: number
      warning_ms?: number
      activity_throttle_ms?: number
      refresh_interval_ms?: number
      activity_events?: string[]
    }
  }>('/api/admin/integrations/fcal', {
    fcal_api_key: fcalApiKey,
    auth_session_limit_per_minute: authSessionLimitPerMinute,
    auth_refresh_limit_per_minute: authRefreshLimitPerMinute,
    autologout: {
      timeout_ms: autologout.timeoutMs,
      warning_ms: autologout.warningMs,
      activity_throttle_ms: autologout.activityThrottleMs,
      refresh_interval_ms: autologout.refreshIntervalMs,
      activity_events: autologout.activityEvents,
    },
  })
  return {
    fcalApiKeyConfigured: data.fcal_api_key_configured,
    authSessionLimitPerMinute: Number(data.auth_session_limit_per_minute || 120),
    authRefreshLimitPerMinute: Number(data.auth_refresh_limit_per_minute || 30),
    autologout: {
      timeoutMs: Number(data.autologout?.timeout_ms || 1800000),
      warningMs: Number(data.autologout?.warning_ms || 180000),
      activityThrottleMs: Number(data.autologout?.activity_throttle_ms || 5000),
      refreshIntervalMs: Number(data.autologout?.refresh_interval_ms || 1500000),
      activityEvents: String((data.autologout?.activity_events || ['click', 'keydown', 'scroll']).join(',')),
    },
  }
}
