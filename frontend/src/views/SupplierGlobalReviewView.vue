<template>
  <div class="review-page">
    <header class="review-header">
      <h1>{{ t('supplierGlobalReview.title') }}</h1>
      <p class="hint">{{ t('supplierGlobalReview.subtitle') }}</p>
      <p class="scope-note">{{ t('supplierGlobalReview.scopeNote') }}</p>
    </header>

    <v-alert v-if="loadError" type="error" variant="tonal" class="mb-3" :text="loadError" />
    <ELoadingState v-else-if="loading" variant="page" :message="t('common.loading')" />

    <template v-else>
      <v-tabs v-model="activeTab" color="primary" class="review-tabs">
        <v-tab value="catalog">
          {{ t('supplierGlobalReview.tabs.catalog') }}
          <span v-if="catalogItems.length" class="badge-count">{{ catalogItems.length }}</span>
        </v-tab>
        <v-tab value="templates">
          {{ t('supplierGlobalReview.tabs.templates') }}
          <span v-if="templates.length" class="badge-count">{{ templates.length }}</span>
        </v-tab>
      </v-tabs>

      <section v-if="activeTab === 'catalog'">
        <EEmptyState
          v-if="catalogItems.length === 0"
          variant="generic"
          :title="t('supplierGlobalReview.catalogEmpty')"
        />
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>{{ t('supplierGlobalReview.columns.supplier') }}</th>
              <th>{{ t('supplierGlobalReview.columns.name') }}</th>
              <th>{{ t('supplierGlobalReview.columns.sku') }}</th>
              <th>{{ t('supplierGlobalReview.columns.tracking') }}</th>
              <th>{{ t('supplierGlobalReview.columns.price') }}</th>
              <th>{{ t('supplierGlobalReview.columns.updated') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in catalogItems" :key="item.id">
              <td>{{ item.supplier_company_name }}</td>
              <td>{{ item.name }}</td>
              <td>{{ item.sku || '—' }}</td>
              <td>{{ item.tracking_type }}</td>
              <td>{{ formatPrice(item.unit_price, item.currency) }}</td>
              <td>{{ formatDate(item.updated_at) }}</td>
              <td class="actions">
                <EButton
                  variant="primary"
                  size="small"
                  :loading="actingId === item.id"
                  @click="approveCatalog(item.id)"
                >
                  {{ t('supplierGlobalReview.approve') }}
                </EButton>
                <EButton
                  variant="danger"
                  size="small"
                  :loading="actingId === item.id"
                  @click="rejectCatalog(item.id)"
                >
                  {{ t('supplierGlobalReview.reject') }}
                </EButton>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-else>
        <EEmptyState
          v-if="templates.length === 0"
          variant="generic"
          :title="t('supplierGlobalReview.templatesEmpty')"
        />
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>{{ t('supplierGlobalReview.columns.supplier') }}</th>
              <th>{{ t('supplierGlobalReview.columns.name') }}</th>
              <th>{{ t('supplierGlobalReview.columns.materialType') }}</th>
              <th>{{ t('supplierGlobalReview.columns.components') }}</th>
              <th>{{ t('supplierGlobalReview.columns.price') }}</th>
              <th>{{ t('supplierGlobalReview.columns.updated') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in templates" :key="item.id">
              <td>{{ item.supplier_company_name }}</td>
              <td>{{ item.name }}</td>
              <td>{{ item.material_type }}</td>
              <td>{{ item.component_count }}</td>
              <td>{{ formatPrice(item.unit_price, item.currency) }}</td>
              <td>{{ formatDate(item.updated_at) }}</td>
              <td class="actions">
                <EButton
                  variant="primary"
                  size="small"
                  :loading="actingId === item.id"
                  @click="approveTemplate(item.id)"
                >
                  {{ t('supplierGlobalReview.approve') }}
                </EButton>
                <EButton
                  variant="danger"
                  size="small"
                  :loading="actingId === item.id"
                  @click="rejectTemplate(item.id)"
                >
                  {{ t('supplierGlobalReview.reject') }}
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
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  approveSupplierGlobalCatalogItem,
  approveSupplierGlobalTemplate,
  listSupplierGlobalReview,
  rejectSupplierGlobalCatalogItem,
  rejectSupplierGlobalTemplate,
  type SupplierGlobalReviewCatalogItem,
  type SupplierGlobalReviewTemplate,
} from '@/api/supplierGlobalReview'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton } from '@/components/form/base'

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(true)
const loadError = ref('')
const activeTab = ref<'catalog' | 'templates'>('catalog')
const catalogItems = ref<SupplierGlobalReviewCatalogItem[]>([])
const templates = ref<SupplierGlobalReviewTemplate[]>([])
const actingId = ref('')

function formatPrice(amount: number | null, currency = 'CHF'): string {
  if (amount == null) return '—'
  return `${amount.toFixed(2)} ${currency}`
}

function formatDate(value: string): string {
  try {
    return new Date(value).toLocaleString()
  } catch {
    return value
  }
}

async function loadReviewQueue() {
  loading.value = true
  loadError.value = ''
  try {
    const data = await listSupplierGlobalReview()
    catalogItems.value = data.catalog_items
    templates.value = data.material_templates
  } catch (err: any) {
    loadError.value = err?.response?.data?.error || t('supplierGlobalReview.errorLoad')
  } finally {
    loading.value = false
  }
}

async function approveCatalog(itemId: string) {
  const ok = await confirm.confirm({
    title: t('supplierGlobalReview.approveTitle'),
    message: t('supplierGlobalReview.approveCatalogMessage'),
    confirmText: t('supplierGlobalReview.approve'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return

  actingId.value = itemId
  try {
    const result = await approveSupplierGlobalCatalogItem(itemId)
    toast.success(result.message || t('supplierGlobalReview.approveSuccess'))
    await loadReviewQueue()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierGlobalReview.actionError'))
  } finally {
    actingId.value = ''
  }
}

async function rejectCatalog(itemId: string) {
  const ok = await confirm.confirm({
    title: t('supplierGlobalReview.rejectTitle'),
    message: t('supplierGlobalReview.rejectCatalogMessage'),
    confirmText: t('supplierGlobalReview.reject'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return

  actingId.value = itemId
  try {
    const result = await rejectSupplierGlobalCatalogItem(itemId)
    toast.success(result.message || t('supplierGlobalReview.rejectSuccess'))
    await loadReviewQueue()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierGlobalReview.actionError'))
  } finally {
    actingId.value = ''
  }
}

async function approveTemplate(templateId: string) {
  const ok = await confirm.confirm({
    title: t('supplierGlobalReview.approveTitle'),
    message: t('supplierGlobalReview.approveTemplateMessage'),
    confirmText: t('supplierGlobalReview.approve'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return

  actingId.value = templateId
  try {
    const result = await approveSupplierGlobalTemplate(templateId)
    toast.success(result.message || t('supplierGlobalReview.approveSuccess'))
    await loadReviewQueue()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierGlobalReview.actionError'))
  } finally {
    actingId.value = ''
  }
}

async function rejectTemplate(templateId: string) {
  const ok = await confirm.confirm({
    title: t('supplierGlobalReview.rejectTitle'),
    message: t('supplierGlobalReview.rejectTemplateMessage'),
    confirmText: t('supplierGlobalReview.reject'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return

  actingId.value = templateId
  try {
    const result = await rejectSupplierGlobalTemplate(templateId)
    toast.success(result.message || t('supplierGlobalReview.rejectSuccess'))
    await loadReviewQueue()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierGlobalReview.actionError'))
  } finally {
    actingId.value = ''
  }
}

onMounted(() => loadReviewQueue())
</script>

<style scoped>
.review-page {
  max-width: 1100px;
}

.review-header h1 {
  margin: 0;
}

.hint,
.scope-note {
  color: #6b7280;
  margin: 8px 0 0;
}

.scope-note {
  font-size: 0.9rem;
  max-width: 720px;
}

.review-tabs {
  margin: 20px 0 16px;
}

.review-tabs :deep(.v-tab) {
  text-transform: none;
}

.badge-count {
  margin-left: 6px;
  background: #fef3c7;
  color: #92400e;
  border-radius: 999px;
  padding: 1px 7px;
  font-size: 0.75rem;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
}

.data-table th {
  background: #f9fafb;
  font-weight: 600;
}

.actions {
  white-space: nowrap;
  display: flex;
  gap: 6px;
}
</style>
