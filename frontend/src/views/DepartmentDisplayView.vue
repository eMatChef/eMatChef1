<template>
  <div class="department-display">
    <header class="display-header">
      <div class="display-header-main">
        <EmcLogoMark size="sm" />
        <div>
          <h1 class="display-title">{{ pageHeading }}</h1>
          <p v-if="!needsPin && deviceState !== 'expired' && displaySubtitle" class="display-subtitle">{{ displaySubtitle }}</p>
        </div>
      </div>
      <div v-if="!needsPin && deviceState !== 'expired'" class="display-header-meta">
        <span v-if="isPreview" class="display-preview-badge">{{ t('display.preview.badge') }}</span>
        <button v-if="isPreview" type="button" class="display-fullscreen-btn" @click="leavePreview">
          {{ t('display.preview.back') }}
        </button>
        <time class="display-clock" :datetime="clockIso">{{ clockLabel }}</time>
        <button
          v-if="!isFullscreen"
          type="button"
          class="display-fullscreen-btn"
          :title="t('display.fullscreen')"
          @click="enterFullscreen"
        >
          {{ t('display.fullscreen') }}
        </button>
      </div>
    </header>

    <ECard v-if="needsPin" variant="outlined" class="display-pin-panel">
      <h2 class="display-pin-title">{{ t('display.pin.title') }}</h2>
      <p class="display-pin-hint">{{ t('display.pin.hint') }}</p>
      <form class="display-pin-form" @submit.prevent="submitPin">
        <ETextField
          v-model="pinInput"
          :placeholder="t('display.pin.placeholder')"
          :disabled="pinSubmitting"
          :error-messages="pinError ? [pinError] : undefined"
          autocomplete="off"
          autocapitalize="characters"
          spellcheck="false"
          maxlength="8"
          inputmode="text"
          hide-details="auto"
          class="display-pin-field"
          @update:model-value="onPinInput"
        />
        <EButton
          type="submit"
          variant="primary"
          block
          :disabled="pinSubmitting || pinInput.length !== 8"
          :loading="pinSubmitting"
        >
          {{ pinSubmitting ? t('display.pin.submitting') : t('display.pin.submit') }}
        </EButton>
      </form>
    </ECard>

    <ECard v-else-if="deviceState === 'expired'" variant="outlined" class="display-pin-panel">
      <h2 class="display-pin-title">{{ t('display.expired.title') }}</h2>
      <p class="display-pin-hint">{{ t('display.expired.text', { name: deviceName }) }}</p>
      <p class="display-pin-hint muted">{{ t('display.expired.waiting') }}</p>
    </ECard>

    <template v-else>
      <div v-if="offline" class="display-offline" role="status">
        <strong>{{ t('display.offline.banner') }}</strong>
        <span v-if="lastUpdateLabel"> · {{ t('display.offline.lastUpdate', { time: lastUpdateLabel }) }}</span>
      </div>
      <p v-if="loading && !loaded" class="display-status muted">{{ t('display.loading') }}</p>
      <p v-else-if="loadError" class="display-status error">{{ loadError }}</p>

      <div v-else-if="!showActivities && !showWorkshop && !showStatistics" class="display-status muted">
        {{ scope === 'grossanlass' ? t('display.gaTemplatePending') : t('display.noPanelsEnabled') }}
      </div>

      <section v-if="showStatistics && statistics" class="display-stats">
        <h2 class="panel-title">{{ t('display.statisticsTitle') }}</h2>
        <div v-if="activityStatEntries.length" class="display-stat-group">
          <h3 class="display-stat-group-title">{{ t('display.statisticsActivities') }}</h3>
          <div class="display-stat-cards">
            <div v-for="entry in activityStatEntries" :key="entry.status" class="display-stat-card">
              <span class="display-stat-value">{{ entry.count }}</span>
              <span class="display-stat-label">{{ entry.label }}</span>
            </div>
          </div>
        </div>
        <div v-if="workshopStatEntries.length" class="display-stat-group">
          <h3 class="display-stat-group-title">{{ t('display.statisticsWorkshop') }}</h3>
          <div class="display-stat-cards">
            <div v-for="entry in workshopStatEntries" :key="entry.status" class="display-stat-card">
              <span class="display-stat-value">{{ entry.count }}</span>
              <span class="display-stat-label">{{ entry.label }}</span>
            </div>
          </div>
        </div>
      </section>

      <div v-if="showActivities || showWorkshop" class="display-grid" :class="{ 'display-grid--single': panelCount === 1 }">
        <section v-if="showActivities" class="display-panel">
          <h2 class="panel-title">{{ t('display.upcomingActivities') }}</h2>
          <p v-if="displayActivities.length === 0" class="panel-empty">{{ t('display.noActivities') }}</p>
          <ul v-else class="display-list">
            <li v-for="item in displayActivities" :key="item.id" class="display-row">
              <div class="display-row-text">
                <span class="display-row-name">{{ item.name }}</span>
                <div
                  v-if="item.group_path?.length && item.type !== 'external'"
                  class="display-group-path"
                >
                  <span
                    v-for="(line, lineIdx) in item.group_path"
                    :key="lineIdx"
                    class="display-group-path-line"
                    :style="{ paddingLeft: `${line.level * 12}px` }"
                  >{{ line.label }}</span>
                </div>
                <p v-if="item.venue_label" class="display-venue">
                  <span class="display-venue-label">{{ t('display.venue') }}</span>
                  {{ item.venue_label }}
                </p>
                <span class="display-row-meta">
                  <span class="status-pill activity-status" :class="activityStatusClass(item.status)">{{ activityStatusLabel(item.status) }}</span>
                  <span v-if="item.periodLabel">{{ item.periodLabel }}</span>
                </span>
              </div>
              <PublicQrTag
                v-if="item.publicUrl"
                :url="item.publicUrl"
                :code="item.public_code"
                :size="qrSize"
                :image-label="item.name"
                :image-entity-id="item.id"
              />
              <span v-else class="display-no-qr">{{ t('display.noQr') }}</span>
            </li>
          </ul>
        </section>

        <section v-if="showWorkshop" class="display-panel">
          <h2 class="panel-title">{{ t('display.openWorkshop') }}</h2>
          <p v-if="displayWorkshopTickets.length === 0" class="panel-empty">{{ t('display.noWorkshop') }}</p>
          <ul v-else class="display-list">
            <li v-for="item in displayWorkshopTickets" :key="item.id" class="display-row">
              <div class="display-row-text">
                <span class="display-row-name">{{ item.title }}</span>
                <span class="display-row-meta">
                  <span class="priority-pill" :class="item.priority">{{ item.priority_label }}</span>
                  <span class="status-pill workshop" :class="item.display_phase || item.phase || 'triage'">{{ item.phase_label || workshopPhaseLabel(item.display_phase || item.phase || 'triage') }}</span>
                  <span>{{ item.material_item.name }}</span>
                </span>
              </div>
              <PublicQrTag
                v-if="item.publicUrl"
                :url="item.publicUrl"
                :code="item.public_code"
                :size="qrSize"
                :image-label="item.title"
                :image-entity-id="item.id"
              />
              <span v-else class="display-no-qr">{{ t('display.noQr') }}</span>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import EButton from '@/components/form/base/EButton.vue'
