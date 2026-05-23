<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackShellCheckToggle from '@/components/activities/PackShellCheckToggle.vue'
import PackRepackIssueRow from '@/components/activities/PackRepackIssueRow.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'
import PackShellMiniCountRow from '@/components/activities/PackShellMiniCountRow.vue'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import type { ActivityIssueReportRow } from '@/api/activities'
import { getMaterialStorageLocations } from '@/api/materials'
import {
  applyCountedQtyToReview,
  applyGroupAutoResolution,
  buildPackCrateCheckLinesPayload,
  buildDefaultShellForwardLineReviews,
  defaultLineReview,
  allInventoryLocationsSettled,
  applyInventoryLocationCounted,
  buildInitialInventoryLocationReviews,
  buildMiniInventoryLocations,
  formatStorageLocationPlaceLabel,
  inventoryCoversShortfall,
  inventoryFoundQtyFromReviews,
  inventorySurplusAtLocation,
  miniInventoryContainerLocations,
  miniInventoryLooseLocations,
  storageLocationRowKey,
  type InventoryLocationReview,
  shellForwardLineKey,
  shortfallQty,
  surplusQty,
  type ShellForwardCheckLine,
  type ShellForwardLineReview,
} from '@/components/activities/packCrateForwardCheck'
import type { PackCrateCheckRequest } from '@/api/activityPackCrateCheck'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

const props = defineProps<{
  open: boolean
  label: string
  moveQty: number
  sections: PackCrateShellPeekSection[]
  departmentId: string
  containerBatchId: string | null
  looseStockByMid: Record<string, number>
  stockLoading: boolean
  historyReplenishByKey: Record<string, boolean>
  historyPrefillHint: string | null
  canReportIssues: boolean
  /** Gruppe/Leiter (u, l1–l3): vereinfachte Texte, keine MW-Lageraktionen */
  groupMode?: boolean
  /** Nur Check speichern — ohne ans Event weiterbuchen */
  checkOnly?: boolean
  submitError?: string | null
  submitting: boolean
  emptyHint: string
  embeddedIssuesByLineKey: Record<string, ActivityIssueReportRow[]>
  repackIssueReviews: Record<string, 'ok' | 'problem' | null>
  orphanIssues: ActivityIssueReportRow[]
  /** Aus letztem Kistencheck — sonst Standard-Reviews */
  initialLineReviews?: Record<string, ShellForwardLineReview> | null
  packItemId?: string | null
}>()

const emit = defineEmits<{
  cancel: []
  submit: [payload: PackCrateCheckRequest]
  'set-repack-review': [issueId: string, status: 'ok' | 'problem']
}>()

const { t } = useI18n()

const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY, null) as Record<string, unknown> | null

const lineReviews = ref<Record<string, ShellForwardLineReview>>({})
const locationsLoadingByMid = ref<Record<string, boolean>>({})

const checkLines = computed((): ShellForwardCheckLine[] => {
  const out: ShellForwardCheckLine[] = []
  for (const sec of props.sections) {
    const isExtra = sec.subsectionKey === 'extra'
    for (const line of sec.lines) {
      out.push({
        key: shellForwardLineKey(sec.subsectionKey, line.id),
        subsectionKey: sec.subsectionKey,
        materialName: line.materialName,
        quantity: line.quantity,
        materialItemId: (line.materialItemId ?? '').trim() || null,
        isExtra,
        serialHint: (line.serialHint ?? '').trim() || null,
      })
    }
  }
  return out
})

function reviewFor(key: string, expectedQty: number): ShellForwardLineReview {
  return lineReviews.value[key] ?? defaultLineReview(expectedQty)
}

function looseStock(mid: string | null): number {
  if (!mid) return 0
  return props.looseStockByMid[mid] ?? 0
}

function lineCountedQty(key: string, expectedQty: number): number {
  return Math.max(0, Math.floor(Number(reviewFor(key, expectedQty).countedQty)) || 0)
}

function patchReview(key: string, expectedQty: number, isExtra: boolean, patch: Partial<ShellForwardLineReview>) {
  const cur = reviewFor(key, expectedQty)
  const touchesLineCount =
    patch.countedQty !== undefined || patch.status !== undefined || patch.resolution !== undefined
  const mergedInput = { ...cur, ...patch }
  if (patch.countedQty !== undefined && patch.status === undefined && cur.status === 'ok') {
    mergedInput.status = null
  }
  const merged = touchesLineCount
    ? applyCountedQtyToReview(mergedInput, expectedQty, isExtra, { explicitOkOnly: true })
    : mergedInput
  lineReviews.value = { ...lineReviews.value, [key]: merged }
  const packItemId = props.packItemId
}

function onCountedChange(line: ShellForwardCheckLine, raw: number) {
  patchReview(line.key, line.quantity, line.isExtra, { countedQty: raw })
  const updated = lineReviews.value[line.key]
  if (!updated || updated.status !== 'problem' || line.isExtra) return
  if (props.groupMode) {
    lineReviews.value = {
      ...lineReviews.value,
      [line.key]: applyGroupAutoResolution(updated, line.quantity),
    }
    return
  }
  const miss = shortfallQty(line.quantity, updated.countedQty)
  if (miss > 0 && updated.inventoryPhase === 'none') {
    void ensureInventoryLocations(line)
  }
}

