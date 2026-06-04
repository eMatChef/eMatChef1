<template>
  <div class="accounting-subpage accounting-group-costs">
    <p class="description intro">
      {{ t('accounting.groupCosts.intro') }}
    </p>
    <p v-if="data?.scope === 'leader_limited'" class="muted-hint">
      {{ t('accounting.groupCosts.leaderScopeHint') }}
    </p>

    <div class="gc-toolbar">
      <ESelect
        v-model="year"
        :items="yearSelectItems"
        :label="t('accounting.common.year')"
        hide-details="auto"
        class="gc-year-select"
        @update:model-value="reload"
      />
    </div>

    <EEmptyState
      v-if="!yearOptions.length && !loading"
      :title="t('accounting.common.noBookingYears')"
    />
    <p v-else-if="loadError" class="gc-error">{{ loadError }}</p>
    <ELoadingState v-else-if="loading" variant="inline" :message="t('accounting.common.loading')" />
    <template v-else-if="data">
      <EEmptyState
        v-if="data.rows.length === 0"
        :title="t('accounting.groupCosts.empty', { year })"
      />
      <template v-else>
        <div class="acc-kpi-grid gc-kpis">
          <div class="acc-kpi-card">
            <div class="acc-kpi-label">{{ t('accounting.groupCosts.kpiSumLabel', { year }) }}</div>
            <div class="acc-kpi-value">CHF {{ formatMoney(data.totals.ist_chf) }}</div>
            <div class="acc-kpi-meta">{{ t('accounting.groupCosts.kpiMeta', { count: data.totals.booking_count }) }}</div>
          </div>
          <div v-if="parseFloat(data.totals.open_chf) > 0" class="acc-kpi-card acc-kpi-card--warn">
            <div class="acc-kpi-label">{{ t('accounting.groupCosts.kpiOpen') }}</div>
            <div class="acc-kpi-value">CHF {{ formatMoney(data.totals.open_chf) }}</div>
          </div>
        </div>
        <div class="cost-centers-table-wrap">
          <table class="cost-centers-table gc-table">
            <thead>
              <tr>
                <th>{{ t('common.group') }}</th>
                <th class="col-num">{{ t('accounting.groupCosts.colBookings') }}</th>
                <th class="col-num">{{ t('accounting.groupCosts.colSumChf') }}</th>
                <th class="col-num">{{ t('accounting.groupCosts.colOpenChf') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.rows" :key="row.group_id">
                <td>{{ row.group_name }}</td>
                <td class="col-num">{{ row.booking_count }}</td>
                <td class="col-num"><strong>CHF {{ formatMoney(row.total_chf) }}</strong></td>
                <td class="col-num">
                  <span v-if="parseFloat(row.open_chf) > 0" class="open-amount">CHF {{ formatMoney(row.open_chf) }}</span>
                  <span v-else class="muted">{{ t('accounting.common.dash') }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { listGroupCosts, type AccountingGroupCostsResponse } from '@/api/accountingGroupCosts'
import { useAccountingBookingYears } from '@/composables/useAccountingBookingYears'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { ESelect } from '@/components/form/base'

const route = useRoute()
const { t } = useI18n()
const departmentId = computed(() => String(route.params.departmentId || ''))

const { years: yearOptions, refreshYears, defaultYear } = useAccountingBookingYears(departmentId)
const year = ref(new Date().getFullYear())

const yearSelectItems = computed(() =>
  yearOptions.value.map((y) => ({ title: String(y), value: y }))
)

const loading = ref(true)
const loadError = ref('')
const data = ref<AccountingGroupCostsResponse | null>(null)

function formatMoney(s: string): string {
  const n = parseFloat(s)
  if (Number.isNaN(n)) return s
  return n.toFixed(2)
}

async function bootstrap() {
  await refreshYears()
  const dy = defaultYear()
  if (dy != null) year.value = dy
}

async function load() {
  const id = departmentId.value
  if (!id) return
  if (!yearOptions.value.length) {
    data.value = null
    loading.value = false
    return
  }
  loading.value = true
  loadError.value = ''
  try {
    data.value = await listGroupCosts(id, year.value)
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    loadError.value = msg || t('accounting.groupCosts.loadError')
    data.value = null
  } finally {
    loading.value = false
  }
}

function reload() {
  void load()
}

watch(
  departmentId,
  async () => {
    await bootstrap()
    await load()
  },
  { immediate: true },
)
</script>

<style scoped>
@import '@/styles/accounting-view.css';

.intro {
  margin-bottom: 18px;
}

.muted-hint {
  font-size: 13px;
  color: #6b7280;
  margin: -8px 0 16px;
}

.gc-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
  margin-bottom: 20px;
}

.gc-year-select {
  max-width: 160px;
}

.gc-table .col-num {
  text-align: right;
}

.open-amount {
  color: #b45309;
  font-weight: 600;
}

.gc-error {
  color: #b91c1c;
  font-size: 14px;
}

.accounting-group-costs {
  max-width: 920px;
}
</style>
