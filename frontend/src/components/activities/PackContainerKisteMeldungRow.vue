<script setup lang="ts">
import { inject } from 'vue'
import { useI18n } from 'vue-i18n'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackContainerKisteMeldungRow' })

const props = defineProps<{
  materialItemId: string
  linkedContainerLabel?: string | null
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>
</script>

<template>
  <div
    v-if="ctx.canReportIssues"
    class="pack-container-kiste-meldung-row"
    @click.stop
  >
    <span class="pack-container-kiste-meldung-label">{{ t('activities.common.crate') }}</span>
    <template v-if="(ctx.isPackMaterialConsumable as (id: string) => boolean)(materialItemId)">
      <button
        type="button"
        class="btn-issue-quick btn-issue-consumed"
        @click="
          (ctx.emitConsumptionForMaterialId as (id: string, h?: unknown) => void)(materialItemId, {
            linkedContainerLabel: linkedContainerLabel ?? undefined,
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
        @click="(ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(materialItemId, 'loss')"
      >
        {{ t('activities.common.issueLoss') }}
      </button>
      <button
        type="button"
        class="btn-issue-quick btn-issue-repair"
        @click="(ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(materialItemId, 'repair')"
      >
        {{ t('activities.common.issueRepair') }}
      </button>
    </template>
  </div>
</template>