async function ensureInventoryLocations(line: ShellForwardCheckLine) {
  const mid = line.materialItemId
  if (!mid || !props.departmentId) return
  const cur = lineReviews.value[line.key]
  if (cur?.inventoryLocations.length) {
    lineReviews.value = {
      ...lineReviews.value,
      [line.key]: { ...cur, inventoryPhase: 'active' },
    }
    return
  }
  locationsLoadingByMid.value = { ...locationsLoadingByMid.value, [mid]: true }
  try {
    const res = await getMaterialStorageLocations(mid, props.departmentId)
    const locs = buildMiniInventoryLocations(
      res.direct ?? [],
      res.via_physical_combo ?? [],
      props.containerBatchId,
    )
    const phase = locs.length > 0 ? 'active' : 'skipped'
    const cur2 = lineReviews.value[line.key] ?? defaultLineReview(line.quantity)
    const locReviews = buildInitialInventoryLocationReviews(locs)
    lineReviews.value = {
      ...lineReviews.value,
      [line.key]: {
        ...cur2,
        inventoryLocations: locs,
        inventoryLocationReviews: locReviews,
        inventoryPhase: phase,
        resolution: phase === 'skipped' ? 'loss' : cur2.resolution,
        missingQty: shortfallQty(line.quantity, cur2.countedQty),
        note:
          phase === 'skipped'
            ? cur2.note || t('activities.packList.shellForwardPreEventLossNote')
            : cur2.note,
      },
    }
    if (phase === 'skipped' && looseStock(mid) > 0) {
      const miss = shortfallQty(line.quantity, cur2.countedQty)
      lineReviews.value[line.key] = {
        ...lineReviews.value[line.key]!,
        doReplenishAfterLoss: true,
        replenishQty: Math.min(miss, looseStock(mid)),
      }
    }
  } catch {
    const cur2 = lineReviews.value[line.key] ?? defaultLineReview(line.quantity)
    lineReviews.value = {
      ...lineReviews.value,
      [line.key]: {
        ...cur2,
        inventoryPhase: 'skipped',
        resolution: 'loss',
        missingQty: shortfallQty(line.quantity, cur2.countedQty),
      },
    }
  } finally {
    locationsLoadingByMid.value = { ...locationsLoadingByMid.value, [mid]: false }
  }
}

function locationReviewFor(
  lineKey: string,
  expectedQty: number,
  locKey: string,
  locExpectedQty: number,
): InventoryLocationReview {
  const r = reviewFor(lineKey, expectedQty)
  return (
    r.inventoryLocationReviews[locKey] ?? {
      countedQty: locExpectedQty,
      status: null,
    }
  )
}

function crateShortfallFor(line: ShellForwardCheckLine): number {
  return shortfallQty(line.quantity, reviewFor(line.key, line.quantity).countedQty)
}

function inventoryFoundFor(line: ShellForwardCheckLine): number {
  const r = reviewFor(line.key, line.quantity)
  return inventoryFoundQtyFromReviews(r.inventoryLocations, r.inventoryLocationReviews)
}

function inventoryCoversFor(line: ShellForwardCheckLine): boolean {
  const r = reviewFor(line.key, line.quantity)
  return inventoryCoversShortfall(crateShortfallFor(line), r.inventoryLocations, r.inventoryLocationReviews)
}

function syncInventoryCompletion(line: ShellForwardCheckLine) {
  const r = reviewFor(line.key, line.quantity)
  const miss = crateShortfallFor(line)
  const found = inventoryFoundFor(line)
  const allDone = allInventoryLocationsSettled(r)
  const covers = inventoryCoversFor(line)

  if (allDone && covers) {
    patchReview(line.key, line.quantity, line.isExtra, {
      inventoryPhase: 'done',
      resolution: 'found_elsewhere',
      missingQty: miss,
      note:
        r.note ||
        t('activities.packList.shellForwardInventoryFoundNote', { found, miss }),
      doReplenishAfterLoss: false,
      replenishQty: null,
    })
    return
  }

  patchReview(line.key, line.quantity, line.isExtra, {
    inventoryPhase: allDone ? 'done' : 'active',
    resolution: allDone ? 'loss' : r.resolution === 'found_elsewhere' ? null : r.resolution,
    missingQty: miss,
    note: allDone && !covers ? r.note || t('activities.packList.shellForwardPreEventLossNote') : r.note,
    doReplenishAfterLoss: allDone && !covers && looseStock(line.materialItemId) > 0,
    replenishQty:
      allDone && !covers && looseStock(line.materialItemId) > 0
        ? Math.min(miss, looseStock(line.materialItemId))
        : r.replenishQty,
  })
}

function patchInventoryLocation(
  line: ShellForwardCheckLine,
  locKey: string,
  locExpectedQty: number,
  patch: Partial<InventoryLocationReview>,
) {
  const r = reviewFor(line.key, line.quantity)
  const cur = locationReviewFor(line.key, line.quantity, locKey, locExpectedQty)
  const withPatch = { ...cur, ...patch }
  if (patch.countedQty !== undefined && patch.status === undefined) {
    withPatch.status = null
  }
  const merged = applyInventoryLocationCounted(withPatch, locExpectedQty)
  patchReview(line.key, line.quantity, line.isExtra, {
    inventoryLocationReviews: {
      ...r.inventoryLocationReviews,
      [locKey]: merged,
    },
  })
  syncInventoryCompletion(line)
}

function onInventoryLocationCounted(line: ShellForwardCheckLine, loc: { qty: number }, locKey: string, raw: number) {
  patchInventoryLocation(line, locKey, loc.qty, { countedQty: raw })
}

