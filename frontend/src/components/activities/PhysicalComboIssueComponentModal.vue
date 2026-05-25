<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import {
  flattenPhysicalComboIssueLines,
  type PhysicalComboIssueSelection,
} from '@/components/activities/physicalComboIssueFlow'
import { packMaterialDisplayName } from '@/components/activities/packMaterialDisplay'
import type { ActivityPackItem } from '@/api/activityPackItems'

const props = defineProps<{
  open: boolean
  loading?: boolean
  issueType: 'loss' | 'repair'
  shellPackItem: ActivityPackItem | null
  sections: PackCrateShellPeekSection[]
}>()

const emit = defineEmits<{
  cancel: []
  confirm: [selections: PhysicalComboIssueSelection[]]
}>()

const { t } = useI18n()

const lines = computed(() => flattenPhysicalComboIssueLines(props.sections))

const lineState = reactive<Record<string, { selected: boolean; qty: number }>>({})

function resetLineState() {
  for (const key of Object.keys(lineState)) {
    delete lineState[key]
  }
  for (const line of lines.value) {
    lineState[line.lineId] = { selected: false, qty: line.maxQty }
  }
}

watch(
  () => [props.open, lines.value.map((l) => l.lineId).join('|')] as const,
  ([open]) => {
    if (open) resetLineState()
  },
  { immediate: true },
)

const comboLabel = computed(() =>
  props.shellPackItem ? packMaterialDisplayName(props.shellPackItem) : '',
)

const issueTypeLabel = computed(() =>
  props.issueType === 'loss'
    ? t('activities.common.issueLoss')
    : t('activities.common.issueRepair'),
)

const selectedCount = computed(() =>
  lines.value.filter((l) => lineState[l.lineId]?.selected).length,
)

const canConfirm = computed(
  () => !props.loading && lines.value.length > 0 && selectedCount.value > 0,
)

function clampQty(lineId: string, raw: number, maxQty: number): number {
  const n = Math.floor(Number(raw))
  if (!Number.isFinite(n) || n < 1) return 1
  return Math.min(maxQty, n)
}

function onQtyInput(lineId: string, maxQty: number, event: Event) {
  const st = lineState[lineId]
  if (!st) return
  st.qty = clampQty(lineId, parseInt((event.target as HTMLInputElement).value, 10), maxQty)
}

function toggleLine(lineId: string, checked: boolean) {
  const st = lineState[lineId]
  if (!st) return
  st.selected = checked
}

function submit() {
  const selections: PhysicalComboIssueSelection[] = []
  for (const line of lines.value) {
    const st = lineState[line.lineId]
    if (!st?.selected) continue
    selections.push({
      materialItemId: line.materialItemId,
      quantity: clampQty(line.lineId, st.qty, line.maxQty),
    })
  }
  if (selections.length === 0) return
  emit('confirm', selections)
}
</script>

<template>
  <PackWorkflowModal :open="open" size="lg" @cancel="emit('cancel')">
    <template #title>
      {{
        t('activities.packList.physicalComboIssueModalTitle', {
          combo: comboLabel,
          issueType: issueTypeLabel,
        })
      }}
    </template>
    <template #intro>
      <p class="pack-modal-hint text-muted">
        {{ t('activities.packList.physicalComboIssueModalIntro', { issueType: issueTypeLabel }) }}
      </p>
      <p v-if="selectedCount > 1" class="pack-modal-hint text-muted">
        {{ t('activities.packList.physicalComboIssueModalMultiHint') }}
      </p>
    </template>

    <div v-if="loading" class="pack-modal-loading-row">
      <span class="spinner" aria-hidden="true" />
      <span>{{ t('activities.packList.physicalComboIssueModalLoading') }}</span>
    </div>
    <p v-else-if="lines.length === 0" class="text-muted">
      {{ t('activities.packList.physicalComboIssueModalEmpty') }}
    </p>
    <ul v-else class="pack-shell-forward-ul physical-combo-issue-modal-ul">
      <li
        v-for="line in lines"
        :key="line.lineId"
        class="physical-combo-issue-modal-line"
      >
        <label class="pack-modal-checkbox-row physical-combo-issue-modal-line__pick">
          <input
            type="checkbox"
            :checked="lineState[line.lineId]?.selected ?? false"
            @change="toggleLine(line.lineId, ($event.target as HTMLInputElement).checked)"
          />
          <span class="physical-combo-issue-modal-line__name">
            {{ line.materialName }}
            <span v-if="line.serialHint" class="text-muted physical-combo-issue-modal-line__serial">
              · {{ line.serialHint }}
            </span>
          </span>
        </label>
        <label
          v-if="lineState[line.lineId]?.selected"
          class="pack-modal-label physical-combo-issue-modal-line__qty"
        >
          <span>{{ t('activities.packList.physicalComboIssueModalQty') }}</span>
          <input
            :value="lineState[line.lineId]?.qty ?? 1"
            type="number"
            class="form-input"
            min="1"
            :max="line.maxQty"
            @input="onQtyInput(line.lineId, line.maxQty, $event)"
          />
        </label>
      </li>
    </ul>

    <template #footer>
      <PackModalFooter
        :primary-label="t('activities.packList.physicalComboIssueModalSubmit', { issueType: issueTypeLabel })"
        :primary-disabled="!canConfirm"
        @cancel="emit('cancel')"
        @primary="submit"
      />
    </template>
  </PackWorkflowModal>
</template>

<style scoped>
.physical-combo-issue-modal-ul {
  margin: 0;
  padding: 0;
  list-style: none;
}

.physical-combo-issue-modal-line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 16px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border-subtle, #e2e8f0);
}

.physical-combo-issue-modal-line:last-child {
  border-bottom: none;
}

.physical-combo-issue-modal-line__pick {
  flex: 1 1 200px;
  margin: 0;
}

.physical-combo-issue-modal-line__name {
  font-weight: 500;
}

.physical-combo-issue-modal-line__serial {
  font-weight: 400;
  font-size: 0.9em;
}

.physical-combo-issue-modal-line__qty {
  flex: 0 0 auto;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.physical-combo-issue-modal-line__qty .form-input {
  width: 4.5rem;
}

.pack-modal-loading-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 0;
}
</style>
