<template>
  <div class="beschaffung-finanzen">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.finanzen.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <template v-else-if="overview">
      <div class="rahmen-card">
        <div class="rahmen-card__fields">
          <ETextField
            v-model="rahmenInput"
            type="number"
            inputmode="decimal"
            step="0.01"
            min="0"
            :label="t('grossanlass.beschaffung.finanzen.rahmenLabel')"
            :hint="t('grossanlass.beschaffung.finanzen.rahmenHint')"
            persistent-hint
          />
          <EButton variant="primary" :loading="isSaving" @click="save">
            {{ t('grossanlass.beschaffung.finanzen.save') }}
          </EButton>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.finanzen.statRahmen') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.rahmen_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.finanzen.statSoll') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.soll_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.finanzen.statIst') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.ist_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.finanzen.statRest') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.rahmen_minus_ist_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.finanzen.statDelta') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.delta_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.finanzen.statOpenQuotes') }}</span>
          <strong class="stat-value">{{ overview.totals.open_quotes_count }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.finanzen.statOrderedOpen') }}</span>
          <strong class="stat-value">{{ overview.totals.ordered_not_received_count }}</strong>
        </div>
      </div>

      <h3 class="section-title">{{ t('grossanlass.beschaffung.finanzen.byRessort') }}</h3>
      <EEmptyState
        v-if="overview.by_group.length === 0"
        variant="default"
        icon="mdi-cash-multiple"
        :title="t('grossanlass.beschaffung.finanzen.noDataTitle')"
        :description="t('grossanlass.beschaffung.finanzen.noDataDescription')"
      />
      <div v-else class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ t('grossanlass.beschaffung.finanzen.colRessort') }}</th>
              <th>{{ t('grossanlass.beschaffung.finanzen.colLines') }}</th>
              <th>{{ t('grossanlass.beschaffung.finanzen.colSoll') }}</th>
              <th>{{ t('grossanlass.beschaffung.finanzen.colIst') }}</th>
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

      <h3 class="section-title section-title--spaced">{{ t('grossanlass.beschaffung.finanzen.byCategory') }}</h3>
      <p class="section-hint">{{ t('grossanlass.beschaffung.finanzen.byCategoryHint') }}</p>
      <div v-if="categoryRows.length > 0" class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ t('grossanlass.beschaffung.finanzen.colCategory') }}</th>
              <th>{{ t('grossanlass.beschaffung.finanzen.colRahmen') }}</th>
              <th>{{ t('grossanlass.beschaffung.finanzen.colLines') }}</th>
              <th>{{ t('grossanlass.beschaffung.finanzen.colSoll') }}</th>
              <th>{{ t('grossanlass.beschaffung.finanzen.colIst') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in categoryRows" :key="row.category_id ?? 'uncategorized'">
              <td>{{ categoryRowLabel(row) }}</td>
              <td>
                <input
                  v-if="row.category_id"
                  v-model="categoryRahmen[row.category_id]"
                  class="rahmen-input"
                  type="number"
                  min="0"
                  step="0.01"
                  :aria-label="t('grossanlass.beschaffung.finanzen.colRahmen')"
                >
                <span v-else>—</span>
              </td>
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
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ETextField } from '@/components/form/base'
import {
  formatChf,
  getGrossanlassProcurementOverview,
  saveGrossanlassProcurementRahmen,
  type GrossanlassProcurementOverview,
} from '@/api/grossanlassProcurement'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = () => String(route.params.departmentId || '')
const isLoading = ref(true)
const isSaving = ref(false)
const overview = ref<GrossanlassProcurementOverview | null>(null)
const rahmenInput = ref<string | number | null>(null)
const categoryRahmen = reactive<Record<string, string>>({})

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

function amountToInput(value: number | null | undefined): string {
  if (value == null) return ''
  return String(value)
}

function parseAmount(value: string | number | null | undefined): number | null {
  if (value === null || value === undefined || value === '') return null
  const normalized = String(value).replace(/['’\s]/g, '').replace(',', '.')
  if (normalized === '') return null
  const n = Number(normalized)
  if (Number.isNaN(n) || n < 0) {
    throw new Error('invalid')
  }
  return n
}

function applyOverview(data: GrossanlassProcurementOverview) {
  overview.value = data
  rahmenInput.value = amountToInput(data.totals.rahmen_chf)
  Object.keys(categoryRahmen).forEach((key) => {
    delete categoryRahmen[key]
  })
  for (const row of data.by_category) {
    if (row.category_id) {
      categoryRahmen[row.category_id] = amountToInput(row.rahmen_chf)
    }
  }
}

async function load() {
  if (!departmentId()) return
  isLoading.value = true
  try {
    applyOverview(await getGrossanlassProcurementOverview(departmentId()))
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.finanzen.errorLoad'))
  } finally {
    isLoading.value = false
  }
}

async function save() {
  if (!departmentId()) return
  isSaving.value = true
  try {
    const categories = Object.entries(categoryRahmen).map(([category_id, value]) => ({
      category_id,
      rahmen_chf: parseAmount(value),
    }))
    applyOverview(
      await saveGrossanlassProcurementRahmen(departmentId(), {
        rahmen_chf: parseAmount(rahmenInput.value),
        categories,
      }),
    )
    toast.success(t('grossanlass.beschaffung.finanzen.saved'))
  } catch (e: any) {
    if (e instanceof Error && e.message === 'invalid') {
      toast.error(t('grossanlass.beschaffung.finanzen.errorAmount'))
    } else {
      toast.error(e.response?.data?.error || t('grossanlass.beschaffung.finanzen.errorSave'))
    }
  } finally {
    isSaving.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.beschaffung-finanzen { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.rahmen-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
  background: #fff;
  margin-bottom: 16px;
}
.rahmen-card__fields {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px;
}
.rahmen-card__fields :deep(.e-form-field) {
  flex: 1 1 220px;
  max-width: 320px;
}
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
.section-hint { margin: -4px 0 10px; color: #64748b; font-size: 0.82rem; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.data-table th { background: #f8fafc; font-weight: 600; }
.rahmen-input {
  width: 120px;
  padding: 6px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font: inherit;
}
</style>
