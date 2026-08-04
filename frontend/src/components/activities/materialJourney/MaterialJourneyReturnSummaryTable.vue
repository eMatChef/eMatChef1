<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  MaterialJourneyReturnSummaryRow,
  MaterialJourneySummaryMode,
} from '@/utils/materialJourneyReturnSummary'

const props = withDefaults(
  defineProps<{
    rows: MaterialJourneyReturnSummaryRow[]
    /** `archive` = Abschluss (inkl. Eingelagert / Nachkauf) */
    mode?: MaterialJourneySummaryMode
  }>(),
  { mode: 'return' },
)

const { t } = useI18n()

const isArchive = computed(() => props.mode === 'archive')

const showLoss = computed(() => props.rows.some((row) => row.loss > 0))
const showRepair = computed(() => props.rows.some((row) => row.repair > 0))
const showConsumption = computed(() => props.rows.some((row) => row.consumption > 0))
const showReplenished = computed(
  () => isArchive.value || props.rows.some((row) => row.replenished > 0),
)
const showStored = computed(
  () => isArchive.value || props.rows.some((row) => row.stored > 0),
)

const positionCount = computed(() => props.rows.length)

const titleKey = computed(() =>
  isArchive.value
    ? 'activities.materialJourney.completedSummary.title'
    : 'activities.materialJourney.returnSummary.title',
)
</script>

<template>
  <section class="material-journey-return-summary section-card" aria-labelledby="return-summary-title">
    <header class="material-journey-return-summary__header">
      <h2 id="return-summary-title" class="material-journey-return-summary__title">
        {{ t(titleKey) }}
      </h2>
      <p class="material-journey-return-summary__meta text-muted">
        {{
          t('activities.materialJourney.returnSummary.positionCount', {
            count: positionCount,
          })
        }}
      </p>
      <p v-if="isArchive" class="material-journey-return-summary__lead text-muted">
        {{ t('activities.materialJourney.completedSummary.lead') }}
      </p>
    </header>

    <div class="material-journey-return-summary__table-wrap">
      <table class="material-journey-return-summary__table">
        <thead>
          <tr>
            <th scope="col">{{ t('activities.materialJourney.returnSummary.columns.material') }}</th>
            <th scope="col" class="material-journey-return-summary__num">
              {{ t('activities.materialJourney.returnSummary.columns.issued') }}
            </th>
            <th scope="col" class="material-journey-return-summary__num">
              {{ t('activities.materialJourney.returnSummary.columns.returned') }}
            </th>
            <th v-if="showConsumption" scope="col" class="material-journey-return-summary__num">
              {{ t('activities.materialJourney.returnSummary.columns.consumption') }}
            </th>
            <th v-if="showLoss" scope="col" class="material-journey-return-summary__num">
              {{ t('activities.materialJourney.returnSummary.columns.loss') }}
            </th>
            <th v-if="showRepair" scope="col" class="material-journey-return-summary__num">
              {{ t('activities.materialJourney.returnSummary.columns.repair') }}
            </th>
            <th v-if="showReplenished" scope="col" class="material-journey-return-summary__num">
              {{ t('activities.materialJourney.returnSummary.columns.replenished') }}
            </th>
            <th v-if="showStored" scope="col" class="material-journey-return-summary__num">
              {{ t('activities.materialJourney.returnSummary.columns.stored') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            :class="{
              'material-journey-return-summary__row--crate': row.kind === 'crate',
              'material-journey-return-summary__row--nested': row.depth > 0,
            }"
          >
            <td class="material-journey-return-summary__material">
              <span
                class="material-journey-return-summary__name"
                :class="{ 'material-journey-return-summary__name--nested': row.depth > 0 }"
              >{{ row.name }}</span>
              <span
                v-if="row.isConsumable"
                class="material-journey-return-summary__tag"
                :class="{ 'material-journey-return-summary__tag--nested': row.depth > 0 }"
              >
                {{ t('activities.materialJourney.returnSummary.consumableTag') }}
              </span>
              <span
                v-if="row.kind === 'replenishment'"
                class="material-journey-return-summary__tag material-journey-return-summary__tag--replen"
                :class="{ 'material-journey-return-summary__tag--nested': row.depth > 0 }"
              >
                {{ t('activities.materialJourney.returnSummary.replenishmentTag') }}
              </span>
            </td>
            <td class="material-journey-return-summary__num">{{ row.issued || '—' }}</td>
            <td class="material-journey-return-summary__num">{{ row.returned || '—' }}</td>
            <td v-if="showConsumption" class="material-journey-return-summary__num">
              {{ row.consumption > 0 ? row.consumption : '—' }}
            </td>
            <td v-if="showLoss" class="material-journey-return-summary__num">
              {{ row.loss > 0 ? row.loss : '—' }}
            </td>
            <td v-if="showRepair" class="material-journey-return-summary__num">
              {{ row.repair > 0 ? row.repair : '—' }}
            </td>
            <td v-if="showReplenished" class="material-journey-return-summary__num">
              {{ row.replenished > 0 ? row.replenished : '—' }}
            </td>
            <td v-if="showStored" class="material-journey-return-summary__num">
              {{ row.stored > 0 ? row.stored : '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-journey-return-summary__header {
  margin-bottom: 10px;
}

.material-journey-return-summary__title {
  margin: 0 0 2px;
  font-size: 15px;
  font-weight: 600;
}

.material-journey-return-summary__meta {
  margin: 0;
  font-size: 13px;
}

.material-journey-return-summary__lead {
  margin: 6px 0 0;
  font-size: 13px;
}

.material-journey-return-summary__table-wrap {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.material-journey-return-summary__table {
  width: 100%;
  min-width: 280px;
  border-collapse: collapse;
  font-size: 14px;
}

.material-journey-return-summary__table th,
.material-journey-return-summary__table td {
  padding: 8px 6px;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.35);
  text-align: left;
  vertical-align: top;
}

.material-journey-return-summary__table th {
  font-size: 12px;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.65);
  white-space: nowrap;
}

.material-journey-return-summary__table tbody tr:last-child td {
  border-bottom: none;
}

.material-journey-return-summary__row--crate .material-journey-return-summary__name {
  font-weight: 600;
}

.material-journey-return-summary__material {
  min-width: 120px;
}

.material-journey-return-summary__name {
  display: block;
  font-weight: 500;
  word-break: break-word;
}

.material-journey-return-summary__name--nested {
  padding-left: 1.25rem;
  position: relative;
  font-weight: 500;
}

.material-journey-return-summary__name--nested::before {
  content: '';
  position: absolute;
  left: 0.35rem;
  top: 0.35em;
  bottom: 0.35em;
  width: 2px;
  border-radius: 1px;
  background: rgba(var(--v-theme-on-surface), 0.2);
}

.material-journey-return-summary__tag {
  display: inline-block;
  margin-top: 4px;
  margin-right: 4px;
  padding: 1px 6px;
  border-radius: 999px;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  font-size: 11px;
  font-weight: 600;
}

.material-journey-return-summary__tag--replen {
  background: rgba(var(--v-theme-warning), 0.12);
  color: rgb(var(--v-theme-warning));
}

.material-journey-return-summary__tag--nested {
  margin-left: 1.25rem;
}

.material-journey-return-summary__num {
  text-align: right;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
</style>
