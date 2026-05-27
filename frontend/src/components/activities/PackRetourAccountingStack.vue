<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'PackRetourAccountingStack' })

const props = withDefaults(
  defineProps<{
    packed?: number
    issued: number
    neverIssued?: number
    notTaken?: number
    consumed?: number
    loss?: number
    repair?: number
    replenishment?: number
    returnedBooked?: number
    retourTotal?: number
    showMismatch?: boolean
  }>(),
  {
    packed: 0,
    neverIssued: 0,
    notTaken: 0,
    consumed: 0,
    loss: 0,
    repair: 0,
    replenishment: 0,
    returnedBooked: 0,
    retourTotal: 0,
    showMismatch: false,
  },
)

const showPackedBreakdown = computed(
  () =>
    (props.packed ?? 0) > 0 &&
    ((props.neverIssued ?? 0) > 0 || (props.packed ?? 0) !== (props.issued ?? 0)),
)

const notTakenTotal = computed(() => (props.neverIssued ?? 0) + (props.notTaken ?? 0))

const { t } = useI18n()

const displayRetourTotal = computed(() => {
  if ((props.retourTotal ?? 0) > 0) return props.retourTotal ?? 0
  return props.returnedBooked ?? 0
})

const outOfWarehouseTotal = computed(() => (props.packed ?? 0) + (props.replenishment ?? 0))

const accountedTotal = computed(
  () =>
    displayRetourTotal.value +
    (props.consumed ?? 0) +
    (props.loss ?? 0) +
    (props.repair ?? 0),
)

const hasBalanceMismatch = computed(() => {
  if (!props.showMismatch) return false
  return outOfWarehouseTotal.value > 0 && accountedTotal.value !== outOfWarehouseTotal.value
})
</script>

<template>
  <div v-if="issued > 0 || displayRetourTotal > 0 || showPackedBreakdown" class="pack-retour-accounting">
    <span v-if="showPackedBreakdown" class="pack-retour-accounting-line text-muted">
      {{ t('activities.packList.retourAccountingPacked', { n: packed }) }}
    </span>
    <span v-if="(replenishment ?? 0) > 0" class="pack-retour-accounting-line text-muted">
      {{ t('activities.packList.retourAccountingReplenishment', { n: replenishment }) }}
    </span>
    <span v-if="issued > 0" class="pack-retour-accounting-line text-muted">
      {{ t('activities.packList.retourAccountingIssued', { n: issued }) }}
    </span>
    <span v-if="(notTakenTotal ?? 0) > 0" class="pack-retour-accounting-line text-muted">
      {{ t('activities.packList.retourAccountingNotTaken', { n: notTakenTotal }) }}
    </span>
    <span v-if="(consumed ?? 0) > 0" class="pack-retour-accounting-line text-muted">
      {{ t('activities.packList.retourAccountingConsumed', { n: consumed }) }}
    </span>
    <span v-if="(loss ?? 0) > 0" class="pack-retour-accounting-line text-muted">
      {{ t('activities.packList.retourAccountingLoss', { n: loss }) }}
    </span>
    <span v-if="(repair ?? 0) > 0" class="pack-retour-accounting-line text-muted">
      {{ t('activities.packList.retourAccountingRepair', { n: repair }) }}
    </span>
    <span v-if="displayRetourTotal > 0 || outOfWarehouseTotal > 0" class="pack-retour-accounting-line">
      {{ t('activities.packList.retourAccountingReturned', { n: displayRetourTotal }) }}
    </span>
    <span v-if="hasBalanceMismatch" class="pack-unpack-qty-warn pack-retour-accounting-line" role="status">
      {{
        t('activities.packList.unpackReturnBalanceMismatch', {
          out: outOfWarehouseTotal,
          retour: displayRetourTotal,
          consumed: consumed ?? 0,
          loss: loss ?? 0,
          repair: repair ?? 0,
        })
      }}
    </span>
  </div>
</template>

<style scoped>
.pack-retour-accounting {
  display: flex;
  flex-direction: column;
  gap: 0.12rem;
  margin-top: 0.2rem;
}

.pack-retour-accounting-line {
  display: block;
  line-height: 1.35;
}
</style>