import ECard from '@/components/form/base/ECard.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import type { DisplayActivityRow, DisplayStatistics, DisplayWorkshopTicketRow } from '@/api/display'
import {
  authenticatePublicDisplay,
  getDisplayPreviewData,
  getPublicDisplaySession,
  type PublicDisplayData,
} from '@/api/displayScreens'
import { getDisplayDeviceData, getDisplayDeviceSession } from '@/api/displayDevice'
import { resolveActivityPublicUrl, resolveWorkshopPublicUrl } from '@/utils/publicQrUrl'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'

const PIN_CHARSET = /[^23456789ABCDEFGHJKLMNPQRSTUVWXYZ]/g

const REFRESH_MS = 60_000
const EXPIRED_POLL_MS = 30_000
/** Wiederverbindung nach Verbindungsverlust: zunehmender Abstand, höchstens 5 Minuten. */
const RECONNECT_BACKOFF_MS = [15_000, 30_000, 60_000, 120_000, 300_000]
const PRIORITY_ORDER: Record<string, number> = { urgent: 0, high: 1, normal: 2, low: 3 }

const route = useRoute()
const router = useRouter()
const { t, te, locale } = useI18n()

const publicId = computed(() => String(route.params.publicId || '').trim())
/** Vorschau für Verwalter (User-Login, keine Display-Sitzung): gleiche Engine, andere Datenquelle. */
const isPreview = computed(() => route.meta.displayPreview === true)
/** Gerätemodus: Anzeige über das Geräte-Credential (TV nach Kopplung, Neustart, Fernumschaltung). */
const isDeviceMode = computed(() => route.meta.displayDevice === true)
const previewDepartmentId = computed(() => String(route.params.departmentId || '').trim())
const previewScreenId = computed(() => String(route.params.screenId || '').trim())
const needsPin = ref(false)
const deviceState = ref<'active' | 'expired'>('active')
const deviceName = ref('')
const offline = ref(false)
const loaded = ref(false)
const lastUpdate = ref<Date | null>(null)
const scope = ref<'department' | 'grossanlass' | undefined>(undefined)
const pinInput = ref('')
const pinError = ref<string | null>(null)
const pinSubmitting = ref(false)
const loading = ref(false)
const loadError = ref<string | null>(null)
const activities = ref<DisplayActivityRow[]>([])
const workshopTickets = ref<DisplayWorkshopTicketRow[]>([])
const departmentName = ref('')
const screenName = ref('')
const subtitleText = ref<string | null>(null)
const showActivities = ref(true)
const showWorkshop = ref(true)
const showStatistics = ref(false)
const allowedActivityTypes = ref<string[]>([])
const allowedActivityStatuses = ref<string[]>([])
const allowedWorkshopStatuses = ref<string[]>([])
const statistics = ref<DisplayStatistics | null>(null)
const clockLabel = ref('')
const clockIso = ref('')
const isFullscreen = ref(false)
const qrSize = 96

