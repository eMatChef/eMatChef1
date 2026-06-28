<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getActivityHistory, type ActivityHistoryEntryRow } from '@/api/activities'
import { useActivityTabLoad } from '@/composables/useActivityTabLoad'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import ActivityTabPanelShell from '@/components/activities/ActivityTabPanelShell.vue'
import {
  historyEntrySummaryLines,
  historyEntryTitle,
  historyStatusChange,
} from '@/components/activities/activityHistoryDisplay'
import {
  aggregatePackHistoryEntries,
  aggregatedPackHistoryTitle,
  formatHistoryTimeRange,
  packMoveSummaryLine,
  type ActivityHistoryDisplayRow,
} from '@/components/activities/activityPackHistoryDisplay'
import { formatUserNicknameFirstNameLastName } from '@/utils/userAvatar'

defineOptions({ name: 'ActivityHistoryTab' })

const props = defineProps<{
  activityId: string
}>()

const { t, te, locale } = useI18n()
const { showFullLoading, isRefreshing, resetTabLoad, withTabLoad } = useActivityTabLoad()
const error = ref<string | null>(null)
const entries = ref<ActivityHistoryEntryRow[]>([])
const filterPackOnly = ref(false)
const expandedGroupIds = ref<Set<string>>(new Set())

async function load(opts?: { forceFull?: boolean }) {
  await withTabLoad(async () => {
    error.value = null
    try {
      const rows = await getActivityHistory(props.activityId)
      entries.value = [...rows].sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
      )
    } catch {
      error.value = t('activities.history.loadFailed')
      entries.value = []
    }
  }, opts)
}

watch(
  () => props.activityId,
  (activityId, prevActivityId) => {
    if (prevActivityId != null && activityId !== prevActivityId) {
      resetTabLoad()
    }
    void load()
  },
  { immediate: true },
)

const filteredEntries = computed(() => {
  if (!filterPackOnly.value) return entries.value
  return entries.value.filter((e) =>
    ['pack_move', 'pack_moveback', 'pack_container_bulk', 'pack_crate_check'].includes(e.action),
  )
})

const displayRows = computed((): ActivityHistoryDisplayRow[] =>
  aggregatePackHistoryEntries(filteredEntries.value),
)

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

function userLabel(e: ActivityHistoryEntryRow): string {
  if (!e.user) return ''
  return formatUserNicknameFirstNameLastName(e.user)
}

function groupId(row: ActivityHistoryDisplayRow & { kind: 'aggregated' }): string {
  return row.entries.map((e) => e.id).join('-')
}

function isGroupExpanded(row: ActivityHistoryDisplayRow & { kind: 'aggregated' }): boolean {
  return expandedGroupIds.value.has(groupId(row))
}

