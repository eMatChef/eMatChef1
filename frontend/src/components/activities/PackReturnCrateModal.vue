<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'
import PackShellCheckToggle from '@/components/activities/PackShellCheckToggle.vue'
import {
  formatReturnCrateLineMeta,
  returnCrateLineInputCap,
  returnCrateLineMissingQty,
  returnCrateLineSurplusQty,
} from '@/utils/materialJourneyReturnCrateLineMeta'
import '@/styles/views/activities/pack-shell-combo.css'

export type ReturnCratePartitionView = {
  shellQty: number
  shellIsExtra: boolean
  shellMaterialName: string
  extraLines: ActivityPackContainerItem[]
  standardLines: ActivityPackContainerItem[]
  hasWarehouseTemplate: boolean
}

export type ReturnCrateSearchMaterial = {
  id: string
  name: string
}

export type ReturnCrateLinePlacement = 'in_crate' | 'loose' | 'shell' | 'added'

export type ReturnCrateLineEdit = {
  id: string
  kind: 'shell' | 'line'
  placement: ReturnCrateLinePlacement
  containerItemId?: string
  materialItemId: string | null
  materialName: string
  /** Erwartete Menge in der Kiste (Lager-Vorlage / gepackt). */
  expectedQty: number
  /** Bestellt / gebucht (Anzeige). */
  ordered: number
  consumed: number
  loss: number
  repair: number
  max: number
  issued: number
  returnedAlready: number
  included: boolean
  qty: number
  isExtra: boolean
  isConsumable: boolean
  /** @deprecated Retour-UI nutzt Shortfall-Aktionen statt separatem Verbrauch-Schritt. */
  consumptionDone: boolean
  /** @deprecated */
  consumptionOpen: number
  /** Bereits vollständig retourniert — nur Anzeige. */
  isDone: boolean
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
  searchableMaterials: ReturnCrateSearchMaterial[]
}>()

const emit = defineEmits<{
  cancel: []
  submit: []
  'update:lines': [lines: ReturnCrateLineEdit[]]
  'add-material': [materialItemId: string]
  'report-loss': [materialItemId: string, qty: number]
  'report-repair': [materialItemId: string, qty: number]
  'report-consumption': [materialItemId: string, materialName: string]
  'report-extra': [materialItemId: string, qty: number, lineId: string]
}>()

const { t } = useI18n()

const materialSearch = ref('')

