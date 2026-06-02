<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackMaterialMeta from '@/components/activities/PackMaterialMeta.vue'
import PackContainerSubsectionsList from '@/components/activities/PackContainerSubsectionsList.vue'
import PackRetourAccountingStack from '@/components/activities/PackRetourAccountingStack.vue'
import PackContainerLineIssueQuick from '@/components/activities/PackContainerLineIssueQuick.vue'
import PackUnpackStoreControls from '@/components/activities/PackUnpackStoreControls.vue'
import PackUnpackUnstoreControls from '@/components/activities/PackUnpackUnstoreControls.vue'
import IconArrowRight from '@/components/icons/IconArrowRight.vue'
import { EButton } from '@/components/form/base'
import { injectPackCtxBool, PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import type { PackRetourAccounting } from '@/components/activities/packNotTakenHelpers'

defineOptions({ name: 'PackUnpackWarehouseContainerCard' })

const props = withDefaults(
  defineProps<{
    container: ActivityPackContainer
    /** pending = noch einzulagern (links); stored = bereits eingelagert (rechts) */
    variant?: 'pending' | 'stored'
  }>(),
  { variant: 'pending' },
)

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>

const packListEditable = computed(() => injectPackCtxBool(ctx, 'packListEditable'))

const innerVisible = computed(
  () => !(ctx.isPackContainerCollapsed as (id: string) => boolean)(props.container.id),
)

const isPhysicalCombo = computed(() =>
  (ctx.isPhysicalComboContainer as (id: string) => boolean)?.(props.container.id) ?? false,
)

function lineRawPendingQty(ci: ActivityPackContainerItem): number {
  return Math.max(0, (ci.quantity_returned ?? 0) - (ci.quantity_stored ?? 0))
}

function lineRemainingStore(ci: ActivityPackContainerItem): number {
  const fn = ctx.containerLineRemainingStore as ((row: ActivityPackContainerItem) => number) | undefined
  const target = lineStoreTarget(ci)
  return fn?.(target) ?? lineRawPendingQty(target)
}

function linePendingQty(ci: ActivityPackContainerItem): number {
  return lineRemainingStore(ci)
}

function lineStoredQty(ci: ActivityPackContainerItem): number {
  const target = lineStoreTarget(ci)
  return target.quantity_stored ?? 0
}

function lineStoreTarget(ci: ActivityPackContainerItem): ActivityPackContainerItem {
  const fn = ctx.resolveActionableContainerLine as
    | ((cid: string, row: ActivityPackContainerItem) => ActivityPackContainerItem)
    | undefined
  return fn?.(props.container.id, ci) ?? ci
}

function lineHasUnpackVisibleQty(ci: ActivityPackContainerItem): boolean {
  if (props.variant === 'stored') return lineStoredQty(ci) > 0
  return linePendingQty(ci) > 0
}

const sectionLines = computed((): ActivityPackContainerItem[] => {
  const fn = ctx.packContainerItemSections as
    | ((c: ActivityPackContainer) => Array<{ lines: ActivityPackContainerItem[] }>)
    | undefined
  return fn?.(props.container)?.flatMap((s) => s.lines) ?? []
})

const headerUnits = computed(() => {
  if (props.variant === 'stored') {
    const fn = ctx.containerStoredDisplayUnits as ((id: string) => number) | undefined
    if (fn) return fn(props.container.id) ?? 0
    const fallback = ctx.containerStoredContentUnits as ((id: string) => number) | undefined
    return fallback?.(props.container.id) ?? 0
  }
  const fn = ctx.containerPendingStoreUnits as ((id: string) => number) | undefined
  return fn?.(props.container.id) ?? 0
})

const showReturnedShell = computed(() => {
  if (props.variant === 'stored') {
    const fn = ctx.containerShowsStoredShell as ((id: string) => boolean) | undefined
    return fn?.(props.container.id) ?? false
  }
  const fn = ctx.containerShowsPendingUnpackShell as ((id: string) => boolean) | undefined
  return fn?.(props.container.id) ?? false
})

const hasVisibleUnpackLines = computed(
  () => sectionLines.value.some((ci) => lineHasUnpackVisibleQty(ci)) || showReturnedShell.value,
)

const shellPackItem = computed((): ActivityPackItem | null => {
  const fn = ctx.shellPackItemForContainer as ((id: string) => ActivityPackItem | undefined) | undefined
  return fn?.(props.container.id) ?? null
})

const shellPendingQty = computed(() => {
  const fn = ctx.containerShellPendingStoreQty as ((id: string) => number) | undefined
  return fn?.(props.container.id) ?? 0
})

const showShellIssueQuick = computed(() => {
  const fn = ctx.showPackIssueForShellUnpack as ((id: string) => boolean) | undefined
  return fn?.(props.container.id) ?? false
})

function isConsumableLine(ci: ActivityPackContainerItem): boolean {
  const mid = ci.material_item_id
  if (!mid) return false
  return (ctx.isPackMaterialConsumable as (id: string) => boolean)(mid)
}

function emitNachbuchungForLine(ci: ActivityPackContainerItem): void {
  const mid = ci.material_item_id
  if (!mid) return
  ;(ctx.emitConsumableNachbuchungForMaterial as (id: string) => void)?.(mid)
}

const canNachbuchung = computed(() => injectPackCtxBool(ctx, 'canRequestConsumableNachbuchung'))

function emitShellConsumption(): void {
  const sh = shellPackItem.value
  if (!sh) return
  ;(ctx.emitConsumptionForMaterialId as (id: string, hints?: unknown) => void)(sh.materialItemId, {
    materialName: sh.materialName,
    linkedContainerLabel: sh.linkedContainerLabel ?? null,
  })
}

function emitShellIssue(issueType: 'loss' | 'repair'): void {
  const sh = shellPackItem.value
  if (!sh) return
  ;(ctx.emitIssueWizardByMaterialId as (id: string, type: 'loss' | 'repair') => void)(
    sh.materialItemId,
    issueType,
  )
}

function packItemForLine(ci: ActivityPackContainerItem): ActivityPackItem | undefined {
  const fn = ctx.packItemForMaterialItemId as ((id: string) => ActivityPackItem | undefined) | undefined
  return fn?.(ci.material_item_id)
}

function lineRetourAccounting(ci: ActivityPackContainerItem): PackRetourAccounting {
  const target = lineStoreTarget(ci)
  const fn = ctx.retourAccountingForContainerLine as
    | ((row: ActivityPackContainerItem) => PackRetourAccounting)
    | undefined
  const returnedBooked = target.quantity_returned ?? 0
  const fallback: PackRetourAccounting = {
    packed: target.quantity_packed ?? 0,
    replenishment: 0,
    issued: target.quantity_issued ?? 0,
    neverIssued: 0,
    notTaken: 0,
    consumed: 0,
    loss: 0,
    repair: 0,
    returnedBooked,
    retourTotal: returnedBooked,
    expectedReturn: returnedBooked,
  }
  return fn?.(target) ?? fallback
}

function lineIssuedQty(ci: ActivityPackContainerItem): number {
  return lineRetourAccounting(ci).issued
}

function lineReturnedQty(ci: ActivityPackContainerItem): number {
  return lineRetourAccounting(ci).retourTotal
}

function lineNotTakenQty(ci: ActivityPackContainerItem): number {
  return lineRetourAccounting(ci).notTaken
}

function lineConsumedQty(ci: ActivityPackContainerItem): number {
  return lineRetourAccounting(ci).consumed
}

function showIssueQuick(ci: ActivityPackContainerItem): boolean {
  if (props.variant !== 'pending') return false
  const fn = ctx.isVirtualWarehouseContainerLine as ((row: ActivityPackContainerItem) => boolean) | undefined
  if (fn?.(ci)) return false
  if (ci.material_item_id) {
    const isConsumableFn = ctx.isPackMaterialConsumable as ((id: string) => boolean) | undefined
    if (isConsumableFn?.(ci.material_item_id)) return false
  }
  if (linePendingQty(ci) > 0) return true
  const showFn = ctx.showPackIssueForContainerLine as
    | ((row: ActivityPackContainerItem, containerId: string) => boolean)
    | undefined
  const containerId = props.container.id
  return showFn?.(ci, containerId) ?? false
}

const showLineStoreControls = computed(
  () =>
    props.variant === 'pending' &&
    packListEditable.value &&
    !isPhysicalCombo.value,
)

function lineStoreControlsVisible(ci: ActivityPackContainerItem): boolean {
  if (!showLineStoreControls.value) return false
  const target = lineStoreTarget(ci)
  if (target.id.startsWith('wh-preview-') || target.id.startsWith('crate-check-')) return false
  return lineRemainingStore(ci) > 0
}

const showHeaderWholeStore = computed(
  () =>
    props.variant === 'pending' &&
    packListEditable.value &&
    isPhysicalCombo.value &&
    headerUnits.value > 0,
)

function onStoreLineInput(ci: ActivityPackContainerItem, event: Event): void {
  const fn = ctx.setContainerStoreLineInput as
    | ((cid: string, ci: ActivityPackContainerItem, value: number | string) => void)
    | undefined
  fn?.(props.container.id, ci, (event.target as HTMLInputElement).value)
}

function commitStoreLine(ci: ActivityPackContainerItem, event?: Event): void {
  if (event) event.preventDefault()
  void (ctx.storeContainerLineToWarehouse as (cid: string, ci: ActivityPackContainerItem) => void | Promise<void>)?.(
    props.container.id,
    ci,
  )
}

function lineStoredMoveBackMax(ci: ActivityPackContainerItem): number {
  const fn = ctx.unpackLineStoredQty as ((cid: string, row: ActivityPackContainerItem) => number) | undefined
  return fn?.(props.container.id, ci) ?? lineStoredQty(ci)
}

function commitUnstoreLine(ci: ActivityPackContainerItem, qty: number): void {
  void (ctx.unstoreContainerLineFromWarehouse as (
    cid: string,
    row: ActivityPackContainerItem,
    q?: number,
  ) => void | Promise<void>)?.(props.container.id, ci, qty)
}

const shellStoredMoveBackMax = computed(() => {
  const fn = ctx.unpackShellStoredQty as ((cid: string) => number) | undefined
  return fn?.(props.container.id) ?? 0
})

function commitUnstoreShell(qty: number): void {
  void (ctx.unstoreContainerShellFromWarehouse as (cid: string, q?: number) => void | Promise<void>)?.(
    props.container.id,
    qty,
  )
}

function onShellStoreInput(event: Event): void {
  ;(ctx.setContainerShellStoreInput as (cid: string, value: number | string) => void)?.(
    props.container.id,
    (event.target as HTMLInputElement).value,
  )
}

function commitShellStore(event?: Event): void {
  if (event) event.preventDefault()
  void (ctx.storeContainerShellToWarehouse as (cid: string) => void | Promise<void>)?.(props.container.id)
}

function commitPhysicalComboWhole(): void {
  void (ctx.storePhysicalComboContainerWhole as (cid: string) => void | Promise<void>)?.(props.container.id)
}
</script>

<template>
  <div
    :id="'pack-container-unpack-' + variant + '-' + container.id"
    class="pack-container-card pack-container-card--unpack-warehouse"
    :class="{
      'pack-container-card--filled': headerUnits > 0,
      'pack-container-card--unpack-stored': variant === 'stored',
    }"
  >
    <div class="pack-container-header-row">
      <button
        type="button"
        class="pack-container-chevron-btn"
        :aria-expanded="innerVisible"
        :aria-label="t('activities.packList.ariaToggleContainer')"
        @click.stop="(ctx.togglePackContainerCollapsed as (id: string) => void)(container.id)"
      >
        <span class="pack-container-chevron" aria-hidden="true">{{ innerVisible ? '▼' : '▶' }}</span>
      </button>
      <div class="pack-container-header-main">
        <div class="pack-container-header-title-block">
          <span class="pack-container-name">{{ container.label }}</span>
          <span
            v-if="isPhysicalCombo"
            class="pack-combo-badge"
            :title="t('activities.detail.comboPhysicalTitle')"
          >{{ t('activities.detail.comboPhysicalShort') }}</span>
          <span class="pack-container-chip text-muted">{{
            variant === 'stored'
              ? t('activities.packList.unpackCrateStoredUnits', { n: headerUnits })
              : t('activities.packList.unpackCrateReturnedUnits', { n: headerUnits })
          }}</span>
        </div>
      </div>
      <div
        v-if="showHeaderWholeStore"
        class="pack-container-header-actions"
        @click.stop
      >
        <button
          type="button"
          class="btn-move-arrow btn-move-arrow--container-header"
          :disabled="ctx.containerBulkLoadingId === container.id || ctx.containerMutationLoading === true"
          :title="t('activities.packList.storeWholeCrateTitle')"
          @click="commitPhysicalComboWhole"
        >
          <IconArrowRight />
        </button>
      </div>
    </div>
    <div v-show="innerVisible" class="pack-container-inner">
      <p v-if="variant === 'pending'" class="pack-containers-at-event-hint text-muted pack-unpack-crate-hint">
        {{
          isPhysicalCombo
            ? t('activities.packList.hintUnpackPhysicalComboCrate')
            : t('activities.packList.hintUnpackCrateCheck')
        }}
      </p>
      <PackContainerSubsectionsList :container="container">
        <template #line="{ ci }">
          <div
            v-if="lineHasUnpackVisibleQty(ci)"
            class="pack-container-line pack-container-line--stacked pack-container-line--unpack"
            :class="{
              'pack-container-line--issue-row':
                (lineStoreControlsVisible(ci) && lineRemainingStore(ci) > 0) || showIssueQuick(ci),
            }"
          >
            <div class="pack-container-line-main pack-container-line-main--unpack">
              <div v-if="packItemForLine(ci)" class="pack-unpack-line-meta">
                <PackMaterialMeta
                  :item="packItemForLine(ci)!"
                  :show-storage-location="true"
                  :show-linked-kiste="false"
                />
              </div>
              <template v-else>
                <span class="pack-container-line-name">{{ ci.material_name || t('common.material') }}</span>
              </template>
              <span class="pack-container-line-qty text-muted">
                {{
                  variant === 'stored'
                    ? t('activities.packList.lineStoredForUnpack', { n: lineStoredQty(ci) })
                    : t('activities.packList.unpackPendingStoreQty', { n: linePendingQty(ci) })
                }}
              </span>
              <PackRetourAccountingStack
                v-if="variant === 'pending'"
                :packed="lineRetourAccounting(ci).packed"
                :replenishment="lineRetourAccounting(ci).replenishment"
                :issued="lineRetourAccounting(ci).issued"
                :never-issued="lineRetourAccounting(ci).neverIssued"
                :not-taken="lineRetourAccounting(ci).notTaken"
                :consumed="lineRetourAccounting(ci).consumed"
                :loss="lineRetourAccounting(ci).loss"
                :repair="lineRetourAccounting(ci).repair"
                :returned-booked="lineRetourAccounting(ci).returnedBooked"
                :retour-total="lineRetourAccounting(ci).retourTotal"
                show-mismatch
              />
            </div>
              <p
                v-if="variant === 'pending' && isConsumableLine(ci) && canNachbuchung"
                class="pack-consumable-nachbuchung-hint"
              >
                <button type="button" class="link-btn" @click="emitNachbuchungForLine(ci)">
                  {{ t('activities.packList.consumableInlineNachbuchung') }}
                </button>
              </p>
            <PackUnpackUnstoreControls
              v-if="variant === 'stored' && packListEditable && lineStoredMoveBackMax(ci) > 0"
              class="pack-unpack-unstore-controls"
              :qty="lineStoredMoveBackMax(ci)"
              :max="lineStoredMoveBackMax(ci)"
              :disabled="ctx.containerMutationLoading === true"
              @move="(q) => commitUnstoreLine(ci, q)"
            />
            <PackUnpackStoreControls
              v-else-if="lineStoreControlsVisible(ci)"
              :qty="
                (ctx.containerStoreLineInputValue as (cid: string, ci: ActivityPackContainerItem) => number)?.(
                  container.id,
                  ci,
                ) ?? lineRemainingStore(ci)
              "
              :max="lineRemainingStore(ci)"
              :disabled="ctx.containerMutationLoading === true"
              :confirm-title="t('activities.packList.storeLineTitle', { count: lineRemainingStore(ci) })"
              @update:qty="
                (v) =>
                  (ctx.setContainerStoreLineInput as (cid: string, ci: ActivityPackContainerItem, val: number) => void)?.(
                    container.id,
                    ci,
                    v,
                  )
              "
              @store="() => commitStoreLine(ci)"
            />
            <PackContainerLineIssueQuick v-if="variant === 'pending'" :line="ci" :visible="showIssueQuick(ci)" />
          </div>
        </template>
      </PackContainerSubsectionsList>
      <div
        v-if="showReturnedShell && shellPackItem"
        class="pack-container-line pack-container-line--shell pack-container-line--stacked pack-container-line--unpack"
        :class="{
          'pack-container-line--issue-row':
            (showLineStoreControls && shellPendingQty > 0) || showShellIssueQuick,
        }"
      >
        <div class="pack-container-line-main pack-container-line-main--unpack">
          <PackMaterialMeta
            :item="shellPackItem"
            :show-storage-location="true"
            :show-linked-kiste="false"
          />
          <span class="pack-container-line-qty text-muted">
            {{
              variant === 'stored'
                ? t('activities.packList.unpackCrateShellStored')
                : t('activities.packList.unpackCrateShellReturned')
            }}
          </span>
        </div>
        <PackUnpackUnstoreControls
          v-if="variant === 'stored' && packListEditable && shellStoredMoveBackMax > 0"
          class="pack-unpack-unstore-controls"
          :qty="shellStoredMoveBackMax"
          :max="shellStoredMoveBackMax"
          :disabled="ctx.containerMutationLoading === true"
          @move="(q) => commitUnstoreShell(q)"
        />
        <PackUnpackStoreControls
          v-else-if="showLineStoreControls && shellPendingQty > 0"
          :qty="(ctx.containerShellStoreInputValue as (cid: string) => number)?.(container.id) ?? shellPendingQty"
          :max="shellPendingQty"
          :disabled="ctx.containerMutationLoading === true"
          :confirm-title="t('activities.packList.storeLineTitle', { count: shellPendingQty })"
          @update:qty="(v) => (ctx.setContainerShellStoreInput as (cid: string, val: number) => void)?.(container.id, v)"
          @store="() => commitShellStore()"
        />
        <div
          v-if="variant === 'pending' && shellPackItem && showShellIssueQuick && !shellPackItem.isConsumable"
          class="pack-container-line-issue-quick"
          @click.stop
        >
          <EButton
            variant="text"
            size="x-small"
            class="btn-issue-quick btn-issue-loss"
            @click="emitShellIssue('loss')"
          >
            {{ t('activities.common.issueLoss') }}
          </EButton>
          <EButton
            variant="text"
            size="x-small"
            class="btn-issue-quick btn-issue-repair"
            @click="emitShellIssue('repair')"
          >
            {{ t('activities.common.issueRepair') }}
          </EButton>
        </div>
      </div>
      <p v-if="!hasVisibleUnpackLines" class="pack-container-empty text-muted">
        {{ t('activities.packList.unpackCrateNoReturnedLines') }}
      </p>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style src="@/styles/views/activities/pack-container-card.css"></style>
