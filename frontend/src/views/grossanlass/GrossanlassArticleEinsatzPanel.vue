<template>
  <section class="section-card">
    <div class="einsatz-head">
      <div>
        <h2 class="section-title">{{ t('grossanlass.materials.detailTabUsage') }}</h2>
        <p class="panel-intro">{{ t('grossanlass.materials.detailEinsatzIntro') }}</p>
        <p v-if="partnerLabel" class="panel-partner">{{ partnerLabel }}</p>
      </div>
      <div class="einsatz-head__actions">
        <EButton variant="secondary" size="small" @click="$emit('book-wish')">
          {{ t('grossanlass.materials.detailBookFromWish') }}
        </EButton>
        <EButton variant="primary" size="small" @click="$emit('book')">
          {{ t('grossanlass.materials.detailBookEinsatz') }}
        </EButton>
      </div>
    </div>

    <EEmptyState
      v-if="rows.length === 0"
      variant="default"
      icon="mdi-calendar-range"
      :title="t('grossanlass.materials.detailEinsatzEmptyTitle')"
      :description="t('grossanlass.materials.detailEinsatzEmpty')"
    />

    <ul v-else class="einsatz-list">
      <li v-for="row in rows" :key="row.id" class="einsatz-card">
        <div class="einsatz-card__head">
          <strong>{{ row.ressort || row.objectName }}</strong>
          <span class="einsatz-status">{{ t(`grossanlass.materialUebersicht.status.${row.status}`) }}</span>
        </div>
        <p>{{ row.fromLabel }} – {{ row.toLabel }}</p>
        <p v-if="row.qty" class="einsatz-meta">{{ t('grossanlass.materialUebersicht.qty', { n: row.qty }) }}</p>
        <p v-if="row.delivery === 'trip'" class="einsatz-meta">
          {{ t('grossanlass.materialUebersicht.deliveryTrip') }}
        </p>
        <p v-else-if="row.delivery === 'pickup'" class="einsatz-meta">
          {{ t('grossanlass.materialUebersicht.deliveryPickup') }}
        </p>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { formatGaIsoLabel, type GaZusageArticle } from '@/views/grossanlass/grossanlassZusagePreviewData'
import type { GaPreviewEinsatz } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

const props = defineProps<{
  article: GaZusageArticle
  rows: GaPreviewEinsatz[]
}>()

defineEmits<{
  book: []
  'book-wish': []
}>()

const { t, locale } = useI18n()

const partnerLabel = computed(() => {
  if (!props.article.presentFromIso || !props.article.presentToIso) return ''
  return t('grossanlass.planung.feinPartner.partnerWindow', {
    partner: props.article.source,
    from: formatGaIsoLabel(props.article.presentFromIso, locale.value),
    to: formatGaIsoLabel(props.article.presentToIso, locale.value),
  })
})
</script>

<style scoped>
.einsatz-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 12px;
}
.einsatz-head__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.panel-intro,
.panel-partner,
.einsatz-meta {
  margin: 0 0 6px;
  font-size: 0.85rem;
  color: #64748b;
}
.einsatz-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}
.einsatz-card {
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}
.einsatz-card__head {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin-bottom: 4px;
}
.einsatz-status {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  background: #e0f2fe;
  color: #075985;
}
.einsatz-card p { margin: 0; font-size: 0.9rem; }
</style>

