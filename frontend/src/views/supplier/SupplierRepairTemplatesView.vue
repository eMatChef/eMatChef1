<template>
  <div class="supplier-page">
    <header class="supplier-page-header">
      <h1>{{ t('supplierRepairTemplates.title') }}</h1>
      <p class="supplier-page-subtitle">{{ companyName }}</p>
      <p class="supplier-page-hint">{{ t('supplierRepairTemplates.subtitle') }}</p>
    </header>

    <ELoadingState
      v-if="loading"
      variant="inline"
      :message="t('common.loading')"
    />
    <div v-else-if="loadError" class="supplier-page-error">
      <v-alert type="error" variant="tonal" :text="loadError" />
    </div>

    <template v-else>
      <div class="toolbar">
        <EButton variant="primary" @click="openImportDialog">
          {{ t('supplierRepairTemplates.import') }}
        </EButton>
        <EButton variant="secondary" @click="loadTemplates">
          {{ t('supplierRepairTemplates.refresh') }}
        </EButton>
      </div>

      <EEmptyState
        v-if="templates.length === 0"
        :title="t('supplierRepairTemplates.empty')"
        :description="t('supplierRepairTemplates.emptyHint')"
      />

      <div v-else class="template-grid">
        <div v-for="tpl in templates" :key="tpl.id" class="template-card">
          <div class="template-card-head">
            <div>
              <h3>{{ tpl.name }}</h3>
              <span class="template-key">{{ tpl.template_key }}</span>
            </div>
            <span class="status-badge" :class="tpl.is_active ? 'active' : 'inactive'">
              {{
                tpl.is_active
                  ? t('supplierRepairTemplates.statusActive')
                  : t('supplierRepairTemplates.statusInactive')
              }}
            </span>
          </div>
          <p class="template-meta">
            {{ t('supplierRepairTemplates.servicesCount', { n: tpl.services_json?.services?.length ?? 0 }) }}
          </p>
          <div class="template-card-actions">
            <EButton variant="secondary" size="small" @click="openPreview(tpl)">
              {{ t('supplierRepairTemplates.preview') }}
            </EButton>
            <EButton variant="secondary" size="small" @click="openEditor(tpl)">
              {{ t('common.edit') }}
            </EButton>
            <EButton variant="text" size="small" @click="confirmDelete(tpl)">
              {{ t('common.delete') }}
            </EButton>
          </div>
        </div>
      </div>
    </template>

    <EDialog v-model="importDialogOpen" :title="t('supplierRepairTemplates.importTitle')" max-width="480">
      <p class="import-hint">{{ t('supplierRepairTemplates.importHint') }}</p>
      <ELoadingState
        v-if="platformLoading"
        variant="inline"
        :message="t('supplierRepairTemplates.loadingPlatform')"
      />
      <p v-else-if="importablePlatformTemplates.length === 0" class="field-hint">
        {{ t('supplierRepairTemplates.nothingToImport') }}
      </p>
      <div v-else class="import-list">
        <button
          v-for="tpl in importablePlatformTemplates"
          :key="tpl.template_key"
          type="button"
          class="import-item"
          :disabled="importingKey === tpl.template_key"
          @click="importTemplate(tpl.template_key)"
        >
          <span class="import-item-name">{{ tpl.name }}</span>
          <span class="import-item-key">{{ tpl.template_key }}</span>
        </button>
      </div>
      <template #actions>
        <EButton variant="secondary" @click="importDialogOpen = false">
          {{ t('common.close') }}
        </EButton>
      </template>
    </EDialog>

    <SupplierRepairTemplateEditorDialog
      v-model="editorOpen"
      v-model:template="editingTemplate"
      :company-id="companyId"
      @saved="onTemplateSaved"
    />

    <RepairSheetPreviewDialog
      v-model="previewOpen"
      :template="previewSheetTemplate"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import {
  deleteSupplierRepairTemplate,
  getSupplierRepairTemplates,
  importSupplierRepairTemplate,
  supplierTemplateToSheetInput,
  type SupplierRepairTemplate,
} from '@/api/supplierRepairTemplates'
import { getPlatformRepairTemplates, type PlatformRepairTemplate } from '@/api/repairTemplates'
import SupplierRepairTemplateEditorDialog from '@/components/supplier/SupplierRepairTemplateEditorDialog.vue'
import RepairSheetPreviewDialog from '@/components/workshop/RepairSheetPreviewDialog.vue'
import type { DepartmentRepairTemplate } from '@/api/repairTemplates'
import { EButton, EDialog } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const authStore = useAuthStore()

