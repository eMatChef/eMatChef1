<template>
  <div class="department-display">
    <header class="display-header">
      <div class="display-header-main">
        <EmcLogoMark size="sm" />
        <div>
          <h1 class="display-title">{{ pageHeading }}</h1>
          <p v-if="!needsPin && displaySubtitle" class="display-subtitle">{{ displaySubtitle }}</p>
        </div>
      </div>
      <div v-if="!needsPin" class="display-header-meta">
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

    <section v-if="needsPin" class="display-pin-panel">
      <h2 class="display-pin-title">{{ t('display.pin.title') }}</h2>
      <p class="display-pin-hint">{{ t('display.pin.hint') }}</p>
      <form class="display-pin-form" @submit.prevent="submitPin">
        <input
          v-model="pinInput"
          type="text"
          class="display-pin-input"
          :placeholder="t('display.pin.placeholder')"
          autocomplete="off"
          autocapitalize="characters"
          spellcheck="false"
          maxlength="8"
          inputmode="text"
          :disabled="pinSubmitting"
          @input="onPinInput"
        />
        <p v-if="pinError" class="display-pin-error">{{ pinError }}</p>
        <button type="submit" class="display-pin-submit" :disabled="pinSubmitting || pinInput.length !== 8">
          {{ pinSubmitting ? t('display.pin.submitting') : t('display.pin.submit') }}
        </button>
      </form>
    </section>

    <template v-else>
      <p v-if="loading" class="display-status muted">{{ t('display.loading') }}</p>
      <p v-else-if="loadError" class="display-status error">{{ loadError }}</p>

      <div v-else-if="!showActivities && !showWorkshop && !showStatistics" class="display-status muted">
        {{ t('display.noPanelsEnabled') }}
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
                  <span class="status-pill workshop" :class="item.status">{{ item.status_label }}</span>
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
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import type { DisplayActivityRow, DisplayStatistics, DisplayWorkshopTicketRow } from '@/api/display'
import {
  authenticatePublicDisplay,
  getPublicDisplayData,
  getPublicDisplaySession,
} from '@/api/displayScreens'
import { resolveActivityPublicUrl, resolveWorkshopPublicUrl } from '@/utils/publicQrUrl'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'

const PIN_CHARSET = /[^23456789ABCDEFGHJKLMNPQRSTUVWXYZ]/g

const REFRESH_MS = 60_000
const PRIORITY_ORDER: Record<string, number> = { urgent: 0, high: 1, normal: 2, low: 3 }

const route = useRoute()
const { t, te, locale } = useI18n()

const publicId = computed(() => String(route.params.publicId || '').trim())
const needsPin = ref(true)
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

let refreshTimer: ReturnType<typeof setInterval> | null = null
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
    .filter((ticket) => !filterByStatus || statusSet.has(ticket.status))
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
  const counts = statistics.value?.workshop_by_status
  if (!counts) return []
  return Object.entries(counts).map(([status, count]) => ({
    status,
    count,
    label: workshopStatusLabel(status),
  }))
})

function workshopStatusLabel(status: string): string {
  const key = `workshop.status.${status}`
  return te(key) ? t(key) : status
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

function onPinInput() {
  pinInput.value = pinInput.value.toUpperCase().replace(PIN_CHARSET, '').slice(0, 8)
  pinError.value = null
}

async function submitPin() {
  const id = publicId.value
  if (!id || pinInput.value.length !== 8) return

  pinSubmitting.value = true
  pinError.value = null
  try {
    await authenticatePublicDisplay(id, pinInput.value)
    needsPin.value = false
    pinInput.value = ''
    await load()
    startTimers()
  } catch {
    pinError.value = t('display.pin.invalid')
  } finally {
    pinSubmitting.value = false
  }
}

async function checkSession(): Promise<boolean> {
  const id = publicId.value
  if (!id) return false
  try {
    const session = await getPublicDisplaySession(id)
    return session.authenticated === true
  } catch {
    return false
  }
}

async function load() {
  const id = publicId.value
  if (!id) {
    loadError.value = t('display.errorNoScreen')
    loading.value = false
    return
  }

  loading.value = true
  loadError.value = null
  try {
    const data = await getPublicDisplayData(id)
    activities.value = data.activities
    workshopTickets.value = data.workshopTickets
    departmentName.value = data.department_name || ''
    screenName.value = data.screen_name || ''
    subtitleText.value = data.subtitle_text ?? null
    showActivities.value = data.show_activities !== false
    showWorkshop.value = data.show_workshop !== false
    showStatistics.value = data.show_statistics === true
    allowedActivityTypes.value = data.activity_types?.length ? data.activity_types : []
    allowedActivityStatuses.value = data.activity_statuses?.length ? data.activity_statuses : []
    allowedWorkshopStatuses.value = data.workshop_statuses?.length ? data.workshop_statuses : []
    statistics.value = data.statistics ?? null
  } catch (err: unknown) {
    const status = (err as { response?: { status?: number } })?.response?.status
    if (status === 401) {
      needsPin.value = true
      stopTimers()
      return
    }
    console.error('display load failed', err)
    loadError.value = t('display.errorLoad')
  } finally {
    loading.value = false
  }
}

async function bootstrap() {
  const id = publicId.value
  if (!id) {
    needsPin.value = true
    pinError.value = t('display.errorNoScreen')
    return
  }

  const authenticated = await checkSession()
  needsPin.value = !authenticated
  if (authenticated) {
    await load()
    startTimers()
  }
}

function startTimers() {
  stopTimers()
  updateClock()
  clockTimer = setInterval(updateClock, 30_000)
  refreshTimer = setInterval(() => {
    void load()
  }, REFRESH_MS)
}

function stopTimers() {
  if (clockTimer) {
    clearInterval(clockTimer)
    clockTimer = null
  }
  if (refreshTimer) {
    clearInterval(refreshTimer)
    refreshTimer = null
  }
}

onMounted(() => {
  document.addEventListener('fullscreenchange', onFullscreenChange)
  void bootstrap()
})

onBeforeUnmount(() => {
  document.removeEventListener('fullscreenchange', onFullscreenChange)
  stopTimers()
})

watch(publicId, () => {
  stopTimers()
  needsPin.value = true
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
  padding: 28px 32px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 8px 32px rgba(15, 23, 42, 0.08);
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

.display-pin-input {
  font-size: 1.5rem;
  letter-spacing: 0.35em;
  text-align: center;
  padding: 14px 16px;
  border: 2px solid #cbd5e1;
  border-radius: 10px;
  font-weight: 700;
  text-transform: uppercase;
}

.display-pin-input:focus {
  outline: none;
  border-color: #3b82f6;
}

.display-pin-error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}

.display-pin-submit {
  padding: 12px 16px;
  border: none;
  border-radius: 10px;
  background: #2563eb;
  color: #fff;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.display-pin-submit:disabled {
  opacity: 0.55;
  cursor: not-allowed;
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
  margin-bottom: 6px;
  word-break: break-word;
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