function toggleGroup(row: ActivityHistoryDisplayRow & { kind: 'aggregated' }): void {
  const id = groupId(row)
  const next = new Set(expandedGroupIds.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  expandedGroupIds.value = next
}

function aggregatedUserLabel(row: ActivityHistoryDisplayRow & { kind: 'aggregated' }): string {
  return userLabel(row.entries[0])
}
</script>

<template>
  <div class="activity-history-tab">
    <ActivityTabHeader :title="t('activities.detail.sectionHistory')" />
    <ActivityTabPanelShell
      :loading="showFullLoading"
      :refreshing="isRefreshing"
      :loading-message="t('activities.history.loading')"
      loading-class="activity-history-loading"
    >
      <div v-if="!error && entries.length > 0" class="activity-history-filters">
        <button
          type="button"
          class="activity-history-filter-chip"
          :class="{ 'activity-history-filter-chip--active': !filterPackOnly }"
          @click="filterPackOnly = false"
        >
          {{ t('activities.history.filterAll') }}
        </button>
        <button
          type="button"
          class="activity-history-filter-chip"
          :class="{ 'activity-history-filter-chip--active': filterPackOnly }"
          @click="filterPackOnly = true"
        >
          {{ t('activities.history.filterPack') }}
        </button>
      </div>

      <p v-if="error" class="text-muted">{{ error }}</p>
      <p v-else-if="displayRows.length === 0" class="text-muted">{{ t('activities.history.empty') }}</p>
      <ul v-else class="activity-history-list">
        <li
          v-for="(row, rowIndex) in displayRows"
          :key="row.kind === 'single' ? row.entry.id : `agg-${rowIndex}`"
          class="activity-history-item"
        >
          <template v-if="row.kind === 'single'">
            <div class="activity-history-item-head">
              <span class="history-dot" :class="row.entry.action" aria-hidden="true"></span>
              <strong class="activity-history-action">{{ titleFor(row.entry) }}</strong>
              <span class="text-muted activity-history-when">{{ formatWhen(row.entry.created_at) }}</span>
            </div>
            <p v-if="userLabel(row.entry)" class="activity-history-user text-muted">
              {{ t('activities.history.userBy', { name: userLabel(row.entry) }) }}
            </p>
            <p v-if="statusChange(row.entry)" class="activity-history-status-change text-muted">
              <span class="activity-history-field">{{ t('common.status') }}:</span>
              <span class="history-status-old">{{ statusChange(row.entry)!.old }}</span>
              <span class="history-status-arrow" aria-hidden="true">→</span>
              <span class="history-status-new">{{ statusChange(row.entry)!.new }}</span>
            </p>
            <ul v-else-if="summaryLines(row.entry).length > 0" class="activity-history-lines">
              <li v-for="(line, idx) in summaryLines(row.entry)" :key="idx" class="text-muted">
                {{ line }}
              </li>
            </ul>
          </template>

          <template v-else>
            <button
              type="button"
              class="activity-history-aggregate"
              :aria-expanded="isGroupExpanded(row)"
              @click="toggleGroup(row)"
            >
              <div class="activity-history-item-head">
                <span class="history-dot pack_move" aria-hidden="true"></span>
                <strong class="activity-history-action">
                  {{ aggregatedPackHistoryTitle(row.entries, t, te) }}
                </strong>
                <span class="text-muted activity-history-when">
                  {{ formatHistoryTimeRange(row.entries, locale) }}
                </span>
              </div>
              <p v-if="aggregatedUserLabel(row)" class="activity-history-user text-muted">
                {{ t('activities.history.userBy', { name: aggregatedUserLabel(row) }) }}
              </p>
              <span class="activity-history-aggregate-hint text-muted">
                {{
                  isGroupExpanded(row)
                    ? t('activities.history.packAggregateCollapse')
                    : t('activities.history.packAggregateExpand')
                }}
              </span>
            </button>
            <ul v-if="isGroupExpanded(row)" class="activity-history-lines activity-history-lines--nested">
              <li
                v-for="entry in row.entries"
                :key="entry.id"
                class="text-muted activity-history-nested-line"
              >
                <span class="activity-history-nested-time">{{ formatWhen(entry.created_at) }}</span>
                {{ packMoveSummaryLine(entry, t, te) }}
              </li>
            </ul>
          </template>
        </li>
      </ul>
    </ActivityTabPanelShell>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/detail-workflow.css';

.activity-inline-loading .spinner-sm {
  width: 22px;
  height: 22px;
  border-width: 2px;
}

.activity-history-tab {
  max-width: 720px;
}

.activity-history-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.activity-history-filter-chip {
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 999px;
  padding: 6px 12px;
  font-size: 13px;
  cursor: pointer;
}

.activity-history-filter-chip--active {
  border-color: #2563eb;
  background: #eff6ff;
  color: #1d4ed8;
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

.activity-history-lines--nested {
  margin-top: 8px;
  padding-left: 8px;
  border-left: 2px solid #e2e8f0;
}

.activity-history-nested-line {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.activity-history-nested-time {
  flex: 0 0 auto;
  font-variant-numeric: tabular-nums;
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

.history-dot.pack_crate_check,
.history-dot.pack_move,
.history-dot.pack_moveback,
.history-dot.pack_container_bulk {
  background: #3b82f6;
}

.history-dot.created {
  background: #10b981;
}

.history-dot.updated {
  background: #64748b;
}

.history-dot.material_changed,
.history-dot.material_items_changed {
  background: #6366f1;
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

.activity-history-aggregate {
  display: block;
  width: 100%;
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.activity-history-aggregate-hint {
  display: block;
  margin: 4px 0 0 20px;
  font-size: 12px;
}
</style>
