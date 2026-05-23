<script setup lang="ts">
import { IconArrowRight } from '@/components/icons'
import { computed, inject, unref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import { isPackForwardToEventStage } from '@/components/activities/packStageQuantities'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import type { PackCrateShellPeekLine } from '@/components/activities/PackCrateShellInlinePanel.vue'

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
    packListEditable.value &&
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
  const keyFn = ctx.containerIssueLineKey as ((containerId: string, rowId: string) => string) | undefined
  const inputs = injectRef<Record<string, number> | undefined>(ctx.containerUnissueLineInputs)
  const k = keyFn?.(props.containerId, ci.id)
  if (k && inputs?.[k] != null) return inputs[k]
  return unissueableMax.value || 1
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

const remainingIssue = computed(() => {
  const ci = containerItem.value
  if (!ci) return 0
  const fn = ctx.containerLineRemainingIssue as ((row: ActivityPackContainerItem) => number) | undefined
  return fn ? fn(ci) : 0
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
        <template v-if="remainingIssue > 0">
          {{
            t('activities.packList.lineNotYetIssued', {
              rem: remainingIssue,
              packed: containerItem.quantity_packed,
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
      v-if="showControls && unissueableMax > 0"
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
    <div v-if="showControls && issueableMax > 0" class="pack-card-actions">
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
          <IconArrowRight />
        </button>
      </div>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/pack-container-card.css"></style>
<style src="@/styles/views/activities/pack-shell-combo.css"></style>
