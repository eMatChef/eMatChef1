<template>
  <div class="ga-einsatz">
    <div class="ga-einsatz__toolbar">
      <div class="ga-einsatz__views" role="tablist">
        <button
          type="button"
          class="ga-einsatz__view-btn"
          :class="{ 'ga-einsatz__view-btn--active': viewMode === 'object' }"
          @click="viewMode = 'object'"
        >
          {{ t('grossanlass.materialUebersicht.viewByObject') }}
        </button>
        <button
          type="button"
          class="ga-einsatz__view-btn"
          :class="{ 'ga-einsatz__view-btn--active': viewMode === 'ressort' }"
          @click="viewMode = 'ressort'"
        >
          {{ t('grossanlass.materialUebersicht.viewByRessort') }}
        </button>
      </div>
      <p class="ga-einsatz__hint">{{ t('grossanlass.materialUebersicht.timelineHint') }}</p>
    </div>

    <article
      v-for="group in groups"
      :key="group.key"
      class="ga-einsatz-group"
    >
      <header class="ga-einsatz-group__head">
        <div>
          <h3 class="ga-einsatz-group__title">{{ group.title }}</h3>
          <p v-if="group.subtitle" class="ga-einsatz-group__sub">{{ group.subtitle }}</p>
        </div>
        <span class="ga-einsatz-group__meta">
          {{ kindLabel(group.rows[0]) }}
          <template v-if="group.rows[0]?.kind === 'quantity'">
            · {{ t('grossanlass.materialUebersicht.stockQty', { n: group.rows[0].stock }) }}
          </template>
        </span>
      </header>

      <div class="ga-einsatz-tl" aria-hidden="true">
        <div class="ga-einsatz-tl__days">
          <span v-for="day in days" :key="day.iso">{{ t(`grossanlass.materialUebersicht.${day.key}`) }}</span>
        </div>
        <div class="ga-einsatz-tl__track">
          <span
            v-for="row in group.rows"
            :key="row.id"
            class="ga-einsatz-tl__bar"
            :class="barClass(row)"
            :style="einsatzBarStyle(row)"
            :title="barTitle(row)"
          >
            {{ row.ressort }}
          </span>
        </div>
      </div>

      <ul class="ga-einsatz-list">
        <li
          v-for="row in group.rows"
          :key="row.id"
          class="ga-einsatz-row"
          :class="{ 'ga-einsatz-row--conflict': !!row.conflictId }"
        >
          <div class="ga-einsatz-row__main">
            <strong>{{ viewMode === 'object' ? row.ressort : row.objectName }}</strong>
            <span v-if="viewMode === 'object' && row.bauprojekt"> · {{ row.bauprojekt }}</span>
            <span v-else-if="viewMode === 'ressort' && row.bauprojekt"> · {{ row.bauprojekt }}</span>
          </div>
          <div class="ga-einsatz-row__meta">
            <span>{{ row.fromLabel }} – {{ row.toLabel }}</span>
            <span>{{ row.who }}</span>
            <span v-if="row.kind === 'quantity'">
              {{ t('grossanlass.materialUebersicht.qty', { n: row.qty }) }}
            </span>
            <span class="ga-einsatz-status" :class="`ga-einsatz-status--${row.status}`">
              {{ statusLabel(row.status) }}
            </span>
            <span v-if="row.conflictId" class="ga-einsatz-status ga-einsatz-status--conflict">
              {{ t('grossanlass.materialUebersicht.conflictBadge') }}
            </span>
          </div>
        </li>
      </ul>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  createGrossanlassEinsatzPreview,
  einsatzBarStyle,
  GA_EINSATZ_TIMELINE_DAYS,
  groupEinsaetze,
  type GaEinsatzStatus,
  type GaEinsatzViewMode,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'

const props = withDefaults(defineProps<{
  rows?: GaPreviewEinsatz[]
}>(), {
  rows: undefined,
})

const { t } = useI18n()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const viewMode = ref<GaEinsatzViewMode>('object')
const preview = computed(() => createGrossanlassEinsatzPreview(tr))
const days = GA_EINSATZ_TIMELINE_DAYS

