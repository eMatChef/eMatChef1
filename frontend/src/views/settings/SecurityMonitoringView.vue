<template>
  <div class="security-monitoring">
    <div class="header">
      <div>
        <h1>{{ t('securityMonitoring.title') }}</h1>
        <p>{{ t('securityMonitoring.subtitle') }}</p>
      </div>
      <div class="controls">
        <ETextField
          v-model.number="minutes"
          :label="t('securityMonitoring.windowMinutesLabel')"
          type="number"
          hide-details="auto"
          class="minutes-field"
        />
        <EButton variant="secondary" :loading="loading" @click="load">{{ t('common.refresh') }}</EButton>
      </div>
    </div>

    <nav class="subnav">
      <router-link to="/admin-dashboard/verwaltung/security-monitoring" class="subnav-item" :class="{ active: activeTab === 'overview' }">
        {{ t('securityMonitoring.tabs.overview') }}
      </router-link>
      <router-link to="/admin-dashboard/verwaltung/security-monitoring/alerts" class="subnav-item" :class="{ active: activeTab === 'alerts' }">
        {{ t('securityMonitoring.tabs.alertHistory') }}
      </router-link>
      <router-link to="/admin-dashboard/verwaltung/security-monitoring/settings" class="subnav-item" :class="{ active: activeTab === 'settings' }">
        {{ t('securityMonitoring.tabs.settings') }}
      </router-link>
    </nav>

    <p class="hint">{{ t('securityMonitoring.infoLiveOnly') }}</p>
    <p class="hint">{{ t('securityMonitoring.infoRestart') }}</p>

    <template v-if="activeTab === 'overview'">
      <div class="cards">
        <article class="card">
          <h3>401</h3>
          <p>{{ snapshot?.totals['401'] ?? 0 }}</p>
        </article>
        <article class="card">
          <h3>429</h3>
          <p>{{ snapshot?.totals['429'] ?? 0 }}</p>
        </article>
        <article class="card">
          <h3>5xx</h3>
          <p>{{ snapshot?.totals['5xx'] ?? 0 }}</p>
        </article>
      </div>

      <div v-if="snapshot" class="alert-box">
        <h3>{{ t('securityMonitoring.loginAlertTitle') }}</h3>
        <p>
          {{ t('securityMonitoring.loginAlertThresholdPrefix') }}
          <strong>> {{ snapshot.loginThreshold.threshold }}</strong>
          {{ t('securityMonitoring.loginAlertThresholdMiddle') }}
          <strong>{{ snapshot.loginThreshold.windowMinutes }} {{ t('securityMonitoring.minutesUnit') }}</strong>.
        </p>
        <p>{{ t('securityMonitoring.loginAlertHistoryHits') }} <strong>{{ snapshot.alerts.length }}</strong></p>
      </div>

      <v-alert v-if="error" type="error" variant="tonal" class="mb-3" :text="error" />

      <table v-if="snapshot" class="table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Endpoint</th>
            <th>Anzahl</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in snapshot.topPaths" :key="`${row.status}-${row.path}`">
            <td>{{ row.status }}</td>
            <td><code>{{ row.path }}</code></td>
            <td>{{ row.count }}</td>
          </tr>
          <tr v-if="snapshot.topPaths.length === 0">
            <td colspan="3">{{ t('securityMonitoring.noMatches') }}</td>
          </tr>
        </tbody>
      </table>
    </template>

    <section v-if="snapshot && activeTab === 'alerts'" class="history">
      <h2>{{ t('securityMonitoring.alertHistoryTitle') }}</h2>
      <table class="table">
        <thead>
          <tr>
            <th>{{ t('securityMonitoring.columns.time') }}</th>
            <th>{{ t('securityMonitoring.columns.type') }}</th>
            <th>{{ t('securityMonitoring.columns.attempts') }}</th>
            <th>{{ t('securityMonitoring.columns.window') }}</th>
            <th>Login</th>
            <th>IP</th>
            <th>{{ t('common.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="event in snapshot.alerts" :key="event.id">
            <td>{{ formatTs(event.createdAt) }}</td>
            <td><code>{{ event.alertType }}</code></td>
            <td>{{ event.eventCount }}</td>
            <td>{{ event.windowMinutes }} min</td>
            <td>{{ event.identifier || '—' }}</td>
            <td>{{ event.ipAddress || '—' }}</td>
            <td>{{ event.statusCode ?? '—' }}</td>
          </tr>
          <tr v-if="snapshot.alerts.length === 0">
            <td colspan="7">{{ t('securityMonitoring.noAlertsHistory') }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="activeTab === 'settings'" class="settings">
      <h2>{{ t('securityMonitoring.settings.title') }}</h2>
      <p class="hint">{{ t('securityMonitoring.settings.subtitle') }}</p>
      <div class="settings-grid">
        <ETextField
          id="auth-session-limit"
          v-model.number="authSessionLimitInput"
          :label="t('securityMonitoring.settings.authSessionLimitLabel')"
          type="number"
          :hint="t('securityMonitoring.settings.authSessionLimitHint')"
          persistent-hint
          hide-details="auto"
        />
        <ETextField
          id="auth-refresh-limit"
          v-model.number="authRefreshLimitInput"
          :label="t('securityMonitoring.settings.authRefreshLimitLabel')"
          type="number"
          :hint="t('securityMonitoring.settings.authRefreshLimitHint')"
          persistent-hint
          hide-details="auto"
        />
        <ETextField
          id="autologout-timeout-ms"
          v-model.number="autologoutTimeoutMsInput"
          :label="t('securityMonitoring.settings.autologoutTimeoutLabel')"
          type="number"
          hide-details="auto"
        />
        <ETextField
          id="autologout-warning-ms"
          v-model.number="autologoutWarningMsInput"
          :label="t('securityMonitoring.settings.autologoutWarningLabel')"
          type="number"
          hide-details="auto"
        />
        <ETextField
          id="autologout-throttle-ms"
          v-model.number="autologoutThrottleMsInput"
          :label="t('securityMonitoring.settings.autologoutThrottleLabel')"
          type="number"
          hide-details="auto"
        />
        <ETextField
          id="autologout-refresh-ms"
          v-model.number="autologoutRefreshIntervalMsInput"
          :label="t('securityMonitoring.settings.autologoutRefreshLabel')"
          type="number"
          hide-details="auto"
        />
        <ETextField
          id="autologout-events"
          v-model="autologoutEventsInput"
          :label="t('securityMonitoring.settings.autologoutEventsLabel')"
          :placeholder="'click,keydown,scroll'"
          :hint="t('securityMonitoring.settings.autologoutEventsHint')"
          persistent-hint
          hide-details="auto"
          class="field-group-full"
        />
      </div>
      <div class="save-actions">
        <EButton variant="secondary" :disabled="!dirtySettings || savingSettings" @click="resetSettings">
          {{ t('securityMonitoring.settings.reset') }}
        </EButton>
        <EButton variant="primary" :loading="savingSettings" :disabled="!dirtySettings || savingSettings" @click="saveSettings">
          {{ savingSettings ? t('securityMonitoring.settings.saving') : t('common.save') }}
        </EButton>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { getFcalIntegration, saveFcalIntegration, type FcalIntegrationStatus } from '@/api/adminIntegrations'
import { getSecurityMonitoring, type SecurityMonitoringSnapshot } from '@/api/securityMonitoring'
import { useToast } from '@/composables/useToast'
import { EButton, ETextField } from '@/components/form/base'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const minutes = ref(60)
const loading = ref(false)
const error = ref('')
const snapshot = ref<SecurityMonitoringSnapshot | null>(null)
const activeTab = computed<'overview' | 'alerts' | 'settings'>(() => {
  if (route.path.endsWith('/security-monitoring/settings')) return 'settings'
  if (route.path.endsWith('/security-monitoring/alerts')) return 'alerts'
  return 'overview'
})

const savingSettings = ref(false)
const integrationStatus = ref<FcalIntegrationStatus | null>(null)
const authSessionLimitInput = ref(120)
const authRefreshLimitInput = ref(30)
const autologoutTimeoutMsInput = ref(3600000)
const autologoutWarningMsInput = ref(300000)
const autologoutThrottleMsInput = ref(5000)
const autologoutRefreshIntervalMsInput = ref(1500000)
const autologoutEventsInput = ref('click,keydown,scroll')

const dirtySettings = computed(() => {
  const s = integrationStatus.value
  if (!s) return false
  return (
    authSessionLimitInput.value !== s.authSessionLimitPerMinute ||
    authRefreshLimitInput.value !== s.authRefreshLimitPerMinute ||
    autologoutTimeoutMsInput.value !== s.autologout.timeoutMs ||
    autologoutWarningMsInput.value !== s.autologout.warningMs ||
    autologoutThrottleMsInput.value !== s.autologout.activityThrottleMs ||
    autologoutRefreshIntervalMsInput.value !== s.autologout.refreshIntervalMs ||
    autologoutEventsInput.value !== s.autologout.activityEvents
  )
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    snapshot.value = await getSecurityMonitoring(minutes.value)
  } catch (e: any) {
    error.value = e?.response?.data?.error || t('securityMonitoring.loadError')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
  void loadSettings()
})

function formatTs(value: string | null): string {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('de-CH')
}

function applySettings(status: FcalIntegrationStatus): void {
  integrationStatus.value = status
  authSessionLimitInput.value = status.authSessionLimitPerMinute
  authRefreshLimitInput.value = status.authRefreshLimitPerMinute
  autologoutTimeoutMsInput.value = status.autologout.timeoutMs
  autologoutWarningMsInput.value = status.autologout.warningMs
  autologoutThrottleMsInput.value = status.autologout.activityThrottleMs
  autologoutRefreshIntervalMsInput.value = status.autologout.refreshIntervalMs
  autologoutEventsInput.value = status.autologout.activityEvents
}

async function loadSettings() {
  try {
    const status = await getFcalIntegration()
    applySettings(status)
  } catch {
    // Monitoring bleibt nutzbar auch wenn Settings API gerade fehlschlägt.
  }
}

function clampInt(value: number, min: number, max: number, fallback: number): number {
  const n = Number.isFinite(value) ? Math.trunc(value) : fallback
  return Math.max(min, Math.min(max, n))
}

function resetSettings() {
  if (integrationStatus.value) applySettings(integrationStatus.value)
}

async function saveSettings() {
  const current = integrationStatus.value
  if (!current) return
  savingSettings.value = true
  try {
    const status = await saveFcalIntegration(
      '',
      clampInt(authSessionLimitInput.value, 10, 1200, current.authSessionLimitPerMinute),
      clampInt(authRefreshLimitInput.value, 5, 600, current.authRefreshLimitPerMinute),
      {
        timeoutMs: clampInt(autologoutTimeoutMsInput.value, 60000, 86400000, current.autologout.timeoutMs),
        warningMs: clampInt(autologoutWarningMsInput.value, 15000, 3600000, current.autologout.warningMs),
        activityThrottleMs: clampInt(autologoutThrottleMsInput.value, 500, 60000, current.autologout.activityThrottleMs),
        refreshIntervalMs: clampInt(autologoutRefreshIntervalMsInput.value, 60000, 3600000, current.autologout.refreshIntervalMs),
        activityEvents: autologoutEventsInput.value,
      }
    )
    applySettings(status)
    toast.success(t('securityMonitoring.settings.saved'))
  } catch (e: any) {
    toast.error(e?.response?.data?.error || t('securityMonitoring.settings.saveError'))
  } finally {
    savingSettings.value = false
  }
}
</script>

<style scoped>
.security-monitoring { display: grid; gap: 16px; }
.header { display: flex; justify-content: space-between; gap: 16px; align-items: end; }
.controls { display: flex; gap: 10px; align-items: end; }
.minutes-field { max-width: 120px; }
.subnav { display: flex; gap: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
.subnav-item { border: 1px solid #cbd5e1; background: #fff; color: #334155; border-radius: 8px; padding: 8px 12px; cursor: pointer; text-decoration: none; }
.subnav-item.active { border-color: #2563eb; color: #1d4ed8; background: #eff6ff; }
.hint { margin: 0; font-size: 13px; color: #475569; }
.cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #fff; }
.card h3 { margin: 0 0 6px; font-size: 14px; color: #334155; }
.card p { margin: 0; font-size: 28px; font-weight: 700; color: #0f172a; }
.alert-box { border: 1px solid #fbbf24; background: #fffbeb; border-radius: 10px; padding: 12px; }
.alert-box h3 { margin: 0 0 8px; font-size: 15px; }
.alert-box p { margin: 0; color: #78350f; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; }
.history h2, .settings h2 { margin: 4px 0 8px; font-size: 18px; color: #0f172a; }
.settings-grid { margin-top: 12px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.field-group-full { grid-column: 1 / -1; }
.save-actions { display: flex; gap: 10px; margin-top: 14px; }
</style>

