<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import PackIssueQuickActions from '@/components/activities/PackIssueQuickActions.vue'
import { packMaterialDisplayName } from '@/components/activities/packMaterialDisplay'
import type {
  MaterialScanResolveResult,
  MaterialScanShelfLine,
} from '@/composables/materialScanResolve'
import {
  formatPackScanProgressHint,
  formatPackScanQuantityHint,
} from '@/utils/packScanQuantityHint'

const props = defineProps<{
  result: MaterialScanResolveResult
  message: string
  focusedPackItemId?: string | null
  inlineResult?: MaterialScanResolveResult | null
  inlineMessage?: string
  inlineQuantityHint?: string
  inlineQuantityProgress?: string
  inlinePrimaryLabel?: string
  inlinePrimaryEnabled?: boolean
  inlineShowBulkConfirm?: boolean
  inlineBulkConfirmed?: boolean
  inlineShowInCrate?: boolean
  inlineInCrateLabel?: string
  inlineShowIssueActions?: boolean
  inlineIssueIsConsumable?: boolean
  inlineShowIssueConsumption?: boolean
}>()

const emit = defineEmits<{
  focusLine: [line: MaterialScanShelfLine]
  inlinePrimary: []
  inlineInCrate: []
  inlineConfirmBulk: []
  inlineDismissLine: []
  inlineConsumed: []
  inlineLoss: []
  inlineRepair: []
  inlineDamage: []
  dismiss: []
}>()

const { t } = useI18n()

const toneClass = computed(() => `material-scan-shelf-card--${props.result.tone}`)

const lines = computed(() => props.result.shelfLines ?? [])

const showLocationGroups = computed(() => {
  const entityType = props.result.storageLookup?.entity_type
  return entityType === 'storage_rack' || entityType === 'storage_address'
})

type ShelfLineGroup = {
  key: string
  label: string
  lines: MaterialScanShelfLine[]
}

const lineGroups = computed((): ShelfLineGroup[] => {
  if (!showLocationGroups.value) {
    return [{ key: 'flat', label: '', lines: lines.value }]
  }

  const grouped = new Map<string, MaterialScanShelfLine[]>()
  for (const line of lines.value) {
    const key = line.locationLabel.trim() || '__none__'
    const bucket = grouped.get(key)
    if (bucket) bucket.push(line)
    else grouped.set(key, [line])
  }

  return [...grouped.entries()]
    .sort(([a], [b]) => {
      if (a === '__none__') return 1
      if (b === '__none__') return -1
      return a.localeCompare(b, undefined, { sensitivity: 'base' })
    })
    .map(([key, groupLines]) => ({
      key,
      label: groupLabel(key),
      lines: groupLines,
    }))
})

function groupLabel(key: string): string {
  if (key === '__none__') {
    return t('activities.materialJourney.scan.result.shelf_group_no_slot')
  }
  if (props.result.storageLookup?.entity_type === 'storage_rack') {
    return t('activities.materialJourney.scan.result.shelf_group_slot', { slot: key })
  }
  return key
}

function isLineFocused(line: MaterialScanShelfLine): boolean {
  return props.focusedPackItemId === line.packItem.id
}

function quantityHint(line: MaterialScanShelfLine): string {
  if (line.moveQty <= 0) return ''
  return formatPackScanQuantityHint(line.packItem, line.moveQty, t)
}

function quantityProgress(line: MaterialScanShelfLine): string {
  if (line.moveQty <= 0 || line.totalQty <= 0) return ''
  return formatPackScanProgressHint(line.doneQty, line.totalQty, t)
}
</script>