function setInventoryLocationOk(line: ShellForwardCheckLine, loc: { qty: number }, locKey: string) {
  const cur = locationReviewFor(line.key, line.quantity, locKey, loc.qty)
  patchInventoryLocation(line, locKey, loc.qty, { countedQty: cur.countedQty, status: 'ok' })
}

function skipInventory(line: ShellForwardCheckLine) {
  const miss = shortfallQty(line.quantity, reviewFor(line.key, line.quantity).countedQty)
  patchReview(line.key, line.quantity, line.isExtra, {
    inventoryPhase: 'skipped',
    resolution: 'loss',
    missingQty: miss,
    note: t('activities.packList.shellForwardPreEventLossNote'),
    doReplenishAfterLoss: looseStock(line.materialItemId) > 0,
    replenishQty:
      looseStock(line.materialItemId) > 0
        ? Math.min(miss, looseStock(line.materialItemId))
        : null,
  })
}

function setLineOk(line: ShellForwardCheckLine) {
  const cur = reviewFor(line.key, line.quantity)
  patchReview(line.key, line.quantity, line.isExtra, {
    countedQty: cur.countedQty,
    status: 'ok',
    resolution: null,
    missingQty: null,
    inventoryPhase: 'none',
  })
}

function lineSettled(line: ShellForwardCheckLine): boolean {
  const r = lineReviews.value[line.key]
  if (!r || r.status === null) return false
  const issues = props.embeddedIssuesByLineKey[line.key] ?? []
  if (issues.some((em) => props.repackIssueReviews[em.id] !== 'ok')) return false
  if (props.groupMode) {
    if (r.status === 'ok') return true
    const miss = shortfallQty(line.quantity, r.countedQty)
    const sur = surplusQty(line.quantity, r.countedQty)
    if (miss > 0) {
      return r.resolution === 'not_taken' && (r.missingQty ?? 0) >= 1
    }
    if (sur > 0) {
      return r.resolution === 'extra' && (r.missingQty ?? 0) >= 1
    }
    return false
  }
  if (r.status === 'ok') return true
  if (!r.resolution) return false
  if (r.resolution === 'extra') {
    return r.note.trim() !== '' || (r.missingQty != null && r.missingQty >= 1)
  }
  if (r.resolution === 'return_surplus') {
    const max = surplusQty(line.quantity, r.countedQty)
    const q = r.returnSurplusQty ?? r.missingQty ?? max
    return q >= 1 && q <= max
  }
  if (r.resolution === 'not_taken') {
    return (r.missingQty != null && r.missingQty >= 1) || r.note.trim() !== ''
  }
  if (r.resolution === 'loss') {
    const miss = r.missingQty != null && r.missingQty >= 1
    if (!miss && !r.note.trim()) return false
    if (r.inventoryPhase === 'active' && !allInventoryLocationsSettled(r)) return false
    if (r.doReplenishAfterLoss) {
      const need = r.replenishQty ?? 0
      return need >= 1 && looseStock(line.materialItemId) >= need
    }
    return true
  }
  if (r.resolution === 'replenish') {
    const need = r.missingQty != null && r.missingQty >= 1 ? r.missingQty : 1
    return looseStock(line.materialItemId) >= need
  }
  if (r.resolution === 'found_elsewhere') {
    return (
      r.inventoryPhase === 'done' &&
      allInventoryLocationsSettled(r) &&
      inventoryCoversFor(line)
    )
  }
  if (r.resolution === 'repair') {
    return (r.missingQty != null && r.missingQty >= 1) || r.note.trim() !== ''
  }
  return false
}

const allSettled = computed(() => checkLines.value.every((l) => lineSettled(l)))
const allOk = computed(() =>
  checkLines.value.every((l) => lineReviews.value[l.key]?.status === 'ok'),
)
const hasProblems = computed(() =>
  checkLines.value.some((l) => lineReviews.value[l.key]?.status === 'problem'),
)
const canSubmitComplete = computed(() => allSettled.value && allOk.value)
const canSubmitIncomplete = computed(() => allSettled.value && hasProblems.value)
const submitBlocked = computed(
  () => props.submitting || (!canSubmitComplete.value && !canSubmitIncomplete.value),
)

const submitLabel = computed(() => {
  if (props.checkOnly) {
    if (canSubmitComplete.value) return t('activities.packList.repeatCrateCheckSubmitOk')
    if (canSubmitIncomplete.value) {
      return props.groupMode
        ? t('activities.packList.repeatCrateCheckSubmitIncompleteGroup')
        : t('activities.packList.repeatCrateCheckSubmitIncomplete')
    }
    return t('activities.packList.shellForwardSubmitPickActionShort')
  }
  if (canSubmitComplete.value) return t('activities.packList.shellForwardSubmitOk')
  if (canSubmitIncomplete.value) {
    if (props.groupMode) {
      return t('activities.packList.shellForwardGroupSubmitIncomplete')
    }
    return t('activities.packList.shellForwardSubmitWithProtocol', { label: props.label })
  }
  return t('activities.packList.shellForwardSubmitPickActionShort')
})

const modalTitle = computed(() =>
  props.checkOnly
    ? t('activities.packList.repeatCrateCheckTitle')
    : t('activities.packList.shellForwardTitle'),
)

