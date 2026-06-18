<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EButton from '@/components/form/base/EButton.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import { getDepartmentMaterialUsageStats, type DepartmentMaterialUsageItem } from '@/api/departmentMaterialUsage'

const route = useRoute()
const { t } = useI18n()

const departmentId = computed(() => String(route.params.departmentId || ''))

const fromDate = ref('')
const toDate = ref('')
const limit = ref(20)
const loading = ref(false)
const error = ref<string | null>(null)
const items = ref<DepartmentMaterialUsageItem[]>([])

function defaultFromDate(): string {
  const d = new Date()
  d.setMonth(d.getMonth() - 3)
  return d.toISOString().slice(0, 10)
}

function defaultToDate(): string {
  return new Date().toISOString().slice(0, 10)
}

fromDate.value = defaultFromDate()
toDate.value = defaultToDate()

async function loadStats(): Promise<void> {
  if (!departmentId.value) return
  loading.value = true
  error.value = null
  try {
    const fromIso = fromDate.value ? new Date(`${fromDate.value}T00:00:00`).toISOString() : undefined
    const toIso = toDate.value ? new Date(`${toDate.value}T23:59:59`).toISOString() : undefined
    const result = await getDepartmentMaterialUsageStats(departmentId.value, {
      from: fromIso,
      to: toIso,
      limit: limit.value,
    })
    items.value = result.items
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
    items.value = []
  } finally {
    loading.value = false
  }
}

watch(departmentId, () => {
  void loadStats()
}, { immediate: true })
</script>

<template>
  <PageShell class="statistics-view">
    <template #title>{{ t('statistics.materialUsage.title') }}</template>
    <template #subtitle>{{ t('statistics.materialUsage.subtitle') }}</template>

    <div class="statistics-view__filters section-card">
      <ETextField
        v-model="fromDate"
        type="date"
        :label="t('statistics.materialUsage.fromLabel')"
      />
      <ETextField
        v-model="toDate"
        type="date"
        :label="t('statistics.materialUsage.toLabel')"
      />
      <EButton variant="primary" :loading="loading" @click="loadStats">
        {{ t('statistics.materialUsage.apply') }}
      </EButton>
    </div>

    <ELoadingState v-if="loading" variant="page" :message="t('common.loading')" />

    <div v-else-if="error" class="statistics-view__error section-card">
      <p>{{ error }}</p>
      <EButton variant="primary" size="small" @click="loadStats">{{ t('common.retry') }}</EButton>
    </div>

    <div v-else class="statistics-view__table-wrap section-card">
      <table class="statistics-view__table">
        <thead>
          <tr>
            <th>{{ t('statistics.materialUsage.colMaterial') }}</th>
            <th class="statistics-view__num">{{ t('statistics.materialUsage.colMoves') }}</th>
            <th class="statistics-view__num">{{ t('statistics.materialUsage.colQuantity') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="items.length === 0">
            <td colspan="3" class="text-muted">{{ t('statistics.materialUsage.empty') }}</td>
          </tr>
          <tr v-for="row in items" :key="row.materialItemId">
            <td>{{ row.materialName }}</td>
            <td class="statistics-view__num">{{ row.moveCount }}</td>
            <td class="statistics-view__num">{{ row.totalQuantity }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </PageShell>
</template>

<style scoped>
.statistics-view__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
  padding: 16px;
  margin-bottom: 16px;
}

.statistics-view__table-wrap {
  overflow-x: auto;
  padding: 0;
}

.statistics-view__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9375rem;
}

.statistics-view__table th,
.statistics-view__table td {
  padding: 10px 14px;
  text-align: left;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.statistics-view__num {
  text-align: right;
  white-space: nowrap;
}

.statistics-view__error {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
