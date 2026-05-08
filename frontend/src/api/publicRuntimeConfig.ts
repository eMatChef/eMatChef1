import apiClient from './apiClient'

export interface RuntimeAutologoutConfig {
  timeoutMs: number
  warningMs: number
  activityThrottleMs: number
  refreshIntervalMs: number
  activityEvents: string
}

export async function getPublicRuntimeConfig(): Promise<{ autologout?: RuntimeAutologoutConfig }> {
  const { data } = await apiClient.get<{
    autologout?: {
      timeout_ms?: number
      warning_ms?: number
      activity_throttle_ms?: number
      refresh_interval_ms?: number
      activity_events?: string[]
    }
  }>('/api/public/runtime-config')
  if (!data.autologout) return {}
  return {
    autologout: {
      timeoutMs: Number(data.autologout.timeout_ms || 0),
      warningMs: Number(data.autologout.warning_ms || 0),
      activityThrottleMs: Number(data.autologout.activity_throttle_ms || 0),
      refreshIntervalMs: Number(data.autologout.refresh_interval_ms || 0),
      activityEvents: String((data.autologout.activity_events || []).join(',')),
    },
  }
}