let refreshTimer: ReturnType<typeof setTimeout> | null = null
let failures = 0
let clockTimer: ReturnType<typeof setInterval> | null = null

const pageHeading = computed(() => {
  const parts: string[] = []
  if (departmentName.value.trim()) parts.push(departmentName.value.trim())
  if (screenName.value.trim()) parts.push(screenName.value.trim())
  if (parts.length) return `${parts.join(' · ')}`
  return t('display.title')
})

const displaySubtitle = computed(() => {
  const custom = subtitleText.value?.trim()
  if (custom) return custom
  return t('display.subtitle')
})

const panelCount = computed(() => (showActivities.value ? 1 : 0) + (showWorkshop.value ? 1 : 0))

type DisplayActivityItem = DisplayActivityRow & { periodLabel: string; publicUrl: string }

const displayActivities = computed((): DisplayActivityItem[] => {
  const now = Date.now()
  const todayStart = new Date()
  todayStart.setHours(0, 0, 0, 0)
  const horizon = now + 60 * 24 * 60 * 60 * 1000

  const typeSet = new Set(allowedActivityTypes.value)
  const statusSet = new Set(allowedActivityStatuses.value)
  const filterByType = typeSet.size > 0
  const filterByStatus = statusSet.size > 0

  return activities.value
    .filter((a) => {
      if (filterByType && !typeSet.has(a.type)) return false
      if (filterByStatus && !statusSet.has(a.status)) return false
      const startRaw = a.usage_start || a.planning_start
      const endRaw = a.usage_end || a.planning_end
      if (!startRaw) {
        return ['packing', 'packed', 'at_event'].includes(a.status)
      }
      const startMs = new Date(startRaw).getTime()
      const endMs = endRaw ? new Date(endRaw).getTime() : startMs
      if (Number.isNaN(startMs)) return true
      return endMs >= todayStart.getTime() && startMs <= horizon
    })
    .map((a) => ({
      ...a,
      periodLabel: formatPeriod(a),
      publicUrl: resolveActivityPublicUrl(a.public_url, a.public_code),
    }))
    .sort((a, b) => {
      const aStart = a.usage_start || a.planning_start || ''
      const bStart = b.usage_start || b.planning_start || ''
      return aStart.localeCompare(bStart)
    })
})

type DisplayWorkshopItem = DisplayWorkshopTicketRow & { publicUrl: string }

const displayWorkshopTickets = computed((): DisplayWorkshopItem[] => {
  const statusSet = new Set(allowedWorkshopStatuses.value)
  const filterByStatus = statusSet.size > 0

  return workshopTickets.value
    .filter((ticket) => !filterByStatus || statusSet.has(ticket.display_phase || ticket.phase || 'triage'))
    .map((ticket) => ({
      ...ticket,
      publicUrl: resolveWorkshopPublicUrl(ticket.public_url, ticket.public_code),
    }))
    .sort((a, b) => {
      const pa = PRIORITY_ORDER[a.priority] ?? 9
      const pb = PRIORITY_ORDER[b.priority] ?? 9
      if (pa !== pb) return pa - pb
      return b.created_at.localeCompare(a.created_at)
    })
})

