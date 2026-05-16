<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityIssueReportRow } from '@/api/activities'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'
import PackRepackIssueRow from '@/components/activities/PackRepackIssueRow.vue'

const props = defineProps<{
  open: boolean
  mode: 'single' | 'move_all'
  issues: ActivityIssueReportRow[]
  lineReviews: Record<string, 'ok' | 'problem' | null>
  canSubmit: boolean
  hasProblemMarked: boolean
  materialHeading: (r: ActivityIssueReportRow) => string
  typeLabel: (r: ActivityIssueReportRow) => string
}>()

const emit = defineEmits<{
  cancel: []
  confirm: []
  'set-review': [issueId: string, status: 'ok' | 'problem']
}>()

const { t } = useI18n()

const title = computed(() =>
  props.mode === 'move_all'
    ? t('activities.packList.repackIssuesModalTitleMoveAll')
    : t('activities.packList.repackIssuesModalTitle'),
)

const submitLabel = computed(() =>
  props.mode === 'move_all'
    ? t('activities.packList.repackIssuesModalSubmitMoveAll')
    : t('activities.packList.repackIssuesModalSubmit'),
)
</script>

<template>
  <PackWorkflowModal :open="open" size="lg" @cancel="emit('cancel')">
    <template #title>{{ title }}</template>
    <template #intro>
      <p class="pack-modal-hint text-muted">{{ t('activities.packList.repackIssuesModalIntro') }}</p>
    </template>

    <ul class="pack-shell-forward-ul pack-repack-issues-modal-ul">
      <PackRepackIssueRow
        v-for="r in issues"
        :key="'repack-modal-' + r.id"
        :material-heading="materialHeading(r)"
        :type-label="typeLabel(r)"
        :quantity="r.quantity"
        :description="String(r.description ?? '')"
        :review-status="lineReviews[r.id] ?? null"
        :ok-title="t('activities.packList.shellForwardLineOkTitle')"
        :ok-aria-label="t('activities.packList.shellForwardLineOkAria', { name: materialHeading(r) })"
        :problem-title="t('activities.packList.shellForwardLineProblemTitle')"
        :problem-aria-label="t('activities.packList.shellForwardLineProblemAria', { name: materialHeading(r) })"
        @set-review="(st) => emit('set-review', r.id, st)"
      />
    </ul>
    <p v-if="hasProblemMarked" class="pack-shell-forward-submit-hint text-muted">
      {{ t('activities.packList.repackIssueMarkedProblemHint') }}
    </p>

    <template #footer>
      <PackModalFooter
        :primary-label="submitLabel"
        :primary-disabled="!canSubmit"
        @cancel="emit('cancel')"
        @primary="emit('confirm')"
      />
    </template>
  </PackWorkflowModal>
</template>
