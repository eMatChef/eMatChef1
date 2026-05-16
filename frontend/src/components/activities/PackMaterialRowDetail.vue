<script setup lang="ts">
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackStage } from '@/components/activities/packStageQuantities'
import {
  getStageLeftQty,
  getStageRightQty,
  getStageTotalQty,
} from '@/components/activities/packStageQuantities'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'PackMaterialRowDetail' })

const props = defineProps<{
  item: ActivityPackItem
  stage: PackStage
  stageRightLabel: string
  side: 'left' | 'right'
  looseQty?: number
  qtyInContainers?: number
  looseIssuedAtEvent?: number
  useDetailStack?: boolean
}>()

const { t } = useI18n()

const leftQty = () => getStageLeftQty(props.item, props.stage)
const rightQty = () => getStageRightQty(props.item, props.stage)
const totalQty = () => getStageTotalQty(props.item, props.stage)
</script>

<template>
  <div :class="useDetailStack ? 'pack-card-detail-stack' : undefined">
    <span class="pack-card-detail">
      <template v-if="side === 'left'">
        <template v-if="stage === 'packed_issued' && leftQty() > 0">
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
        <template v-else> {{ leftQty() }} / {{ totalQty() }} </template>
      </template>

      <template v-else-if="side === 'right' && stage === 'confirmed_packed'">
        <template v-if="rightQty() > 0">
          <span>{{ t('activities.packList.loosePieces', { n: looseQty ?? 0 }) }}</span>
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
