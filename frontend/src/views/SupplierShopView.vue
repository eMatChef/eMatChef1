<template>
  <div class="shop-page">
    <header class="shop-header">
      <h1>{{ t('supplierShop.title') }}</h1>
      <p class="hint">{{ t('supplierShop.subtitle') }}</p>
    </header>

    <nav class="tabs">
      <v-tabs v-model="activeTab" color="primary" class="shop-tabs">
        <v-tab value="catalog">{{ t('supplierShop.tabs.catalog') }}</v-tab>
        <v-tab value="templates">{{ t('supplierShop.tabs.templates') }}</v-tab>
        <v-tab value="deliveries">
          {{ t('supplierShop.tabs.deliveries') }}
          <span v-if="openDeliveryCount" class="badge-count">{{ openDeliveryCount }}</span>
        </v-tab>
        <v-tab value="watchlist">
          {{ t('supplierShop.tabs.watchlist') }}
          <span v-if="watchlist.length" class="badge-count">{{ watchlist.length }}</span>
        </v-tab>
      </v-tabs>
    </nav>

    <v-alert v-if="loadError" type="error" variant="tonal" class="mb-3" :text="loadError" />
    <ELoadingState v-else-if="loading" variant="page" :message="t('common.loading')" />

    <template v-else>
      <!-- Catalog -->
      <section v-if="activeTab === 'catalog'">
        <div class="filter-bar">
          <ESearchField
            v-model="catalogSearch"
            :label="t('supplierShop.searchFilter')"
            :placeholder="t('supplierShop.searchPlaceholder')"
            class="filter-search"
          />
          <ESelect
            v-model="selectedCompanyId"
            :label="t('supplierShop.supplierFilter')"
            :items="companySelectItems"
            hide-details="auto"
            class="filter-select"
            @update:model-value="onCompanyChange"
          />
        </div>
        <p v-if="filteredCatalogItems.length === 0" class="state">{{ catalogEmptyMessage }}</p>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>{{ t('supplierShop.columns.name') }}</th>
              <th>{{ t('supplierShop.columns.supplier') }}</th>
              <th>{{ t('supplierShop.columns.sku') }}</th>
              <th>{{ t('supplierShop.columns.tracking') }}</th>
              <th>{{ t('supplierShop.columns.price') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filteredCatalogItems" :key="item.id">
              <td>{{ item.name }}</td>
              <td>{{ supplierName(item.supplier_company_id, item.supplier_company_name) }}</td>
              <td>{{ item.sku || '—' }}</td>
              <td>{{ trackingLabel(item.tracking_type) }}</td>
              <td>{{ formatPrice(item.unit_price, item.currency) }}</td>
              <td class="actions">
                <EButton variant="secondary" size="small" @click="addToWatchlist(item)">
                  {{ t('supplierShop.addToWatchlist') }}
                </EButton>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Templates -->
      <section v-else-if="activeTab === 'templates'">
        <div class="filter-bar">
          <ESearchField
            v-model="templateSearch"
            :label="t('supplierShop.searchFilter')"
            :placeholder="t('supplierShop.searchPlaceholder')"
            class="filter-search"
          />
          <ESelect
            v-model="selectedCompanyId"
            :label="t('supplierShop.supplierFilter')"
            :items="companySelectItems"
            hide-details="auto"
            class="filter-select"
            @update:model-value="onCompanyChange"
          />
        </div>
        <p v-if="filteredTemplates.length === 0" class="state">{{ templateEmptyMessage }}</p>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>{{ t('supplierShop.columns.name') }}</th>
              <th>{{ t('supplierShop.columns.supplier') }}</th>
              <th>{{ t('supplierShop.columns.materialType') }}</th>
              <th>{{ t('supplierShop.columns.components') }}</th>
              <th>{{ t('supplierShop.columns.price') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tpl in filteredTemplates" :key="tpl.id">
              <td>{{ tpl.name }}</td>
              <td>{{ supplierName(tpl.supplier_company_id, tpl.supplier_company_name) }}</td>
              <td>{{ materialTypeLabel(tpl.material_type) }}</td>
              <td>{{ tpl.component_count }}</td>
              <td>{{ formatPrice(tpl.unit_price, tpl.currency) }}</td>
              <td class="actions">
                <EButton
                  variant="primary"
                  size="small"
                  :loading="importingTemplateId === tpl.id"
                  @click="importTemplate(tpl)"
                >
                  {{ importingTemplateId === tpl.id ? t('supplierShop.importing') : t('supplierShop.importTemplate') }}
                </EButton>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Deliveries -->
      <section v-else-if="activeTab === 'deliveries'">
        <div class="filter-bar">
          <ESelect
            v-model="deliveryStatusFilter"
            :label="t('supplierShop.deliveryStatusFilter')"
            :items="deliveryStatusItems"
            hide-details="auto"
            class="filter-select"
            @update:model-value="loadDeliveries"
          />
        </div>
        <p v-if="deliveries.length === 0" class="state">{{ deliveriesEmptyMessage }}</p>
        <article v-for="delivery in deliveries" :key="delivery.id" class="card">
          <header class="card-header">
            <div>
              <strong>{{ delivery.supplier_company_name }}</strong>
              <span class="muted"> · {{ delivery.delivery_ref || t('departmentSupplierDeliveries.noRef') }}</span>
              <span class="status-badge" :class="delivery.status">{{ deliveryStatusLabel(delivery.status) }}</span>
            </div>
            <EButton
              v-if="delivery.status === 'submitted'"
              variant="primary"
              size="small"
              :loading="importingId === delivery.id"
              @click="importDelivery(delivery)"
            >
              {{ importingId === delivery.id ? t('supplierShop.importing') : t('supplierShop.importDelivery') }}
            </EButton>
          </header>
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
                <td>{{ line.unit_price != null ? line.unit_price.toFixed(2) : '—' }}</td>
                <td>{{ (line.serial_numbers || []).join(', ') || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </article>
      </section>

      <!-- Watchlist -->
      <section v-else>
        <div class="filter-bar filter-bar-summary">
          <div class="budget">
            {{ t('supplierShop.budgetTotal') }}:
            <strong>{{ budgetTotal.toFixed(2) }} CHF</strong>
          </div>
        </div>
        <p v-if="watchlist.length === 0" class="state">{{ t('supplierShop.watchlistEmpty') }}</p>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>{{ t('supplierShop.columns.name') }}</th>
              <th>{{ t('supplierShop.columns.supplier') }}</th>
              <th>{{ t('supplierShop.columns.qty') }}</th>
              <th>{{ t('supplierShop.columns.price') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in watchlist" :key="item.catalog_item_id">
              <td>{{ item.name }}</td>
              <td>{{ item.supplier_company_name }}</td>
              <td>
                <ETextField
                  v-model.number="item.qty"
                  type="number"
                  hide-details
                  density="compact"
                  class="qty-input"
                  @update:model-value="persistWatchlist"
                />
              </td>
              <td>{{ formatPrice((item.unit_price ?? 0) * item.qty, item.currency) }}</td>
              <td class="actions">
                <EButton
                  variant="primary"
                  size="small"
                  :loading="importingCatalogId === item.catalog_item_id"
                  @click="importWatchlistItem(item)"
                >
                  {{ t('supplierShop.importItem') }}
                </EButton>
                <EButton variant="danger" size="small" @click="removeFromWatchlist(index)">
                  {{ t('common.delete') }}
                </EButton>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import type { SupplierCatalogItem } from '@/api/supplierCatalog'
import { listDepartmentSupplierDeliveries, type SupplierDelivery } from '@/api/supplierDeliveries'
import {
  importSupplierCatalogItem,
  importSupplierDelivery,
  importSupplierTemplate,
  listSupplierShopCatalog,
  listSupplierShopCompanies,
  listSupplierShopTemplates,
  loadWatchlist,
  saveWatchlist,
  watchlistBudgetTotal,
  type SupplierShopCompany,
  type SupplierShopTemplate,
  type WatchlistItem,
} from '@/api/supplierShop'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ESearchField, ESelect, ETextField } from '@/components/form/base'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const departmentId = computed(() => route.params.departmentId as string)
const loading = ref(true)
const loadError = ref('')
const activeTab = ref<'catalog' | 'templates' | 'deliveries' | 'watchlist'>('catalog')
const shopTabs = ['catalog', 'templates', 'deliveries', 'watchlist'] as const

function syncTabFromRoute() {
  const tab = route.query.tab
  if (typeof tab === 'string' && (shopTabs as readonly string[]).includes(tab)) {
    activeTab.value = tab as (typeof shopTabs)[number]
  }
}
const companies = ref<SupplierShopCompany[]>([])
const selectedCompanyId = ref('')
const catalogSearch = ref('')
const templateSearch = ref('')
const deliveryStatusFilter = ref<'submitted' | 'imported' | 'all'>('all')
const catalogItems = ref<SupplierCatalogItem[]>([])
const templates = ref<SupplierShopTemplate[]>([])
const deliveries = ref<SupplierDelivery[]>([])
const openDeliveryCount = ref(0)
const watchlist = ref<WatchlistItem[]>([])
const importingId = ref('')
const importingCatalogId = ref('')
const importingTemplateId = ref('')

const budgetTotal = computed(() => watchlistBudgetTotal(watchlist.value))

const companySelectItems = computed(() => [
  { title: t('supplierShop.filterAllSuppliers'), value: '' },
  ...companies.value.map((c) => ({ title: c.name, value: c.id })),
])

const deliveryStatusItems = computed(() => [
  { title: t('supplierShop.deliveryStatus.open'), value: 'submitted' },
  { title: t('supplierShop.deliveryStatus.imported'), value: 'imported' },
  { title: t('supplierShop.deliveryStatus.all'), value: 'all' },
])

function matchesSearch(query: string, ...values: Array<string | null | undefined>): boolean {
  const needle = query.trim().toLowerCase()
  if (!needle) return true
  return values.some((value) => (value ?? '').toLowerCase().includes(needle))
}

const filteredCatalogItems = computed(() =>
  catalogItems.value.filter((item) =>
    matchesSearch(
      catalogSearch.value,
      item.name,
      item.sku,
      supplierName(item.supplier_company_id, item.supplier_company_name),
    ),
  ),
)

const filteredTemplates = computed(() =>
  templates.value.filter((tpl) =>
    matchesSearch(
      templateSearch.value,
      tpl.name,
      supplierName(tpl.supplier_company_id, tpl.supplier_company_name),
    ),
  ),
)

const catalogEmptyMessage = computed(() => {
  if (catalogSearch.value.trim()) return t('supplierShop.catalogNoMatch')
  return t('supplierShop.catalogEmpty')
})

const templateEmptyMessage = computed(() => {
  if (templateSearch.value.trim()) return t('supplierShop.templatesNoMatch')
  return t('supplierShop.templatesEmpty')
})

const deliveriesEmptyMessage = computed(() => {
  if (deliveryStatusFilter.value === 'imported') return t('supplierShop.deliveriesEmptyImported')
  if (deliveryStatusFilter.value === 'submitted') return t('supplierShop.deliveriesEmptyOpen')
  return t('supplierShop.deliveriesEmpty')
})

function materialTypeLabel(type: string): string {
  if (type === 'physical_combo') return t('supplierShop.materialType.physicalCombo')
  if (type === 'virtual_combo') return t('supplierShop.materialType.virtualCombo')
  return type
}

function trackingLabel(type: string): string {
  return type === 'serialized'
    ? t('supplierCatalog.tracking.serialized')
    : t('supplierCatalog.tracking.bulk')
}

function supplierName(companyId: string, name?: string): string {
  if (name) return name
  return companies.value.find((c) => c.id === companyId)?.name || '—'
}

function deliveryStatusLabel(status: SupplierDelivery['status']): string {
  if (status === 'imported') return t('supplierShop.deliveryStatus.imported')
  if (status === 'submitted') return t('supplierShop.deliveryStatus.open')
  return status
}

function formatPrice(amount: number | null, currency = 'CHF'): string {
  if (amount == null) return '—'
  return `${amount.toFixed(2)} ${currency}`
}

function persistWatchlist() {
  saveWatchlist(departmentId.value, watchlist.value)
}

function addToWatchlist(item: SupplierCatalogItem) {
  const existing = watchlist.value.find((w) => w.catalog_item_id === item.id)
  if (existing) {
    existing.qty += 1
  } else {
    watchlist.value.push({
      catalog_item_id: item.id,
      name: item.name,
      sku: item.sku,
      qty: 1,
      unit_price: item.unit_price,
      currency: item.currency,
      tracking_type: item.tracking_type,
      supplier_company_id: item.supplier_company_id,
      supplier_company_name: supplierName(item.supplier_company_id, item.supplier_company_name),
    })
  }
  persistWatchlist()
  toast.success(t('supplierShop.addedToWatchlist'))
}

function removeFromWatchlist(index: number) {
  watchlist.value.splice(index, 1)
  persistWatchlist()
}

async function loadCompaniesAndDeliveries() {
  loading.value = true
  loadError.value = ''
  try {
    companies.value = await listSupplierShopCompanies(departmentId.value)
    await Promise.all([loadCatalog(), loadTemplates(), loadDeliveries(), loadOpenDeliveryCount()])
    watchlist.value = loadWatchlist(departmentId.value)
  } catch (err: any) {
    loadError.value = err?.response?.data?.error || t('supplierShop.errorLoad')
  } finally {
    loading.value = false
  }
}

async function loadOpenDeliveryCount() {
  try {
    const res = await listDepartmentSupplierDeliveries(departmentId.value, 'submitted')
    openDeliveryCount.value = res.deliveries.length
  } catch {
    openDeliveryCount.value = 0
  }
}

async function loadDeliveries() {
  try {
    const res = await listDepartmentSupplierDeliveries(departmentId.value, deliveryStatusFilter.value)
    deliveries.value = res.deliveries
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierShop.errorLoad'))
  }
}

async function loadTemplates() {
  try {
    templates.value = await listSupplierShopTemplates(
      departmentId.value,
      selectedCompanyId.value || undefined,
    )
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierShop.errorLoad'))
  }
}

async function loadCatalog() {
  try {
    catalogItems.value = await listSupplierShopCatalog(
      departmentId.value,
      selectedCompanyId.value || undefined,
    )
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierShop.errorLoad'))
  }
}

function onCompanyChange() {
  loadCatalog()
  loadTemplates()
}

async function importTemplate(tpl: SupplierShopTemplate) {
  const ok = await confirm.confirm({
    title: t('supplierShop.importTemplateTitle'),
    message: t('supplierShop.importTemplateMessage', { name: tpl.name }),
    confirmText: t('supplierShop.importTemplate'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return

  importingTemplateId.value = tpl.id
  try {
    const result = await importSupplierTemplate(departmentId.value, {
      supplier_material_template_id: tpl.id,
      name: tpl.name,
    })
    toast.success(result.message || t('supplierShop.importTemplateSuccess'))
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierShop.importError'))
  } finally {
    importingTemplateId.value = ''
  }
}

async function importDelivery(delivery: SupplierDelivery) {
  const ok = await confirm.confirm({
    title: t('supplierShop.importDeliveryTitle'),
    message: t('supplierShop.importDeliveryMessage', { ref: delivery.delivery_ref || delivery.id }),
    confirmText: t('supplierShop.importDelivery'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return

  importingId.value = delivery.id
  try {
    const result = await importSupplierDelivery(departmentId.value, delivery.id)
    toast.success(result.message || t('supplierShop.importSuccess'))
    await loadDeliveries()
    await loadOpenDeliveryCount()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierShop.importError'))
  } finally {
    importingId.value = ''
  }
}

async function importWatchlistItem(item: WatchlistItem) {
  if (item.tracking_type === 'serialized') {
    toast.error(t('supplierShop.serializedUseDelivery'))
    return
  }

  const ok = await confirm.confirm({
    title: t('supplierShop.importItemTitle'),
    message: t('supplierShop.importItemMessage', { name: item.name, qty: item.qty }),
    confirmText: t('supplierShop.importItem'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return

  importingCatalogId.value = item.catalog_item_id
  try {
    await importSupplierCatalogItem(departmentId.value, {
      catalog_item_id: item.catalog_item_id,
      qty: item.qty,
    })
    toast.success(t('supplierShop.importSuccess'))
    const idx = watchlist.value.findIndex((w) => w.catalog_item_id === item.catalog_item_id)
    if (idx >= 0) {
      watchlist.value.splice(idx, 1)
      persistWatchlist()
    }
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierShop.importError'))
  } finally {
    importingCatalogId.value = ''
  }
}

watch(activeTab, (tab) => {
  if (tab === 'templates' && templates.value.length === 0) {
    loadTemplates()
  }
  if (tab === 'catalog' && catalogItems.value.length === 0) {
    loadCatalog()
  }
})

watch(() => route.query.tab, syncTabFromRoute)

watch(departmentId, () => loadCompaniesAndDeliveries())
onMounted(() => {
  syncTabFromRoute()
  loadCompaniesAndDeliveries()
})
</script>

<style scoped>
.shop-page {
  max-width: 1100px;
}

.shop-header h1 {
  margin: 0;
}

.hint {
  color: #6b7280;
  margin: 8px 0 0;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin: 20px 0 12px;
  flex-wrap: wrap;
}

.filter-bar {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 16px;
  padding: 12px 14px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.filter-search {
  flex: 1;
  min-width: 200px;
}

.filter-select {
  min-width: 220px;
}

.filter-bar-summary {
  align-items: center;
  justify-content: flex-end;
}

.budget {
  font-size: 0.95rem;
}

.tabs {
  margin-bottom: 16px;
}

.shop-tabs :deep(.v-tab) {
  text-transform: none;
}

.badge-count {
  margin-left: 6px;
  background: #dbeafe;
  color: #1d4ed8;
  border-radius: 999px;
  padding: 1px 7px;
  font-size: 0.75rem;
}

.state {
  color: #6b7280;
  margin: 24px 0;
}

.state.error {
  color: #b91c1c;
}

.data-table,
.lines-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td,
.lines-table th,
.lines-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
}

.data-table th,
.lines-table th {
  background: #f9fafb;
  font-weight: 600;
}

.actions {
  white-space: nowrap;
  display: flex;
  gap: 6px;
}

.qty-input {
  max-width: 88px;
}

.card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.muted {
  color: #6b7280;
}

.status-badge {
  margin-left: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 999px;
  padding: 2px 8px;
}

.status-badge.submitted {
  background: #dbeafe;
  color: #1d4ed8;
}

.status-badge.imported {
  background: #dcfce7;
  color: #15803d;
}
</style>