const companyId = computed(() => String(route.params.companyId || ''))
const companyName = computed(() => {
  const company = authStore.supplierCompanies?.find((c) => c.id === companyId.value)
  return company?.name ?? ''
})

const loading = ref(true)
const loadError = ref('')
const templates = ref<SupplierRepairTemplate[]>([])
const platformTemplates = ref<PlatformRepairTemplate[]>([])
const importDialogOpen = ref(false)
const platformLoading = ref(false)
const importingKey = ref('')
const editorOpen = ref(false)
const editingTemplate = ref<SupplierRepairTemplate | null>(null)
const previewOpen = ref(false)
const previewSheetTemplate = ref<DepartmentRepairTemplate | null>(null)

const importablePlatformTemplates = computed(() => {
  const imported = new Set(templates.value.map((tpl) => tpl.template_key))
  return platformTemplates.value.filter((tpl) => tpl.is_active && !imported.has(tpl.template_key))
})

async function loadTemplates() {
  if (!companyId.value) return
  loading.value = true
  loadError.value = ''
  try {
    templates.value = await getSupplierRepairTemplates(companyId.value)
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    loadError.value = message || t('supplierRepairTemplates.loadError')
  } finally {
    loading.value = false
  }
}

async function loadPlatformTemplates() {
  platformLoading.value = true
  try {
    platformTemplates.value = await getPlatformRepairTemplates()
  } catch {
    platformTemplates.value = []
  } finally {
    platformLoading.value = false
  }
}

function openImportDialog() {
  importDialogOpen.value = true
  void loadPlatformTemplates()
}

async function importTemplate(templateKey: string) {
  if (!companyId.value || importingKey.value) return
  importingKey.value = templateKey
  try {
    const imported = await importSupplierRepairTemplate(companyId.value, templateKey)
    const index = templates.value.findIndex((tpl) => tpl.template_key === imported.template_key)
    if (index >= 0) {
      templates.value[index] = imported
    } else {
      templates.value.push(imported)
    }
    toast.success(t('supplierRepairTemplates.toastImported'))
    importDialogOpen.value = false
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('supplierRepairTemplates.toastImportError'))
  } finally {
    importingKey.value = ''
  }
}

function openEditor(template: SupplierRepairTemplate) {
  editingTemplate.value = template
  editorOpen.value = true
}

function openPreview(template: SupplierRepairTemplate) {
  previewSheetTemplate.value = {
    ...template,
    department_id: '',
  } as DepartmentRepairTemplate
  previewOpen.value = true
}

function onTemplateSaved(updated: SupplierRepairTemplate) {
  const index = templates.value.findIndex((tpl) => tpl.id === updated.id)
  if (index >= 0) {
    templates.value[index] = updated
  }
}

async function confirmDelete(template: SupplierRepairTemplate) {
  if (!companyId.value) return
  if (!window.confirm(t('supplierRepairTemplates.deleteConfirm', { name: template.name }))) return
  try {
    await deleteSupplierRepairTemplate(companyId.value, template.template_key)
    templates.value = templates.value.filter((tpl) => tpl.id !== template.id)
    toast.success(t('supplierRepairTemplates.toastDeleted'))
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('supplierRepairTemplates.toastDeleteError'))
  }
}

onMounted(() => {
  void loadTemplates()
})
</script>

<style scoped>
.toolbar {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.template-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.template-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px;
  background: #fff;
}

.template-card-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}

.template-card-head h3 {
  margin: 0 0 4px;
  font-size: 15px;
}

.template-key {
  font-size: 11px;
  color: #9ca3af;
  font-family: ui-monospace, monospace;
}

.template-meta {
  margin: 0 0 12px;
  font-size: 12px;
  color: #6b7280;
}

.status-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 999px;
  white-space: nowrap;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.inactive {
  background: #f3f4f6;
  color: #6b7280;
}

.template-card-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.import-hint,
.field-hint {
  font-size: 13px;
  color: #6b7280;
}

.import-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.import-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
  text-align: left;
}

.import-item:hover:not(:disabled) {
  border-color: #c7d2fe;
  background: #f8fafc;
}

.import-item-name {
  font-weight: 500;
}

.import-item-key {
  font-size: 11px;
  color: #9ca3af;
  font-family: ui-monospace, monospace;
}
</style>
