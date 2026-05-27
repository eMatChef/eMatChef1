<template>
  <div class="accounting-subpage accounting-overview">
    <p class="description intro">
      {{ t('accounting.overview.intro') }}
    </p>

    <div v-if="!yearOptions.length && !loading" class="empty-hint">
      {{ t('accounting.common.noBookingYears') }}
    </div>
    <div v-else-if="loadError" class="overview-error">{{ loadError }}</div>
    <div v-else-if="loading" class="loading-inline">{{ t('accounting.common.loading') }}</div>
    <template v-else-if="overview">
      <div class="overview-toolbar">
        <label class="overview-year-label">
          {{ t('accounting.overview.yearDetailLabel') }}
          <select v-model.number="selectedYear" class="filter-select" @change="reload">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </label>
      </div>

      <div class="acc-kpi-grid">
        <div class="acc-kpi-card">
          <div class="acc-kpi-label">{{ t('accounting.overview.kpiCostsActual', { year: overview.selected_year }) }}</div>
          <div class="acc-kpi-value">CHF {{ formatMoney(overview.selected_year_total_chf) }}</div>
          <div class="acc-kpi-meta">{{ t('accounting.overview.kpiBookingsMeta', { count: overview.selected_year_booking_count }) }}</div>
        </div>
        <div class="acc-kpi-card">
          <div class="acc-kpi-label">{{ t('accounting.overview.kpiCostCenters') }}</div>
          <div class="acc-kpi-value">{{ overview.cost_center_count }}</div>
          <div class="acc-kpi-meta">
            <router-link :to="{ name: 'AccountingCostCenters', params: { departmentId } }">{{ t('accounting.common.manage') }}</router-link>
          </div>
        </div>
        <div class="acc-kpi-card" :class="{ 'acc-kpi-card--warn': overview.pending_followup_count > 0 }">
          <div class="acc-kpi-label">{{ t('accounting.overview.kpiPending') }}</div>
          <div class="acc-kpi-value">{{ overview.pending_followup_count }}</div>
          <div class="acc-kpi-meta">
            <router-link
              v-if="overview.pending_followup_count > 0"
              :to="{ name: 'AccountingBookings', params: { departmentId }, query: { sub: 'assign' } }"
            >
              {{ t('accounting.common.assign') }}
            </router-link>
            <span v-else>{{ t('accounting.common.emDash') }}</span>
          </div>
        </div>
      </div>

      <section v-if="overview.years.length" class="overview-section">
        <h2 class="overview-section-title">{{ t('accounting.overview.sectionByYear') }}</h2>
        <div class="cost-centers-table-wrap">
          <table class="cost-centers-table overview-table">
            <thead>
              <tr>
                <th>{{ t('accounting.overview.colYear') }}</th>
                <th>{{ t('accounting.overview.colBookings') }}</th>
                <th class="col-num">{{ t('accounting.overview.colSumChf') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in overview.years" :key="row.year">
                <td>
                  <button type="button" class="linklike" @click="selectedYear = row.year; reload()">
                    {{ row.year }}
                  </button>
                </td>
                <td>{{ row.booking_count }}</td>
                <td class="col-num"><strong>CHF {{ formatMoney(row.total_chf) }}</strong></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="overview-section">
        <h2 class="overview-section-title">{{ t('accounting.overview.sectionByCc', { year: overview.selected_year }) }}</h2>
        <div v-if="!overview.by_cost_center.length" class="empty-hint">{{ t('accounting.overview.emptyNoCenters') }}</div>
        <div v-else class="cost-centers-table-wrap">
          <table class="cost-centers-table overview-table">
            <thead>
              <tr>
                <th>{{ t('accounting.overview.colCostCenter') }}</th>
                <th>{{ t('accounting.overview.colBookings') }}</th>
                <th class="col-num">{{ t('accounting.overview.colSumChf') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in overview.by_cost_center" :key="row.cost_center_id">
                <td>{{ row.name }}</td>
                <td>{{ row.booking_count }}</td>
                <td class="col-num">CHF {{ formatMoney(row.total_chf) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="overview.by_entry_type.length" class="overview-section">
        <h2 class="overview-section-title">{{ t('accounting.overview.sectionByEntryType', { year: overview.selected_year }) }}</h2>
        <div class="cost-centers-table-wrap">
          <table class="cost-centers-table overview-table">
            <thead>
              <tr>
                <th>{{ t('accounting.common.type') }}</th>
                <th>{{ t('accounting.overview.colBookings') }}</th>
                <th class="col-num">{{ t('accounting.overview.colSumChf') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in overview.by_entry_type" :key="row.entry_type">
                <td>{{ entryLabel(row.entry_type) }}</td>
                <td>{{ row.booking_count }}</td>
                <td class="col-num">CHF {{ formatMoney(row.total_chf) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <p class="overview-footnote">
        <router-link :to="{ name: 'AccountingBookings', params: { departmentId } }">{{ t('accounting.common.linkToBookings') }}</router-link>
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { getAccountingOverview, type AccountingOverview } from '@/api/accountingOverview'
import { useAccountingBookingYears } from '@/composables/useAccountingBookingYears'

const route = useRoute()
const { t, te } = useI18n()
const departmentId = computed(() => String(route.params.departmentId || ''))

function entryLabel(k: string): string {
  const key = `accounting.entryType.${k}`
  return te(key) ? t(key) : k
}

const loading = ref(true)
const loadError = ref('')
const overview = ref<AccountingOverview | null>(null)
const { years: yearOptions, refreshYears, defaultYear } = useAccountingBookingYears(departmentId)
const selectedYear = ref(new Date().getFullYear())

function formatMoney(s: string): string {
  const n = parseFloat(s)
  if (Number.isNaN(n)) return s
  return n.toFixed(2)
}

async function load() {
  const id = departmentId.value
  if (!id) return
  loading.value = true
  loadError.value = ''
  try {
    await refreshYears()
    if (yearOptions.value.length && !yearOptions.value.includes(selectedYear.value)) {
      const dy = defaultYear()
      if (dy != null) selectedYear.value = dy
    }
    if (!yearOptions.value.length) {
      overview.value = null
      return
    }
    const data = await getAccountingOverview(id, selectedYear.value)
    overview.value = data
    selectedYear.value = data.selected_year
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    loadError.value = msg || t('accounting.overview.loadError')
    overview.value = null
  } finally {
    loading.value = false
  }
}

function reload() {
  void load()
}

watch(
  departmentId,
  () => {
    void load()
  },
  { immediate: true }
)
</script>

<style scoped>
@import '@/styles/accounting-view.css';

.intro {
  margin-bottom: 20px;
}

.overview-toolbar {
  margin-bottom: 20px;
}

.overview-year-label {
  display: inline-flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.filter-select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  min-width: 120px;
}

.acc-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 28px;
}

.acc-kpi-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px 18px;
}

.acc-kpi-card--warn {
  border-color: #fbbf24;
  background: linear-gradient(135deg, #fffbeb 0%, #fff 40%);
}

.acc-kpi-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.acc-kpi-value {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin-top: 6px;
}

.acc-kpi-meta {
  font-size: 13px;
  color: #6b7280;
  margin-top: 8px;
}

.acc-kpi-meta a {
  color: #2563eb;
  text-decoration: none;
}

.acc-kpi-meta a:hover {
  text-decoration: underline;
}

.overview-section {
  margin-bottom: 28px;
}

.overview-section-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px;
}

.overview-table .col-num {
  text-align: right;
}

.linklike {
  background: none;
  border: none;
  padding: 0;
  color: #2563eb;
  cursor: pointer;
  font: inherit;
  text-decoration: underline;
}

.linklike:hover {
  color: #1d4ed8;
}

.overview-error {
  color: #b91c1c;
  font-size: 14px;
}

.overview-footnote {
  margin-top: 8px;
  font-size: 14px;
}

.loading-inline {
  padding: 24px;
  color: #6b7280;
}

.empty-hint {
  color: #6b7280;
  font-size: 14px;
  padding: 8px 0 16px;
}

.accounting-overview {
  max-width: 1040px;
}
</style>