const introText = computed(() => {
  if (props.checkOnly) {
    return props.groupMode
      ? t('activities.packList.repeatCrateCheckIntroGroup', { label: props.label })
      : t('activities.packList.repeatCrateCheckIntro', { label: props.label })
  }
  return props.groupMode
    ? t('activities.packList.shellForwardIntroGroup', { label: props.label, n: props.moveQty })
    : t('activities.packList.shellForwardIntro', { label: props.label, n: props.moveQty })
})

const wizardHintText = computed(() =>
  props.groupMode
    ? t('activities.packList.shellForwardWizardHintGroup')
    : t('activities.packList.shellForwardWizardHint'),
)

const needAllLinesHint = computed(() =>
  props.groupMode
    ? t('activities.packList.shellForwardNeedAllLinesGroup')
    : t('activities.packList.shellForwardNeedAllLines'),
)

function initReviews() {
  const initial = props.initialLineReviews
  if (initial && Object.keys(initial).length > 0) {
    lineReviews.value = { ...initial }
    return
  }
  lineReviews.value = buildDefaultShellForwardLineReviews(checkLines.value)
}

watch(
  () => [props.open, props.initialLineReviews] as const,
  ([isOpen]) => {
    if (!isOpen) return
    initReviews()
  },
)

function buildPayload(result: 'ok' | 'incomplete'): PackCrateCheckRequest {
  return {
    container_batch_id: props.containerBatchId,
    result,
    lines: buildPackCrateCheckLinesPayload(
      checkLines.value,
      lineReviews.value,
      props.historyReplenishByKey,
    ),
  }
}

function onPrimarySubmit() {
  if (canSubmitComplete.value) emit('submit', buildPayload('ok'))
  else if (canSubmitIncomplete.value) emit('submit', buildPayload('incomplete'))
}

function varianceKind(line: ShellForwardCheckLine): 'ok' | 'short' | 'surplus' | 'unset' {
  const r = lineReviews.value[line.key]
  if (!r) return 'unset'
  if (r.status === 'ok' && r.countedQty === line.quantity) return 'ok'
  if (r.status === 'problem') {
    if (r.countedQty > line.quantity) return 'surplus'
    if (r.countedQty < line.quantity) return 'short'
  }
  return 'unset'
}

function inventoryLooseFor(key: string, expectedQty: number) {
  return miniInventoryLooseLocations(reviewFor(key, expectedQty).inventoryLocations)
}

function inventoryContainersFor(key: string, expectedQty: number) {
  return miniInventoryContainerLocations(reviewFor(key, expectedQty).inventoryLocations)
}

function issueTypeLabel(r: ActivityIssueReportRow): string {
  return (r.type_label || r.type || '').trim() || r.type
}

function asCheckLine(sec: PackCrateShellPeekSection, line: PackCrateShellPeekSection['lines'][0]): ShellForwardCheckLine {
  return {
    key: shellForwardLineKey(sec.subsectionKey, line.id),
    subsectionKey: sec.subsectionKey,
    materialName: line.materialName,
    quantity: line.quantity,
    materialItemId: (line.materialItemId ?? '').trim() || null,
    isExtra: sec.subsectionKey === 'extra',
    serialHint: (line.serialHint ?? '').trim() || null,
  }
}
</script>

