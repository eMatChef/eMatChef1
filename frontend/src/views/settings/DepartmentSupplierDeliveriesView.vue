<template>
  <div class="page">
    <header class="page-header">
      <h1>{{ t('departmentSupplierDeliveries.title') }}</h1>
      <p>{{ t('departmentSupplierDeliveries.subtitle') }}</p>
    </header>

    <div v-if="loading" class="state">{{ t('common.loading') }}</div>
    <div v-else-if="loadError" class="state error">{{ loadError }}</div>
    <p v-else-if="deliveries.length === 0" class="state">{{ t('departmentSupplierDeliveries.empty') }}</p>

    <div v-else class="list">
      <article v-for="delivery in deliveries" :key="delivery.id" class="card">
        <header class="card-header">
          <div>
            <strong>{{ delivery.supplier_company_name }}</strong>
            <span class="muted"> · {{ delivery.delivery_ref || t('departmentSupplierDeliveries.noRef') }}</span>
          </div>
          <span class="badge">{{ formatDate(delivery.delivered_at) }}</span>
        </header>
        <p v-if="delivery.notes" class="notes">{{ delivery.notes }}</p>
        <table class="lines-table">
          <thead>
            <tr>
              <th>{{ t('departmentSupplierDeliveries.columns.item') }}</th>
              <th>{{ t('departmentSupplierDeliveries.columns.qty') }}</th>
              <th>{{ t('departmentSupplierDeliveries.columns.price') }}</th>
              <th>{{ t('departmentSupplierDeliveries.columns.serials') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in delivery.lines" :key="line.id">
              <td>{{ line.catalog_item_name }}</td>
              <td>{{ line.qty }}</td>
              <td>{{ line.unit_price != null ? `${line.unit_price.toFixed(2)}` : '—' }}</td>
              <td>{{ (line.serial_numbers || []).join(', ') || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { listDepartmentSupplierDeliveries, type SupplierDelivery } from '@/api/supplierDeliveries'

const route = useRoute()
const { t } = useI18n()

const departmentId = computed(() => route.params.departmentId as string)
const loading = ref(true)
const loadError = ref('')
const deliveries = ref<SupplierDelivery[]>([])

function formatDate(value: string | null): string {
  return value ? value.slice(0, 10) : '—'
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const res = await listDepartmentSupplierDeliveries(departmentId.value, 'submitted')
    deliveries.value = res.deliveries
  } catch (err: any) {
    loadError.value = err?.response?.data?.error || t('departmentSupplierDeliveries.errorLoad')
  } finally {
    loading.value = false
  }
}

watch(departmentId, () => load())
onMounted(() => load())
</script>

<style scoped>
.page { max-width: 960px; }
.page-header h1 { margin: 0; font-size: 1.5rem; }
.page-header p { color: #6b7280; margin-top: 8px; }
.state { margin-top: 16px; color: #6b7280; }
.error { color: #b91c1c; }
.list { display: flex; flex-direction: column; gap: 16px; margin-top: 16px; }
.card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fff; }
.card-header { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.muted { color: #6b7280; }
.badge { font-size: 13px; color: #374151; }
.notes { margin: 0 0 12px; color: #4b5563; font-size: 14px; }
.lines-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.lines-table th, .lines-table td { padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: left; }
.lines-table th { font-size: 12px; color: #6b7280; text-transform: uppercase; }
</style>
