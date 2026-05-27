<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import PackConsumableQuickRow from '@/components/activities/PackConsumableQuickRow.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackIssueQuickActions' })

const props = defineProps<{
  isConsumable: boolean
  materialItemId?: string
  /** Verbrauch buchen anzeigen (false = nur Nachlieferung) */
  showConsumption?: boolean
}>()

const emit = defineEmits<{
  consumed: []
  loss: []
  repair: []
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY, null) as Record<string, unknown> | null

const useInlineConsumption = computed(() => {
  if (!props.isConsumable || !props.materialItemId || !ctx) return false
  const fn = ctx.useConsumableInlineAdjust as (() => boolean) | undefined
  return fn?.() ?? false
})

const showNachbuchung = computed(() => {
  if (!props.isConsumable || !props.materialItemId || !ctx) return false
  const fn = ctx.showConsumableNachbuchungForMaterial as ((id: string) => boolean) | undefined
  return fn?.(props.materialItemId) ?? false
})

const showConsumptionButton = computed(() => props.showConsumption !== false)
</script>

<template>
  <div class="pack-card-issue-quick-row">
    <PackConsumableQuickRow
      v-if="isConsumable && useInlineConsumption && materialItemId"
      :material-item-id="materialItemId"
      :show-consumption="showConsumptionButton"
    />
    <template v-else-if="isConsumable && materialItemId">
      <button
        v-if="showConsumptionButton"
        type="button"
        class="btn-issue-quick btn-issue-consumed"
        @click.stop="emit('consumed')"
      >
        {{ t('activities.common.issueConsumed') }}
      </button>
      <button
        v-if="showNachbuchung"
        type="button"
        class="btn-issue-quick btn-issue-nachbuchung"
        @click.stop="
          (ctx?.emitConsumableNachbuchungForMaterial as ((id: string) => void) | undefined)?.(
            materialItemId,
          )
        "
      >
        {{ t('activities.packList.consumableInlineNachbuchung') }}
      </button>
    </template>
    <template v-else-if="!isConsumable">
      <button type="button" class="btn-issue-quick btn-issue-loss" @click.stop="emit('loss')">
        {{ t('activities.common.issueLoss') }}
      </button>
      <button type="button" class="btn-issue-quick btn-issue-repair" @click.stop="emit('repair')">
        {{ t('activities.common.issueRepair') }}
      </button>
    </template>
  </div>
</template>
