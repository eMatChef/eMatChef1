<template>
  <div class="beschaffung-uebersicht">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.uebersicht.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <template v-else-if="overview">
      <div class="stats-grid">
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.uebersicht.statSoll') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.soll_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.uebersicht.statIst') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.ist_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.uebersicht.statDelta') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.delta_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.uebersicht.statOpenQuotes') }}</span>
          <strong class="stat-value">{{ overview.totals.open_quotes_count }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.uebersicht.statOrderedOpen') }}</span>
          <strong class="stat-value">{{ overview.totals.ordered_not_received_count }}</strong>
        </div>
      </div>

      <h3 class="section-title">{{ t('grossanlass.beschaffung.uebersicht.byRessort') }}</h3>
      <EEmptyState
        v-if="overview.by_group.length === 0"
        variant="default"
        icon="mdi-chart-box-outline"
        :title="t('grossanlass.beschaffung.uebersicht.noDataTitle')"
        :description="t('grossanlass.beschaffung.uebersicht.noDataDescription')"
      />
      <div v-else class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ t('grossanlass.beschaffung.uebersicht.colRessort') }}</th>
              <th>{{ t('grossanlass.beschaffung.uebersicht.colLines') }}</th>
              <th>{{ t('grossanlass.beschaffung.uebersicht.colSoll') }}</th>
              <th>{{ t('grossanlass.beschaffung.uebersicht.colIst') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in overview.by_group" :key="row.group_id">
              <td>{{ row.group_name }}</td>
              <td>{{ row.line_count }}</td>
              <td>{{ formatChf(row.soll_chf) }}</td>
              <td>{{ formatChf(row.ist_chf) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h3 class="section-title section-title--spaced">{{ t('grossanlass.beschaffung.uebersicht.byCategory') }}</h3>
      <div v-if="categoryRows.length > 0" class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ t('grossanlass.beschaffung.uebersicht.colCategory') }}</th>
              <th>{{ t('grossanlass.beschaffung.uebersicht.colLines') }}</th>
              <th>{{ t('grossanlass.beschaffung.uebersicht.colSoll') }}</th>
              <th>{{ t('grossanlass.beschaffung.uebersicht.colIst') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in categoryRows" :key="row.category_id ?? 'uncategorized'">
              <td>{{ categoryRowLabel(row) }}</td>
              <td>{{ row.line_count }}</td>
              <td>{{ formatChf(row.soll_chf) }}</td>
              <td>{{ formatChf(row.ist_chf) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  formatChf,
  getGrossanlassProcurementOverview,
  type GrossanlassProcurementOverview,
} from '@/api/grossanlassProcurement'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = () => String(route.params.departmentId || '')
const isLoading = ref(true)
const overview = ref<GrossanlassProcurementOverview | null>(null)

const categoryRows = computed(() => overview.value?.by_category ?? [])

function categoryRowLabel(row: GrossanlassProcurementOverview['by_category'][number]): string {
  if (!row.category_name) {
    return t('grossanlass.beschaffung.bedarf.categoryUncategorized')
  }
  if (row.parent_name) {
    return `${row.parent_name} / ${row.category_name}`
  }
  return row.category_name
}

async function load() {
  if (!departmentId()) return
  isLoading.value = true
  try {
    overview.value = await getGrossanlassProcurementOverview(departmentId())
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.uebersicht.errorLoad'))
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.beschaffung-uebersicht { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}
.stat-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  background: #fff;
}
.stat-label { display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
.stat-value { font-size: 1.1rem; color: #0f172a; }
.section-title { margin: 0 0 10px; font-size: 0.95rem; font-weight: 600; }
.section-title--spaced { margin-top: 24px; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.data-table th { background: #f8fafc; font-weight: 600; }
</style>
