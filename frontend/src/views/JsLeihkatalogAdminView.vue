<template>
  <div class="js-leihkatalog-admin">
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('jsLeihkatalog.title') }}</h2>
        <p class="settings-description">{{ t('jsLeihkatalog.subtitle') }}</p>
        <p v-if="category" class="js-leihkatalog-category-hint text-muted">
          {{ t('jsLeihkatalog.categoryHint', { name: category.name }) }}
        </p>
      </div>
      <div class="page-header-actions">
        <EButton variant="secondary" :loading="isLoading" @click="loadItems">
          {{ t('common.refresh') }}
        </EButton>
        <EButton variant="secondary" :loading="isSyncing" @click="onSyncManifest">
          {{ t('jsLeihkatalog.syncManifest') }}
        </EButton>
        <EButton variant="primary" @click="openCreateDialog">
          {{ t('jsLeihkatalog.addButton') }}
        </EButton>
      </div>
    </div>

    <ELoadingState v-if="isLoading" variant="table" :rows="6" :message="t('jsLeihkatalog.loading')" />

    <div v-else-if="error" class="error-block">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadItems">{{ t('common.retry') }}</EButton>
    </div>

    <EEmptyState
      v-else-if="items.length === 0"
      variant="generic"
      :title="t('jsLeihkatalog.empty')"
      :description="t('jsLeihkatalog.emptyHint')"
    />

    <div v-else class="table-wrapper">
      <table class="users-table js-leihkatalog-table">
        <thead>
          <tr>
            <th class="col-line">{{ t('jsLeihkatalog.columns.pdfLine') }}</th>
            <th>{{ t('common.name') }}</th>
            <th>{{ t('jsLeihkatalog.columns.dotation') }}</th>
            <th>{{ t('jsLeihkatalog.columns.stock') }}</th>
            <th class="actions-col">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in items" :key="row.id">
            <td class="col-line">{{ row.pdf_line_no ?? '—' }}</td>
            <td>
              <span class="js-leihkatalog-name">{{ row.name }}</span>
              <span v-if="row.description" class="js-leihkatalog-desc text-muted">{{ row.description }}</span>
            </td>
            <td class="text-muted">{{ row.dotation_hint || '—' }}</td>
            <td>
              <span v-if="row.stock_available != null">{{ row.stock_available }}</span>
              <span v-else class="text-muted">{{ t('jsLeihkatalog.unlimitedStock') }}</span>
            </td>
            <td class="actions-col">
              <EButton variant="text" size="small" :title="t('common.edit')" @click="openEditDialog(row)">
                <v-icon icon="mdi-pencil-outline" size="18" />
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="field-hint text-muted js-leihkatalog-footer-hint">
        {{ t('jsLeihkatalog.footerHint', { count: items.length }) }}
      </p>
    </div>

    <EDialog
      v-model="showFormDialog"
      :max-width="520"
      :title="formMode === 'create' ? t('jsLeihkatalog.createTitle') : t('jsLeihkatalog.editTitle')"
      persistent
    >
      <div class="form-grid">
        <div class="form-group">
          <label>{{ t('jsLeihkatalog.fields.pdfLineNo') }}</label>
          <input v-model.number="form.pdf_line_no" type="number" min="1" class="form-input" />
        </div>
        <div class="form-group span-2">
          <label>{{ t('common.name') }}</label>
          <input v-model="form.name" type="text" class="form-input" />
        </div>
        <div class="form-group span-2">
          <label>{{ t('common.description') }}</label>
          <textarea v-model="form.description" rows="2" class="form-input" />
        </div>
        <div class="form-group">
          <label>{{ t('jsLeihkatalog.fields.stockQty') }}</label>
          <input v-model.number="form.stock_qty" type="number" min="0" class="form-input" />
          <span class="field-hint text-muted">{{ t('jsLeihkatalog.fields.stockQtyHint') }}</span>
        </div>
      </div>
      <template #actions>
        <EButton variant="secondary" @click="showFormDialog = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :loading="isSaving" @click="saveForm">
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EDialog from '@/components/form/base/EDialog.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { useToast } from '@/composables/useToast'
import {
  createJsLeihkatalogItem,
  getJsLeihkatalogAdmin,
  syncJsLeihkatalogManifest,
  updateJsLeihkatalogItem,
  type JsLeihkatalogCategory,
  type JsLeihkatalogItem,
} from '@/api/jsLeihkatalog'

