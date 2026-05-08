import apiClient from './apiClient'

export interface SecurityMonitoringSnapshot {
  minutes: number
  totals: {
    '401': number
    '429': number
    '5xx': number
  }
  loginThreshold: {
    threshold: number
    windowMinutes: number
  }
  alerts: Array<{
    id: string
    alertType: string
    severity: string
    windowMinutes: number
    eventCount: number
    ipAddress: string | null
    identifier: string | null
    path: string
    statusCode: number | null
    createdAt: string | null
    context: Record<string, unknown>
  }>
  topPaths: Array<{
    status: number
    path: string
    count: number
  }>
}

export async function getSecurityMonitoring(minutes = 60): Promise<SecurityMonitoringSnapshot> {
  const { data } = await apiClient.get<{
    minutes: number
    totals: { '401': number; '429': number; '5xx': number }
    login_threshold?: { threshold: number; window_minutes: number }
    alerts?: Array<{
      id: string
      alert_type: string
      severity: string
      window_minutes: number
      event_count: number
      ip_address: string | null
      identifier: string | null
      path: string
      status_code: number | null
      created_at: string | null
      context: Record<string, unknown>
    }>
    top_paths: Array<{ status: number; path: string; count: number }>
  }>('/api/admin/security-monitoring', { params: { minutes } })

  return {
    minutes: Number(data.minutes || 60),
    totals: {
      '401': Number(data.totals?.['401'] || 0),
      '429': Number(data.totals?.['429'] || 0),
      '5xx': Number(data.totals?.['5xx'] || 0),
    },
    loginThreshold: {
      threshold: Number(data.login_threshold?.threshold || 5),
      windowMinutes: Number(data.login_threshold?.window_minutes || 15),
    },
    alerts: Array.isArray(data.alerts)
      ? data.alerts.map((item) => ({
          id: String(item.id || ''),
          alertType: String(item.alert_type || ''),
          severity: String(item.severity || 'warning'),
          windowMinutes: Number(item.window_minutes || 0),
          eventCount: Number(item.event_count || 0),
          ipAddress: item.ip_address || null,
          identifier: item.identifier || null,
          path: String(item.path || ''),
          statusCode: Number.isFinite(Number(item.status_code)) ? Number(item.status_code) : null,
          createdAt: item.created_at || null,
          context: item.context || {},
        }))
      : [],
    topPaths: Array.isArray(data.top_paths) ? data.top_paths : [],
  }
}

