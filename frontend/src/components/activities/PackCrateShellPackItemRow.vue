<script setup lang="ts">
import { IconArrowRight } from '@/components/icons'
import { computed, inject, unref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackMoveControls from '@/components/activities/PackMoveControls.vue'
import { isPackConfirmedStage } from '@/components/activities/packStageQuantities'
import PackCrateShellInlinePanel, {
  type PackCrateShellPeekSection,
} from '@/components/activities/PackCrateShellInlinePanel.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import { packShellContainerForPackItem } from '@/components/activities/packShellCrateHelpers'
import type { ActivityPackContainer } from '@/api/activityContainers'

defineOptions({ name: 'PackCrateShellPackItemRow' })

const props = defineProps<{
  shellPackItem: ActivityPackItem
  stageRightLabel: string
  /** Lagerort/Kiste — nur solange Material noch im Lager (Bestätigt links) */
  showStorageLocation?: boolean
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown> &
  Record<string, unknown>

const collapseKey = computed(() => `shell-pack-${props.shellPackItem.id}`)

function injectRef<T>(raw: unknown): T {
  return unref(raw as Ref<T> | T)
}

const shellContainer = computed((): ActivityPackContainer | undefined => {
  const list = injectRef<ActivityPackContainer[] | undefined>(ctx.packContainers)
  return packShellContainerForPackItem(props.shellPackItem, Array.isArray(list) ? list : [])
})

const displayName = computed(() => {
  const label = (shellContainer.value?.label ?? '').trim()
  const name = props.shellPackItem.materialName
  if (label && label !== name) return `${label} – ${name}`
  return name
})

const innerVisible = computed(
  () => !(ctx.isPackContainerCollapsed as (id: string) => boolean)(collapseKey.value),
)

const shellPeekSections = computed((): PackCrateShellPeekSection[] => {
  const fn = ctx.peekSectionsForShellPackItem as ((pi: ActivityPackItem) => PackCrateShellPeekSection[]) | undefined
  return fn ? fn(props.shellPackItem) : []
})

const shellPeekEmptyHint = computed(() => {
  const fn = ctx.crateShellPeekEmptyHint as ((pi: ActivityPackItem) => string) | undefined
  return fn ? fn(props.shellPackItem) : ''
})

const shellLineCount = computed(() =>
  shellPeekSections.value.reduce((n, sec) => n + sec.lines.length, 0),
)

function onToggleExpand() {
  const wasOpen = innerVisible.value
  ;(ctx.togglePackContainerCollapsed as (id: string) => void)(collapseKey.value)
  if (!wasOpen && shellLineCount.value > 0) {
    // Subsection defaults applied via defaultExpanded on inline panel
  }
}

const activePackStage = computed(() => {
  const raw = ctx.activePackStage as unknown
  if (raw == null) return ''
  return String(unref(raw as Ref<string> | string))
})

const useQtyMoveControls = computed(() => isPackConfirmedStage(activePackStage.value as import('@/components/activities/packStageQuantities').PackStage))

const shellCanMoveForward = computed(() => {
  const fn = ctx.packIssueForwardMax as ((p: ActivityPackItem) => number) | undefined
  return fn ? fn(props.shellPackItem) > 0 : false
})

const shellMoveQty = computed(() => {
  const inputs = ctx.moveQtyInputs as unknown
  const map =
    inputs != null ? (unref(inputs as Ref<Record<string, number>> | Record<string, number>) as Record<string, number>) : {}
  const maxFn = ctx.packIssueForwardMax as ((p: ActivityPackItem) => number) | undefined
  return map[props.shellPackItem.id] ?? (maxFn ? maxFn(props.shellPackItem) : 0)
})

function moveShellCrateForward(qtyFromControl?: number) {
  const maxFn = ctx.packIssueForwardMax as ((p: ActivityPackItem) => number) | undefined
  const moveFn = ctx.moveToNextStage as ((p: ActivityPackItem, qty?: number) => void | Promise<void>) | undefined
  if (!maxFn || !moveFn) return
  const max = maxFn(props.shellPackItem)
  if (max < 1) return
  const raw = qtyFromControl ?? shellMoveQty.value
  const moveQty = Math.min(max, Math.max(1, Math.floor(Number(raw) || max)))
  void moveFn(props.shellPackItem, moveQty)
}
</script>

<template>
  <div
    :id="'pack-shell-row-' + shellPackItem.id"
    class="pack-container-card pack-container-card--shell"
  >
    <div class="pack-container-header-row">
      <button
        type="button"
        class="pack-container-chevron-btn"
        :aria-expanded="innerVisible"
        :aria-label="t('activities.packList.ariaToggleContainer')"
        @click.stop="onToggleExpand"
      >
        <span class="pack-container-chevron" aria-hidden="true">{{ innerVisible ? '▼' : '▶' }}</span>
      </button>
      <div class="pack-container-header-main">
        <div class="pack-container-header-title-block pack-container-header-title-block--shell">
          <span class="pack-container-name">{{ displayName }}</span>
          <span
            v-if="shellPackItem.materialType === 'physical_combo'"
            class="pack-combo-badge"
            :title="t('activities.detail.comboPhysicalTitle')"
          >{{ t('activities.detail.comboPhysicalShort') }}</span>
          <span
            v-else-if="shellPackItem.linkedContainerLabel"
            class="pack-combo-badge pack-combo-badge--kiste"
            :title="t('activities.packList.kisteLabel', { label: shellPackItem.linkedContainerLabel })"
          >{{ shellPackItem.linkedContainerLabel }}</span>
          <span v-if="shellLineCount > 0" class="pack-container-chip text-muted">{{
            t('activities.common.itemsUnit', { count: shellLineCount })
          }}</span>
        </div>
      </div>
      <div v-if="ctx.packListEditable && shellCanMoveForward" class="pack-container-header-actions" @click.stop>
        <PackMoveControls
          v-if="useQtyMoveControls"
          direction="forward"
          :qty="shellMoveQty"
          :max="(ctx.packIssueForwardMax as (p: ActivityPackItem) => number)(shellPackItem)"
          :disabled="ctx.movingId === shellPackItem.id"
          :forward-title="
            (ctx.forwardMoveTitleForItem as (p: ActivityPackItem) => string | undefined)?.(shellPackItem) ?? ''
          "
          @update:qty="(ctx.setMoveQtyForItem as (id: string, n: number) => void)(shellPackItem.id, $event)"
          @move="moveShellCrateForward"
        />
        <button
          v-else
          type="button"
          class="btn-move-arrow btn-move-arrow--container-header"
          :disabled="ctx.movingId === shellPackItem.id"
          :title="
            t('activities.packList.shellMoveWholeCrateTitle', {
              stage: stageRightLabel,
            })
          "
          @click="moveShellCrateForward"
        >
          <IconArrowRight />
        </button>
      </div>
    </div>
    <div v-show="innerVisible" class="pack-container-inner pack-container-inner--shell">
      <div
        v-if="
          showStorageLocation &&
          (shellPackItem.storageAddressName ||
            shellPackItem.storageSlotName ||
            shellPackItem.linkedContainerLabel)
        "
        class="pack-shell-storage text-muted"
      >
        <div v-if="shellPackItem.linkedContainerLabel">
          {{ t('activities.packList.kisteLabel', { label: shellPackItem.linkedContainerLabel }) }}
        </div>
        <div v-if="shellPackItem.storageAddressName">
          {{ t('activities.packList.storageLabel', { name: shellPackItem.storageAddressName }) }}
        </div>
        <div v-if="shellPackItem.storageSlotName">
          {{ t('activities.packList.slotLabel', { name: shellPackItem.storageSlotName }) }}
        </div>
      </div>
      <PackCrateShellInlinePanel
        :sections="shellPeekSections"
        :empty-hint="shellPeekEmptyHint"
        :loose-issue-container-id="shellContainer?.id ?? null"
        :loose-issue-crate-label="displayName"
        :stage-right-label="stageRightLabel"
        :reality-banner="
          (ctx.crateRealityBannerForPackItem as ((p: ActivityPackItem) => string | null) | undefined)?.(
            shellPackItem,
          ) ?? null
        "
        :show-template-toggle="
          (ctx.showCrateTemplateToggle as ((p: ActivityPackItem) => boolean) | undefined)?.(shellPackItem) ??
          false
        "
        :use-reality-view="
          (ctx.useCrateRealityForPackItem as ((id: string) => boolean) | undefined)?.(shellPackItem.id) ??
          true
        "
        separate-section-rows
        :default-expanded="innerVisible"
        @toggle-reality-view="
          (ctx.toggleCrateRealityView as ((p: ActivityPackItem) => void) | undefined)?.(shellPackItem)
        "
      />
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style src="@/styles/views/activities/pack-container-card.css"></style>
<style src="@/styles/views/activities/pack-shell-combo.css"></style>
<style scoped>
.pack-combo-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  background: #ede9fe;
  color: #5b21b6;
  flex-shrink: 0;
}
.pack-combo-badge--kiste {
  background: #dbeafe;
  color: #1e40af;
}
</style>
