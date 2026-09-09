<template>
  <div class="ga-preview-page">
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
        <div class="ga-conflict-card__body">
          <h3 class="ga-conflict-card__title">{{ conflict.title }}</h3>
          <p class="ga-conflict-card__text">{{ conflict.text }}</p>
          <EButton variant="primary" size="small" @click="openConflict(conflict)">
            {{ conflict.einsatz_ids.length > 1
              ? t('grossanlass.materialUebersicht.conflictResolveBoth')
              : t('grossanlass.materialUebersicht.conflictResolveOne') }}
          </EButton>
        </div>
      </article>
    </div>
    <EEmptyState
      v-else
      :title="t('grossanlass.materialUebersicht.emptyConflictsTitle')"
      :description="t('grossanlass.materialUebersicht.emptyConflictsText')"
    />

    <GrossanlassEinsatzPreviewPanel v-if="conflictRows.length" :rows="conflictRows" />

    <GrossanlassConflictResolveDialog
      v-model="dialogOpen"
      :conflict="activeConflict"
      :rows="allRows"
      :present-from-iso="presentFromIso"
      :present-to-iso="presentToIso"
      :stock="activeStock"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassEinsatzPreviewPanel from '@/views/grossanlass/GrossanlassEinsatzPreviewPanel.vue'
import GrossanlassConflictResolveDialog from '@/views/grossanlass/GrossanlassConflictResolveDialog.vue'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import type { GaUebersichtConflict } from '@/api/grossanlassUebersicht'

const { t } = useI18n()
const uebersicht = useGaUebersicht()
const catalog = useGaCommitmentCatalog()

const dialogOpen = ref(false)
const activeConflict = ref<GaUebersichtConflict | null>(null)

const conflicts = computed(() => uebersicht.data.value?.conflicts ?? [])
const allRows = computed(() => uebersicht.bookingRows())
const conflictRows = computed(() => allRows.value.filter((row) => !!row.conflictId))

const presentFromIso = computed(() => {
  const id = activeConflict.value?.object_id
  if (!id) return ''
  return catalog.articles.value.find((article) => article.id === id)?.presentFromIso || ''
})
const presentToIso = computed(() => {
  const id = activeConflict.value?.object_id
  if (!id) return ''
  return catalog.articles.value.find((article) => article.id === id)?.presentToIso || ''
})
const activeStock = computed(() => {
  const id = activeConflict.value?.einsatz_ids[0]
  const row = allRows.value.find((item) => item.id === id)
  return row?.stock ?? 1
})

function openConflict(conflict: GaUebersichtConflict) {
  activeConflict.value = conflict
  dialogOpen.value = true
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: var(--color-text-muted, #6b7280); font-size: 0.9rem; }
.ga-conflicts { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }
.ga-conflict-card {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 12px 14px;
  border: 1px solid color-mix(in srgb, var(--color-error) 35%, transparent);
  border-radius: 10px;
  background: var(--color-error-bg);
}
.ga-conflict-card__icon { color: var(--color-error); margin-top: 2px; }
.ga-conflict-card__body { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; min-width: 0; }
.ga-conflict-card__title { margin: 0; font-size: 0.95rem; }
.ga-conflict-card__text { margin: 0; font-size: 0.85rem; color: var(--color-error); }
</style>