const activityStatEntries = computed(() => {
  const counts = statistics.value?.activities_by_status
  if (!counts) return []
  return Object.entries(counts).map(([status, count]) => ({
    status,
    count,
    label: activityStatusLabel(status),
  }))
})

const workshopStatEntries = computed(() => {
  const counts = statistics.value?.workshop_by_phase
  if (!counts) return []
  return Object.entries(counts).map(([phase, count]) => ({
    status: phase,
    count,
    label: workshopPhaseLabel(phase),
  }))
})

function workshopPhaseLabel(phase: string): string {
  const key = `workshop.phase.${phase}`
  return te(key) ? t(key) : phase
}

function intlTag(): string {
  return String(locale.value ?? '').startsWith('de') ? 'de-CH' : 'en-CH'
}

function formatPeriod(a: DisplayActivityRow): string {
  const startRaw = a.usage_start || a.planning_start || ''
  const endRaw = a.usage_end || a.planning_end || ''
  const start = formatDateTime(startRaw)
  const end = formatDateTime(endRaw)
  if (start && end) return `${start} – ${end}`
  return start || end || ''
}

function formatDateTime(iso: string): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString(intlTag(), {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function activityStatusLabel(status: string): string {
  const displayKey = `display.activityStatus.${activityStatusI18nKey(status)}`
  if (te(displayKey)) return t(displayKey)
  const key = `activities.status.${activityStatusI18nKey(status)}`
  return te(key) ? t(key) : status
}

function updateClock() {
  const now = new Date()
  clockIso.value = now.toISOString()
  clockLabel.value = now.toLocaleString(intlTag(), {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function onFullscreenChange() {
  isFullscreen.value = !!document.fullscreenElement
}

async function enterFullscreen() {
  try {
    await document.documentElement.requestFullscreen()
  } catch {
    /* ignore */
  }
}

function onPinInput(value?: string) {
  pinInput.value = (value ?? pinInput.value).toUpperCase().replace(PIN_CHARSET, '').slice(0, 8)
  pinError.value = null
}

async function submitPin() {
  const id = publicId.value
  if (!id || pinInput.value.length !== 8) return

  pinSubmitting.value = true
  pinError.value = null
  try {
    // Die manuelle Anmeldung legt ein eigenes Gerät an (Credential-Cookie, 90 Tage).
    await authenticatePublicDisplay(id, pinInput.value)
    pinInput.value = ''
    await router.replace({ name: 'DisplayDevice' })
  } catch {
    pinError.value = t('display.pin.invalid')
  } finally {
    pinSubmitting.value = false
  }
}

function leavePreview() {
  if (window.opener) {
    window.close()
    return
  }
  const back = String(route.query.back || '')
  void router.push(back.startsWith('/') && !back.startsWith('//') ? back : `/${previewDepartmentId.value}`)
}

function onBrowserOffline() {
  // Verbindung weg: letzte Anzeige bleibt stehen, aber sofort deutlich als veraltet markiert.
  if (isDeviceMode.value && loaded.value) offline.value = true
}

function onBrowserOnline() {
  // Wieder verbunden: Berechtigung/Zuordnung prüfen und frische Daten laden (ein Aufruf erledigt beides).
  if (isDeviceMode.value) {
    failures = 0
    void load()
  }
}

function onVisibilityChange() {
  if (isPreview.value && document.visibilityState === 'visible') void load()
}

const lastUpdateLabel = computed(() =>
  lastUpdate.value ? lastUpdate.value.toLocaleString(locale.value, { dateStyle: 'short', timeStyle: 'medium' }) : '',
)

function applyData(data: PublicDisplayData) {
  activities.value = data.activities
  workshopTickets.value = data.workshopTickets
  departmentName.value = data.department_name || ''
  screenName.value = data.screen_name || ''
  subtitleText.value = data.subtitle_text ?? null
  scope.value = data.scope
  showActivities.value = data.show_activities !== false
  showWorkshop.value = data.show_workshop !== false
  showStatistics.value = data.show_statistics === true
  allowedActivityTypes.value = data.activity_types?.length ? data.activity_types : []
  allowedActivityStatuses.value = data.activity_statuses?.length ? data.activity_statuses : []
  allowedWorkshopStatuses.value = data.workshop_statuses?.length ? data.workshop_statuses : []
  statistics.value = data.statistics ?? null
}

/** Verwirft alle angezeigten Inhalte (Widerruf, abgelaufene Freigabe): nichts bleibt im Speicher sichtbar. */
function clearData() {
  applyData({ activities: [], workshopTickets: [], statistics: null })
  loaded.value = false
  lastUpdate.value = null
}

function scheduleRefresh(ms: number) {
  if (refreshTimer) clearTimeout(refreshTimer)
  refreshTimer = setTimeout(() => void load(), ms)
}

async function loadDevice() {
  try {
    const result = await getDisplayDeviceData()
    if (result.device) deviceName.value = result.device.name
    if (result.state === 'active' && result.data) {
      deviceState.value = 'active'
      applyData(result.data)
      loaded.value = true
      lastUpdate.value = new Date()
      offline.value = false
      failures = 0
      loadError.value = null
      scheduleRefresh(REFRESH_MS)
      return
    }
    failures = 0
    offline.value = false
    if (result.state === 'expired') {
      // Freigabe abgelaufen: Warteseite, regelmässig prüfen, ob administrativ wieder freigegeben wurde.
      clearData()
      deviceState.value = 'expired'
      scheduleRefresh(EXPIRED_POLL_MS)
      return
    }
    // Kein oder widerrufenes Gerät: zurück zur QR-Kopplung.
    clearData()
    stopTimers()
    await router.replace({ name: 'DisplayHome' })
  } catch {
    // Verbindungsverlust: zuletzt geladene Anzeige bleibt sichtbar, deutlich als veraltet markiert.
    offline.value = true
    scheduleRefresh(RECONNECT_BACKOFF_MS[Math.min(failures, RECONNECT_BACKOFF_MS.length - 1)] ?? 300_000)
    failures += 1
  }
}

async function load() {
  if (isDeviceMode.value) {
    await loadDevice()
    return
  }

  const id = previewScreenId.value
  if (!id) {
    loadError.value = t('display.errorNoScreen')
    loading.value = false
    return
  }

  if (!loaded.value) loading.value = true
  loadError.value = null
  try {
    applyData(await getDisplayPreviewData(previewDepartmentId.value, id))
    loaded.value = true
    lastUpdate.value = new Date()
  } catch (err: unknown) {
    console.error('display preview load failed', err)
    loadError.value = t('display.errorLoad')
  } finally {
    loading.value = false
  }
  scheduleRefresh(REFRESH_MS)
}

async function bootstrap() {
  if (isPreview.value) {
    needsPin.value = false
    await load()
    startClock()
    return
  }
  if (isDeviceMode.value) {
    needsPin.value = false
    loading.value = true
    await load()
    loading.value = false
    startClock()
    return
  }

  // Alt-/Lesezeichen-URL /display/{publicId}: vorhandenes Gerät übernehmen, Alt-Sitzung migrieren oder manuell anmelden.
  const id = publicId.value
  const device = await getDisplayDeviceSession().catch(() => null)
  if (device && (device.state === 'active' || device.state === 'expired')) {
    await router.replace({ name: 'DisplayDevice' })
    return
  }
  if (id) {
    try {
      const session = await getPublicDisplaySession(id)
      if (session.authenticated === true) {
        await router.replace({ name: 'DisplayDevice' })
        return
      }
    } catch {
      /* manuelle Anmeldung */
    }
  }
  needsPin.value = true
  if (!id) pinError.value = t('display.errorNoScreen')
}

function startClock() {
  if (clockTimer) clearInterval(clockTimer)
  updateClock()
  clockTimer = setInterval(updateClock, 30_000)
}

function stopTimers() {
  if (clockTimer) {
    clearInterval(clockTimer)
    clockTimer = null
  }
  if (refreshTimer) {
    clearTimeout(refreshTimer)
    refreshTimer = null
  }
}

onMounted(() => {
  document.addEventListener('fullscreenchange', onFullscreenChange)
  document.addEventListener('visibilitychange', onVisibilityChange)
  window.addEventListener('offline', onBrowserOffline)
  window.addEventListener('online', onBrowserOnline)
  void bootstrap()
})

onBeforeUnmount(() => {
  document.removeEventListener('fullscreenchange', onFullscreenChange)
  document.removeEventListener('visibilitychange', onVisibilityChange)
  window.removeEventListener('offline', onBrowserOffline)
  window.removeEventListener('online', onBrowserOnline)
  stopTimers()
})

watch(publicId, () => {
  if (isPreview.value || isDeviceMode.value) return
  stopTimers()
  pinInput.value = ''
  pinError.value = null
  void bootstrap()
})
</script>

<style scoped>
.department-display {
  min-height: 100vh;
  padding: 24px 28px 32px;
  background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
  color: #0f172a;
}

.display-header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
}

.display-header-main {
  display: flex;
  align-items: center;
  gap: 16px;
}

.display-title {
  margin: 0;
  font-size: clamp(1.35rem, 2.5vw, 2rem);
  font-weight: 800;
  line-height: 1.2;
}

.display-subtitle {
  margin: 4px 0 0;
  font-size: 0.95rem;
  color: #64748b;
}

.display-header-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.display-clock {
  font-size: 1.1rem;
  font-weight: 600;
  color: #334155;
  font-variant-numeric: tabular-nums;
}

.display-offline {
  position: sticky;
  top: 0;
  z-index: 5;
  margin: 0 0 12px;
  padding: 10px 16px;
  border-radius: 10px;
  background: #fee2e2;
  color: #991b1b;
  font-size: 1rem;
}

.display-preview-badge {
  padding: 4px 10px;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
  font-size: 0.8rem;
  font-weight: 700;
}

.display-fullscreen-btn {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #fff;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.display-fullscreen-btn:hover {
  background: #f1f5f9;
}

.display-pin-panel {
  max-width: 420px;
  margin: 48px auto 0;
  padding: 28px 32px !important;
}

.display-pin-title {
  margin: 0 0 8px;
  font-size: 1.25rem;
  font-weight: 700;
}

.display-pin-hint {
  margin: 0 0 20px;
  color: #64748b;
  font-size: 0.95rem;
}

.display-pin-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.display-pin-field :deep(input) {
  font-size: 1.5rem;
  letter-spacing: 0.35em;
  text-align: center;
  font-weight: 700;
  text-transform: uppercase;
}

.display-status {
  font-size: 1.1rem;
  padding: 24px 0;
}

.display-stats {
  margin-bottom: 24px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px 22px;
  box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
}

.display-stat-group + .display-stat-group {
  margin-top: 16px;
}

.display-stat-group-title {
  margin: 0 0 10px;
  font-size: 0.9rem;
  font-weight: 600;
  color: #64748b;
}

.display-stat-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.display-stat-card {
  min-width: 88px;
  padding: 10px 14px;
  border-radius: 10px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.display-stat-value {
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1.1;
  color: #0f172a;
}

.display-stat-label {
  font-size: 0.75rem;
  color: #64748b;
  text-align: center;
}

.display-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap: 24px;
  align-items: start;
}

.display-grid--single {
  grid-template-columns: 1fr;
  max-width: 720px;
  margin: 0 auto;
}

.display-panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px 22px;
  box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
}

.panel-title {
  margin: 0 0 16px;
  font-size: 1.15rem;
  font-weight: 700;
  color: #1e293b;
}

.panel-empty {
  margin: 0;
  color: #64748b;
  font-size: 0.95rem;
}

.display-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.display-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 14px;
  border-radius: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.display-row-text {
  flex: 1;
  min-width: 0;
}

.display-row-name {
  display: block;
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.3;
  margin-bottom: 4px;
  word-break: break-word;
}

.display-group-path {
  display: flex;
  flex-direction: column;
  gap: 1px;
  margin-bottom: 6px;
  min-width: 0;
}

.display-group-path-line {
  display: block;
  font-size: 0.82rem;
  line-height: 1.35;
  color: #475569;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.display-group-path-line:first-child {
  font-weight: 600;
  color: #334155;
}

.display-venue {
  margin: 0 0 6px;
  font-size: 0.82rem;
  line-height: 1.4;
  color: #475569;
  word-break: break-word;
}

.display-venue-label {
  font-weight: 600;
  color: #64748b;
  margin-right: 4px;
}

.display-row-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: #64748b;
}

.status-pill,
.priority-pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.priority-pill {
  background: #e2e8f0;
  color: #334155;
}

.status-pill.workshop {
  background: #e2e8f0;
  color: #334155;
}

.priority-pill.urgent {
  background: #fee2e2;
  color: #b91c1c;
}

.priority-pill.high {
  background: #ffedd5;
  color: #c2410c;
}

.display-no-qr {
  flex-shrink: 0;
  font-size: 0.8rem;
  color: #94a3b8;
  text-align: center;
  max-width: 5.5rem;
}

.muted {
  color: #64748b;
}

.error {
  color: #b91c1c;
}

@media (min-width: 1200px) {
  .department-display {
    padding: 32px 40px 40px;
  }

  .display-row-name {
    font-size: 1.15rem;
  }
}
</style>