<template>
  <PackWorkflowModal :open="open" size="lg" @cancel="emit('cancel')">
    <template #title>{{ modalTitle }}</template>
    <template #intro>
      <p v-if="submitError" class="pack-shell-forward-submit-error" role="alert">
        {{ submitError }}
      </p>
      <p class="pack-modal-hint text-muted">
        {{ introText }}
      </p>
      <p class="pack-modal-hint text-muted pack-shell-forward-wizard-hint">
        {{ wizardHintText }}
      </p>
      <p
        v-if="historyPrefillHint"
        class="pack-shell-forward-history-hint"
        :class="{ 'pack-shell-forward-history-hint--confirm': groupMode }"
        role="status"
      >
        {{ historyPrefillHint }}
      </p>
    </template>

    <div class="pack-shell-forward-preview">
      <template v-for="sec in sections" :key="'sf-' + sec.subsectionKey">
        <div class="pack-container-subsection-title">{{ sec.title }}</div>
        <ul v-if="sec.lines.length > 0" class="pack-shell-forward-ul">
          <li
            v-for="line in sec.lines"
            :key="sec.subsectionKey + '-' + line.id"
            class="pack-shell-forward-li pack-shell-forward-li--wizard"
            :class="{
              'pack-shell-forward-li--ok': varianceKind(asCheckLine(sec, line)) === 'ok',
              'pack-shell-forward-li--short': varianceKind(asCheckLine(sec, line)) === 'short',
              'pack-shell-forward-li--surplus': varianceKind(asCheckLine(sec, line)) === 'surplus',
              'pack-shell-forward-li--pending':
                varianceKind(asCheckLine(sec, line)) === 'unset' &&
                reviewFor(asCheckLine(sec, line).key, line.quantity).countedQty === line.quantity,
            }"
          >
            <template v-for="cl in [asCheckLine(sec, line)]" :key="cl.key">
              <div class="pack-shell-forward-li-row">
                <div class="pack-shell-forward-li-main pack-shell-forward-li-main--stacked">
                  <div class="pack-shell-forward-li-meta">
                    <div class="pack-shell-forward-li-name">{{ line.materialName }}</div>
                    <div class="pack-shell-forward-li-sub text-muted">
                      <span>{{ t('activities.packList.shellForwardExpectedQty', { n: line.quantity }) }}</span>
                      <span
                        v-if="line.serialHint"
                        class="pack-shell-forward-li-serial"
                        :title="t('activities.packList.shellForwardSerialCheckTitle')"
                      >
                        {{ t('activities.packList.shellForwardSerialSn', { serial: line.serialHint }) }}
                      </span>
                    </div>
                  </div>
                  <p
                    v-if="historyReplenishByKey[cl.key]"
                    class="pack-shell-forward-replenish-hint text-muted"
                  >
                    {{ t('activities.packList.shellForwardReplenishedFromStockHint') }}
                  </p>
                </div>
                <div class="pack-shell-forward-li-actions pack-shell-forward-variance-actions">
                  <button
                    type="button"
                    class="shell-forward-variance-btn shell-forward-variance-btn--minus"
                    :class="{ 'shell-forward-variance-btn--active': varianceKind(cl) === 'short' }"
                    :title="t('activities.packList.shellForwardMinusTitle')"
                    :disabled="historyReplenishByKey[cl.key] || cl.isExtra"
                    @click="
                      (() => {
                        const next = Math.max(0, lineCountedQty(cl.key, cl.quantity) - 1)
                        patchReview(cl.key, cl.quantity, cl.isExtra, { countedQty: next })
                        onCountedChange(cl, next)
                      })()
                    "
                  >
                    −
                  </button>
                  <label class="pack-shell-forward-count-label">
                    <span class="sr-only">{{ t('activities.packList.shellForwardCountedQty') }}</span>
                    <input
                      :value="reviewFor(cl.key, cl.quantity).countedQty"
                      type="number"
                      min="0"
                      class="form-input pack-shell-forward-count-input"
                      :disabled="!!historyReplenishByKey[cl.key]"
                      @input="
                        onCountedChange(
                          cl,
                          Math.max(0, parseInt(($event.target as HTMLInputElement).value, 10) || 0),
                        )
                      "
                    />
                  </label>
                  <button
                    type="button"
                    class="shell-forward-variance-btn shell-forward-variance-btn--plus"
                    :class="{ 'shell-forward-variance-btn--active': varianceKind(cl) === 'surplus' }"
                    :title="t('activities.packList.shellForwardPlusTitle')"
                    :disabled="historyReplenishByKey[cl.key]"
                    @click="
                      (() => {
                        const next = lineCountedQty(cl.key, cl.quantity) + 1
                        patchReview(cl.key, cl.quantity, cl.isExtra, { countedQty: next })
                        onCountedChange(cl, next)
                      })()
                    "
                  >
                    +
                  </button>
                  <PackShellCheckToggle
                    ok-only
                    :ok-active="reviewFor(cl.key, cl.quantity).status === 'ok'"
                    :ok-title="t('activities.packList.shellForwardLineOkTitle')"
                    :ok-aria-label="t('activities.packList.shellForwardLineOkAria', { name: line.materialName })"
                    @ok="setLineOk(cl)"
                  />
                </div>
              </div>

              <PackRepackIssueRow
                v-for="em in embeddedIssuesByLineKey[cl.key] ?? []"
                :key="'emb-' + em.id"
                embedded
                :material-heading="em.material_name || t('activities.common.material')"
                :type-label="issueTypeLabel(em)"
                :quantity="em.quantity"
                :description="String(em.description ?? '')"
                :review-status="repackIssueReviews[em.id] ?? null"
                :ok-title="t('activities.packList.shellForwardLineOkTitle')"
                :ok-aria-label="t('activities.packList.shellForwardLineOkAria', { name: issueTypeLabel(em) })"
                :problem-title="t('activities.packList.shellForwardLineProblemTitle')"
                :problem-aria-label="t('activities.packList.shellForwardLineProblemAria', { name: issueTypeLabel(em) })"
                @set-review="(st) => emit('set-repack-review', em.id, st)"
              />

              <p
                v-if="groupMode && varianceKind(cl) === 'short'"
                class="pack-shell-forward-group-variance-msg"
              >
                {{
                  t('activities.packList.shellForwardGroupShortMsg', {
                    n: shortfallQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                  })
                }}
              </p>
              <p
                v-if="groupMode && varianceKind(cl) === 'surplus'"
                class="pack-shell-forward-group-variance-msg"
              >
                {{
                  t('activities.packList.shellForwardGroupSurplusMsg', {
                    n: surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                  })
                }}
              </p>

              <!-- Surplus: zurück ins Lager oder dokumentieren (MW/DC) -->
              <div
                v-if="
                  !groupMode &&
                  varianceKind(cl) === 'surplus' &&
                  reviewFor(cl.key, cl.quantity).status === 'problem'
                "
                class="pack-shell-forward-li-problem"
              >
                <p class="pack-shell-forward-variance-msg pack-shell-forward-variance-msg--surplus">
                  {{
                    t('activities.packList.shellForwardSurplusMsg', {
                      n: surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                    })
                  }}
                </p>
                <div v-if="containerBatchId && !cl.isExtra" class="pack-shell-forward-surplus-actions">
                  <label class="pack-modal-label pack-shell-forward-surplus-qty">
                    <span>{{ t('activities.packList.shellForwardReturnSurplusQty') }}</span>
                    <input
                      :value="
                        reviewFor(cl.key, cl.quantity).returnSurplusQty ??
                        surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty)
                      "
                      type="number"
                      min="1"
                      :max="surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty)"
                      class="form-input pack-shell-forward-count-input"
                      @input="
                        patchReview(cl.key, cl.quantity, cl.isExtra, {
                          returnSurplusQty: Math.min(
                            surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                            Math.max(
                              1,
                              parseInt(($event.target as HTMLInputElement).value, 10) || 1,
                            ),
                          ),
                          missingQty: surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                        })
                      "
                    />
                  </label>
                  <button
                    type="button"
                    class="btn-outline btn-xs"
                    :class="{
                      'btn-primary': reviewFor(cl.key, cl.quantity).resolution === 'return_surplus',
                    }"
                    @click="
                      patchReview(cl.key, cl.quantity, cl.isExtra, {
                        resolution: 'return_surplus',
                        missingQty: surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                        returnSurplusQty:
                          reviewFor(cl.key, cl.quantity).returnSurplusQty ??
                          surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                        createSurplusInspectionTask:
                          reviewFor(cl.key, cl.quantity).createSurplusInspectionTask,
                      })
                    "
                  >
                    {{
                      t('activities.packList.shellForwardResolutionReturnSurplus', {
                        n:
                          reviewFor(cl.key, cl.quantity).returnSurplusQty ??
                          surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                      })
                    }}
                  </button>
                  <label class="pack-shell-forward-inventory-check">
                    <input
                      type="checkbox"
                      :checked="reviewFor(cl.key, cl.quantity).createSurplusInspectionTask"
                      @change="
                        patchReview(cl.key, cl.quantity, cl.isExtra, {
                          createSurplusInspectionTask: ($event.target as HTMLInputElement).checked,
                        })
                      "
                    />
                    <span>{{ t('activities.packList.shellForwardSurplusInspectionTask') }}</span>
                  </label>
                </div>
                <label class="pack-modal-label">
                  <span>{{ t('activities.packList.shellForwardLineNote') }}</span>
                  <input
                    :value="reviewFor(cl.key, cl.quantity).note"
                    type="text"
                    class="form-input"
                    @input="
                      patchReview(cl.key, cl.quantity, cl.isExtra, {
                        note: ($event.target as HTMLInputElement).value,
                        resolution:
                          reviewFor(cl.key, cl.quantity).resolution === 'return_surplus'
                            ? 'return_surplus'
                            : 'extra',
                        missingQty: surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                      })
                    "
                  />
                </label>
                <button
                  v-if="!containerBatchId || cl.isExtra"
                  type="button"
                  class="btn-outline btn-xs"
                  @click="
                    patchReview(cl.key, cl.quantity, cl.isExtra, {
                      resolution: 'extra',
                      missingQty: surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                    })
                  "
                >
                  {{ t('activities.packList.shellForwardResolutionSurplus') }}
                </button>
              </div>

              <!-- Shortfall: mini inventory + loss + replenish (MW/DC) -->
              <div
                v-if="
                  !groupMode &&
                  varianceKind(cl) === 'short' &&
                  reviewFor(cl.key, cl.quantity).status === 'problem'
                "
                class="pack-shell-forward-li-problem"
              >
                <p class="pack-shell-forward-variance-msg pack-shell-forward-variance-msg--short">
                  {{
                    t('activities.packList.shellForwardShortMsg', {
                      n: shortfallQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                    })
                  }}
                </p>

                <div
                  v-if="
                    ['active', 'done'].includes(reviewFor(cl.key, cl.quantity).inventoryPhase) &&
                    reviewFor(cl.key, cl.quantity).inventoryLocations.length > 0
                  "
                  class="pack-shell-forward-inventory"
                >
                  <p class="pack-shell-forward-inventory-intro text-muted">
                    {{ t('activities.packList.shellForwardInventoryIntro') }}
                  </p>
                  <p
                    v-if="inventoryFoundFor(cl) > 0 || crateShortfallFor(cl) > 0"
                    class="pack-shell-forward-inventory-progress"
                    :class="{ 'pack-shell-forward-inventory-progress--ok': inventoryCoversFor(cl) }"
                  >
                    {{
                      inventoryCoversFor(cl)
                        ? t('activities.packList.shellForwardInventoryFoundOk', {
                            found: inventoryFoundFor(cl),
                            miss: crateShortfallFor(cl),
                          })
                        : t('activities.packList.shellForwardInventoryFoundProgress', {
                            found: inventoryFoundFor(cl),
                            miss: crateShortfallFor(cl),
                          })
                    }}
                  </p>

                  <template v-if="inventoryLooseFor(cl.key, cl.quantity).length > 0">
                    <p class="pack-shell-forward-inventory-group-title">
                      {{ t('activities.packList.shellForwardInventoryLooseTitle') }}
                    </p>
                    <ul class="pack-shell-forward-inventory-list">
                      <PackShellMiniCountRow
                        v-for="loc in inventoryLooseFor(cl.key, cl.quantity)"
                        :key="storageLocationRowKey(loc)"
                        :label="formatStorageLocationPlaceLabel(loc)"
                        :expected-qty="loc.qty"
                        :counted-qty="
                          locationReviewFor(cl.key, cl.quantity, storageLocationRowKey(loc), loc.qty)
                            .countedQty
                        "
                        :review-status="
                          locationReviewFor(cl.key, cl.quantity, storageLocationRowKey(loc), loc.qty)
                            .status
                        "
                        :minus-title="t('activities.packList.shellForwardMinusTitle')"
                        :plus-title="t('activities.packList.shellForwardPlusTitle')"
                        :ok-title="t('activities.packList.shellForwardLineOkTitle')"
                        :ok-aria-label="
                          t('activities.packList.shellForwardInventoryLocationOkAria', {
                            place: formatStorageLocationPlaceLabel(loc),
                          })
                        "
                        @update:counted-qty="
                          onInventoryLocationCounted(cl, loc, storageLocationRowKey(loc), $event)
                        "
                        @ok="setInventoryLocationOk(cl, loc, storageLocationRowKey(loc))"
                      />
                    </ul>
                  </template>

                  <template v-if="inventoryContainersFor(cl.key, cl.quantity).length > 0">
                    <p class="pack-shell-forward-inventory-group-title">
                      {{ t('activities.packList.shellForwardInventoryContainersTitle') }}
                    </p>
                    <ul class="pack-shell-forward-inventory-list">
                      <PackShellMiniCountRow
                        v-for="loc in inventoryContainersFor(cl.key, cl.quantity)"
                        :key="storageLocationRowKey(loc)"
                        :label="formatStorageLocationPlaceLabel(loc)"
                        :expected-qty="loc.qty"
                        :counted-qty="
                          locationReviewFor(cl.key, cl.quantity, storageLocationRowKey(loc), loc.qty)
                            .countedQty
                        "
                        :review-status="
                          locationReviewFor(cl.key, cl.quantity, storageLocationRowKey(loc), loc.qty)
                            .status
                        "
                        :minus-title="t('activities.packList.shellForwardMinusTitle')"
                        :plus-title="t('activities.packList.shellForwardPlusTitle')"
                        :ok-title="t('activities.packList.shellForwardLineOkTitle')"
                        :ok-aria-label="
                          t('activities.packList.shellForwardInventoryLocationOkAria', {
                            place: formatStorageLocationPlaceLabel(loc),
                          })
                        "
                        @update:counted-qty="
                          onInventoryLocationCounted(cl, loc, storageLocationRowKey(loc), $event)
                        "
                        @ok="setInventoryLocationOk(cl, loc, storageLocationRowKey(loc))"
                      />
                    </ul>
                  </template>

                  <button type="button" class="btn-outline btn-xs" @click="skipInventory(cl)">
                    {{ t('activities.packList.shellForwardInventoryNotFound') }}
                  </button>
                </div>

                <div
                  v-if="
                    reviewFor(cl.key, cl.quantity).resolution !== 'found_elsewhere' &&
                    (['done', 'skipped'].includes(reviewFor(cl.key, cl.quantity).inventoryPhase) ||
                      (reviewFor(cl.key, cl.quantity).inventoryPhase === 'active' &&
                        reviewFor(cl.key, cl.quantity).inventoryLocations.length === 0))
                  "
                  class="pack-shell-forward-loss-block"
                >
                  <p class="text-muted pack-shell-forward-loss-hint">
                    {{ t('activities.packList.shellForwardLossBlockHint') }}
                  </p>
                  <label class="pack-modal-label">
                    <span>{{ t('activities.packList.shellForwardLineMissingQty') }}</span>
                    <input
                      :value="reviewFor(cl.key, cl.quantity).missingQty ?? shortfallQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty)"
                      type="number"
                      min="1"
                      :max="cl.quantity"
                      class="form-input"
                      @input="
                        patchReview(cl.key, cl.quantity, cl.isExtra, {
                          missingQty: Math.max(1, parseInt(($event.target as HTMLInputElement).value, 10) || 1),
                          resolution: 'loss',
                        })
                      "
                    />
                  </label>
                  <label class="pack-modal-label">
                    <span>{{ t('activities.packList.shellForwardLineNote') }}</span>
                    <input
                      :value="reviewFor(cl.key, cl.quantity).note"
                      type="text"
                      class="form-input"
                      :placeholder="t('activities.packList.shellForwardPreEventLossNote')"
                      @input="
                        patchReview(cl.key, cl.quantity, cl.isExtra, {
                          note: ($event.target as HTMLInputElement).value,
                          resolution: 'loss',
                        })
                      "
                    />
                  </label>
                  <label
                    v-if="looseStock(cl.materialItemId) > 0 && !historyReplenishByKey[cl.key]"
                    class="pack-modal-checkbox-row"
                  >
                    <input
                      type="checkbox"
                      :checked="reviewFor(cl.key, cl.quantity).doReplenishAfterLoss"
                      @change="
                        patchReview(cl.key, cl.quantity, cl.isExtra, {
                          doReplenishAfterLoss: ($event.target as HTMLInputElement).checked,
                          replenishQty: Math.min(
                            shortfallQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                            looseStock(cl.materialItemId),
                          ),
                          resolution: 'loss',
                        })
                      "
                    />
                    <span>{{
                      t('activities.packList.shellForwardReplenishAfterLoss', {
                        n: looseStock(cl.materialItemId),
                      })
                    }}</span>
                  </label>
                  <label
                    v-if="reviewFor(cl.key, cl.quantity).doReplenishAfterLoss"
                    class="pack-modal-label"
                  >
                    <span>{{ t('activities.packList.shellForwardReplenishQty') }}</span>
                    <input
                      :value="reviewFor(cl.key, cl.quantity).replenishQty ?? 1"
                      type="number"
                      min="1"
                      :max="looseStock(cl.materialItemId)"
                      class="form-input"
                      @input="
                        patchReview(cl.key, cl.quantity, cl.isExtra, {
                          replenishQty: Math.max(
                            1,
                            parseInt(($event.target as HTMLInputElement).value, 10) || 1,
                          ),
                          resolution: 'loss',
                        })
                      "
                    />
                  </label>
                </div>

                <p v-if="locationsLoadingByMid[cl.materialItemId ?? '']" class="text-muted">
                  {{ t('activities.packList.shellForwardInventoryLoading') }}
                </p>

                <div v-if="sec.subsectionKey !== 'extra'" class="pack-shell-forward-alt-actions">
                  <button
                    type="button"
                    class="btn-outline btn-xs"
                    @click="
                      patchReview(cl.key, cl.quantity, cl.isExtra, {
                        resolution: 'not_taken',
                        missingQty: shortfallQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                      })
                    "
                  >
                    {{ t('activities.packList.shellForwardResolutionNotTaken') }}
                  </button>
                  <button
                    v-if="
                      looseStock(cl.materialItemId) > 0 &&
                      !historyReplenishByKey[cl.key] &&
                      reviewFor(cl.key, cl.quantity).inventoryPhase === 'none'
                    "
                    type="button"
                    class="btn-outline btn-xs"
                    :disabled="stockLoading"
                    @click="
                      patchReview(cl.key, cl.quantity, cl.isExtra, {
                        resolution: 'replenish',
                        missingQty: shortfallQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                      })
                    "
                  >
                    {{
                      t('activities.packList.shellForwardResolutionReplenish', {
                        n: looseStock(cl.materialItemId),
                      })
                    }}
                  </button>
                </div>
              </div>
            </template>
          </li>
        </ul>
      </template>
      <p v-if="sections.length === 0" class="text-muted pack-modal-hint">{{ emptyHint }}</p>
      <p v-else-if="!allSettled" class="pack-shell-forward-hint text-muted">
        {{ needAllLinesHint }}
      </p>
      <p
        v-else-if="groupMode && hasProblems"
        class="pack-shell-forward-group-deviation-banner"
        role="status"
      >
        {{ t('activities.packList.shellForwardGroupDeviationBanner') }}
      </p>
    </div>

    <template #footer>
      <PackModalFooter
        :primary-label="submitLabel"
        :primary-disabled="submitBlocked"
        :cancel-disabled="submitting"
        @cancel="emit('cancel')"
        @primary="onPrimarySubmit"
      />
    </template>
  </PackWorkflowModal>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style src="@/styles/views/activities/pack-workflow-modals.css"></style>