<template>
  <section class="material-scan-shelf-card section-card" :class="toneClass" aria-live="polite">
    <header class="material-scan-shelf-card__header">
      <p class="material-scan-shelf-card__title">{{ result.title }}</p>
      <p class="material-scan-shelf-card__message text-muted">{{ message }}</p>
    </header>

    <div v-if="lines.length > 0" class="material-scan-shelf-card__groups">
      <section
        v-for="group in lineGroups"
        :key="group.key"
        class="material-scan-shelf-card__group"
      >
        <h3
          v-if="showLocationGroups && group.label"
          class="material-scan-shelf-card__group-title"
        >
          {{ group.label }}
        </h3>
        <ul class="material-scan-shelf-card__list">
          <li
            v-for="line in group.lines"
            :key="line.packItem.id"
            class="material-scan-shelf-card__line-wrap"
            :class="{ 'material-scan-shelf-card__line-wrap--focused': isLineFocused(line) }"
          >
            <button
              v-if="!isLineFocused(line)"
              type="button"
              class="material-scan-shelf-card__line"
              @click="emit('focusLine', line)"
            >
              <span class="material-scan-shelf-card__line-title">
                {{ packMaterialDisplayName(line.packItem) }}
              </span>
              <span v-if="quantityHint(line)" class="material-scan-shelf-card__line-qty">
                {{ quantityHint(line) }}
              </span>
              <span
                v-if="quantityProgress(line)"
                class="material-scan-shelf-card__line-progress text-muted"
              >
                {{ quantityProgress(line) }}
              </span>
            </button>

            <div
              v-else-if="inlineResult"
              class="material-scan-shelf-card__line material-scan-shelf-card__line--expanded"
            >
              <span class="material-scan-shelf-card__line-title">
                {{ packMaterialDisplayName(line.packItem) }}
              </span>
              <span v-if="inlineQuantityHint" class="material-scan-shelf-card__line-qty">
                {{ inlineQuantityHint }}
              </span>
              <span
                v-if="inlineQuantityProgress"
                class="material-scan-shelf-card__line-progress text-muted"
              >
                {{ inlineQuantityProgress }}
              </span>
              <p v-if="inlineMessage" class="material-scan-shelf-card__line-detail text-muted">
                {{ inlineMessage }}
              </p>
              <p
                v-if="inlineShowBulkConfirm && !inlineBulkConfirmed"
                class="material-scan-shelf-card__line-hint"
              >
                {{ t('activities.materialJourney.scan.bulkConfirmHint') }}
              </p>
              <div class="material-scan-shelf-card__line-actions">
                <PackIssueQuickActions
                  v-if="inlineShowIssueActions && inlineResult.packItem"
                  :is-consumable="inlineIssueIsConsumable === true"
                  :material-item-id="inlineResult.packItem.materialItemId"
                  :material-name="inlineResult.title"
                  :show-consumption="inlineShowIssueConsumption !== false"
                  compact
                  @consumed="emit('inlineConsumed')"
                  @loss="emit('inlineLoss')"
                  @repair="emit('inlineRepair')"
                  @damage="emit('inlineDamage')"
                />
                <EButton
                  v-if="inlineShowBulkConfirm && !inlineBulkConfirmed"
                  variant="secondary"
                  size="small"
                  @click="emit('inlineConfirmBulk')"
                >
                  {{ t('activities.materialJourney.scan.bulkConfirm') }}
                </EButton>
                <EButton
                  v-if="inlinePrimaryEnabled"
                  variant="primary"
                  size="small"
                  @click="emit('inlinePrimary')"
                >
                  {{ inlinePrimaryLabel }}
                </EButton>
                <EButton
                  v-if="inlineShowInCrate"
                  variant="secondary"
                  size="small"
                  @click="emit('inlineInCrate')"
                >
                  {{ inlineInCrateLabel }}
                </EButton>
                <EButton variant="secondary" size="small" @click="emit('inlineDismissLine')">
                  {{ t('common.close') }}
                </EButton>
              </div>
            </div>
          </li>
        </ul>
      </section>
    </div>

    <div class="material-scan-shelf-card__actions">
      <EButton variant="secondary" size="small" @click="emit('dismiss')">
        {{ t('common.close') }}
      </EButton>
    </div>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
