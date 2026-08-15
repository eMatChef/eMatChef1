<template>
  <div class="templates-settings">
    <div class="settings-header">
      <div>
        <h1>{{ pageTitle }}</h1>
        <p class="subtitle">{{ pageSubtitle }}</p>
      </div>
      <div class="header-actions">
        <button class="btn-primary" @click="openCreateDialog">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ t('settings.templates.newTemplate') }}
        </button>
      </div>
    </div>

    <div v-if="canImportExportTemplates" class="tab-bar">
      <button type="button" class="tab-btn" :class="{ active: activeTab === 'list' }" @click="activeTab = 'list'">
        {{ t('settings.templates.tabList') }}
      </button>
      <button type="button" class="tab-btn" :class="{ active: activeTab === 'import' }" @click="activeTab = 'import'">
        {{ t('settings.templates.tabImport') }}
      </button>
      <button type="button" class="tab-btn" :class="{ active: activeTab === 'export' }" @click="activeTab = 'export'">
        {{ t('settings.templates.tabExport') }}
      </button>
    </div>

    <!-- Import Tab -->
    <div v-if="canImportExportTemplates && activeTab === 'import'" class="io-panel">
      <div class="card actions-card">
        <label class="btn-primary file-label">
          <input type="file" accept=".json,application/json" class="file-input" @change="handleFileSelect" />
          {{ t('settings.templates.uploadJson') }}
        </label>
        <span v-if="importFile" class="file-name">{{ importFile.name }}</span>
      </div>
      <p class="hint">{{ t('settings.templates.importHint') }}</p>

      <div v-if="importFile" class="card preview-card">
        <div class="preview-toolbar">
          <label class="duplicate-default">
            {{ t('settings.templates.duplicateAction') }}
            <select v-model="duplicateAction" class="form-select-sm">
              <option value="skip">{{ t('settings.templates.duplicateSkip') }}</option>
              <option value="update">{{ t('settings.templates.duplicateUpdate') }}</option>
              <option value="create">{{ t('settings.templates.duplicateCreate') }}</option>
            </select>
          </label>
          <button type="button" class="btn-secondary btn-sm" :disabled="isImporting" @click="runDryRun">
            {{ t('settings.templates.validate') }}
          </button>
          <button type="button" class="btn-primary btn-sm" :disabled="isImporting || !importFile" @click="executeImport">
            {{ isImporting ? t('settings.templates.importSubmitting') : t('settings.templates.importSubmit') }}
          </button>
        </div>

        <div v-if="importResult" class="import-result" :class="{ success: importResult.success, error: !importResult.success }">
          <template v-if="importResult.success">
            <strong>{{ importResult.dry_run ? t('settings.templates.previewTitle') : t('settings.templates.importSuccessStrong') }}</strong>
            {{ t('settings.templates.importStats', {
              created: importResult.stats.created,
              updated: importResult.stats.updated,
              skipped: importResult.stats.skipped,
              errors: importResult.stats.errors,
            }) }}
          </template>
          <template v-else>
            <strong>{{ t('settings.templates.errorLabel') }}:</strong> {{ importResult.error }}
          </template>
        </div>

        <div v-if="importResult?.rows?.length" class="table-wrap">
          <table class="preview-table">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ t('settings.templates.colName') }}</th>
                <th>{{ t('settings.templates.colAction') }}</th>
                <th>{{ t('settings.templates.colStatus') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in importResult.rows" :key="row.template_index" :class="{ 'row-error': row.status === 'error' }">
                <td>{{ row.template_index + 1 }}</td>
                <td>{{ row.name || '—' }}</td>
                <td>{{ row.action || '—' }}</td>
                <td>
                  <span v-if="row.errors?.length">{{ row.errors.join(', ') }}</span>
                  <span v-else>{{ row.status }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Export Tab -->
    <div v-else-if="canImportExportTemplates && activeTab === 'export'" class="io-panel">
      <div class="card export-card">
        <h2>{{ t('settings.templates.exportTitle') }}</h2>
        <p class="hint">{{ t('settings.templates.exportHint') }}</p>
        <div class="export-form">
          <label>
            {{ t('settings.templates.exportManufacturer') }}
            <select v-model="exportManufacturer" class="filter-select">
              <option value="">{{ t('settings.templates.exportAllManufacturers') }}</option>
              <option v-for="m in manufacturers" :key="m" :value="m">{{ m }}</option>
              <option :value="NO_MANUFACTURER_KEY">{{ t('settings.templates.generalMixed') }}</option>
            </select>
          </label>
          <button type="button" class="btn-primary" :disabled="isExporting" @click="runExport">
            {{ isExporting ? t('settings.templates.exporting') : t('settings.templates.exportDownload') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Vorlagen-Liste -->
    <template v-else-if="!canImportExportTemplates || activeTab === 'list'">
    <div class="search-bar">
      <div class="search-box">
        <SearchFieldInput
          v-model="searchQuery"
          :label="t('settings.templates.searchPlaceholder')"
        />
      </div>
      <div class="filter-group">
        <select v-model="filterManufacturer" class="filter-select">
          <option value="">{{ t('settings.templates.allManufacturers') }}</option>
          <option v-for="m in listManufacturers" :key="m" :value="m">{{ m }}</option>
          <option :value="NO_MANUFACTURER_KEY">{{ t('settings.templates.generalMixed') }}</option>
        </select>
        <select v-model="filterType" class="filter-select">
          <option value="">{{ t('settings.templates.allTypes') }}</option>
          <option value="physical_combo">{{ t('settings.templates.physicalCombo') }}</option>
          <option value="virtual_combo">{{ t('settings.templates.virtualCombo') }}</option>
        </select>
      </div>
      <div class="template-count">
        {{ t('settings.templates.count', { count: filteredTemplates.length }) }}
      </div>
    </div>

    <!-- Vorlagen-Liste -->
    <div class="templates-list" v-if="!isLoading">
      <!-- Gruppiert nach Hersteller -->
      <div
        v-for="group in groupedTemplates"
        :key="group.manufacturer"
        class="manufacturer-group"
      >
        <div class="manufacturer-header" @click="toggleManufacturer(group.manufacturer)">
          <div class="manufacturer-left">
            <button class="expand-btn" :class="{ expanded: expandedManufacturers.has(group.manufacturer) }">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
              </svg>
            </button>
            <div class="manufacturer-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
              </svg>
            </div>
            <div class="manufacturer-info">
              <span class="manufacturer-name">{{ manufacturerDisplayName(group.manufacturer) }}</span>
              <span class="manufacturer-meta">{{ t('settings.templates.manufacturerTemplateCount', { count: group.templates.length }) }}</span>
            </div>
          </div>
        </div>

        <transition name="expand">
          <div v-if="expandedManufacturers.has(group.manufacturer)" class="templates-in-group">
            <div
              v-for="template in group.templates"
              :key="template.id"
              class="template-item"
              @click="openEditDialog(template)"
            >
              <div class="template-left">
                <div class="template-icon" :class="template.material_type">
                  <svg v-if="template.material_type === 'physical_combo'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                  </svg>
                </div>
                <div class="template-info">
                  <div class="template-name-row">
                    <span class="template-name">{{ template.name }}</span>
                    <span v-if="!isGlobalAdminMode && template.is_global" class="badge global">{{ t('settings.templates.badgeGlobal') }}</span>
                    <span v-if="!isGlobalAdminMode && !template.is_global" class="badge department">{{ t('settings.templates.badgeDepartment') }}</span>
                    <span v-if="!template.is_active" class="badge inactive">{{ t('settings.templates.badgeInactive') }}</span>
                    <span v-if="isSinglePartTemplate(template)" class="badge single-part">{{ t('settings.templates.badgeSinglePart') }}</span>
                  </div>
                  <div class="template-meta">
                    <span class="meta-item">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                      </svg>
                      {{ t('settings.templates.componentCount', { count: template.component_count }) }}
                    </span>
                    <span v-if="template.capacity" class="meta-item">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                      </svg>
                      {{ t('settings.templates.capacityPersons', { count: template.capacity }) }}
                    </span>
                    <span class="meta-item type-badge" :class="template.material_type">
                      {{ template.material_type === 'physical_combo' ? t('settings.templates.typePhysicalShort') : t('settings.templates.typeVirtualShort') }}
                    </span>
                    <span v-if="template.tent_type" class="meta-item">
                      {{ formatTentType(template.tent_type) }}
                    </span>
                  </div>
                </div>
              </div>
              <div class="template-actions">
                <button class="action-btn" @click.stop="duplicateTemplate(template)" :title="duplicateTitle">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                  </svg>
                </button>
                <button v-if="template.can_edit" class="action-btn" @click.stop="openEditDialog(template)" :title="t('common.edit')">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
                <button v-else class="action-btn view-only" @click.stop="openEditDialog(template)" :title="t('settings.templates.viewOnlyTitle')">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
                <button v-if="template.can_edit" class="action-btn delete" @click.stop="confirmDelete(template)" :title="t('common.delete')">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Leerer Zustand -->
      <div v-if="filteredTemplates.length === 0" class="empty-state">
        <div class="empty-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <h3>{{ t('settings.templates.emptyTitle') }}</h3>
        <p>{{ emptyDescription }}</p>
        <div class="empty-actions">
          <button v-if="canImportExportTemplates" class="btn-secondary" @click="activeTab = 'import'">{{ t('settings.templates.jsonImport') }}</button>
          <button class="btn-primary" @click="openCreateDialog">{{ t('settings.templates.firstTemplate') }}</button>
        </div>
      </div>
    </div>

    <!-- Ladezustand -->
    <div v-else class="loading-state">
      <div class="spinner"></div>
      <p>{{ t('settings.templates.loading') }}</p>
    </div>
    </template>

    <!-- Start-Assistent (Neue Vorlage) -->
    <TemplateStartWizard
      v-if="showStartWizard"
      :department-id="departmentId"
      :template-scope="templateScope"
      @close="handleWizardClose"
      @complete="handleWizardComplete"
    />

    <!-- Template Edit/Create Dialog -->
    <TemplateEditDialog
      v-if="showEditDialog"
      :department-id="departmentId"
      :template-scope="templateScope"
      :template="editingTemplate"
      :initial-wizard="wizardResult"
      :readonly="editingReadonly"
      @close="closeEditDialog"
      @saved="handleTemplateSaved"
    />

    <!-- Lösch-Bestätigung -->
    <Teleport to="body">
      <div v-if="showDeleteConfirm" class="modal-overlay">
        <div class="modal-dialog modal-dialog--confirm">
          <h3>{{ t('settings.templates.deleteConfirmTitle') }}</h3>
          <p>
            {{ t('settings.templates.deleteConfirmMessage', { name: deletingTemplate?.name }) }}
          </p>
          <p class="warning-hint">
            {{ t('settings.templates.deleteConfirmWarning') }}
          </p>
          <div class="confirm-actions">
            <button class="btn-secondary" @click="showDeleteConfirm = false">{{ t('common.cancel') }}</button>
            <button class="btn-danger" @click="executeDelete" :disabled="isDeleting">
              {{ isDeleting ? t('common.deleteInProgress') : t('common.delete') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import {
  getTemplates,
  getGlobalTemplates,
  deleteTemplate,
  importTemplates,
  importGlobalTemplates,
  exportTemplates,
  exportGlobalTemplates,
  NO_MANUFACTURER_KEY,
  type Template,
  type TemplateImportResponse,
  type TemplateImportDuplicateAction,
  type TemplateWizardResult,
} from '@/api/templates'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import SearchFieldInput from '@/components/common/SearchFieldInput.vue'
import TemplateEditDialog from '@/components/template/TemplateEditDialog.vue'
import TemplateStartWizard from '@/components/template/TemplateStartWizard.vue'

const props = withDefaults(defineProps<{
  mode?: 'department' | 'global-admin'
}>(), {
  mode: 'department',
})

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const { isMaterialwart } = useDepartmentMemberRole()
const departmentId = computed(() => route.params.departmentId as string)
const isGlobalAdminMode = computed(() => props.mode === 'global-admin')
const canImportExportTemplates = computed(() => isGlobalAdminMode.value || isMaterialwart.value)
const templateScope = computed(() => (isGlobalAdminMode.value ? 'global' : 'department') as 'global' | 'department')

const pageTitle = computed(() =>
  isGlobalAdminMode.value ? t('settings.templates.globalAdmin.title') : t('settings.templates.title')
)
const pageSubtitle = computed(() =>
  isGlobalAdminMode.value ? t('settings.templates.globalAdmin.subtitle') : t('settings.templates.subtitle')
)
const emptyDescription = computed(() =>
  isGlobalAdminMode.value ? t('settings.templates.globalAdmin.emptyDescription') : t('settings.templates.emptyDescription')
)
const duplicateTitle = computed(() =>
  isGlobalAdminMode.value ? t('settings.templates.globalAdmin.duplicateTitle') : t('settings.templates.duplicateTitle')
)

const activeTab = ref<'list' | 'import' | 'export'>('list')
const templates = ref<Template[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const filterManufacturer = ref('')
const filterType = ref('')
const expandedManufacturers = ref(new Set<string>())

// Start Wizard / Edit Dialog
const showStartWizard = ref(false)
const wizardResult = ref<TemplateWizardResult | null>(null)
const showEditDialog = ref(false)
const editingTemplate = ref<Template | null>(null)
const editingReadonly = ref(false)

// Delete
const showDeleteConfirm = ref(false)
const deletingTemplate = ref<Template | null>(null)
const isDeleting = ref(false)

// Import / Export
const importFile = ref<File | null>(null)
const isImporting = ref(false)
const importResult = ref<(TemplateImportResponse & { error?: string }) | null>(null)
const duplicateAction = ref<TemplateImportDuplicateAction>('skip')
const exportManufacturer = ref('')
const isExporting = ref(false)

// Computed: Hersteller-Liste (Export: nur eigene Dep-Vorlagen; Liste: alle sichtbaren)
const manufacturers = computed(() => {
  const set = new Set<string>()
  const source = isGlobalAdminMode.value
    ? templates.value
    : templates.value.filter((tpl) => !tpl.is_global)
  source.forEach((tpl) => {
    if (tpl.manufacturer) set.add(tpl.manufacturer)
  })
  return Array.from(set).sort()
})

const listManufacturers = computed(() => {
  const set = new Set<string>()
  templates.value.forEach((tpl) => {
    if (tpl.manufacturer) set.add(tpl.manufacturer)
  })
  return Array.from(set).sort()
})

// Computed: Gefilterte Vorlagen
const filteredTemplates = computed(() => {
  let result = templates.value

  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(t =>
      t.name.toLowerCase().includes(query) ||
      (t.manufacturer && t.manufacturer.toLowerCase().includes(query)) ||
      (t.description && t.description.toLowerCase().includes(query))
    )
  }

  if (filterManufacturer.value) {
    if (filterManufacturer.value === NO_MANUFACTURER_KEY) {
      result = result.filter(t => isGeneralMixedTemplate(t))
    } else {
      result = result.filter(t => t.manufacturer === filterManufacturer.value)
    }
  }

  if (filterType.value) {
    result = result.filter(t => t.material_type === filterType.value)
  }

  return result
})

function manufacturerDisplayName(key: string): string {
  return key === NO_MANUFACTURER_KEY ? t('settings.templates.generalMixed') : key
}

function isGeneralMixedTemplate(template: Template): boolean {
  return !template.manufacturer && !template.manufacturer_address_id
}

// Computed: Gruppiert nach Hersteller
const groupedTemplates = computed(() => {
  const groups: Record<string, Template[]> = {}
  for (const tpl of filteredTemplates.value) {
    const key = isGeneralMixedTemplate(tpl) ? NO_MANUFACTURER_KEY : (tpl.manufacturer || NO_MANUFACTURER_KEY)
    if (!groups[key]) groups[key] = []
    groups[key].push(tpl)
  }
  return Object.entries(groups)
    .map(([manufacturer, tpls]) => ({
      manufacturer,
      templates: tpls.sort((a, b) => a.name.localeCompare(b.name)),
    }))
    .sort((a, b) => {
      if (a.manufacturer === NO_MANUFACTURER_KEY) return 1
      if (b.manufacturer === NO_MANUFACTURER_KEY) return -1
      return manufacturerDisplayName(a.manufacturer).localeCompare(manufacturerDisplayName(b.manufacturer))
    })
})

// Hersteller-Gruppen expandieren/kollabieren
function toggleManufacturer(manufacturer: string) {
  if (expandedManufacturers.value.has(manufacturer)) {
    expandedManufacturers.value.delete(manufacturer)
  } else {
    expandedManufacturers.value.add(manufacturer)
  }
}

// Tent-Typ formatieren
function formatTentType(type: string): string {
  const key = `settings.templates.tentType.${type}`
  const translated = t(key)
  return translated !== key ? translated : type
}

function isSinglePartTemplate(template: Template): boolean {
  return template.template_kind === 'single_part' || template.component_count === 1
}

// Dialog-Funktionen
function openCreateDialog() {
  wizardResult.value = null
  showStartWizard.value = true
}

function handleWizardComplete(result: TemplateWizardResult) {
  showStartWizard.value = false
  wizardResult.value = result
  editingTemplate.value = null
  editingReadonly.value = false
  showEditDialog.value = true
}

function handleWizardClose() {
  showStartWizard.value = false
}

function openEditDialog(template: Template) {
  editingTemplate.value = template
  editingReadonly.value = !template.can_edit
  showEditDialog.value = true
}

function closeEditDialog() {
  showEditDialog.value = false
  editingTemplate.value = null
  editingReadonly.value = false
  wizardResult.value = null
}

async function handleTemplateSaved() {
  closeEditDialog()
  await loadTemplates()
}

// Duplizieren – Department: eigene Kopie; Admin: globale Kopie
async function duplicateTemplate(template: Template) {
  if (isGlobalAdminMode.value) {
    editingTemplate.value = {
      ...template,
      id: '',
      name: `${template.name} (${t('settings.templates.duplicateNameSuffix')})`,
      scope: 'global',
      is_global: true,
      department_id: null,
      can_edit: true,
    }
  } else {
    editingTemplate.value = {
      ...template,
      id: '',
      name: `${template.name} (${t('settings.templates.duplicateNameSuffix')})`,
      scope: 'department',
      is_global: false,
      department_id: departmentId.value,
      can_edit: true,
    }
  }
  editingReadonly.value = false
  showEditDialog.value = true
}

// Löschen
function confirmDelete(template: Template) {
  deletingTemplate.value = template
  showDeleteConfirm.value = true
}

async function executeDelete() {
  if (!deletingTemplate.value) return
  isDeleting.value = true
  try {
    await deleteTemplate(deletingTemplate.value.id)
    await loadTemplates()
    showDeleteConfirm.value = false
    deletingTemplate.value = null
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.templates.deleteError'))
  } finally {
    isDeleting.value = false
  }
}

// Import
function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files && input.files.length > 0) {
    importFile.value = input.files[0]
    importResult.value = null
  }
}

async function parseImportFile(): Promise<unknown> {
  if (!importFile.value) throw new Error('No file')
  const text = await importFile.value.text()
  return JSON.parse(text)
}

async function runImportRequest(dryRun: boolean) {
  const json = await parseImportFile()
  const options = { dryRun, duplicateAction: duplicateAction.value }
  return isGlobalAdminMode.value
    ? await importGlobalTemplates(json, options)
    : await importTemplates(departmentId.value, json, options)
}

async function runDryRun() {
  if (!importFile.value) return
  isImporting.value = true
  importResult.value = null
  try {
    importResult.value = await runImportRequest(true)
  } catch (err: unknown) {
    importResult.value = { success: false, error: importErrorMessage(err), dry_run: true, manufacturer: '', rows: [], stats: { created: 0, updated: 0, skipped: 0, errors: 1 }, total: 0, created: 0, updated: 0, skipped: 0 }
    toast.error(importResult.value.error!)
  } finally {
    isImporting.value = false
  }
}

async function executeImport() {
  if (!importFile.value) return
  isImporting.value = true
  importResult.value = null
  try {
    const result = await runImportRequest(false)
    importResult.value = result
    if (result.success) {
      toast.success(t('settings.templates.importSuccessStrong'))
      await loadTemplates()
    }
  } catch (err: unknown) {
    const msg = importErrorMessage(err)
    importResult.value = { success: false, error: msg, dry_run: false, manufacturer: '', rows: [], stats: { created: 0, updated: 0, skipped: 0, errors: 1 }, total: 0, created: 0, updated: 0, skipped: 0 }
    toast.error(msg)
  } finally {
    isImporting.value = false
  }
}

function importErrorMessage(err: unknown): string {
  if (err instanceof SyntaxError) return t('settings.templates.invalidJson')
  const axiosErr = err as { response?: { data?: { error?: string } } }
  return axiosErr.response?.data?.error || (err as Error).message || t('settings.templates.importFailed')
}

async function runExport() {
  isExporting.value = true
  try {
    const manufacturer = exportManufacturer.value || undefined
    const data = isGlobalAdminMode.value
      ? await exportGlobalTemplates(manufacturer)
      : await exportTemplates(departmentId.value, manufacturer)
    const slug = (data.manufacturer || 'templates').toLowerCase().replace(/[^a-z0-9]+/g, '-')
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${slug}-templates-v5.json`
    a.click()
    URL.revokeObjectURL(url)
    toast.success(t('settings.templates.exportSuccess'))
  } catch (err: unknown) {
    const axiosErr = err as { response?: { data?: { error?: string } } }
    toast.error(axiosErr.response?.data?.error || t('settings.templates.exportFailed'))
  } finally {
    isExporting.value = false
  }
}

// Daten laden
async function loadTemplates() {
  isLoading.value = true
  try {
    templates.value = isGlobalAdminMode.value
      ? await getGlobalTemplates()
      : await getTemplates(departmentId.value)
    expandedManufacturers.value.clear()
  } catch (err) {
    console.error(t('settings.templates.loadError'), err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadTemplates()
})
</script>

<style scoped>
.templates-settings {
  min-height: 500px;
}

.settings-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.settings-header h1 {
  font-size: 24px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.settings-header .subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.tab-bar {
  display: flex;
  gap: 4px;
  margin-bottom: 20px;
  border-bottom: 1px solid #e5e7eb;
}

.tab-btn {
  padding: 10px 16px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #6b7280;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  margin-bottom: -1px;
}

.tab-btn.active {
  color: var(--color-primary);
  border-bottom-color: var(--color-primary);
}

.io-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 20px;
}

.actions-card {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.file-label {
  cursor: pointer;
  display: inline-flex;
}

.file-input {
  display: none;
}

.file-name {
  font-size: 13px;
  color: #6b7280;
}

.hint {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

.preview-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.duplicate-default {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #374151;
}

.form-select-sm {
  padding: 4px 8px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13px;
}

.table-wrap {
  overflow-x: auto;
}

.preview-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.preview-table th,
.preview-table td {
  padding: 8px 12px;
  text-align: left;
  border-bottom: 1px solid #f3f4f6;
}

.preview-table th {
  font-weight: 600;
  color: #374151;
  background: #f9fafb;
}

.row-error {
  background: #fef2f2;
}

.export-card h2 {
  font-size: 16px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.export-form {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 16px;
}

.export-form label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  color: #374151;
}

/* Buttons use shared ui/buttons.css */

/* Search & Filter */
.search-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.search-input-wrapper {
  position: relative;
  flex: 1;
  min-width: 200px;
  max-width: 350px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
}

/* Search input base uses shared ui/page-layout.css */

.filter-group {
  display: flex;
  gap: 8px;
}

/* Filter select base uses shared ui/page-layout.css */

.template-count {
  font-size: 13px;
  color: #6b7280;
  margin-left: auto;
}

/* Templates List */
.templates-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.manufacturer-group {
  background: #f9fafb;
  border-radius: 10px;
  overflow: hidden;
}

.manufacturer-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: white;
  cursor: pointer;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.2s;
}

.manufacturer-header:hover {
  background: #f9fafb;
}

.manufacturer-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.expand-btn {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  transition: transform 0.2s;
}

.expand-btn.expanded {
  transform: rotate(90deg);
}

.manufacturer-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: var(--color-primary-muted-bg);
  color: var(--color-primary);
}

.manufacturer-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.manufacturer-name {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

.manufacturer-meta {
  font-size: 12px;
  color: #9ca3af;
}

.templates-in-group {
  border-top: 1px solid #f3f4f6;
}

.template-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px 14px 52px;
  background: #fafafa;
  cursor: pointer;
  transition: background 0.2s;
  border-bottom: 1px solid #f3f4f6;
}

.template-item:last-child {
  border-bottom: none;
}

.template-item:hover {
  background: #f3f4f6;
}

.template-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

.template-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  flex-shrink: 0;
}

.template-icon.physical_combo {
  background: #dbeafe;
  color: #2563eb;
}

.template-icon.virtual_combo {
  background: #fce7f3;
  color: #db2777;
}

.template-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.template-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.template-name {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.badge {
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 500;
  flex-shrink: 0;
}

.badge.inactive {
  background: #f3f4f6;
  color: #6b7280;
}

.badge.global {
  background: #dbeafe;
  color: #1d4ed8;
}

.badge.department {
  background: #ecfdf5;
  color: #065f46;
}

.badge.single-part {
  background: #ecfdf5;
  color: #047857;
}

.template-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #6b7280;
}

.type-badge {
  padding: 1px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 500;
}

.type-badge.physical_combo {
  background: #dbeafe;
  color: #1d4ed8;
}

.type-badge.virtual_combo {
  background: #fce7f3;
  color: #be185d;
}

.template-actions {
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.2s;
  flex-shrink: 0;
}

.template-item:hover .template-actions {
  opacity: 1;
}

.action-btn {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #f3f4f6;
  color: #374151;
}

.action-btn.delete:hover {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fecaca;
}

.action-btn.view-only {
  color: #9ca3af;
}

.action-btn.view-only:hover {
  background: #f3f4f6;
  color: #6b7280;
}

/* Expand Transition */
.expand-enter-active,
.expand-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
}

.expand-enter-to,
.expand-leave-from {
  opacity: 1;
  max-height: 2000px;
}

/* Empty state base uses shared ui/states.css */

.empty-icon {
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 50%;
  color: #9ca3af;
  margin-bottom: 16px;
}

/* Empty-state title/text typography uses shared ui/states.css */

.empty-actions {
  display: flex;
  gap: 12px;
}

/* Loading state base uses shared ui/states.css */

/* Modal overlay + confirm dialog: shared ui/modals.css */

/* Import Dialog */
.import-dialog {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.close-btn {
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
}

.close-btn:hover {
  background: #f3f4f6;
  color: #374151;
}

.dialog-body {
  padding: 24px;
}

.import-info {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 16px 0;
  line-height: 1.5;
}

.import-info code {
  display: block;
  margin-top: 8px;
  padding: 8px 12px;
  background: #f3f4f6;
  border-radius: 6px;
  font-size: 12px;
  color: #374151;
}

.file-upload-area {
  border: 2px dashed #d1d5db;
  border-radius: 10px;
  padding: 30px;
  text-align: center;
  transition: all 0.2s;
  margin-bottom: 16px;
}

.file-upload-area.dragging {
  border-color: var(--color-primary-light);
  background: var(--color-primary-muted-bg);
}

.file-input {
  display: none;
}

.upload-content {
  cursor: pointer;
  color: #9ca3af;
}

.upload-content p {
  font-size: 14px;
  margin: 12px 0 0 0;
  color: #6b7280;
}

.upload-content .file-selected {
  color: var(--color-primary);
  font-weight: 500;
}

.import-result {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 13px;
}

.import-result.success {
  background: #ecfdf5;
  color: #065f46;
}

.import-result.error {
  background: #fef2f2;
  color: #991b1b;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
}

/* Danger button uses shared ui/buttons.css */
</style>