<style scoped>
.pack-shell-forward-wizard-hint {
  margin-top: 4px;
  font-size: 13px;
}

.pack-shell-forward-submit-error {
  margin: 0 0 10px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  font-size: 14px;
}

.pack-shell-forward-group-variance-msg {
  margin: 8px 0 0;
  padding: 8px 10px;
  border-radius: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  font-size: 13px;
}

.pack-shell-forward-group-deviation-banner {
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e40af;
  font-size: 14px;
}

.pack-shell-forward-history-hint {
  margin-top: 10px;
  font-size: 13px;
  line-height: 1.45;
}

.pack-shell-forward-history-hint--confirm {
  padding: 10px 12px;
  border-radius: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  font-weight: 500;
}

.pack-shell-forward-variance-actions {
  flex-wrap: wrap;
  gap: 4px;
}

.shell-forward-variance-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  font-size: 18px;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
}

.shell-forward-variance-btn--minus.shell-forward-variance-btn--active {
  border-color: #dc2626;
  background: #fef2f2;
  color: #b91c1c;
}

.shell-forward-variance-btn--plus.shell-forward-variance-btn--active {
  border-color: #ea580c;
  background: #fff7ed;
  color: #c2410c;
}


.pack-shell-forward-variance-msg {
  font-size: 13px;
  margin: 0 0 8px;
  font-weight: 600;
}

