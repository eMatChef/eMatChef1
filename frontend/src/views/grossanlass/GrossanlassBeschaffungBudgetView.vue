<template>
  <div class="beschaffung-budget">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.budget.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <template v-else-if="overview">
      <v-tabs
        :model-value="area"
        class="budget-area-tabs"
        color="primary"
        :aria-label="t('grossanlass.beschaffung.budget.tabsAria')"
        @update:model-value="onAreaChange"
      >
        <v-tab v-for="item in areaItems" :key="item.id" :value="item.id" :data-testid="`budget-tab-${item.id}`">
          {{ item.label }}
        </v-tab>
      </v-tabs>

      <p class="area-hint">{{ t(`grossanlass.beschaffung.budget.hint.${area}`) }}</p>

      <div v-if="overview.can_manage" class="limits">
        <ETextField
          v-model="materialInput"
          type="number"
          inputmode="decimal"
          step="0.01"
          min="0"
          :label="t('grossanlass.beschaffung.budget.limitMaterial')"
          hide-details
        />
        <ETextField
          v-model="logistikInput"
          type="number"
          inputmode="decimal"
          step="0.01"
          min="0"
          :label="t('grossanlass.beschaffung.budget.limitLogistik')"
          hide-details
        />
        <div class="limits__total">
          <span class="stat-label">{{ t('grossanlass.beschaffung.budget.limitTotal') }}</span>
          <strong>{{ formatChf(limitSum) }}</strong>
        </div>
        <EButton variant="primary" :loading="isSaving" @click="saveLimits">
          {{ t('grossanlass.beschaffung.budget.saveLimits') }}
        </EButton>
      </div>

      <p v-if="figures.over_budget" class="budget-alert" role="alert" data-testid="budget-over">
        {{ t('grossanlass.beschaffung.budget.overBudget', { amount: formatChf(Math.max(figures.overrun_expected_chf, figures.overrun_committed_chf)) }) }}
      </p>

      <div class="stats-grid">
        <div v-for="card in cards" :key="card.key" class="stat-card" :class="card.tone" :data-testid="`budget-${card.key}`">
          <span class="stat-label">{{ t(`grossanlass.beschaffung.budget.${card.key}`) }}</span>
          <strong class="stat-value">{{ formatChf(card.value) }}</strong>
        </div>
      </div>

      <p class="area-hint">{{ t('grossanlass.beschaffung.budget.stageHint') }}</p>
    </template>

    <EEmptyState v-else icon="mdi-cash-multiple" :title="t('grossanlass.beschaffung.budget.loadError')" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, ETextField } from '@/components/form/base'
import {
  formatChf,
  getGrossanlassBudgetAreas,
  saveGrossanlassBudgetAreaLimits,
  type GrossanlassBudgetAreaKey,
  type GrossanlassBudgetAreaOverview,
} from '@/api/grossanlassProcurement'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => String(route.params.departmentId || ''))
const isLoading = ref(true)
const isSaving = ref(false)
const overview = ref<GrossanlassBudgetAreaOverview | null>(null)
const materialInput = ref<string | number | null>(null)
const logistikInput = ref<string | number | null>(null)

const AREAS: GrossanlassBudgetAreaKey[] = ['total', 'material', 'logistik']
const area = computed<GrossanlassBudgetAreaKey>(() => {
  const q = String(route.query.bereich || '')
  return (AREAS as string[]).includes(q) ? (q as GrossanlassBudgetAreaKey) : 'total'
})
const areaItems = computed(() => AREAS.map((id) => ({ id, label: t(`grossanlass.beschaffung.budget.tab.${id}`) })))
const figures = computed(() => overview.value![area.value])

const cards = computed(() => {
  const f = figures.value
  return [
    { key: 'planned', value: f.planned_chf, tone: '' },
    { key: 'expected', value: f.expected_chf, tone: f.overrun_expected_chf > 0 ? 'is-over' : '' },
    { key: 'offers', value: f.offers_chf, tone: '' },
    { key: 'commitments', value: f.commitments_chf, tone: '' },
    { key: 'ordered', value: f.ordered_chf, tone: '' },
    { key: 'actual', value: f.actual_chf, tone: '' },
    { key: 'remaining', value: f.remaining_chf, tone: f.remaining_chf < 0 ? 'is-over' : '' },
  ]
})

const toNumber = (v: string | number | null): number => {
  const n = typeof v === 'number' ? v : Number(String(v ?? '').replace(',', '.'))
  return Number.isFinite(n) && n > 0 ? n : 0
}
const limitSum = computed(() => toNumber(materialInput.value) + toNumber(logistikInput.value))

function onAreaChange(next: unknown) {
  if (typeof next !== 'string') return
  void router.replace({ query: { ...route.query, bereich: next === 'total' ? undefined : next } })
}

function apply(next: GrossanlassBudgetAreaOverview) {
  overview.value = next
  materialInput.value = next.material.planned_chf > 0 ? next.material.planned_chf : null
  logistikInput.value = next.logistik.planned_chf > 0 ? next.logistik.planned_chf : null
}

async function load() {
  isLoading.value = true
  try {
    apply(await getGrossanlassBudgetAreas(departmentId.value))
  } catch (e: unknown) {
    overview.value = null
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.budget.loadError'))
  } finally {
    isLoading.value = false
  }
}

async function saveLimits() {
  isSaving.value = true
  try {
    apply(
      await saveGrossanlassBudgetAreaLimits(departmentId.value, {
        material_chf: toNumber(materialInput.value) || null,
        logistik_chf: toNumber(logistikInput.value) || null,
      }),
    )
    toast.success(t('grossanlass.beschaffung.budget.saved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.budget.saveError'))
  } finally {
    isSaving.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.area-hint {
  margin: 8px 0 12px;
  font-size: 0.82rem;
  color: #64748b;
}
.limits {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px 20px;
  margin-bottom: 16px;
}
.limits :deep(.e-form-field) {
  flex: 1 1 200px;
  max-width: 260px;
}
.limits__total strong {
  display: block;
  font-size: 1.05rem;
  color: #0f172a;
}
.budget-alert {
  margin: 0 0 12px;
  padding: 8px 12px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fef2f2;
  color: #991b1b;
  font-size: 0.9rem;
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 12px;
}
.stat-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  background: #fff;
}
.stat-card.is-over {
  border-color: #fca5a5;
}
.stat-card.is-over .stat-value {
  color: #b91c1c;
}
.stat-label {
  display: block;
  margin-bottom: 4px;
  font-size: 0.75rem;
  color: #64748b;
}
.stat-value {
  font-size: 1.1rem;
  color: #0f172a;
}
</style>
