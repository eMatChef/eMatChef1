<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getActivityHistory, type ActivityHistoryEntryRow } from '@/api/activities'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import {
  historyEntrySummaryLines,
  historyEntryTitle,
  historyStatusChange,
} from '@/components/activities/activityHistoryDisplay'

defineOptions({ name: 'ActivityHistoryTab' })

const props = defineProps<{
  activityId: string
}>()

const { t, te, locale } = useI18n()
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

function formatWhen(iso: string): string {
  try {
    return new Date(iso).toLocaleString(locale.value, { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}

function titleFor(e: ActivityHistoryEntryRow): string {
  return historyEntryTitle(e, t, te)
}

function summaryLines(e: ActivityHistoryEntryRow): string[] {
  return historyEntrySummaryLines(e, t, te)
}

function statusChange(e: ActivityHistoryEntryRow) {
  return historyStatusChange(e, t, te)
}
</script>

<template>
  <div class="activity-history-tab">
    <ActivityTabHeader :title="t('activities.detail.sectionHistory')" />
    <div class="section-card activity-tab-panel-card">
      <p v-if="loading" class="text-muted activity-inline-loading">
        <span class="spinner spinner-sm"></span>
        {{ t('activities.history.loading') }}
      </p>
      <p v-else-if="error" class="text-muted">{{ error }}</p>
      <p v-else-if="entries.length === 0" class="text-muted">{{ t('activities.history.empty') }}</p>
      <ul v-else class="activity-history-list">
        <li v-for="e in entries" :key="e.id" class="activity-history-item">
          <div class="activity-history-item-head">
            <span class="history-dot" :class="e.action" aria-hidden="true"></span>
            <strong class="activity-history-action">{{ titleFor(e) }}</strong>
            <span class="text-muted activity-history-when">{{ formatWhen(e.created_at) }}</span>
          </div>
          <p v-if="e.user?.name" class="activity-history-user text-muted">
            {{ t('activities.history.userBy', { name: e.user.name }) }}
          </p>
          <p v-if="statusChange(e)" class="activity-history-status-change text-muted">
            <span class="activity-history-field">{{ t('activities.history.fieldStatus') }}:</span>
            <span class="history-status-old">{{ statusChange(e)!.old }}</span>
            <span class="history-status-arrow" aria-hidden="true">→</span>
            <span class="history-status-new">{{ statusChange(e)!.new }}</span>
          </p>
          <ul v-else-if="summaryLines(e).length > 0" class="activity-history-lines">
            <li v-for="(line, idx) in summaryLines(e)" :key="idx" class="text-muted">
              {{ line }}
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-panel.css"></style>
<style scoped>
@import '@/styles/views/activities/detail-workflow.css';

.activity-history-tab {
  max-width: 720px;
}

.activity-history-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.activity-history-item {
  padding: 12px 0;
  border-bottom: 1px solid #e2e8f0;
}

.activity-history-item:last-child {
  border-bottom: none;
}

.activity-history-item-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px 12px;
}

.activity-history-action {
  font-size: 15px;
}

.activity-history-when {
  margin-left: auto;
  font-size: 12px;
}

.activity-history-user {
  margin: 4px 0 0 20px;
  font-size: 13px;
}

.activity-history-lines {
  margin: 6px 0 0 20px;
  padding: 0;
  list-style: none;
  font-size: 13px;
  line-height: 1.45;
}

.activity-history-lines li + li {
  margin-top: 4px;
}

.history-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #94a3b8;
  flex-shrink: 0;
  margin-top: 0.35em;
}

.history-dot.status_changed {
  background: #f59e0b;
}

.history-dot.pack_crate_check {
  background: #3b82f6;
}

.history-dot.created {
  background: #10b981;
}

.history-dot.updated {
  background: #64748b;
}

.activity-history-status-change {
  margin: 6px 0 0 20px;
  font-size: 13px;
}

.activity-history-field {
  margin-right: 4px;
}

.history-status-old {
  color: #b91c1c;
}

.history-status-new {
  color: #15803d;
  font-weight: 600;
}

.history-status-arrow {
  margin: 0 6px;
  color: #64748b;
}
</style>
