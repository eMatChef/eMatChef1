<template>
  <div class="activity-issues-tab">
    <div class="section-card">
      <div class="activity-issues-head">
        <h2 class="section-title">{{ t('components.activityIssuesTab.title') }}</h2>
        <button
          v-if="canCreate"
          type="button"
          class="btn-primary btn-sm"
          @click="$emit('open-wizard')"
        >
          {{ t('components.activityIssuesTab.createReport') }}
        </button>
      </div>
      <p v-if="isLoading" class="activity-inline-loading">
        <span class="spinner spinner-sm"></span>
        <span>{{ t('components.activityIssuesTab.loading') }}</span>
      </p>
      <template v-else>
        <p v-if="reports.length === 0" class="text-muted">{{ t('components.activityIssuesTab.empty') }}</p>
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
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getActivityIssues, type ActivityIssueReportRow } from '@/api/activities'
import { useToast } from '@/composables/useToast'

defineOptions({ name: 'ActivityIssuesTab' })

const props = defineProps<{
  activityId: string
  /** Parent erhöht nach erfolgreicher Meldung → Liste neu */
  reloadToken?: number
  canCreate: boolean
}>()

defineEmits<{
  'open-wizard': []
}>()

const { t } = useI18n()
const toast = useToast()
const isLoading = ref(false)
const reports = ref<ActivityIssueReportRow[]>([])

const reportsSorted = computed(() =>
  [...reports.value]
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

async function load() {
  isLoading.value = true
  try {
    reports.value = await getActivityIssues(props.activityId)
  } catch {
    reports.value = []
    toast.error(t('components.activityIssuesTab.loadError'))
  } finally {
    isLoading.value = false
  }
}

watch(
  () => [props.activityId, props.reloadToken ?? 0] as const,
  () => {
    void load()
  },
  { immediate: true },
)
</script>

<style scoped>
@import '@/styles/views/activities/detail-workflow.css';

.activity-issues-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}
.activity-issues-head .section-title {
  margin: 0;
}
</style>
