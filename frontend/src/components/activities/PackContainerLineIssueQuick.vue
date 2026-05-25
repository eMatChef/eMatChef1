<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import PackConsumableQuickRow from '@/components/activities/PackConsumableQuickRow.vue'
import {
  injectPackCtxBool,
  PACK_WAREHOUSE_ISSUE_INJECT_KEY,
} from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackContainerLineIssueQuick' })

const props = defineProps<{
  line: ActivityPackContainerItem
  /** Nur anzeigen wenn > 0 (z. B. remaining return oder issued) */
  visible?: boolean
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, unknown>

const isConsumable = computed(() => {
  const mid = props.line.material_item_id
  if (!mid) return false
  return (ctx.isPackMaterialConsumable as (id: string) => boolean)(mid)
})

const useInlineConsumption = computed(() => {
  if (!isConsumable.value) return false
  const fn = ctx.useConsumableInlineAdjust as (() => boolean) | undefined
  return fn?.() ?? false
})

const showNachbuchung = computed(() => {
  const mid = props.line.material_item_id
  if (!mid) return false
  const fn = ctx.showConsumableNachbuchungForMaterial as ((id: string) => boolean) | undefined
  return fn?.(mid) ?? false
})

function showPackIssueForLine(): boolean {
  const fn = ctx.showPackIssueForContainerLine as
    | ((ci: ActivityPackContainerItem, containerId: string) => boolean)
    | undefined
  const containerId = (ctx.packContainerIdForContainerItem as ((ci: ActivityPackContainerItem) => string | null) | undefined)?.(
    props.line,
  )
  if (fn && containerId) return fn(props.line, containerId)
  return props.visible !== false && (props.line.quantity_issued ?? 0) > 0
}

function showPackLineActions(): boolean {
  if (props.visible === false) return false
  if (useInlineConsumption.value && props.line.material_item_id) {
    return showPackConsumptionAllowed() || showNachbuchung.value
  }
  if (isConsumable.value && showNachbuchung.value && props.line.material_item_id) {
    return true
  }
  return (
    showPackIssueForLine() &&
    Boolean(props.line.material_item_id) &&
    (() => {
      const mid = props.line.material_item_id!
      if (isConsumable.value) {
        return injectPackCtxBool(ctx, 'canReportConsumption')
      }
      return injectPackCtxBool(ctx, 'canReportIssues')
    })()
  )
}

function showPackConsumptionAllowed(): boolean {
  return injectPackCtxBool(ctx, 'canReportConsumption') && Boolean(props.line.material_item_id)
}

function showConsumptionOnLine(): boolean {
  if (!isConsumable.value || !props.line.material_item_id) return false
  const fn = ctx.showConsumableConsumptionForMaterial as ((id: string) => boolean) | undefined
  if (fn) return fn(props.line.material_item_id)
  return showPackConsumptionAllowed()
}
</script>

<template>
  <PackConsumableQuickRow
    v-if="showPackLineActions() && useInlineConsumption && line.material_item_id"
    :material-item-id="line.material_item_id"
    :show-consumption="showConsumptionOnLine()"
    compact
  />
  <div v-else-if="showPackLineActions()" class="pack-container-line-issue-quick" @click.stop>
    <template v-if="isConsumable">
      <button
        v-if="showConsumptionOnLine()"
        type="button"
        class="btn-issue-quick btn-issue-consumed"
        @click="
          (ctx.emitConsumptionForMaterialId as (id: string, h?: unknown) => void)(line.material_item_id!, {
            materialName: line.material_name,
            linkedContainerLabel: line.batch_label || line.serial_number || null,
          })
        "
      >
        {{ t('activities.common.issueConsumed') }}
      </button>
      <button
        v-if="showNachbuchung"
        type="button"
        class="btn-issue-quick btn-issue-nachbuchung"
        @click="
          (ctx.emitConsumableNachbuchungForMaterial as ((id: string) => void) | undefined)?.(
            line.material_item_id!,
          )
        "
      >
        {{ t('activities.packList.consumableInlineNachbuchung') }}
      </button>
    </template>
    <template v-else>
      <button
        type="button"
        class="btn-issue-quick btn-issue-loss"
        @click="(ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(line.material_item_id!, 'loss')"
      >
        {{ t('activities.common.issueLoss') }}
      </button>
      <button
        type="button"
        class="btn-issue-quick btn-issue-repair"
        @click="(ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(line.material_item_id!, 'repair')"
      >
        {{ t('activities.common.issueRepair') }}
      </button>
    </template>
  </div>
</template>
