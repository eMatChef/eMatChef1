<script setup lang="ts">
import { inject } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackContainerKisteMeldungRow' })

defineProps<{
  containerId: string
  materialItemId: string
  linkedContainerLabel?: string | null
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>

function onIssuePick(materialItemId: string, issueType: 'loss' | 'repair'): void {
  ;(ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(materialItemId, issueType)
}
</script>

<template>
  <div
    v-if="
      (ctx.showKisteMeldungForContainer as ((id: string) => boolean) | undefined)?.(containerId) ??
      false
    "
    class="pack-container-kiste-meldung-row"
    @click.stop
  >
    <span class="pack-container-kiste-meldung-label">{{ t('activities.common.crate') }}</span>
    <template v-if="(ctx.isPackMaterialConsumable as (id: string) => boolean)(materialItemId)">
      <EButton
        variant="text"
        size="x-small"
        class="btn-issue-quick btn-issue-consumed"
        @click="
          (ctx.emitConsumptionForMaterialId as (id: string, h?: unknown) => void)(materialItemId, {
            linkedContainerLabel: linkedContainerLabel ?? undefined,
          })
        "
      >
        {{ t('activities.common.issueConsumed') }}
      </EButton>
    </template>
    <template v-else>
      <button
        type="button"
        class="btn-issue-quick btn-issue-loss"
        :title="t('activities.common.issueLoss')"
        @click="onIssuePick(materialItemId, 'loss')"
      >
        {{ t('activities.common.issueLoss') }}
      </button>
      <button
        type="button"
        class="btn-issue-quick btn-issue-repair"
        :title="t('activities.common.issueRepair')"
        @click="onIssuePick(materialItemId, 'repair')"
      >
        {{ t('activities.common.issueRepair') }}
      </button>
    </template>
  </div>
</template>
