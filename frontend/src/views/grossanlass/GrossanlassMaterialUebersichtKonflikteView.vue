<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.materialUebersicht.konflikteIntro') }}</p>

    <div v-if="conflicts.length" class="ga-conflicts">
      <article
        v-for="conflict in conflicts"
        :key="conflict.id"
        class="ga-conflict-card"
      >
        <div class="ga-conflict-card__icon">
          <v-icon icon="mdi-alert-decagram-outline" size="22" />
        </div>
        <div>
          <h3 class="ga-conflict-card__title">{{ conflict.title }}</h3>
          <p class="ga-conflict-card__text">{{ conflict.text }}</p>
        </div>
      </article>
    </div>
    <EEmptyState
      v-else
      :title="t('grossanlass.materialUebersicht.emptyConflictsTitle')"
      :description="t('grossanlass.materialUebersicht.emptyConflictsText')"
    />

    <GrossanlassEinsatzPreviewPanel v-if="conflictRows.length" :rows="conflictRows" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import GrossanlassEinsatzPreviewPanel from '@/views/grossanlass/GrossanlassEinsatzPreviewPanel.vue'
import { createGrossanlassEinsatzPreview } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

const { t } = useI18n()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const preview = computed(() => createGrossanlassEinsatzPreview(tr))
const conflicts = computed(() => preview.value.conflicts)
const conflictRows = computed(() =>
  preview.value.einsaetze.filter((row) => !!row.conflictId),
)
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }

.ga-conflicts {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 18px;
}

.ga-conflict-card {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 12px 14px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fef2f2;
}

.ga-conflict-card__icon {
  color: #dc2626;
  margin-top: 2px;
}

.ga-conflict-card__title {
  margin: 0 0 4px;
  font-size: 0.95rem;
}

.ga-conflict-card__text {
  margin: 0;
  font-size: 0.85rem;
  color: #7f1d1d;
}
</style>
