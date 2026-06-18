<script setup lang="ts">
import { computed, ref } from 'vue'
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
  max: number
  issued: number
  returnedAlready: number
  included: boolean
  qty: number
  isExtra: boolean
  isConsumable: boolean
  consumptionDone: boolean
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
  if (line.placement === 'loose' || line.isDone) return false
  if (line.isConsumable || missingQty(line) < 1 || !line.materialItemId) return false
  return props.canReportIssues
}

function lineQtyHint(line: ReturnCrateLineEdit): string {
  if (line.isDone) {
    return t('activities.packList.returnCrateModalLineDone')
  }
  if (line.placement === 'loose') {
    return t('activities.packList.returnCrateModalLooseQty', { n: line.max })
  }
  if (line.kind === 'shell') {
    return t('activities.packList.returnCrateModalStillQty', { n: line.max })
  }
  const parts: string[] = []
  if (line.expectedQty > 0) {
    parts.push(t('activities.packList.returnCrateModalExpectedQty', { n: line.expectedQty }))
  }
  if (line.max > 0) {
    parts.push(
      t('activities.packList.returnCrateModalLineQty', {
        still: line.max,
        issued: line.issued,
      }),
    )
  } else if (line.returnedAlready > 0) {
    parts.push(t('activities.packList.returnCrateModalLineDone'))
  }
  return parts.join(' · ')
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
            <template v-else-if="line.isDone">
              <div class="pack-return-crate-line-done">
                <span class="pack-return-crate-line-name">{{ line.materialName }}</span>
                <span class="pack-return-crate-line-qty text-muted">{{ lineQtyHint(line) }}</span>
                <span class="pack-return-crate-done-badge" role="status">{{
                  t('activities.packList.returnCrateModalLineDone')
                }}</span>
              </div>
            </template>
            <template v-else>
              <div class="pack-return-crate-line-inline">
                <label class="pack-return-crate-line-check">
                  <input
                    :checked="line.included"
                    type="checkbox"
                    @change="onIncludedChange(line, ($event.target as HTMLInputElement).checked)"
                  />
                  <span class="pack-return-crate-line-name">{{ line.materialName }}</span>
                </label>
                <span
                  class="pack-return-crate-line-qty text-muted"
                  :title="lineQtyHint(line)"
                >{{ lineQtyHint(line) }}</span>
                <span v-if="line.isExtra" class="pack-return-crate-badge">{{
                  t('activities.packList.returnCrateModalBadgeExtra')
                }}</span>
                <div v-if="line.included && line.max > 0" class="pack-return-crate-line-controls">
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
                      :aria-label="t('activities.packList.returnCrateModalReturnQty')"
                      @input="onQtyInput(line, $event)"
                    />
                    <button type="button" class="btn-qty" :disabled="line.qty >= line.max" @click="bumpQty(line, 1)">
                      +
                    </button>
                  </div>
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

.pack-return-crate-line-done {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem 0.65rem;
  opacity: 0.85;
}
</style>

