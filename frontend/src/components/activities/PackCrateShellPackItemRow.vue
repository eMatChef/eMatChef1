<script setup lang="ts">
import { IconArrowRight } from '@/components/icons'
import { computed, inject, unref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackMoveControls from '@/components/activities/PackMoveControls.vue'
import PackMaterialStorageStack from '@/components/activities/PackMaterialStorageStack.vue'
import { isPackConfirmedStage } from '@/components/activities/packStageQuantities'
import PackCrateShellInlinePanel, {
  type PackCrateShellPeekSection,
} from '@/components/activities/PackCrateShellInlinePanel.vue'
import PackContainerSubsectionsList from '@/components/activities/PackContainerSubsectionsList.vue'
import type { PackContainerItemSection } from '@/components/activities/packShellCrateHelpers'
import { injectPackCtxBool, PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import { packShellContainerForPackItem } from '@/components/activities/packShellCrateHelpers'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackContainerItem } from '@/api/activityContainers'

defineOptions({ name: 'PackCrateShellPackItemRow' })

const props = defineProps<{
  shellPackItem: ActivityPackItem
  stageRightLabel: string
  /** Lagerort/Kiste — nur solange Material noch im Lager (Bestätigt links) */
  showStorageLocation?: boolean
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY)!

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

const useLinkedContainerSubsections = computed(() => shellContainer.value != null)

const shellPeekSections = computed((): PackCrateShellPeekSection[] => {
  if (useLinkedContainerSubsections.value) return []
  const fn = ctx.peekSectionsForShellPackItem as ((pi: ActivityPackItem) => PackCrateShellPeekSection[]) | undefined
  return fn ? fn(props.shellPackItem) : []
})

const shellContainerSections = computed((): PackContainerItemSection[] => {
  const c = shellContainer.value
  if (!c) return []
  const fn = ctx.packContainerItemSections as ((container: ActivityPackContainer) => PackContainerItemSection[]) | undefined
  return fn ? fn(c) : []
})

const shellPeekEmptyHint = computed(() => {
  const fn = ctx.crateShellPeekEmptyHint as ((pi: ActivityPackItem) => string) | undefined
  return fn ? fn(props.shellPackItem) : ''
})

const shellLineCount = computed(() => {
  if (useLinkedContainerSubsections.value) {
    return shellContainerSections.value.reduce((n, sec) => n + sec.lines.length, 0)
  }
  return shellPeekSections.value.reduce((n, sec) => n + sec.lines.length, 0)
})

function isPreviewLine(ci: ActivityPackContainerItem): boolean {
  const fn = ctx.isVirtualWarehouseContainerLine as ((row: ActivityPackContainerItem) => boolean) | undefined
  return fn ? fn(ci) : false
}

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

const packForwardEditable = computed(() => injectPackCtxBool(ctx, 'packForwardEditable'))

const showCrateCheckBtn = computed(() => {
  const fn = ctx.showShellCrateCheckButton as ((p: ActivityPackItem) => boolean) | undefined
  return fn ? fn(props.shellPackItem) : false
})

const crateCheckBtnLabel = computed(() => {
  const fn = ctx.shellCrateCheckButtonLabel as ((p: ActivityPackItem) => string) | undefined
  return fn ? fn(props.shellPackItem) : ''
})

const crateCheckSubmitting = computed(() => Boolean(unref(ctx.shellForwardSubmitting as Ref<boolean> | boolean | undefined)))

function onCrateCheckClick() {
  const fn = ctx.openShellCrateCheckOnlyModal as ((p: ActivityPackItem) => void | Promise<void>) | undefined
  if (fn) void fn(props.shellPackItem)
}

const shellMoveQty = computed(() => {
  const inputs = ctx.moveQtyInputs as unknown
  const map =
    inputs != null ? (unref(inputs as Ref<Record<string, number>> | Record<string, number>) as Record<string, number>) : {}
  const maxFn = ctx.packIssueForwardMax as ((p: ActivityPackItem) => number) | undefined
  return map[props.shellPackItem.id] ?? (maxFn ? maxFn(props.shellPackItem) : 0)
})

const shellForwardLimits = computed(() => {
  const fn = ctx.packForwardMoveControlLimits as
    | ((p: ActivityPackItem) => { max: number; inputMax: number; warnIfBelow?: number })
    | undefined
  return fn
    ? fn(props.shellPackItem)
    : { max: 0, inputMax: 1, warnIfBelow: undefined as number | undefined }
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
      <div
        v-if="packForwardEditable && (shellCanMoveForward || showCrateCheckBtn)"
        class="pack-container-header-actions"
        @click.stop
      >
        <button
          v-if="showCrateCheckBtn"
          type="button"
          class="btn-outline btn-sm pack-shell-crate-check-btn"
          :disabled="ctx.movingId === shellPackItem.id || crateCheckSubmitting"
          @click="onCrateCheckClick"
        >
          {{ crateCheckBtnLabel }}
        </button>
        <PackMoveControls
          v-if="shellCanMoveForward && useQtyMoveControls"
          direction="forward"
          :qty="shellMoveQty"
          :max="shellForwardLimits.max"
          :input-max="shellForwardLimits.inputMax"
          :warn-if-below="shellForwardLimits.warnIfBelow"
          :disabled="ctx.movingId === shellPackItem.id"
          :forward-title="
            (ctx.forwardMoveTitleForItem as (p: ActivityPackItem) => string | undefined)?.(shellPackItem) ?? ''
          "
          @update:qty="(ctx.setMoveQtyForItem as (id: string, n: number) => void)(shellPackItem.id, $event)"
          @move="moveShellCrateForward"
        />
        <button
          v-else-if="shellCanMoveForward"
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
        v-if="showStorageLocation && shellPackItem.linkedContainerLabel"
        class="pack-card-kiste text-muted pack-shell-storage-kiste"
      >
        {{ t('activities.packList.kisteLabel', { label: shellPackItem.linkedContainerLabel }) }}
      </div>
      <PackMaterialStorageStack
        v-if="showStorageLocation"
        :storage="shellPackItem"
        variant="shell"
      />
      <PackContainerSubsectionsList
        v-if="useLinkedContainerSubsections && shellContainer"
        :container="shellContainer"
      >
        <template #line="{ ci }">
          <div
            class="pack-container-line pack-container-line--peek"
            :class="{ 'pack-container-line--preview': isPreviewLine(ci) }"
          >
            <div class="pack-container-line-main">
              <span class="pack-container-line-name">{{
                ci.material_name || t('common.material')
              }}</span>
              <span class="pack-container-line-qty">{{
                t('activities.packList.qtyInContainerLine', { n: ci.quantity_packed ?? 0 })
              }}</span>
            </div>
          </div>
        </template>
        <template #empty>
          <p class="pack-container-empty text-muted">{{ shellPeekEmptyHint }}</p>
        </template>
      </PackContainerSubsectionsList>
      <PackCrateShellInlinePanel
        v-else
        :sections="shellPeekSections"
        :empty-hint="shellPeekEmptyHint"
        :check-pack-item="shellPackItem"
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
        :default-expanded="false"
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
