<template>
  <div class="activity-issues-tab">
    <ActivityTabHeader :title="t('components.activityIssuesTab.title')">
      <template #actions>
        <button
          v-if="canCreate"
          type="button"
          class="btn-primary btn-sm"
          @click="$emit('open-wizard')"
        >
          {{ t('components.activityIssuesTab.createReport') }}
        </button>
      </template>
    </ActivityTabHeader>
    <ActivityTabPanelShell
      :loading="!reportsReady"
      :loading-message="t('components.activityIssuesTab.loading')"
      loading-class="activity-issues-loading"
    >
      <p v-if="readOnlyHint" class="batch-field-hint activity-issues-readonly-hint">
        {{ t('components.activityIssuesTab.readOnlyBeforeAtEvent') }}
      </p>
      <p v-if="reportsSorted.length === 0" class="text-muted">{{ t('components.activityIssuesTab.empty') }}</p>
      <div v-else class="issues-list">
        <div
          v-for="r in reportsSorted"
          :key="r.id"
          class="issue-card"
          :class="{ resolved: r.resolved }"
        >
          <div class="issue-header">
            <span class="issue-type-badge" :class="r.type">{{ r.type_label || r.type }}</span>
            <span v-if="r.material_name" class="issue-material">{{ r.material_name }}</span>
            <span class="issue-qty">×{{ r.quantity }}</span>
            <span class="issue-time">{{ formatDateTime(r.reported_at) }}</span>
          </div>
          <div v-if="r.description" class="issue-description">{{ r.description }}</div>
          <div v-if="r.resolved" class="issue-footer">
            <span class="issue-resolved">{{
              r.resolved_at
                ? t('components.activityIssuesTab.resolvedWithDate', { at: formatDateTime(r.resolved_at) })
                : t('components.activityIssuesTab.resolved')
            }}</span>
          </div>
        </div>
      </div>
    </ActivityTabPanelShell>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityIssueReportRow } from '@/api/activities'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import ActivityTabPanelShell from '@/components/activities/ActivityTabPanelShell.vue'

defineOptions({ name: 'ActivityIssuesTab' })

const props = defineProps<{
  activityId: string
  /** Vom Parent bereits geladen — kein eigener Fetch, kein Doppel-Spinner beim Tab-Wechsel */
  reports: ActivityIssueReportRow[]
  reportsReady: boolean
  canCreate: boolean
  readOnlyHint?: boolean
}>()

defineEmits<{
  'open-wizard': []
}>()

const { t } = useI18n()

const reportsSorted = computed(() =>
  [...props.reports]
    .filter((r) => r.type !== 'consumption')
    .sort((a, b) => {
      const ta = new Date(a.reported_at).getTime()
      const tb = new Date(b.reported_at).getTime()
      if (tb !== ta) return tb - ta
      return b.id.localeCompare(a.id)
    }),
)

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('de-CH', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<style scoped>
@import '@/styles/views/activities/detail-workflow.css';
</style>