const sourceRows = computed(() => props.rows ?? preview.value.einsaetze)
const groups = computed(() => groupEinsaetze(sourceRows.value, viewMode.value))

function kindLabel(row?: GaPreviewEinsatz): string {
  if (!row) return ''
  return row.kind === 'unique'
    ? t('grossanlass.materialUebersicht.kindUnique')
    : t('grossanlass.materialUebersicht.kindQuantity')
}

function statusLabel(status: GaEinsatzStatus): string {
  return t(`grossanlass.materialUebersicht.status.${status}`)
}

function barClass(row: GaPreviewEinsatz): Record<string, boolean> {
  return {
    'ga-einsatz-tl__bar--conflict': !!row.conflictId,
    'ga-einsatz-tl__bar--issued': row.status === 'issued',
    'ga-einsatz-tl__bar--returned': row.status === 'returned',
  }
}

function barTitle(row: GaPreviewEinsatz): string {
  return `${row.objectName} · ${row.ressort} · ${row.fromLabel} – ${row.toLabel}`
}
</script>

<style scoped>
.ga-einsatz {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ga-einsatz__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.ga-einsatz__views {
  display: inline-flex;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}

.ga-einsatz__view-btn {
  border: 0;
  background: transparent;
  padding: 8px 14px;
  font-size: 0.85rem;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
}

.ga-einsatz__view-btn--active {
  background: #ecfdf5;
  color: #047857;
}

.ga-einsatz__hint {
  margin: 0;
  font-size: 0.8rem;
  color: #64748b;
}

.ga-einsatz-group {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  overflow: hidden;
}

.ga-einsatz-group__head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: baseline;
  padding: 12px 16px 8px;
}

.ga-einsatz-group__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.ga-einsatz-group__sub {
  margin: 2px 0 0;
  font-size: 0.8rem;
  color: #64748b;
}

.ga-einsatz-group__meta {
  font-size: 0.78rem;
  color: #64748b;
  white-space: nowrap;
}

.ga-einsatz-tl {
  padding: 0 16px 12px;
}

.ga-einsatz-tl__days {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  font-size: 0.72rem;
  color: #94a3b8;
  margin-bottom: 6px;
}

.ga-einsatz-tl__track {
  position: relative;
  height: 28px;
  border-radius: 6px;
  background:
    repeating-linear-gradient(
      90deg,
      #f8fafc 0,
      #f8fafc calc(100% / 3 - 1px),
      #e2e8f0 calc(100% / 3 - 1px),
      #e2e8f0 calc(100% / 3)
    );
}

.ga-einsatz-tl__bar {
  position: absolute;
  top: 4px;
  height: 20px;
  border-radius: 4px;
  background: #059669;
  color: #fff;
  font-size: 0.65rem;
  line-height: 20px;
  padding: 0 6px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  box-sizing: border-box;
}

.ga-einsatz-tl__bar--issued {
  background: #d97706;
}

.ga-einsatz-tl__bar--returned {
  background: #64748b;
}

.ga-einsatz-tl__bar--conflict {
  background: #dc2626;
  box-shadow: 0 0 0 2px #fecaca;
  z-index: 1;
}

.ga-einsatz-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border-top: 1px solid #f1f5f9;
}

.ga-einsatz-row {
  padding: 10px 16px;
}

.ga-einsatz-row + .ga-einsatz-row {
  border-top: 1px solid #f1f5f9;
}

.ga-einsatz-row--conflict {
  background: #fef2f2;
}

.ga-einsatz-row__main {
  font-size: 0.9rem;
}

.ga-einsatz-row__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  margin-top: 4px;
  font-size: 0.78rem;
  color: #64748b;
}

.ga-einsatz-status {
  display: inline-flex;
  align-items: center;
  padding: 1px 8px;
  border-radius: 999px;
  font-weight: 600;
  font-size: 0.72rem;
}

.ga-einsatz-status--planned {
  background: #e0e7ff;
  color: #3730a3;
}

.ga-einsatz-status--issued {
  background: #ffedd5;
  color: #9a3412;
}

.ga-einsatz-status--returned {
  background: #f3f4f6;
  color: #4b5563;
}

.ga-einsatz-status--conflict {
  background: #fee2e2;
  color: #991b1b;
}
</style>
