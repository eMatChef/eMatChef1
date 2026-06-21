<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
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
}>()

const emit = defineEmits<{
  activateLine: [line: MaterialScanShelfLine]
  dismiss: []
}>()

const { t } = useI18n()

const toneClass = computed(() => `material-scan-shelf-card--${props.result.tone}`)

const lines = computed(() => props.result.shelfLines ?? [])

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

    <ul v-if="lines.length > 0" class="material-scan-shelf-card__list">
      <li v-for="line in lines" :key="line.packItem.id">
        <button
          type="button"
          class="material-scan-shelf-card__line"
          @click="emit('activateLine', line)"
        >
          <span class="material-scan-shelf-card__line-title">
            {{ packMaterialDisplayName(line.packItem) }}
          </span>
          <span v-if="quantityHint(line)" class="material-scan-shelf-card__line-qty">
            {{ quantityHint(line) }}
          </span>
          <span v-if="quantityProgress(line)" class="material-scan-shelf-card__line-progress text-muted">
            {{ quantityProgress(line) }}
          </span>
        </button>
      </li>
    </ul>

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
