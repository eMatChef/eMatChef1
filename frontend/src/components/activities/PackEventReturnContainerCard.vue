<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import PackContainerLineIssueQuick from '@/components/activities/PackContainerLineIssueQuick.vue'
import PackContainerSubsectionsList from '@/components/activities/PackContainerSubsectionsList.vue'
import IconArrowRight from '@/components/icons/IconArrowRight.vue'
import { injectPackCtxBool, PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackEventReturnContainerCard' })

const props = defineProps<{
  container: ActivityPackContainer
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>

const packListEditable = computed(() => injectPackCtxBool(ctx, 'packListEditable'))

const innerVisible = computed(
  () => !(ctx.isPackContainerCollapsed as (id: string) => boolean)(props.container.id),
)

const innerReturnable = computed(() =>
  (ctx.containerInnerReturnableUnits as (id: string) => number | undefined)?.(props.container.id) ?? 0,
)

const shellQty = computed(() => {
  if (innerReturnable.value > 0) return 0
  return (ctx.containerShellStillAtEventQty as (id: string) => number)(props.container.id)
})

const shellMid = computed(() =>
  (ctx.shellMaterialIdForContainer as (id: string) => string | null)(props.container.id),
)

const shellIssueLine = computed((): ActivityPackContainerItem | null => {
  const mid = shellMid.value
  if (!mid) return null
  const shellPi = (ctx.shellPackItemForContainer as (id: string) => { materialName?: string } | undefined)?.(
    props.container.id,
  )
  return {
    id: `shell-${props.container.id}`,
    material_item_id: mid,
    material_name: shellPi?.materialName ?? t('activities.packList.shellMaterialLine'),
    quantity_issued: shellQty.value,
    quantity_returned: 0,
    quantity_packed: 0,
  } as ActivityPackContainerItem
})

function toggleContainerExpanded(): void {
  ;(ctx.togglePackContainerCollapsed as (id: string) => void)(props.container.id)
}

function lineRemainingReturn(ci: ActivityPackContainerItem): number {
  return (ctx.containerLineRemainingReturn as (row: ActivityPackContainerItem, cid?: string) => number)(
    ci,
    props.container.id,
  )
}

function lineReturnLabel(ci: ActivityPackContainerItem): string {
  const n = lineRemainingReturn(ci)
  if (n <= 0) return t('activities.packList.returnRecorded')
  if ((ci.quantity_issued ?? 0) === 0 && (ci.quantity_packed ?? 0) > 0) {
    const atEvent = (ctx.containerHasIssuedAtEvent as ((id: string) => boolean) | undefined)?.(
      props.container.id,
    )
    if (!atEvent) {
      return t('activities.packList.lineInCrateWarehouseReturn', { n })
    }
    return t('activities.packList.lineInCrateForReturn', { n })
  }
  return t('activities.packList.lineStillAtEvent', { n })
}

function onReturnLineInput(ci: ActivityPackContainerItem, event: Event): void {
  const fn = ctx.setContainerReturnLineInput as
    | ((cid: string, ci: ActivityPackContainerItem, value: number | string) => void)
    | undefined
  fn?.(props.container.id, ci, (event.target as HTMLInputElement).value)
}

function commitReturnLine(ci: ActivityPackContainerItem, event?: Event): void {
  if (event) event.preventDefault()
  void (ctx.returnContainerLineToWarehouse as (cid: string, ci: ActivityPackContainerItem) => void | Promise<void>)?.(
    props.container.id,
    ci,
  )
}

function onShellReturnInput(event: Event): void {
  ;(ctx.setContainerShellReturnInput as (cid: string, value: number | string) => void)?.(
    props.container.id,
    (event.target as HTMLInputElement).value,
  )
}

function commitShellReturn(event?: Event): void {
  if (event) event.preventDefault()
  void (ctx.returnContainerShellToWarehouse as (cid: string) => void | Promise<void>)?.(props.container.id)
}

function showIssueQuick(ci: ActivityPackContainerItem): boolean {
  if ((ctx.isVirtualWarehouseContainerLine as (row: ActivityPackContainerItem) => boolean)(ci)) return false
  return lineRemainingReturn(ci) > 0
}
</script>

<template>
  <div
    :id="'pack-container-event-ret-' + container.id"
    class="pack-container-card"
    :class="{
      'pack-container-card--filled':
        (ctx.containerHasAssignedContents as ((id: string) => boolean) | undefined)?.(container.id) ?? false,
      'pack-container-card--at-event':
        (ctx.containerHasIssuedAtEvent as ((id: string) => boolean) | undefined)?.(container.id) ??
        false,
      'pack-container-card--collapsed': !innerVisible,
    }"
  >
    <div class="pack-container-header-row">
      <button
        type="button"
        class="pack-container-chevron-btn"
        :aria-expanded="innerVisible"
        :aria-label="t('activities.packList.ariaToggleContainer')"
        @click.stop="toggleContainerExpanded"
      >
        <span class="pack-container-chevron" aria-hidden="true">{{ innerVisible ? '▼' : '▶' }}</span>
      </button>
      <div class="pack-container-header-main">
        <div class="pack-container-header-title-block">
          <button
            type="button"
            class="pack-container-name pack-container-name-btn"
            @click.stop="toggleContainerExpanded"
          >
            {{ container.label }}
          </button>
          <span class="pack-container-chip text-muted">{{
            t('activities.common.itemsUnit', {
              count: (ctx.containerItemCount as (id: string) => number)(container.id),
            })
          }}</span>
        </div>
      </div>
      <div
        v-if="packListEditable && (ctx.containerReturnableUnits as (id: string) => number)(container.id) > 0"
        class="pack-container-header-actions"
        @click.stop
      >
        <button
          type="button"
          class="btn btn-xs btn-primary"
          :disabled="ctx.containerBulkLoadingId === container.id"
          :title="t('activities.packList.allToReturn')"
          @click="(ctx.returnContainerToWarehouse as (c: ActivityPackContainer) => void | Promise<void>)(container)"
        >
          {{ t('activities.packList.allToReturn') }}
        </button>
      </div>
    </div>
    <div v-show="innerVisible" class="pack-container-inner">
      <PackContainerSubsectionsList :container="container">
        <template #line="{ ci }">
          <div class="pack-container-line pack-container-line--issue-row pack-container-line--stacked">
            <div class="pack-container-line-main">
              <span class="pack-container-line-name">{{ ci.material_name || t('common.material') }}</span>
              <span class="pack-container-line-qty text-muted">
                {{ lineReturnLabel(ci) }}
              </span>
            </div>
            <div
              v-if="packListEditable && lineRemainingReturn(ci) > 0"
              class="pack-card-actions"
              @click.stop
            >
              <div class="pack-move-inline">
                <input
                  :value="
                    (ctx.containerReturnLineInputValue as (cid: string, ci: ActivityPackContainerItem) => number)?.(
                      container.id,
                      ci,
                    ) ?? lineRemainingReturn(ci)
                  "
                  type="number"
                  min="1"
                  :max="lineRemainingReturn(ci)"
                  class="pack-move-input"
                  @input="onReturnLineInput(ci, $event)"
                  @keyup.enter="commitReturnLine(ci, $event)"
                />
                <button
                  type="button"
                  class="btn-move-arrow"
                  :disabled="ctx.containerMutationLoading === true"
                  :title="t('activities.packList.returnLineTitle', { count: lineRemainingReturn(ci) })"
                  @click="commitReturnLine(ci)"
                >
                  <IconArrowRight />
                </button>
              </div>
            </div>
            <PackContainerLineIssueQuick :line="ci" :visible="showIssueQuick(ci)" />
          </div>
        </template>
      </PackContainerSubsectionsList>
      <p
        v-if="innerReturnable > 0 && (ctx.containerShellReturnableUnits as (id: string) => number | undefined)?.(container.id) > 0"
        class="pack-container-shell-hint text-muted text-sm"
      >
        {{ t('activities.packList.returnShellAfterContentsHint') }}
      </p>
      <div
        v-if="shellQty > 0"
        class="pack-container-line pack-container-line--issue-row pack-container-line--shell pack-container-line--stacked"
      >
        <div class="pack-container-line-main">
          <span class="pack-container-line-name">{{ t('activities.packList.shellMaterialLine') }}</span>
          <span class="pack-container-line-qty text-muted">
            {{ t('activities.packList.shellStillAtEvent', { n: shellQty }) }}
          </span>
        </div>
        <div v-if="packListEditable" class="pack-card-actions" @click.stop>
          <div class="pack-move-inline">
            <input
              :value="(ctx.containerShellReturnInputValue as (cid: string) => number)?.(container.id) ?? shellQty"
              type="number"
              min="1"
              :max="shellQty"
              class="pack-move-input"
              @input="onShellReturnInput"
              @keyup.enter="commitShellReturn($event)"
            />
            <button
              type="button"
              class="btn-move-arrow"
              :disabled="ctx.containerMutationLoading === true"
              :title="t('activities.packList.returnLineTitle', { count: shellQty })"
              @click="commitShellReturn()"
            >
              <IconArrowRight />
            </button>
          </div>
        </div>
        <PackContainerLineIssueQuick
          v-if="shellIssueLine"
          :line="shellIssueLine"
          :visible="shellQty > 0"
        />
      </div>
      <p
        v-if="(ctx.containerItemCount as (id: string) => number)(container.id) === 0 && shellQty <= 0"
        class="pack-container-empty text-muted"
      >
        {{ t('activities.packList.noLines') }}
      </p>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style src="@/styles/views/activities/pack-container-card.css"></style>
<style scoped>
.pack-container-name-btn {
  background: none;
  border: none;
  padding: 0;
  font: inherit;
  font-weight: 600;
  color: inherit;
  cursor: pointer;
  text-align: left;
}

.pack-container-name-btn:hover {
  color: #0f766e;
  text-decoration: underline;
}
</style>