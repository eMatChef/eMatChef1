<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import PackIssueQuickActions from '@/components/activities/PackIssueQuickActions.vue'
import type { MaterialScanResolveResult } from '@/composables/materialScanResolve'

const props = defineProps<{
  result: MaterialScanResolveResult
  message: string
  quantityHint?: string
  quantityProgress?: string
  primaryLabel: string
  primaryEnabled: boolean
  showBulkConfirm: boolean
  bulkConfirmed: boolean
  showInCrate?: boolean
  inCrateLabel?: string
  dismissLabel?: string
  showIssueActions?: boolean
  issueIsConsumable?: boolean
  showIssueConsumption?: boolean
  warehouseWarning?: string | null
  warehouseLinePreview?: string | null
  warehouseHint?: string | null
  showWarehouseAction?: boolean
  warehouseActionLabel?: string
  warehouseActionEnabled?: boolean
}>()

const emit = defineEmits<{
  primary: []
  inCrate: []
  confirmBulk: []
  dismiss: []
  consumed: []
  loss: []
  repair: []
  damage: []
  warehouseAction: []
}>()

const { t } = useI18n()

const toneClass = computed(() => `material-scan-result-card--${props.result.tone}`)

const closeLabel = computed(() => props.dismissLabel ?? t('common.close'))
</script>

<template>
  <section class="material-scan-result-card section-card" :class="toneClass" aria-live="polite">
    <div class="material-scan-result-card__body">
      <p class="material-scan-result-card__title">{{ result.title }}</p>
      <p v-if="quantityHint" class="material-scan-result-card__quantity">{{ quantityHint }}</p>
      <p v-if="quantityProgress" class="material-scan-result-card__progress text-muted">
        {{ quantityProgress }}
      </p>
      <p class="material-scan-result-card__message text-muted">{{ message }}</p>
      <p v-if="warehouseWarning" class="material-scan-result-card__warehouse-warning">
        {{ warehouseWarning }}
      </p>
      <p v-if="warehouseLinePreview" class="material-scan-result-card__warehouse-preview text-muted">
        {{ warehouseLinePreview }}
      </p>
      <p v-if="warehouseHint" class="material-scan-result-card__warehouse-hint text-muted">
        {{ warehouseHint }}
      </p>
      <p v-if="showBulkConfirm && !bulkConfirmed" class="material-scan-result-card__hint">
        {{ t('activities.materialJourney.scan.bulkConfirmHint') }}
      </p>
    </div>
    <div class="material-scan-result-card__actions">
      <PackIssueQuickActions
        v-if="showIssueActions && result.packItem"
        :is-consumable="issueIsConsumable === true"
        :material-item-id="result.packItem.materialItemId"
        :material-name="result.title"
        :show-consumption="showIssueConsumption !== false"
        compact
        @consumed="emit('consumed')"
        @loss="emit('loss')"
        @repair="emit('repair')"
        @damage="emit('damage')"
      />
      <EButton
        v-if="showBulkConfirm && !bulkConfirmed"
        variant="secondary"
        size="small"
        @click="emit('confirmBulk')"
      >
        {{ t('activities.materialJourney.scan.bulkConfirm') }}
      </EButton>
      <EButton
        v-if="showWarehouseAction && warehouseActionEnabled"
        variant="primary"
        size="small"
        @click="emit('warehouseAction')"
      >
        {{ warehouseActionLabel ?? t('activities.materialJourney.scan.actionMiniInventory') }}
      </EButton>
      <EButton
        v-else-if="primaryEnabled"
        variant="primary"
        size="small"
        @click="emit('primary')"
      >
        {{ primaryLabel }}
      </EButton>
      <EButton
        v-if="showInCrate"
        variant="secondary"
        size="small"
        @click="emit('inCrate')"
      >
        {{ inCrateLabel }}
      </EButton>
      <EButton variant="secondary" size="small" @click="emit('dismiss')">
        {{ closeLabel }}
      </EButton>
    </div>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