const lineSections = computed((): ReturnCrateLineSection[] => {
  const sections: ReturnCrateLineSection[] = []
  const inCrate = props.lines.filter((l) => l.placement === 'in_crate')
  const added = props.lines.filter((l) => l.placement === 'added')
  const loose = props.lines.filter((l) => l.placement === 'loose')
  const shell = props.lines.filter((l) => l.placement === 'shell')

  if (inCrate.length > 0) {
    sections.push({
      key: 'in_crate',
      titleKey: props.partition.hasWarehouseTemplate
        ? 'activities.packList.returnCrateModalStandardSection'
        : 'activities.packList.returnCrateModalInCrateSection',
      lines: inCrate,
    })
  }
  if (loose.length > 0) {
    sections.push({
      key: 'loose',
      titleKey: 'activities.packList.returnCrateModalLooseSection',
      noteKey: 'activities.packList.returnCrateModalLooseNote',
      lines: loose,
    })
  }
  if (added.length > 0) {
    sections.push({
      key: 'added',
      titleKey: 'activities.packList.returnCrateModalAddedSection',
      noteKey: 'activities.packList.returnCrateModalAddedNote',
      lines: added,
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

const searchQuery = computed(() => materialSearch.value.trim().toLowerCase())

const filteredSearchMaterials = computed(() => {
  const q = searchQuery.value
  const list = props.searchableMaterials
  if (q.length < 1) return list
  return list.filter((m) => m.name.toLowerCase().includes(q) || m.id.toLowerCase().includes(q))
})

const showMaterialSearch = computed(
  () => props.searchableMaterials.length > 0 && !props.contentsLoading,
)

function patchLine(id: string, patch: Partial<ReturnCrateLineEdit>): void {
  emit(
    'update:lines',
    props.lines.map((line) => (line.id === id ? { ...line, ...patch } : line)),
  )
}

function onConfirmOk(line: ReturnCrateLineEdit): void {
  if (line.included && varianceKind(line) === 'ok') {
    patchLine(line.id, { included: false })
    return
  }
  const cap = returnCrateLineInputCap(line.ordered, line.max)
  const nextQty = line.qty < 1 ? line.max : line.qty
  patchLine(line.id, {
    included: true,
    qty: Math.min(Math.max(0, nextQty), cap),
  })
}

function clampQty(line: ReturnCrateLineEdit, raw: number): number {
  if (!Number.isFinite(raw)) return line.max
  const cap = returnCrateLineInputCap(line.ordered, line.max)
  return Math.min(cap, Math.max(0, Math.floor(raw)))
}

function setQty(line: ReturnCrateLineEdit, raw: number): void {
  const qty = clampQty(line, raw)
  patchLine(line.id, { qty, included: qty > 0 ? true : line.included })
}

function onQtyInput(line: ReturnCrateLineEdit, event: Event): void {
  setQty(line, Number((event.target as HTMLInputElement).value))
}

function stepQty(line: ReturnCrateLineEdit, delta: number): void {
  setQty(line, line.qty + delta)
}

function missingQty(line: ReturnCrateLineEdit): number {
  return returnCrateLineMissingQty(line.included, line.max, line.qty)
}

function surplusQty(line: ReturnCrateLineEdit): number {
  return returnCrateLineSurplusQty(line.included, line.max, line.qty)
}

function varianceKind(line: ReturnCrateLineEdit): 'ok' | 'short' | 'surplus' | 'unset' {
  if (!line.included) return 'unset'
  if (missingQty(line) > 0) return 'short'
  if (surplusQty(line) > 0) return 'surplus'
  return 'ok'
}

function showMissingActions(line: ReturnCrateLineEdit): boolean {
  if (line.placement === 'loose' || line.isDone) return false
  if (missingQty(line) < 1 || !line.materialItemId) return false
  return props.canReportIssues || (line.isConsumable && props.canReportConsumption)
}

function showSurplusActions(line: ReturnCrateLineEdit): boolean {
  if (line.placement === 'loose' || line.isDone) return false
  if (surplusQty(line) < 1 || !line.materialItemId) return false
  return true
}

function lineMeta(line: ReturnCrateLineEdit): string {
  return formatReturnCrateLineMeta(
    {
      ordered: line.ordered,
      consumed: line.consumed,
      loss: line.loss,
      repair: line.repair,
    },
    t,
  )
}

function pickSearchMaterial(materialId: string): void {
  materialSearch.value = ''
  emit('add-material', materialId)
}

function onAddMaterialSelect(event: Event): void {
  const id = (event.target as HTMLSelectElement).value
  if (!id) return
  ;(event.target as HTMLSelectElement).value = ''
  pickSearchMaterial(id)
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
            :class="{ 'pack-return-crate-line-row--done': line.isDone }"
          >
            <template v-if="line.isDone">
              <div class="pack-crate-shell-check-line pack-crate-shell-check-line--ok">
                <div class="pack-crate-shell-check-line__main pack-shell-forward-li-meta">
                  <div class="pack-shell-forward-li-name">{{ line.materialName }}</div>
                  <div v-if="lineMeta(line)" class="pack-shell-forward-li-sub text-muted">
                    {{ lineMeta(line) }}
                  </div>
                </div>
                <span class="pack-return-crate-done-badge" role="status">{{
                  t('activities.packList.returnCrateModalLineDone')
                }}</span>
              </div>
            </template>
            <template v-else>
              <div
                class="pack-crate-shell-check-line"
                :class="{
                  'pack-crate-shell-check-line--ok': varianceKind(line) === 'ok',
                  'pack-crate-shell-check-line--short': varianceKind(line) === 'short',
                  'pack-crate-shell-check-line--surplus': varianceKind(line) === 'surplus',
                  'pack-crate-shell-check-line--pending': varianceKind(line) === 'unset',
                }"
              >
                <div class="pack-crate-shell-check-line__main pack-shell-forward-li-meta">
                  <div class="pack-shell-forward-li-name">
                    {{ line.materialName }}
                    <span v-if="line.isExtra" class="pack-return-crate-badge">{{
                      t('activities.packList.returnCrateModalBadgeExtra')
                    }}</span>
                  </div>
                  <div class="pack-shell-forward-li-sub text-muted">
                    <span>{{
                      t('activities.packList.shellForwardExpectedQty', { n: line.max })
                    }}</span>
                    <span v-if="lineMeta(line)">{{ lineMeta(line) }}</span>
                  </div>
                </div>
                <div class="pack-crate-shell-check-line__actions pack-shell-forward-variance-actions">
                  <button
                    type="button"
                    class="shell-forward-variance-btn shell-forward-variance-btn--minus"
                    :class="{ 'shell-forward-variance-btn--active': varianceKind(line) === 'short' }"
                    :title="t('activities.packList.shellForwardMinusTitle')"
                    :disabled="line.qty < 1 && !line.included"
                    @click="stepQty(line, -1)"
                  >
                    −
                  </button>
                  <label class="pack-shell-forward-count-label">
                    <span class="sr-only">{{ t('activities.packList.returnCrateModalReturnQty') }}</span>
                    <input
                      :value="line.qty"
                      type="number"
                      min="0"
                      :max="returnCrateLineInputCap(line.ordered, line.max)"
                      class="form-input pack-shell-forward-count-input"
                      :aria-label="t('activities.packList.returnCrateModalReturnQty')"
                      @input="onQtyInput(line, $event)"
                    />
                  </label>
                  <button
                    type="button"
                    class="shell-forward-variance-btn shell-forward-variance-btn--plus"
                    :class="{ 'shell-forward-variance-btn--active': varianceKind(line) === 'surplus' }"
                    :title="t('activities.packList.shellForwardPlusTitle')"
                    @click="stepQty(line, 1)"
                  >
                    +
                  </button>
                  <PackShellCheckToggle
                    ok-only
                    :ok-active="line.included && varianceKind(line) === 'ok'"
                    :ok-title="t('activities.packList.shellForwardLineOkTitle')"
                    :ok-aria-label="t('activities.packList.returnCrateModalIncludeAria', { name: line.materialName })"
                    @ok="onConfirmOk(line)"
                  />
                </div>
              </div>
              <div
                v-if="showMissingActions(line)"
                class="pack-shell-forward-line-issue-row pack-shell-forward-line-issue-row--problem"
              >
                <span class="pack-return-crate-missing-hint text-muted">{{
                  t('activities.packList.returnCrateModalMissingQty', { n: missingQty(line) })
                }}</span>
                <div class="pack-card-issue-quick-row">
                  <button
                    v-if="line.isConsumable && canReportConsumption && line.materialItemId"
                    type="button"
                    class="btn-issue-quick btn-issue-consumed"
                    @click="emit('report-consumption', line.materialItemId!, line.materialName)"
                  >
                    {{ t('activities.common.issueConsumed') }}
                  </button>
                  <button
                    v-if="canReportIssues && line.materialItemId"
                    type="button"
                    class="btn-issue-quick btn-issue-loss"
                    @click="emit('report-loss', line.materialItemId!, missingQty(line))"
                  >
                    {{ t('activities.common.issueLoss') }}
                  </button>
                  <button
                    v-if="canReportIssues && line.materialItemId"
                    type="button"
                    class="btn-issue-quick btn-issue-repair"
                    @click="emit('report-repair', line.materialItemId!, missingQty(line))"
                  >
                    {{ t('activities.common.issueRepair') }}
                  </button>
                </div>
              </div>
              <div
                v-if="showSurplusActions(line)"
                class="pack-shell-forward-line-issue-row pack-return-crate-surplus-row"
              >
                <span class="pack-return-crate-missing-hint text-muted">{{
                  t('activities.packList.returnCrateModalSurplusQty', { n: surplusQty(line) })
                }}</span>
                <div class="pack-card-issue-quick-row">
                  <button
                    type="button"
                    class="btn-issue-quick btn-issue-extra"
                    @click="emit('report-extra', line.materialItemId!, surplusQty(line), line.id)"
                  >
                    {{ t('activities.packList.returnCrateModalBookExtra') }}
                  </button>
                </div>
              </div>
            </template>
          </li>
        </ul>
      </div>

      <div v-if="showMaterialSearch" class="pack-return-crate-search">
        <p class="pack-modal-hint text-muted">{{ t('activities.packList.returnCrateModalSearchHint') }}</p>
        <div class="pack-return-crate-add-row">
          <input
            v-model="materialSearch"
            type="search"
            class="form-input pack-return-crate-search-input"
            :placeholder="t('activities.packList.returnCrateModalSearchPlaceholder')"
            autocomplete="off"
          />
          <select
            class="form-input pack-return-crate-add-select"
            :aria-label="t('activities.packList.returnCrateModalAddPickPlaceholder')"
            @change="onAddMaterialSelect"
          >
            <option value="">{{ t('activities.packList.returnCrateModalAddPickPlaceholder') }}</option>
            <option v-for="m in filteredSearchMaterials" :key="'ret-pick-' + m.id" :value="m.id">
              {{ m.name }}
            </option>
          </select>
        </div>
        <p
          v-if="searchQuery.length > 0 && filteredSearchMaterials.length < 1"
          class="pack-return-crate-search-empty text-muted"
        >
          {{ t('activities.packList.returnCrateModalSearchEmpty') }}
        </p>
      </div>

      <p
        v-if="lineSections.length < 1 && !showMaterialSearch"
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

<style src="@/styles/views/activities/detail-workflow.css"></style>

<style scoped>
.pack-return-crate-search {
  margin: 0.75rem 0 1rem;
}

.pack-return-crate-add-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.35rem;
}

.pack-return-crate-search-input {
  flex: 1 1 12rem;
  min-width: 0;
}

.pack-return-crate-add-select {
  flex: 1 1 14rem;
  min-width: 0;
}

.pack-return-crate-search-empty {
  margin: 0.35rem 0 0;
  font-size: 12px;
}

.pack-shell-forward-li-name .pack-return-crate-badge {
  margin-left: 6px;
  vertical-align: middle;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
