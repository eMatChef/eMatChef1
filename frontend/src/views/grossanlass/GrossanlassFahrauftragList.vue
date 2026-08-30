<template>
  <section class="ga-trips">
    <div class="ga-trips__head">
      <h3 class="ga-trips__title">{{ t('grossanlass.materialUebersicht.tripsTitle') }}</h3>
      <span class="ga-trips__count">{{ rows.length }}</span>
    </div>
    <ul class="ga-trips__list">
      <li v-for="row in rows" :key="row.id" class="ga-trips__item">
        <div class="ga-trips__meta">
          <strong>{{ row.objectName }}</strong>
          <span>{{ t('grossanlass.materialUebersicht.qty', { n: row.qty }) }} · {{ row.ressort || '–' }}</span>
          <span>{{ row.fromLabel }} – {{ row.toLabel }}</span>
          <span v-if="row.who">{{ row.who }}</span>
        </div>
        <div class="ga-trips__flags">
          <span v-if="row.packed" class="ga-trips__badge">{{ t('grossanlass.materialUebersicht.tripsPacked') }}</span>
          <span v-if="row.tripReleased" class="ga-trips__badge ga-trips__badge--ok">
            {{ t('grossanlass.materialUebersicht.tripsReleased') }}
          </span>
          <span v-if="row.place === 'out'" class="ga-trips__badge ga-trips__badge--out">
            {{ t('grossanlass.materialUebersicht.tripsPlaceEmpty') }}
          </span>
          <span v-if="!row.destinationPlaceId" class="ga-trips__badge ga-trips__badge--warn">
            {{ t('grossanlass.materialUebersicht.tripsNoDestination') }}
          </span>
          <span v-if="row.status === 'issued'" class="ga-trips__badge">
            {{ t('grossanlass.materialUebersicht.status.issued') }}
          </span>
        </div>
        <div class="ga-trips__actions">
          <EButton
            v-if="row.status !== 'issued'"
            variant="secondary"
            size="small"
            :disabled="busyId === row.id"
            @click="$emit('toggle-packed', row)"
          >
            {{ row.packed ? t('grossanlass.materialUebersicht.tripsUnpack') : t('grossanlass.materialUebersicht.tripsPack') }}
          </EButton>
          <EButton
            v-if="row.packed && !row.tripReleased && row.status !== 'issued'"
            variant="primary"
            size="small"
            :disabled="busyId === row.id"
            @click="$emit('release', row)"
          >
            {{ t('grossanlass.materialUebersicht.tripsRelease') }}
          </EButton>
          <EButton
            v-if="row.tripReleased && row.status !== 'issued'"
            variant="primary"
            size="small"
            :disabled="busyId === row.id || !canStart(row)"
            @click="$emit('issue', row)"
          >
            {{ t('grossanlass.materialUebersicht.tripsIssue') }}
          </EButton>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import type { GaPreviewEinsatz } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

const props = defineProps<{
  rows: GaPreviewEinsatz[]
  busyId?: string | null
  canStartTrip?: (row: GaPreviewEinsatz) => boolean
}>()

defineEmits<{
  'toggle-packed': [row: GaPreviewEinsatz]
  release: [row: GaPreviewEinsatz]
  issue: [row: GaPreviewEinsatz]
}>()

const { t } = useI18n()

function canStart(row: GaPreviewEinsatz): boolean {
  if (!row.destinationPlaceId) return false
  if (props.canStartTrip) return props.canStartTrip(row)
  return true
}
</script>

<style scoped>
.ga-trips {
  margin: 0 0 20px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  padding: 14px 16px;
}
.ga-trips__head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.ga-trips__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
}
.ga-trips__count {
  font-size: 0.85rem;
  color: #64748b;
}
.ga-trips__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}
.ga-trips__item {
  display: grid;
  gap: 8px;
  padding: 10px 0;
  border-top: 1px solid #f1f5f9;
}
.ga-trips__item:first-child { border-top: 0; padding-top: 0; }
.ga-trips__meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 0.85rem;
  color: #64748b;
}
.ga-trips__meta strong { color: #0f172a; font-size: 0.95rem; }
.ga-trips__flags { display: flex; flex-wrap: wrap; gap: 6px; }
.ga-trips__badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: #e2e8f0;
  color: #334155;
}
.ga-trips__badge--ok { background: #ccfbf1; color: #0f766e; }
.ga-trips__badge--out { background: #ffedd5; color: #c2410c; }
.ga-trips__badge--warn { background: #fee2e2; color: #b91c1c; }
.ga-trips__actions { display: flex; flex-wrap: wrap; gap: 8px; }
</style>
