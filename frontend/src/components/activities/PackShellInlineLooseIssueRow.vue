<script setup lang="ts">
import { computed, inject, unref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import { isPackForwardToEventStage } from '@/components/activities/packStageQuantities'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import type { PackCrateShellPeekLine } from '@/components/activities/packCrateShellPeekTypes'

defineOptions({ name: 'PackShellInlineLooseIssueRow' })

const props = defineProps<{
  containerId: string
  line: PackCrateShellPeekLine
  crateLabel: string
  stageRightLabel: string
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown> &
  Record<string, unknown>

function injectRef<T>(raw: unknown): T {
  return unref(raw as Ref<T> | T)
}

const activePackStage = computed(() => {
  const raw = ctx.activePackStage as unknown
  if (raw == null) return ''
  return String(unref(raw as Ref<string> | string))
})

const packListEditable = computed(() => Boolean(injectRef(ctx.packListEditable)))
const packForwardEditable = computed(() => Boolean(injectRef(ctx.packForwardEditable)))
const packBackwardEditable = computed(() => Boolean(injectRef(ctx.packBackwardEditable)))
const containerMutationLoading = computed(() => Boolean(injectRef(ctx.containerMutationLoading)))

const containerItem = computed((): ActivityPackContainerItem | null => {
  const map = injectRef<Record<string, ActivityPackContainerItem[]> | undefined>(ctx.containerItemsByContainerId)
  const rows = map?.[props.containerId] ?? []
  const byId = rows.find((ci) => ci.id === props.line.id)
  if (byId) return byId
  const mid = (props.line.materialItemId ?? '').trim()
  if (mid) {
    return rows.find((ci) => ci.material_item_id === mid) ?? null
  }
  return null
})

const showControls = computed(
  () =>
    (packForwardEditable.value || packBackwardEditable.value) &&
    isPackForwardToEventStage(activePackStage.value as import('@/components/activities/packStageQuantities').PackStage) &&
    containerItem.value != null,
)

const issueableMax = computed(() => {
  const ci = containerItem.value
  if (!ci) return 0
  const fn = ctx.containerLineIssueableMax as ((row: ActivityPackContainerItem) => number) | undefined
  return fn ? fn(ci) : 0
})

const unissueableMax = computed(() => {
  const ci = containerItem.value
  if (!ci) return 0
  const fn = ctx.containerLineUnissueableMax as ((row: ActivityPackContainerItem) => number) | undefined
  return fn ? fn(ci) : 0
})

const unissueQty = computed(() => {
  const ci = containerItem.value
  if (!ci) return 1
  const fn = ctx.containerUnissueLineInputValue as
    | ((containerId: string, row: ActivityPackContainerItem) => number)
    | undefined
  return fn ? fn(props.containerId, ci) : unissueableMax.value || 1
})

const issueQty = computed(() => {
  const ci = containerItem.value
  if (!ci) return 1
  const fn = ctx.containerIssueLineInputValue as
    | ((containerId: string, row: ActivityPackContainerItem) => number)
    | undefined
  return fn ? fn(props.containerId, ci) : issueableMax.value || 1
})

const issueTitle = computed(() => {
  const ci = containerItem.value
  if (!ci) return ''
  const fn = ctx.containerIssueLineLooseTitle as
    | ((containerId: string, row: ActivityPackContainerItem) => string)
    | undefined
  return fn ? fn(props.containerId, ci) : ''
})

function onIssueInput(event: Event) {
  const ci = containerItem.value
  if (!ci) return
  const fn = ctx.setContainerIssueLineInput as
    | ((containerId: string, row: ActivityPackContainerItem, value: number | string) => void)
    | undefined
  fn?.(props.containerId, ci, (event.target as HTMLInputElement).value)
}

function onIssueClick() {
  const ci = containerItem.value
  if (!ci) return
  const fn = ctx.issueContainerLineToEvent as
    | ((containerId: string, row: ActivityPackContainerItem) => void | Promise<void>)
    | undefined
  void fn?.(props.containerId, ci)
}

function onUnissueInput(event: Event) {
  const ci = containerItem.value
  if (!ci) return
  const fn = ctx.setContainerUnissueLineInput as
    | ((containerId: string, row: ActivityPackContainerItem, value: number | string) => void)
    | undefined
  fn?.(props.containerId, ci, (event.target as HTMLInputElement).value)
}

function onUnissueClick() {
  const ci = containerItem.value
  if (!ci) return
  const fn = ctx.unissueContainerLineToPacked as
    | ((containerId: string, row: ActivityPackContainerItem) => void | Promise<void>)
    | undefined
  void fn?.(props.containerId, ci)
}

const lineIssueDisplay = computed(() => {
  const ci = containerItem.value
  if (!ci) return { rem: 0, packed: 0, missingFromPlan: 0 }
  const fn = ctx.containerLineIssueDisplay as
    | ((row: ActivityPackContainerItem) => { rem: number; packed: number; missingFromPlan: number })
    | undefined
  if (fn) return fn(ci)
  const remFn = ctx.containerLineRemainingIssue as ((row: ActivityPackContainerItem) => number) | undefined
  return {
    rem: remFn ? remFn(ci) : 0,
    packed: ci.quantity_packed ?? 0,
    missingFromPlan: 0,
  }
})

const contentsTravelWithShellAtEvent = computed(() => {
  const fn = ctx.containerContentsTravelWithShellAtEvent as ((containerId: string) => boolean) | undefined
  return fn ? fn(props.containerId) : false
})

const lineIssuedDisplayQty = computed(() => {
  const ci = containerItem.value
  if (!ci) return props.line.quantity
  const fn = ctx.containerLineIssuedDisplayQty as ((row: ActivityPackContainerItem) => number) | undefined
  return fn ? fn(ci) : ci.quantity_issued ?? props.line.quantity
})

const lineIssuedDisplayPacked = computed(() => {
  const ci = containerItem.value
  if (!ci) return props.line.quantity
  const fn = ctx.containerLineIssuedDisplayPacked as ((row: ActivityPackContainerItem) => number) | undefined
  return fn ? fn(ci) : ci.quantity_packed ?? props.line.quantity
})
</script>

<template>
  <div
    v-if="containerItem"
    class="pack-container-line pack-container-line--issue-row pack-container-line--stacked"
  >
    <div class="pack-container-line-main">
      <span class="pack-container-line-name">{{ line.materialName }}</span>
      <span v-if="line.serialHint" class="pack-combo-crate-inline__serial text-muted">
        {{ t('activities.packList.shellForwardSerialSn', { serial: line.serialHint }) }}
      </span>
      <span class="pack-container-line-qty text-muted">
        <template v-if="lineIssueDisplay.rem > 0">
          {{
            lineIssueDisplay.missingFromPlan > 0
              ? t('activities.packList.lineNotYetIssuedCrateGap', {
                  rem: lineIssueDisplay.rem,
                  packed: lineIssueDisplay.packed,
                  missing: lineIssueDisplay.missingFromPlan,
                  stage: stageRightLabel,
                })
              : t('activities.packList.lineNotYetIssued', {
                  rem: lineIssueDisplay.rem,
                  packed: lineIssueDisplay.packed,
                  stage: stageRightLabel,
                })
          }}
        </template>
        <template v-else-if="contentsTravelWithShellAtEvent">
          {{
            t('activities.packList.issuedFraction', {
              issued: lineIssuedDisplayQty,
              packed: lineIssuedDisplayPacked,
              stage: stageRightLabel,
            })
          }}
        </template>
        <template v-else>
          {{
            t('activities.common.piecesShort', {
              count: line.quantity,
            })
          }}
        </template>
      </span>
    </div>
    <div
      v-if="showControls && packBackwardEditable && unissueableMax > 0"
      class="pack-card-actions pack-card-actions-left"
    >
      <button
        type="button"
        class="btn-moveback-arrow"
        :disabled="containerMutationLoading"
        :title="t('activities.packList.unissueLineTitle', { max: unissueableMax })"
        @click="onUnissueClick"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M19 12H5" />
          <polyline points="12 19 5 12 12 12 5" />
        </svg>
      </button>
      <input
        :value="unissueQty"
        type="number"
        min="1"
        :max="unissueableMax"
        class="pack-moveback-input"
        @input="onUnissueInput"
        @keyup.enter="onUnissueClick"
      />
    </div>
    <div v-if="showControls && packForwardEditable && issueableMax > 0" class="pack-card-actions">
      <div class="pack-move-inline">
        <input
          :value="issueQty"
          type="number"
          min="1"
          :max="issueableMax"
          class="pack-move-input"
          @input="onIssueInput"
          @keyup.enter="onIssueClick"
        />
        <button
          type="button"
          class="btn-move-arrow"
          :disabled="containerMutationLoading"
          :title="issueTitle"
          @click="onIssueClick"
        >
          <v-icon icon="mdi-arrow-right" size="12" />
        </button>
      </div>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/pack-container-card.css"></style>