.pack-shell-forward-variance-msg--short {
  color: #b91c1c;
}

.pack-shell-forward-variance-msg--surplus {
  color: #c2410c;
}

.pack-shell-forward-surplus-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 10px 14px;
  margin-bottom: 10px;
}

.pack-shell-forward-surplus-qty {
  margin: 0;
}

.pack-shell-forward-inventory {
  margin: 8px 0;
  padding: 10px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.pack-shell-forward-inventory-intro {
  margin: 0 0 8px;
  font-size: 13px;
}

.pack-shell-forward-inventory-group-title {
  margin: 10px 0 4px;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
}

.pack-shell-forward-inventory-list {
  margin: 8px 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pack-shell-forward-inventory-check {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
  padding: 4px 0;
  cursor: pointer;
}

.pack-shell-forward-inventory-progress {
  margin: 0 0 10px;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
}

.pack-shell-forward-inventory-progress--ok {
  color: #15803d;
  font-weight: 600;
}

.pack-shell-forward-loss-block {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1 dashed #e2e8f0;
}

.pack-shell-forward-loss-hint {
  font-size: 12px;
  margin-bottom: 8px;
}

.pack-shell-forward-alt-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}

.pack-shell-forward-li--short {
  border-left: 3px solid #dc2626;
}

.pack-shell-forward-li--surplus {
  border-left: 3px solid #ea580c;
}

.pack-shell-forward-li--ok {
  border-left: 3px solid #16a34a;
}

.pack-shell-forward-li--pending {
  border-left: 3px solid #cbd5e1;
}
</style>
