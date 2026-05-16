<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import PackContainerKisteMeldungRow from '@/components/activities/PackContainerKisteMeldungRow.vue'
import PackContainerLineIssueQuick from '@/components/activities/PackContainerLineIssueQuick.vue'
import PackContainerSubsectionsList from '@/components/activities/PackContainerSubsectionsList.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackEventReturnContainerCard' })

const props = defineProps<{
  container: ActivityPackContainer
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>

const innerVisible = computed(
  () => !(ctx.isPackContainerCollapsed as (id: string) => boolean)(props.container.id),
)

const shellQty = computed(() =>
  (ctx.containerShellStillAtEventQty as (id: string) => number)(props.container.id),
)

const shellMid = computed(() =>
  (ctx.shellMaterialIdForContainer as (id: string) => string | null)(props.container.id),
)

function lineRemainingReturn(ci: ActivityPackContainerItem): number {
  return (ctx.containerLineRemainingReturn as (row: ActivityPackContainerItem) => number)(ci)
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
          <span class="pack-container-chip text-muted">{{
            t('activities.common.itemsUnit', {
              count: (ctx.containerItemCount as (id: string) => number)(container.id),
            })
          }}</span>
        </div>
      </div>
      <div
        v-if="ctx.packListEditable && (ctx.containerReturnableUnits as (id: string) => number)(container.id) > 0"
        class="pack-container-header-actions"
        @click.stop
      >
        <button
          type="button"
          class="btn btn-xs btn-primary"
          :disabled="ctx.containerBulkLoadingId === container.id"
          :title="
            t('activities.packList.stockPiecesTitle', {
              count: (ctx.containerReturnableUnits as (id: string) => number)(container.id),
            })
          "
          @click="(ctx.returnContainerToWarehouse as (c: ActivityPackContainer) => void | Promise<void>)(container)"
        >
          {{ t('activities.packList.allToReturn') }}
        </button>
      </div>
    </div>
    <PackContainerKisteMeldungRow
      v-if="container.container_material_item_id"
      :material-item-id="String(container.container_material_item_id)"
      :linked-container-label="container.label"
    />
    <div v-show="innerVisible" class="pack-container-inner">
      <PackContainerSubsectionsList :container="container">
        <template #line="{ ci }">
          <div class="pack-container-line pack-container-line--stacked">
            <div class="pack-container-line-main">
              <span class="pack-container-line-name">{{ ci.material_name || t('activities.common.material') }}</span>
              <span class="pack-container-line-qty text-muted">
                <template v-if="lineRemainingReturn(ci) > 0">
                  {{ t('activities.packList.lineStillAtEvent', { n: lineRemainingReturn(ci) }) }}
                </template>
                <template v-else>{{ t('activities.packList.returnRecorded') }}</template>
              </span>
            </div>
            <PackContainerLineIssueQuick :line="ci" :visible="showIssueQuick(ci)" />
          </div>
        </template>
      </PackContainerSubsectionsList>
      <div
        v-if="shellQty > 0"
        class="pack-container-line pack-container-line--shell pack-container-line--stacked"
      >
        <div class="pack-container-line-main">
          <span class="pack-container-line-name">{{ t('activities.packList.shellMaterialLine') }}</span>
          <span class="pack-container-line-qty text-muted">
            {{ t('activities.packList.shellStillAtEvent', { n: shellQty }) }}
          </span>
        </div>
        <div v-if="ctx.canReportIssues && shellMid" class="pack-container-line-issue-quick" @click.stop>
          <template v-if="(ctx.isPackMaterialConsumable as (id: string) => boolean)(shellMid)">
            <button
              type="button"
              class="btn-issue-quick btn-issue-consumed"
              @click="
                (ctx.emitConsumptionForMaterialId as (id: string, h?: unknown) => void)(shellMid, {
                  linkedContainerLabel: container.label,
                })
              "
            >
              {{ t('activities.common.issueConsumed') }}
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              class="btn-issue-quick btn-issue-loss"
              @click="(ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(shellMid, 'loss')"
            >
              {{ t('activities.common.issueLoss') }}
            </button>
            <button
              type="button"
              class="btn-issue-quick btn-issue-repair"
              @click="(ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(shellMid, 'repair')"
            >
              {{ t('activities.common.issueRepair') }}
            </button>
          </template>
        </div>
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
