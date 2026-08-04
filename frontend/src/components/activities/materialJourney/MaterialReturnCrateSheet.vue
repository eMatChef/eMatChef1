<script setup lang="ts">
import PackReturnCrateModal, {
  type ReturnCrateLineEdit,
} from '@/components/activities/PackReturnCrateModal.vue'
import type { ReturnCratePartitionView } from '@/components/activities/PackReturnCrateModal.vue'

defineProps<{
  open: boolean
  containerLabel: string
  partition: ReturnCratePartitionView
  lines: ReturnCrateLineEdit[]
  submitting: boolean
  submitDisabled: boolean
  canReportConsumption?: boolean
  canReportIssues?: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update:lines': [lines: ReturnCrateLineEdit[]]
  submit: []
  'report-consumption': [materialItemId: string, materialName: string]
  'report-loss': [materialItemId: string, qty: number]
  'report-repair': [materialItemId: string, qty: number]
  'report-extra': [materialItemId: string, qty: number, lineId: string]
}>()

function onCancel(): void {
  emit('update:open', false)
}
</script>

<template>
  <PackReturnCrateModal
    :open="open"
    :container-label="containerLabel"
    :contents-loading="false"
    :contents-error="false"
    :no-linked-batch="false"
    :partition="partition"
    :lines="lines"
    :not-taken-reminders="[]"
    :not-taken-line="() => ''"
    :can-report-issues="canReportIssues !== false"
    :can-report-consumption="canReportConsumption !== false"
    :submitting="submitting"
    :submit-disabled="submitDisabled"
    :searchable-materials="[]"
    @cancel="onCancel"
    @submit="emit('submit')"
    @update:lines="emit('update:lines', $event)"
    @report-consumption="(mid, name) => emit('report-consumption', mid, name)"
    @report-loss="(mid, qty) => emit('report-loss', mid, qty)"
    @report-repair="(mid, qty) => emit('report-repair', mid, qty)"
    @report-extra="(mid, qty, lineId) => emit('report-extra', mid, qty, lineId)"
  />
</template>
