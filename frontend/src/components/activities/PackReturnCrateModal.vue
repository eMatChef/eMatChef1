<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'

export type ReturnCratePartitionView = {
  shellQty: number
  shellIsExtra: boolean
  shellMaterialName: string
  extraLines: ActivityPackContainerItem[]
  standardLines: ActivityPackContainerItem[]
  hasWarehouseTemplate: boolean
}

export type ReturnCrateLineEdit = {
  id: string
  kind: 'shell' | 'line'
  containerItemId?: string
  materialItemId: string | null
  materialName: string
  max: number
  issued: number
  included: boolean
  qty: number
  isExtra: boolean
  isConsumable: boolean
  consumptionDone: boolean
  consumptionOpen: number
}

type ReturnCrateLineSection = {
  key: string
  titleKey: string
  noteKey?: string
  lines: ReturnCrateLineEdit[]
}

const props = defineProps<{
  open: boolean
  containerLabel: string
  contentsLoading: boolean
  contentsError: boolean
  noLinkedBatch: boolean
  partition: ReturnCratePartitionView
  lines: ReturnCrateLineEdit[]
  notTakenReminders: ActivityIssueReportRow[]
  notTakenLine: (r: ActivityIssueReportRow) => string
  canReportIssues: boolean
  canReportConsumption: boolean
  submitting: boolean
  submitDisabled: boolean
}>()

const emit = defineEmits<{
  cancel: []
  submit: []
  'update:lines': [lines: ReturnCrateLineEdit[]]
  'report-loss': [materialItemId: string, qty: number]
  'report-repair': [materialItemId: string, qty: number]
  'report-consumption': [materialItemId: string, materialName: string]
}>()

const { t } = useI18n()

const lineSections = computed((): ReturnCrateLineSection[] => {
  const sections: ReturnCrateLineSection[] = []
  const shell = props.lines.filter((l) => l.kind === 'shell')
  const extra = props.lines.filter((l) => l.kind === 'line' && l.isExtra)
  const standard = props.lines.filter((l) => l.kind === 'line' && !l.isExtra)
  if (extra.length > 0) {
    sections.push({
      key: 'extra',
      titleKey: 'activities.packList.returnCrateModalExtraSection',
      noteKey: 'activities.packList.returnCrateModalExtraNote',
      lines: extra,
    })
  }
  if (standard.length > 0) {
    sections.push({
      key: 'standard',
      titleKey: 'activities.packList.returnCrateModalStandardSection',
      lines: standard,
    })
  }
  if (shell.length > 0) {
    sections.push({
      key: 'shell',
      titleKey: 'activities.packList.returnCrateModalShellSection',
      lines: shell,
    })
  }
  return sections
})

function patchLine(id: string, patch: Partial<ReturnCrateLineEdit>): void {
  emit(
    'update:lines',
    props.lines.map((line) => (line.id === id ? { ...line, ...patch } : line)),
  )
}

function onIncludedChange(line: ReturnCrateLineEdit, included: boolean): void {
  const nextQty = included && line.qty < 1 ? line.max : line.qty
  patchLine(line.id, { included, qty: included ? Math.min(Math.max(1, nextQty), line.max) : line.qty })
}

function clampQty(line: ReturnCrateLineEdit, raw: number): number {
  if (!Number.isFinite(raw)) return line.max
  return Math.min(line.max, Math.max(0, Math.floor(raw)))
}

function bumpQty(line: ReturnCrateLineEdit, delta: number): void {
  patchLine(line.id, { qty: clampQty(line, line.qty + delta) })
}

function onQtyInput(line: ReturnCrateLineEdit, event: Event): void {
  const raw = Number((event.target as HTMLInputElement).value)
  patchLine(line.id, { qty: clampQty(line, raw) })
}

function missingQty(line: ReturnCrateLineEdit): number {
  if (!line.included) return 0
  return Math.max(0, line.max - line.qty)
}

function showMissingActions(line: ReturnCrateLineEdit): boolean {
  if (line.isConsumable || missingQty(line) < 1 || !line.materialItemId) return false
  return props.canReportIssues
}

function lineQtyHint(line: ReturnCrateLineEdit): string {
  if (line.kind === 'shell') {
    return t('activities.packList.returnCrateModalStillQty', { n: line.max })
  }
  return t('activities.packList.returnCrateModalLineQty', {
    still: line.max,
    issued: line.issued,
  })
}

function consumableLineHint(line: ReturnCrateLineEdit): string {
  if (line.consumptionDone) {
    return t('activities.packList.returnCrateModalConsumptionDone')
  }
  return t('activities.packList.returnCrateModalConsumptionOpen', { n: line.consumptionOpen })
}
</script>