const { t } = useI18n()
const toast = useToast()

const isLoading = ref(false)
const isSaving = ref(false)
const isSyncing = ref(false)
const error = ref('')
const items = ref<JsLeihkatalogItem[]>([])
const category = ref<JsLeihkatalogCategory | null>(null)

const showFormDialog = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const editingId = ref<string | null>(null)
const form = ref({
  name: '',
  description: '',
  pdf_line_no: 1,
  stock_qty: 999999,
})

async function loadItems() {
  isLoading.value = true
  error.value = ''
  try {
    const result = await getJsLeihkatalogAdmin()
    category.value = result.category
    items.value = result.items
  } catch (err: unknown) {
    console.error(err)
    const e = err as { response?: { data?: { error?: string } } }
    error.value = e.response?.data?.error ?? t('jsLeihkatalog.loadError')
    items.value = []
  } finally {
    isLoading.value = false
  }
}

function openCreateDialog() {
  formMode.value = 'create'
  editingId.value = null
  const nextLine = items.value.reduce((max, row) => Math.max(max, row.pdf_line_no ?? 0), 0) + 1
  form.value = { name: '', description: '', pdf_line_no: nextLine, stock_qty: 999999 }
  showFormDialog.value = true
}

function openEditDialog(row: JsLeihkatalogItem) {
  formMode.value = 'edit'
  editingId.value = row.id
  form.value = {
    name: row.name,
    description: row.description ?? '',
    pdf_line_no: row.pdf_line_no ?? 1,
    stock_qty: row.stock_available ?? 999999,
  }
  showFormDialog.value = true
}

async function saveForm() {
  if (!form.value.name.trim()) {
    toast.error(t('jsLeihkatalog.validationName'))
    return
  }
  if (!form.value.pdf_line_no || form.value.pdf_line_no < 1) {
    toast.error(t('jsLeihkatalog.validationLineNo'))
    return
  }

  isSaving.value = true
  try {
    if (formMode.value === 'create') {
      await createJsLeihkatalogItem({
        name: form.value.name.trim(),
        pdf_line_no: form.value.pdf_line_no,
        description: form.value.description.trim() || undefined,
        stock_qty: form.value.stock_qty,
      })
      toast.success(t('jsLeihkatalog.createSuccess'))
    } else if (editingId.value) {
      await updateJsLeihkatalogItem(editingId.value, {
        name: form.value.name.trim(),
        pdf_line_no: form.value.pdf_line_no,
        description: form.value.description.trim() || null,
        stock_qty: form.value.stock_qty,
      })
      toast.success(t('jsLeihkatalog.saveSuccess'))
    }
    showFormDialog.value = false
    await loadItems()
  } catch (err: unknown) {
    console.error(err)
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error ?? t('jsLeihkatalog.saveError'))
  } finally {
    isSaving.value = false
  }
}

async function onSyncManifest() {
  isSyncing.value = true
  try {
    const result = await syncJsLeihkatalogManifest()
    toast.success(t('jsLeihkatalog.syncSuccess', { count: result.stats.renamed ?? 0 }))
    await loadItems()
  } catch (err: unknown) {
    console.error(err)
    toast.error(t('jsLeihkatalog.syncError'))
  } finally {
    isSyncing.value = false
  }
}

onMounted(() => {
  void loadItems()
})
</script>

<style scoped>
.js-leihkatalog-admin {
  max-width: 1100px;
}

.page-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}

.page-header-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-start;
}

.settings-title {
  margin: 0 0 6px;
  font-size: 1.35rem;
}

.settings-description {
  margin: 0;
  color: #6b7280;
}

.js-leihkatalog-category-hint {
  margin: 8px 0 0;
  font-size: 13px;
}

.table-wrapper {
  overflow-x: auto;
}

.js-leihkatalog-table .col-line {
  width: 56px;
  font-weight: 600;
  color: #374151;
}

.js-leihkatalog-name {
  display: block;
  font-weight: 500;
}

.js-leihkatalog-desc {
  display: block;
  font-size: 12px;
  margin-top: 2px;
}

.js-leihkatalog-footer-hint {
  margin: 12px 4px 0;
  font-size: 13px;
}

.form-grid {
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: 12px 16px;
}

.form-group.span-2 {
  grid-column: 1 / -1;
}

.form-group label {
  display: block;
  margin-bottom: 4px;
  font-size: 13px;
  font-weight: 500;
}
</style>
