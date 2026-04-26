<template>
  <div class="templates-settings">
    <div class="settings-header">
      <div>
        <h1>{{ t('settings.templates.title') }}</h1>
        <p class="subtitle">{{ t('settings.templates.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <button class="btn-secondary" @click="showImportDialog = true">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          {{ t('settings.templates.jsonImport') }}
        </button>
        <button class="btn-primary" @click="openCreateDialog">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ t('settings.templates.newTemplate') }}
        </button>
      </div>
    </div>

    <!-- Suchleiste + Filter -->
    <div class="search-bar">
      <div class="search-input-wrapper">
        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/>
          <path d="m21 21-4.35-4.35"/>
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="t('settings.templates.searchPlaceholder')"
          class="search-input"
        />
      </div>
      <div class="filter-group">
        <select v-model="filterManufacturer" class="filter-select">
          <option value="">{{ t('settings.templates.allManufacturers') }}</option>
          <option v-for="m in manufacturers" :key="m" :value="m">{{ m }}</option>
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
              <span class="manufacturer-name">{{ group.manufacturer }}</span>
              <span class="manufacturer-meta">{{ group.templates.length }} Vorlagen</span>
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
                    <span v-if="template.is_global" class="badge global">Zentral</span>
                    <span v-else class="badge department">Eigene</span>
                    <span v-if="!template.is_active" class="badge inactive">Inaktiv</span>
                  </div>
                  <div class="template-meta">
                    <span class="meta-item">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                      </svg>
                      {{ template.component_count }} Komponenten
                    </span>
                    <span v-if="template.capacity" class="meta-item">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                      </svg>
                      {{ template.capacity }} Personen
                    </span>
                    <span class="meta-item type-badge" :class="template.material_type">
                      {{ template.material_type === 'physical_combo' ? 'Physisch' : 'Virtuell' }}
                    </span>
                    <span v-if="template.tent_type" class="meta-item">
                      {{ formatTentType(template.tent_type) }}
                    </span>
                  </div>
                </div>
              </div>
              <div class="template-actions">
                <button class="action-btn" @click.stop="duplicateTemplate(template)" title="Als eigene Kopie erstellen">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                  </svg>
                </button>
                <button v-if="template.can_edit" class="action-btn" @click.stop="openEditDialog(template)" :title="t('settings.templates.edit')">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
                <button v-else class="action-btn view-only" @click.stop="openEditDialog(template)" title="Ansehen (nur lesen)">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
                <button v-if="template.can_edit" class="action-btn delete" @click.stop="confirmDelete(template)" :title="t('settings.templates.delete')">
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
        <p>{{ t('settings.templates.emptyDescription') }}</p>
        <div class="empty-actions">
          <button class="btn-secondary" @click="showImportDialog = true">JSON Import</button>
          <button class="btn-primary" @click="openCreateDialog">{{ t('settings.templates.firstTemplate') }}</button>
        </div>
      </div>
    </div>

    <!-- Ladezustand -->
    <div v-else class="loading-state">
      <div class="spinner"></div>
      <p>Vorlagen werden geladen...</p>
    </div>

    <!-- Template Edit/Create Dialog -->
    <TemplateEditDialog
      v-if="showEditDialog"
      :department-id="departmentId"
      :template="editingTemplate"
      :readonly="editingReadonly"
      @close="closeEditDialog"
      @saved="handleTemplateSaved"
    />

    <!-- Import Dialog -->
    <div v-if="showImportDialog" class="modal-overlay">
      <div class="import-dialog">
        <div class="dialog-header">
          <h2>JSON Vorlagen importieren</h2>
          <button class="close-btn" @click="showImportDialog = false">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
        <div class="dialog-body">
          <p class="import-info">
            Importieren Sie Vorlagen im v4-Format. Die JSON-Datei muss folgende Struktur haben:
            <code>{{ '{ "manufacturer": "...", "templates": [...] }' }}</code>
          </p>
          <div class="file-upload-area" :class="{ dragging: isDragging }" @dragover.prevent="isDragging = true" @dragleave="isDragging = false" @drop.prevent="handleFileDrop">
            <input type="file" ref="fileInputEl" accept=".json" @change="handleFileSelect" class="file-input" />
            <div class="upload-content" @click="triggerFileInput()">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
              </svg>
              <p v-if="!importFile">JSON-Datei hierher ziehen oder klicken</p>
              <p v-else class="file-selected">{{ importFile.name }}</p>
            </div>
          </div>
          <div v-if="importResult" class="import-result" :class="{ success: importResult.success, error: !importResult.success }">
            <template v-if="importResult.success">
              <strong>Import erfolgreich!</strong>
              {{ t('settings.templates.importSuccess', { created: importResult.created, skipped: importResult.skipped, manufacturer: importResult.manufacturer }) }}
            </template>
            <template v-else>
              <strong>{{ t('settings.templates.errorLabel') }}:</strong> {{ importResult.error }}
            </template>
          </div>
        </div>
        <div class="dialog-footer">
          <button class="btn-secondary" @click="showImportDialog = false">Schliessen</button>
          <button class="btn-primary" @click="executeImport" :disabled="!importFile || isImporting">
            {{ isImporting ? 'Wird importiert...' : 'Importieren' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Lösch-Bestätigung -->
    <div v-if="showDeleteConfirm" class="modal-overlay">
      <div class="confirm-dialog">
        <h3>{{ t('settings.templates.deleteConfirmTitle') }}</h3>
        <p>
          {{ t('settings.templates.deleteConfirmMessage', { name: deletingTemplate?.name }) }}
        </p>
        <p class="warning-hint">
          Bereits erstellte Materialien werden davon nicht betroffen.
        </p>
        <div class="confirm-actions">
          <button class="btn-secondary" @click="showDeleteConfirm = false">Abbrechen</button>
          <button class="btn-danger" @click="executeDelete" :disabled="isDeleting">
            {{ isDeleting ? t('settings.templates.deleting') : t('settings.templates.delete') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { getTemplates, deleteTemplate, importTemplates, type Template } from '@/api/templates'
import TemplateEditDialog from '@/components/template/TemplateEditDialog.vue'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const departmentId = computed(() => route.params.departmentId as string)

const templates = ref<Template[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const filterManufacturer = ref('')
const filterType = ref('')
const expandedManufacturers = ref(new Set<string>())

// Edit/Create Dialog
const showEditDialog = ref(false)
const editingTemplate = ref<Template | null>(null)
const editingReadonly = ref(false)

// Delete
const showDeleteConfirm = ref(false)
const deletingTemplate = ref<Template | null>(null)
const isDeleting = ref(false)

// Import
const showImportDialog = ref(false)
const importFile = ref<File | null>(null)
const isDragging = ref(false)
const isImporting = ref(false)
const importResult = ref<{ success: boolean; created?: number; skipped?: number; manufacturer?: string; error?: string } | null>(null)
const fileInputEl = ref<HTMLInputElement | null>(null)

function triggerFileInput() {
  fileInputEl.value?.click()
}

// Computed: Hersteller-Liste
const manufacturers = computed(() => {
  const set = new Set<string>()
  templates.value.forEach(t => {
    if (t.manufacturer) set.add(t.manufacturer)
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
    result = result.filter(t => t.manufacturer === filterManufacturer.value)
  }

  if (filterType.value) {
    result = result.filter(t => t.material_type === filterType.value)
  }

  return result
})

// Computed: Gruppiert nach Hersteller
const groupedTemplates = computed(() => {
  const groups: Record<string, Template[]> = {}
  for (const t of filteredTemplates.value) {
    const key = t.manufacturer || 'Ohne Hersteller'
    if (!groups[key]) groups[key] = []
    groups[key].push(t)
  }
  return Object.entries(groups)
    .map(([manufacturer, tpls]) => ({ manufacturer, templates: tpls }))
    .sort((a, b) => a.manufacturer.localeCompare(b.manufacturer))
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
  const map: Record<string, string> = {
    gruppenzelt: 'Gruppenzelt',
    sonstiges: 'Sonstiges',
  }
  return map[type] || type
}

// Dialog-Funktionen
function openCreateDialog() {
  editingTemplate.value = null
  editingReadonly.value = false
  showEditDialog.value = true
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
}

async function handleTemplateSaved() {
  closeEditDialog()
  await loadTemplates()
}

// Duplizieren – erstellt immer eine eigene Department-Kopie
async function duplicateTemplate(template: Template) {
  editingTemplate.value = {
    ...template,
    id: '', // Kein ID = neue Vorlage
    name: `${template.name} (Kopie)`,
    scope: 'department',
    is_global: false,
    department_id: departmentId.value,
    can_edit: true,
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

function handleFileDrop(event: DragEvent) {
  isDragging.value = false
  if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
    const file = event.dataTransfer.files[0]
    if (file.name.endsWith('.json')) {
      importFile.value = file
      importResult.value = null
    }
  }
}

async function executeImport() {
  if (!importFile.value) return
  isImporting.value = true
  importResult.value = null

  try {
    const text = await importFile.value.text()
    const json = JSON.parse(text)
    const result = await importTemplates(departmentId.value, json)
    importResult.value = result
    await loadTemplates()
  } catch (err: any) {
    if (err instanceof SyntaxError) {
      const msg = t('settings.templates.invalidJson')
      importResult.value = { success: false, error: msg }
      toast.error(msg)
    } else {
      const msg = (err as any).response?.data?.error || (err as Error).message || t('settings.templates.importFailed')
      importResult.value = { success: false, error: msg }
      toast.error(msg)
    }
  } finally {
    isImporting.value = false
  }
}

// Daten laden
async function loadTemplates() {
  isLoading.value = true
  try {
    templates.value = await getTemplates(departmentId.value)
    // Alle Gruppen expandieren
    manufacturers.value.forEach(m => expandedManufacturers.value.add(m))
    // Auch "Ohne Hersteller" expandieren falls vorhanden
    if (templates.value.some(t => !t.manufacturer)) {
      expandedManufacturers.value.add('Ohne Hersteller')
    }
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
  background: #ede9fe;
  color: #7c3aed;
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

/* Modal overlay base uses shared ui/modals.css */

/* Import Dialog */
.import-dialog {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.dialog-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.dialog-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
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
  border-color: #8b5cf6;
  background: #faf5ff;
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
  color: #7c3aed;
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

/* Delete Confirm */
.confirm-dialog {
  background: white;
  border-radius: 12px;
  padding: 24px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.confirm-dialog h3 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px 0;
}

.confirm-dialog p {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 8px 0;
}

.warning-hint {
  font-size: 12px;
  color: #9ca3af;
  font-style: italic;
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}

/* Danger button uses shared ui/buttons.css */
</style>