<template>
  <PackWorkflowModal :open="open" size="lg" @cancel="emit('cancel')">
    <template #title>{{
      t('activities.packList.returnCrateModalTitle', { label: containerLabel })
    }}</template>
    <template #intro>
      <p class="pack-modal-hint text-muted">{{ t('activities.packList.returnCrateModalIntro') }}</p>
    </template>

    <div v-if="notTakenReminders.length > 0" class="pack-not-taken-reminder" role="status">
      <div class="pack-not-taken-reminder__title">{{ t('activities.packList.notTakenReminderTitle') }}</div>
      <p class="pack-not-taken-reminder__intro text-muted">
        {{ t('activities.packList.notTakenReminderIntro') }}
      </p>
      <ul class="pack-not-taken-reminder__ul">
        <li v-for="r in notTakenReminders" :key="'ret-nt-' + r.id">{{ notTakenLine(r) }}</li>
      </ul>
    </div>

    <div v-if="contentsLoading" class="pack-modal-loading text-muted">
      {{ t('activities.packList.returnCrateModalLoadingContents') }}
    </div>
    <template v-else>
      <p v-if="contentsError" class="pack-modal-hint pack-modal-hint--warn">
        {{ t('activities.packList.returnCrateModalContentsError') }}
      </p>
      <p v-if="noLinkedBatch" class="pack-modal-hint text-muted">
        {{ t('activities.packList.returnCrateModalNoBatchHint') }}
      </p>
      <p
        v-else-if="partition.hasWarehouseTemplate && partition.extraLines.length > 0"
        class="pack-modal-hint pack-modal-hint--warn"
      >
        {{ t('activities.packList.returnCrateModalExtraHint') }}
      </p>

      <div v-for="section in lineSections" :key="section.key" class="pack-return-crate-block">
        <h4 class="pack-return-crate-subtitle">{{ t(section.titleKey) }}</h4>
        <p v-if="section.noteKey" class="pack-return-crate-note text-muted">{{ t(section.noteKey) }}</p>
        <ul class="pack-return-crate-list">
          <li
            v-for="line in section.lines"
            :key="'ret-' + section.key + '-' + line.id"
            class="pack-return-crate-line-row"
            :class="{ 'pack-return-crate-line-row--done': line.isConsumable && line.consumptionDone }"
          >
            <template v-if="line.isConsumable">
              <div class="pack-return-crate-consumable-main">
                <span class="pack-return-crate-line-name">{{ line.materialName }}</span>
                <span class="pack-return-crate-line-qty text-muted">{{ lineQtyHint(line) }}</span>
                <span v-if="line.isExtra" class="pack-return-crate-badge">{{
                  t('activities.packList.returnCrateModalBadgeExtra')
                }}</span>
              </div>
              <p class="pack-return-crate-consumable-hint text-muted">{{ consumableLineHint(line) }}</p>
              <div v-if="line.consumptionDone" class="pack-return-crate-done-badge" role="status">
                {{ t('activities.packList.returnCrateModalConsumptionDoneBadge') }}
              </div>
              <button
                v-else-if="canReportConsumption && line.materialItemId"
                type="button"
                class="btn btn-sm btn-primary pack-return-crate-consumption-btn"
                @click="emit('report-consumption', line.materialItemId!, line.materialName)"
              >
                {{ t('activities.packList.returnCrateModalBookConsumption') }}
              </button>
            </template>
            <template v-else>
              <label class="pack-return-crate-line-check">
                <input
                  :checked="line.included"
                  type="checkbox"
                  @change="onIncludedChange(line, ($event.target as HTMLInputElement).checked)"
                />
                <span class="pack-return-crate-line-name">{{ line.materialName }}</span>
              </label>
              <span class="pack-return-crate-line-qty text-muted">{{ lineQtyHint(line) }}</span>
              <span v-if="line.isExtra" class="pack-return-crate-badge">{{
                t('activities.packList.returnCrateModalBadgeExtra')
              }}</span>
              <div v-if="line.included" class="pack-return-crate-line-controls">
                <span class="pack-return-crate-return-label text-muted">{{
                  t('activities.packList.returnCrateModalReturnQty')
                }}</span>
                <div class="adjust-qty-row">
                  <button type="button" class="btn-qty" :disabled="line.qty <= 0" @click="bumpQty(line, -1)">
                    −
                  </button>
                  <input
                    :value="line.qty"
                    type="number"
                    min="0"
                    :max="line.max"
                    class="form-input adjust-qty-input"
                    @input="onQtyInput(line, $event)"
                  />
                  <button type="button" class="btn-qty" :disabled="line.qty >= line.max" @click="bumpQty(line, 1)">
                    +
                  </button>
                </div>
              </div>
              <div v-if="showMissingActions(line)" class="pack-return-crate-missing">
                <span class="pack-return-crate-missing-hint text-muted">{{
                  t('activities.packList.returnCrateModalMissingQty', { n: missingQty(line) })
                }}</span>
                <button
                  type="button"
                  class="btn-issue-quick btn-issue-loss"
                  @click="emit('report-loss', line.materialItemId!, missingQty(line))"
                >
                  {{ t('activities.common.issueLoss') }}
                </button>
                <button
                  type="button"
                  class="btn-issue-quick btn-issue-repair"
                  @click="emit('report-repair', line.materialItemId!, missingQty(line))"
                >
                  {{ t('activities.common.issueRepair') }}
                </button>
              </div>
            </template>
          </li>
        </ul>
      </div>

      <p
        v-if="
          partition.shellQty < 1 &&
          partition.extraLines.length < 1 &&
          partition.standardLines.length < 1
        "
        class="pack-modal-hint text-muted"
      >
        {{ t('activities.packList.returnCrateModalEmptyLines') }}
      </p>

      <p v-if="lines.length > 0" class="pack-modal-hint pack-modal-hint--sm text-muted">
        {{ t('activities.packList.returnCrateModalLineHint') }}
      </p>
    </template>

    <template #footer>
      <PackModalFooter
        :primary-label="t('activities.packList.returnCrateModalSubmit')"
        :primary-disabled="submitDisabled"
        :cancel-disabled="submitting"
        @cancel="emit('cancel')"
        @primary="emit('submit')"
      />
    </template>
  </PackWorkflowModal>
</template>

