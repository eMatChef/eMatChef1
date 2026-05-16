<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getActivityHistory, type ActivityHistoryEntryRow } from '@/api/activities'

defineOptions({ name: 'ActivityHistoryTab' })

const props = defineProps<{
  activityId: string
}>()

const { t, locale } = useI18n()
const loading = ref(true)
const error = ref<string | null>(null)
const entries = ref<ActivityHistoryEntryRow[]>([])

async function load() {
  loading.value = true
  error.value = null
  try {
    const rows = await getActivityHistory(props.activityId)
    entries.value = [...rows].sort(
      (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    )
  } catch {
    error.value = t('activities.history.loadFailed')
    entries.value = []
  } finally {
    loading.value = false
  }
}

watch(() => props.activityId, () => void load(), { immediate: true })

const crateCheckEntries = computed(() => entries.value.filter((e) => e.action === 'pack_crate_check'))

function formatWhen(iso: string): string {
  try {
    return new Date(iso).toLocaleString(locale.value, { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}

function actionLabel(action: string): string {
  const key = `activities.history.action_${action}`
  const tr = t(key)
  return tr === key ? action : tr
}

function entryTitle(e: ActivityHistoryEntryRow): string {
  if (e.action === 'pack_crate_check') {
    const result = String(e.changes?.result ?? '').trim()
    const label =
      (e.changes?.shell_material_name as string | undefined) ||
      (e.changes?.container_batch_id as string | undefined) ||
      ''
    return t('activities.history.crateCheckTitle', {
      result: result === 'ok' ? t('activities.history.crateCheckOk') : t('activities.history.crateCheckIncomplete'),
      label: label || '—',
    })
  }
  return actionLabel(e.action)
}

function crateCheckLines(e: ActivityHistoryEntryRow): Array<Record<string, unknown>> {
  const raw = e.changes?.lines
  return Array.isArray(raw) ? (raw as Array<Record<string, unknown>>) : []
}

function lineSummary(ln: Record<string, unknown>): string {
  const name = String(ln.material_name ?? t('activities.common.material'))
  const status = String(ln.status ?? 'ok')
  const counted = ln.counted_qty != null ? Number(ln.counted_qty) : null
  const expected = ln.expected_qty != null ? Number(ln.expected_qty) : null
  const rq = Math.max(0, Number(ln.replenish_qty ?? 0) || 0)
  const parts = [name]
  if (counted != null && expected != null) {
    parts.push(t('activities.history.lineIstSoll', { ist: counted, soll: expected }))
  }
  if (status !== 'ok') {
    parts.push(t(`activities.packList.crateCheckStatus_${status}`, { n: rq || 1 }))
  } else if (rq > 0) {
    parts.push(t('activities.packList.crateCheckReplenishedBadge', { n: rq }))
  }
  return parts.join(' · ')
}
</script>

<template>
  <div class="activity-history-tab">
    <div class="section-card">
      <h2 class="section-title">{{ t('activities.detail.sectionHistory') }}</h2>
      <p v-if="loading" class="text-muted activity-inline-loading">
        <span class="spinner spinner-sm"></span>
        {{ t('activities.history.loading') }}
      </p>
      <p v-else-if="error" class="text-muted">{{ error }}</p>
      <p v-else-if="entries.length === 0" class="text-muted">{{ t('activities.history.empty') }}</p>
      <template v-else>
        <section v-if="crateCheckEntries.length > 0" class="activity-history-block">
          <h3 class="activity-history-subtitle">{{ t('activities.history.crateChecksSection') }}</h3>
          <ul class="activity-history-list">
            <li v-for="e in crateCheckEntries" :key="e.id" class="activity-history-item">
              <div class="activity-history-item-head">
                <span class="history-dot pack_crate_check" aria-hidden="true"></span>
                <strong>{{ entryTitle(e) }}</strong>
                <span class="text-muted activity-history-when">{{ formatWhen(e.created_at) }}</span>
                <span v-if="e.user?.name" class="text-muted"> — {{ e.user.name }}</span>
              </div>
              <ul v-if="crateCheckLines(e).length > 0" class="activity-history-lines">
                <li v-for="(ln, idx) in crateCheckLines(e)" :key="idx" class="text-muted">
                  {{ lineSummary(ln) }}
                </li>
              </ul>
            </li>
          </ul>
        </section>
        <section class="activity-history-block">
          <h3 class="activity-history-subtitle">{{ t('activities.history.allSection') }}</h3>
          <ul class="activity-history-list">
            <li v-for="e in entries" :key="'all-' + e.id" class="activity-history-item activity-history-item--compact">
              <span class="history-dot" :class="e.action" aria-hidden="true"></span>
              <span>{{ entryTitle(e) }}</span>
              <span class="text-muted activity-history-when">{{ formatWhen(e.created_at) }}</span>
            </li>
          </ul>
        </section>
      </template>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-panel.css"></style>
<style scoped>
.activity-history-tab {
  max-width: 720px;
}

.activity-history-block + .activity-history-block {
  margin-top: 20px;
}

.activity-history-subtitle {
  margin: 0 0 10px;
  font-size: 14px;
  font-weight: 600;
}

.activity-history-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.activity-history-item {
  padding: 10px 0;
  border-bottom: 1px solid #e2e8f0;
}

.activity-history-item:last-child {
  border-bottom: none;
}

.activity-history-item-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px 10px;
}

.activity-history-when {
  font-size: 12px;
}

.activity-history-lines {
  margin: 6px 0 0 22px;
  padding: 0;
  list-style: disc;
  font-size: 12px;
}

.activity-history-item--compact {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.history-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #94a3b8;
  flex-shrink: 0;
}
</style>
