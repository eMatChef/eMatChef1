<script setup lang="ts">
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackStage } from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import PackRetourAccountingStack from '@/components/activities/PackRetourAccountingStack.vue'
import type { PackRetourAccounting } from '@/components/activities/packNotTakenHelpers'
import {
  getStageLeftQty,
  getStageRightQty,
  getStageTotalQty,
  isPackConfirmedStage,
  isPackForwardToEventStage,
  isPackForwardWarehouseUiStage,
  isPackReturnStage,
  isPackLogisticsReturnStage,
  isPackReturnPipelineStage,
  isPackUnpackStage,
} from '@/components/activities/packStageQuantities'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'PackMaterialRowDetail' })

const props = defineProps<{
  item: ActivityPackItem
  stage: PackStage
  workflowProfile: PackWorkflowProfile
  stageRightLabel: string
  side: 'left' | 'right'
  looseQty?: number
  qtyInContainers?: number
  looseIssuedAtEvent?: number
  consumedAtEvent?: number
  notTakenToEvent?: number
  expectedReturnQty?: number
  retourAccounting?: PackRetourAccounting
  useDetailStack?: boolean
}>()

const { t } = useI18n()

const leftQty = () => getStageLeftQty(props.item, props.stage, props.workflowProfile)
const rightQty = () => getStageRightQty(props.item, props.stage, props.workflowProfile)
const totalQty = () => getStageTotalQty(props.item, props.stage, props.workflowProfile)

const unpackPendingStoreTotal = computed(() => {
  if (isPackUnpackStage(props.stage) && props.retourAccounting) {
    return Math.max(
      0,
      props.retourAccounting.retourTotal -
        (props.item.quantityStored ?? 0) -
        (props.item.quantityWet ?? 0),
    )
  }
  return leftQty()
})

function showLooseInContainersDetail(stage: PackStage, side: 'left' | 'right'): boolean {
  if (side === 'left' && isPackForwardToEventStage(stage)) return true
  if (side === 'right' && isPackConfirmedStage(stage)) return true
  if (side === 'right' && isPackForwardToEventStage(stage)) return true
  if (side === 'right' && stage === 'at_event_transport_back') return true
  if (side === 'right' && stage === 'transport_back_returned') return true
  if (side === 'left' && isPackLogisticsReturnStage(stage)) return true
  return false
}
</script>

<template>
  <div :class="useDetailStack ? 'pack-card-detail-stack' : undefined">
    <span class="pack-card-detail">
      <template v-if="side === 'left'">
        <template v-if="isPackForwardWarehouseUiStage(stage) && leftQty() > 0">
          <template v-if="(looseQty ?? 0) > 0">
            <span>{{ t('activities.packList.loosePieces', { n: looseQty }) }}</span>
            <span v-if="(qtyInContainers ?? 0) > 0" class="text-muted">
              {{ t('activities.packList.inContainers', { n: qtyInContainers }) }}
            </span>
          </template>
          <template v-else>
            <span class="text-muted">{{ t('activities.packList.allInContainers') }}</span>
          </template>
          <span class="text-muted pack-card-detail-fraction">
            {{
              t('activities.packList.notYetStage', {
                left: leftQty(),
                total: totalQty(),
                stage: stageRightLabel,
              })
            }}
          </span>
        </template>
        <template
          v-else-if="
            item.isConsumable &&
            (consumedAtEvent ?? 0) > 0 &&
            leftQty() <= 0 &&
            rightQty() <= 0 &&
            !isPackReturnStage(stage) &&
            !isPackUnpackStage(stage)
          "
        >
          <span class="text-muted">{{ t('activities.packList.zeroPieces') }}</span>
          <span class="text-muted">
            {{ t('activities.packList.consumableAtEventConsumed', { n: consumedAtEvent }) }}
          </span>
        </template>
        <template v-else-if="isPackConfirmedStage(stage) && leftQty() > 0">
          <span class="text-muted pack-card-detail-fraction">
            {{
              t('activities.packList.notYetStage', {
                left: leftQty(),
                total: totalQty(),
                stage: stageRightLabel,
              })
            }}
          </span>
        </template>
        <template v-else-if="isPackUnpackStage(stage)">
          <span v-if="unpackPendingStoreTotal > 0" class="pack-card-detail-primary">
            {{ t('activities.packList.unpackPendingStoreQty', { n: unpackPendingStoreTotal }) }}
          </span>
          <PackRetourAccountingStack
            v-if="retourAccounting"
            :packed="retourAccounting.packed"
            :replenishment="retourAccounting.replenishment"
            :issued="retourAccounting.issued"
            :never-issued="retourAccounting.neverIssued"
            :not-taken="retourAccounting.notTaken"
            :consumed="retourAccounting.consumed"
            :loss="retourAccounting.loss"
            :repair="retourAccounting.repair"
            :returned-booked="retourAccounting.returnedBooked"
            :retour-total="retourAccounting.retourTotal"
            show-mismatch
          />
        </template>
        <template v-else> {{ leftQty() }} / {{ totalQty() }} </template>
      </template>

      <template v-else-if="side === 'right' && showLooseInContainersDetail(stage, 'right')">
        <template v-if="rightQty() > 0">
          <span>{{ t('activities.packList.loosePieces', { n: looseQty ?? looseIssuedAtEvent ?? rightQty() }) }}</span>
          <span v-if="(qtyInContainers ?? 0) > 0" class="text-muted">
            {{ t('activities.packList.inContainers', { n: qtyInContainers }) }}
          </span>
        </template>
        <template v-else-if="item.isConsumable && (consumedAtEvent ?? 0) > 0">
          <span class="text-muted">{{ t('activities.packList.zeroPieces') }}</span>
          <span class="text-muted">
            {{ t('activities.packList.consumableAtEventConsumed', { n: consumedAtEvent }) }}
          </span>
        </template>
        <span v-else class="text-muted">{{ t('activities.packList.zeroPieces') }}</span>
      </template>

      <template v-else-if="side === 'right' && isPackUnpackStage(stage)">
        <span>{{ t('activities.packList.lineStoredForUnpack', { n: rightQty() }) }}</span>
      </template>

      <template v-else-if="side === 'right' && isPackReturnPipelineStage(stage)">
        <template
          v-if="
            (stage === 'at_event_returned' || stage === 'transport_back_returned') &&
            item.isConsumable &&
            (consumedAtEvent ?? 0) > 0
          "
        >
          {{
            t('activities.packList.returnConsumableDetail', {
              returned: rightQty(),
              consumed: consumedAtEvent,
              issued: totalQty(),
            })
          }}
        </template>
        <template v-else-if="rightQty() > 0">
          <span>{{ t('activities.packList.loosePieces', { n: looseQty ?? looseIssuedAtEvent ?? rightQty() }) }}</span>
          <span v-if="(qtyInContainers ?? 0) > 0" class="text-muted">
            {{ t('activities.packList.inContainers', { n: qtyInContainers }) }}
          </span>
        </template>
        <span v-else class="text-muted">{{ t('activities.packList.zeroPieces') }}</span>
      </template>

      <template v-else-if="side === 'right'">
        {{
          t('activities.packList.issuedFraction', {
            issued: looseIssuedAtEvent ?? rightQty(),
            packed: totalQty(),
            stage: stageRightLabel,
          })
        }}
      </template>
    </span>
    <slot />
  </div>
</template>
