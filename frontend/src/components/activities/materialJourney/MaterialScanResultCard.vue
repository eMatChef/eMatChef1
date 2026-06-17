<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import type { MaterialScanResolveResult } from '@/composables/materialScanResolve'

const props = defineProps<{
  result: MaterialScanResolveResult
  message: string
  primaryLabel: string
  primaryEnabled: boolean
  showBulkConfirm: boolean
  bulkConfirmed: boolean
}>()

const emit = defineEmits<{
  primary: []
  confirmBulk: []
  dismiss: []
}>()

const { t } = useI18n()

const toneClass = computed(() => `material-scan-result-card--${props.result.tone}`)
</script>

<template>
  <section class="material-scan-result-card section-card" :class="toneClass" aria-live="polite">
    <div class="material-scan-result-card__body">
      <p class="material-scan-result-card__title">{{ result.title }}</p>
      <p class="material-scan-result-card__message text-muted">{{ message }}</p>
      <p v-if="showBulkConfirm && !bulkConfirmed" class="material-scan-result-card__hint">
        {{ t('activities.materialJourney.scan.bulkConfirmHint') }}
      </p>
    </div>
    <div class="material-scan-result-card__actions">
      <EButton
        v-if="showBulkConfirm && !bulkConfirmed"
        variant="secondary"
        size="small"
        @click="emit('confirmBulk')"
      >
        {{ t('activities.materialJourney.scan.bulkConfirm') }}
      </EButton>
      <EButton
        v-if="primaryEnabled"
        variant="primary"
        size="small"
        @click="emit('primary')"
      >
        {{ primaryLabel }}
      </EButton>
      <EButton variant="secondary" size="small" @click="emit('dismiss')">
        {{ t('common.close') }}
      </EButton>
    </div>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
