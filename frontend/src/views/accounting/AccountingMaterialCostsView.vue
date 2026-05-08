<template>
  <div class="accounting-subpage accounting-material-costs">
    <p class="description intro">
      {{ t('accounting.materialCosts.intro') }}
    </p>

    <div class="mc-toolbar">
      <label class="mc-year-label">
        {{ t('accounting.common.year') }}
        <select v-model.number="year" class="filter-select" @change="reload">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </label>
      <router-link
        class="btn btn-secondary btn-sm"
        :to="{ name: 'AccountingBookings', params: { departmentId } }"
      >
        {{ t('accounting.common.linkToBookings') }}
      </router-link>
    </div>

    <div v-if="loadError" class="mc-error">{{ loadError }}</div>
    <div v-else-if="loading" class="loading-inline">{{ t('accounting.common.loading') }}</div>
    <template v-else-if="data">
      <div v-if="data.rows.length === 0" class="empty-hint">
        {{ t('accounting.materialCosts.empty', { year }) }}
      </div>
      <template v-else>
        <div class="acc-kpi-grid mc-kpis">
          <div class="acc-kpi-card">
            <div class="acc-kpi-label">{{ t('accounting.materialCosts.kpiSumLabel', { year }) }}</div>
            <div class="acc-kpi-value">CHF {{ formatMoney(data.totals.total_chf) }}</div>
            <div class="acc-kpi-meta">{{ t('accounting.materialCosts.kpiMeta', { count: data.totals.booking_count }) }}</div>
          </div>
        </div>
        <div class="cost-centers-table-wrap">
          <table class="cost-centers-table mc-table">
            <thead>
              <tr>
                <th>{{ t('accounting.materialCosts.colMaterial') }}</th>
                <th class="col-num">{{ t('accounting.materialCosts.colBookings') }}</th>
                <th class="col-num">{{ t('accounting.materialCosts.colSumChf') }}</th>
                <th class="col-actions">{{ t('accounting.common.action') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.rows" :key="row.material_id">
                <td>{{ row.material_name }}</td>
                <td class="col-num">{{ row.booking_count }}</td>
                <td class="col-num"><strong>CHF {{ formatMoney(row.total_chf) }}</strong></td>
                <td class="col-actions">
                  <router-link
                    class="btn-outline btn-xs"
                    :to="materialLink(row.material_id)"
                  >
                    {{ t('accounting.materialCosts.openMaterial') }}
                  </router-link>
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
import { getMaterialCosts, type MaterialCostsResponse } from '@/api/accountingMaterialCosts'

const route = useRoute()
const { t } = useI18n()
const departmentId = computed(() => String(route.params.departmentId || ''))

const year = ref(new Date().getFullYear())
const yearOptions = computed(() => {
  const cy = new Date().getFullYear()
  const list: number[] = []
  for (let y = cy + 1; y >= cy - 6; y--) list.push(y)
  return list
})

const loading = ref(true)
const loadError = ref('')
const data = ref<MaterialCostsResponse | null>(null)

function formatMoney(s: string): string {
  const n = parseFloat(s)
  if (Number.isNaN(n)) return s
  return n.toFixed(2)
}

function materialLink(materialId: string) {
  return {
    name: 'MaterialDetail',
    params: { departmentId: departmentId.value, materialId },
  }
}

async function load() {
  const id = departmentId.value
  if (!id) return
  loading.value = true
  loadError.value = ''
  try {
    data.value = await getMaterialCosts(id, year.value)
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    loadError.value = msg || t('accounting.materialCosts.loadError')
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
  () => {
    void load()
  },
  { immediate: true }
)
</script>

<style scoped>
@import '@/styles/accounting-view.css';

.intro {
  margin-bottom: 18px;
}

.mc-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}

.mc-year-label {
  display: flex;
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
  margin-bottom: 20px;
}

.acc-kpi-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px 18px;
}

.acc-kpi-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
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

.mc-table .col-num {
  text-align: right;
}

.mc-error {
  color: #b91c1c;
  font-size: 14px;
}

.empty-hint {
  color: #6b7280;
  font-size: 14px;
  line-height: 1.5;
}

.loading-inline {
  padding: 20px;
  color: #6b7280;
}

.accounting-material-costs {
  max-width: 920px;
}
</style>
