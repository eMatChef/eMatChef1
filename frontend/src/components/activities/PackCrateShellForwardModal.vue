<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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
  buildPackCrateCheckLinesPayload,
  defaultLineReview,
  allInventoryLocationsSettled,
  applyInventoryLocationCounted,
  buildInitialInventoryLocationReviews,
  buildMiniInventoryLocations,
  formatStorageLocationPlaceLabel,
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
  submitting: boolean
  emptyHint: string
  embeddedIssuesByLineKey: Record<string, ActivityIssueReportRow[]>
  repackIssueReviews: Record<string, 'ok' | 'problem' | null>
  orphanIssues: ActivityIssueReportRow[]
}>()

const emit = defineEmits<{
  cancel: []
  submit: [payload: PackCrateCheckRequest]
  'set-repack-review': [issueId: string, status: 'ok' | 'problem']
}>()

const { t } = useI18n()

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

function patchReview(key: string, expectedQty: number, isExtra: boolean, patch: Partial<ShellForwardLineReview>) {
  const cur = reviewFor(key, expectedQty)
  const touchesLineCount =
    patch.countedQty !== undefined || patch.status !== undefined || patch.resolution !== undefined
  const merged = touchesLineCount
    ? applyCountedQtyToReview({ ...cur, ...patch }, expectedQty, isExtra)
    : { ...cur, ...patch }
  lineReviews.value = { ...lineReviews.value, [key]: merged }
}

function onCountedChange(line: ShellForwardCheckLine, raw: number) {
  const r = reviewFor(line.key, line.quantity)
  patchReview(line.key, line.quantity, line.isExtra, { countedQty: raw })
  const updated = lineReviews.value[line.key]
  if (!updated || updated.status !== 'problem' || line.isExtra) return
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

function syncInventoryCompletion(line: ShellForwardCheckLine) {
  const r = reviewFor(line.key, line.quantity)
  const merged = { ...r }
  const allDone = allInventoryLocationsSettled(merged)
  patchReview(line.key, line.quantity, line.isExtra, {
    inventoryPhase: allDone ? 'done' : 'active',
    resolution: allDone ? 'loss' : r.resolution,
    missingQty: shortfallQty(line.quantity, r.countedQty),
    note: allDone ? r.note || t('activities.packList.shellForwardPreEventLossNote') : r.note,
    doReplenishAfterLoss: allDone && looseStock(line.materialItemId) > 0,
    replenishQty:
      allDone && looseStock(line.materialItemId) > 0
        ? Math.min(shortfallQty(line.quantity, r.countedQty), looseStock(line.materialItemId))
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
  patchReview(line.key, line.quantity, line.isExtra, {
    countedQty: line.quantity,
    status: 'ok',
    resolution: null,
    inventoryPhase: 'none',
  })
}

function lineSettled(line: ShellForwardCheckLine): boolean {
  const r = lineReviews.value[line.key]
  if (!r || r.status === null) return false
  const issues = props.embeddedIssuesByLineKey[line.key] ?? []
  if (issues.some((em) => props.repackIssueReviews[em.id] !== 'ok')) return false
  if (r.status === 'ok') return true
  if (!r.resolution) return false
  if (r.resolution === 'extra') {
    return r.note.trim() !== '' || (r.missingQty != null && r.missingQty >= 1)
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
  if (canSubmitComplete.value) return t('activities.packList.shellForwardSubmitOk')
  if (canSubmitIncomplete.value) {
    return t('activities.packList.shellForwardSubmitWithProtocol', { label: props.label })
  }
  return t('activities.packList.shellForwardSubmitPickActionShort')
})

function initReviews() {
  const next: Record<string, ShellForwardLineReview> = {}
  for (const line of checkLines.value) {
    next[line.key] = defaultLineReview(line.quantity)
  }
  lineReviews.value = next
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) initReviews()
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
  if (r.countedQty === line.quantity) return 'ok'
  if (r.countedQty < line.quantity) return 'short'
  if (r.countedQty > line.quantity) return 'surplus'
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
  }
}
</script>

<template>
  <PackWorkflowModal :open="open" size="lg" @cancel="emit('cancel')">
    <template #title>{{ t('activities.packList.shellForwardTitle') }}</template>
    <template #intro>
      <p class="pack-modal-hint text-muted">
        {{ t('activities.packList.shellForwardIntro', { label, n: moveQty }) }}
      </p>
      <p class="pack-modal-hint text-muted pack-shell-forward-wizard-hint">
        {{ t('activities.packList.shellForwardWizardHint') }}
      </p>
      <p v-if="historyPrefillHint" class="pack-modal-hint text-muted pack-shell-forward-history-hint">
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
            }"
          >
            <template v-for="cl in [asCheckLine(sec, line)]" :key="cl.key">
              <div class="pack-shell-forward-li-row">
                <div class="pack-shell-forward-li-main pack-shell-forward-li-main--stacked">
                  <div class="pack-shell-forward-li-name-qty">
                    <span class="pack-shell-forward-li-name">{{ line.materialName }}</span>
                    <span class="text-muted pack-shell-forward-soll">
                      {{ t('activities.packList.shellForwardExpectedQty', { n: line.quantity }) }}
                    </span>
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
                        const next = Math.max(0, reviewFor(cl.key, cl.quantity).countedQty - 1)
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
                        const next = reviewFor(cl.key, cl.quantity).countedQty + 1
                        patchReview(cl.key, cl.quantity, cl.isExtra, { countedQty: next })
                        onCountedChange(cl, next)
                      })()
                    "
                  >
                    +
                  </button>
                  <PackShellCheckToggle
                    ok-only
                    :ok-active="varianceKind(cl) === 'ok'"
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

              <!-- Surplus note -->
              <div
                v-if="varianceKind(cl) === 'surplus' && reviewFor(cl.key, cl.quantity).status === 'problem'"
                class="pack-shell-forward-li-problem"
              >
                <p class="pack-shell-forward-variance-msg pack-shell-forward-variance-msg--surplus">
                  {{
                    t('activities.packList.shellForwardSurplusMsg', {
                      n: surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                    })
                  }}
                </p>
                <label class="pack-modal-label">
                  <span>{{ t('activities.packList.shellForwardLineNote') }}</span>
                  <input
                    :value="reviewFor(cl.key, cl.quantity).note"
                    type="text"
                    class="form-input"
                    @input="
                      patchReview(cl.key, cl.quantity, cl.isExtra, {
                        note: ($event.target as HTMLInputElement).value,
                        resolution: 'extra',
                        missingQty: surplusQty(cl.quantity, reviewFor(cl.key, cl.quantity).countedQty),
                      })
                    "
                  />
                </label>
              </div>

              <!-- Shortfall: mini inventory + loss + replenish -->
              <div
                v-if="varianceKind(cl) === 'short' && reviewFor(cl.key, cl.quantity).status === 'problem'"
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
                        :disabled="reviewFor(cl.key, cl.quantity).inventoryPhase === 'done'"
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
                        :disabled="reviewFor(cl.key, cl.quantity).inventoryPhase === 'done'"
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
                    ['done', 'skipped'].includes(reviewFor(cl.key, cl.quantity).inventoryPhase) ||
                    (reviewFor(cl.key, cl.quantity).inventoryPhase === 'active' &&
                      reviewFor(cl.key, cl.quantity).inventoryLocations.length === 0)
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
        {{ t('activities.packList.shellForwardNeedAllLines') }}
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

.pack-shell-forward-soll {
  font-size: 12px;
  margin-left: 6px;
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

.pack-shell-forward-count-input {
  width: 3.5rem;
  text-align: center;
  padding: 4px 6px;
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
</style>
