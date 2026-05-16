<script setup lang="ts">
import { inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackContainerLineIssueQuick' })

const props = defineProps<{
  line: ActivityPackContainerItem
  /** Nur anzeigen wenn > 0 (z. B. remaining return oder issued) */
  visible?: boolean
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>

const show = () =>
  props.visible !== false &&
  Boolean(props.line.material_item_id) &&
  Boolean(ctx.canReportIssues)
</script>

<template>
  <div v-if="show()" class="pack-container-line-issue-quick" @click.stop>
    <template v-if="(ctx.isPackMaterialConsumable as (id: string) => boolean)(line.material_item_id!)">
      <button
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
