<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.planung.abholenIntro') }}</p>

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />

    <ul v-else-if="rows.length" class="abholen-list">
      <li v-for="row in rows" :key="row.id">
        <strong>{{ row.objectName }}</strong>
        <span>{{ row.who }} · {{ row.fromLabel }} – {{ row.toLabel }}</span>
        <span>{{ t('grossanlass.materialUebersicht.qty', { n: row.qty }) }}</span>
      </li>
    </ul>

    <EEmptyState
      v-else
      icon="mdi-package-variant"
      :title="t('grossanlass.planung.abholenEmptyTitle')"
      :description="t('grossanlass.planung.abholenEmptyText')"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'

const { t } = useI18n()
const uebersicht = useGaUebersicht()
const catalog = useGaCommitmentCatalog()

const loading = computed(() => uebersicht.loading.value || catalog.loading.value)

/** Abhol-Einsätze ohne Bereich: an keinem Bauprojekt aufgehängt. */
const loosePickupIds = computed(() => {
  const ids = new Set<string>()
  for (const article of catalog.commitments.value) {
    const pickupId = article.item_details?.pickup_einsatz_id
    if (!pickupId) continue
    if (article.item_details?.inbound_mode === 'delivery') continue
    ids.add(pickupId)
  }
  return ids
})

const rows = computed(() =>
  uebersicht.bookingRows().filter((row) => loosePickupIds.value.has(row.id) && !row.groupId),
)
</script>

<style scoped>
.abholen-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.abholen-list li {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
}
</style>
