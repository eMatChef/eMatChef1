<template>
  <div class="workshop-settings" :class="{ 'workshop-settings--embedded': embedded }">
    <div v-if="!embedded" class="settings-header">
      <div>
        <h1>{{ t('settings.workshopSettings.title') }}</h1>
        <p class="subtitle">{{ t('settings.workshopSettings.subtitle') }}</p>
      </div>
    </div>

    <ELoadingState v-if="isLoading" variant="page" :message="t('settings.workshopSettings.loading')" />

    <div v-else class="settings-form">
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon workshop">
            <v-icon icon="mdi-wrench" size="20" />
          </div>
          <div>
            <h3>{{ t('settings.workshopSettings.sections.costs.title') }}</h3>
            <p>{{ t('settings.workshopSettings.sections.costs.description') }}</p>
          </div>
        </div>
        <div class="setting-fields">
          <ETextField
            v-model="form.hourlyRateChf"
            type="number"
            step="0.05"
            min="0"
            :label="t('settings.workshopSettings.fields.hourlyRateChf')"
            :hint="t('settings.workshopSettings.hints.hourlyRateChf')"
            hide-details="auto"
          />
        </div>
      </div>

      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon reminder">
            <v-icon icon="mdi-bell-outline" size="20" />
          </div>
          <div>
            <h3>{{ t('settings.workshopSettings.sections.reminders.title') }}</h3>
            <p>{{ t('settings.workshopSettings.sections.reminders.description') }}</p>
          </div>
        </div>
        <div class="setting-fields">
          <div class="field-row">
            <ESelect
              v-model="form.orderReminderMode"
              :items="reminderModeItems"
              :label="t('settings.workshopSettings.fields.orderReminderMode')"
              hide-details="auto"
            />
            <ETextField
              v-if="form.orderReminderMode === 'days'"
              v-model.number="form.orderReminderDays"
              type="number"
              min="1"
              max="365"
              :label="t('settings.workshopSettings.fields.orderReminderDays')"
              :hint="t('settings.workshopSettings.hints.orderReminderDays')"
              hide-details="auto"
            />
          </div>
        </div>
      </div>

      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon parts">
            <v-icon icon="mdi-cog-outline" size="20" />
          </div>
          <div>
            <h3>{{ t('settings.workshopSettings.sections.spareParts.title') }}</h3>
            <p>{{ t('settings.workshopSettings.sections.spareParts.description') }}</p>
          </div>
        </div>
        <div class="setting-fields">
          <div class="repair-parts-category-info">
            <span class="info-label">{{ t('settings.workshopSettings.fields.sparePartsCategory') }}</span>
            <span class="info-value">{{ t('settings.workshopSettings.repairPartsCategoryName') }}</span>
            <p class="field-hint">{{ t('settings.workshopSettings.hints.sparePartsCategoryAuto') }}</p>
          </div>
        </div>
      </div>

      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon templates">
            <v-icon icon="mdi-file-document-edit-outline" size="20" />
          </div>
          <div class="section-header-text">
            <h3>{{ t('settings.workshopSettings.sections.templates.title') }}</h3>
            <p>{{ t('settings.workshopSettings.sections.templates.description') }}</p>
          </div>
          <EButton
            variant="secondary"
            size="small"
            class="section-action"
            :disabled="importablePlatformTemplates.length === 0"
            @click="importDialogOpen = true"
          >
            {{ t('settings.workshopSettings.templates.importFromPlatform') }}
          </EButton>
        </div>

        <ELoadingState
          v-if="templatesLoading"
          variant="inline"
          :message="t('settings.workshopSettings.templates.loading')"
        />

        <p v-else-if="departmentTemplates.length === 0" class="field-hint">
          {{ t('settings.workshopSettings.templates.empty') }}
        </p>

        <div v-else class="template-list">
          <div
            v-for="tpl in departmentTemplates"
            :key="tpl.id"
            class="template-card"
          >
            <div class="template-card-main">
              <div class="template-card-title">{{ tpl.name }}</div>
              <div class="template-card-meta">
                <span class="template-key">{{ tpl.template_key }}</span>
                <span
                  class="status-badge"
                  :class="tpl.is_active ? 'active' : 'inactive'"
                >
                  {{ tpl.is_active
                    ? t('settings.workshopSettings.templates.statusActive')
                    : t('settings.workshopSettings.templates.statusInactive') }}
                </span>
              </div>
            </div>
            <div class="template-card-actions">
              <EButton variant="secondary" size="small" @click="openPreview(tpl)">
                {{ t('settings.workshopSettings.templates.preview') }}
              </EButton>
              <EButton variant="secondary" size="small" @click="openEditor(tpl)">
                {{ t('common.edit') }}
              </EButton>
            </div>
          </div>
        </div>
      </div>

      <div class="save-bar">
        <span v-if="hasChanges" class="unsaved-hint">
          <v-icon icon="mdi-alert-circle-outline" size="16" />
          {{ t('settings.workshopSettings.unsavedChanges') }}
        </span>
        <div class="save-actions">
          <EButton variant="secondary" :disabled="!hasChanges || isSaving" @click="resetForm">
            {{ t('settings.workshopSettings.reset') }}
          </EButton>
          <EButton variant="primary" :loading="isSaving" :disabled="!hasChanges" @click="saveSettings">
            {{ isSaving ? t('settings.workshopSettings.saving') : t('common.save') }}
          </EButton>
        </div>
      </div>
    </div>

    <EDialog
      v-model="importDialogOpen"
      :title="t('settings.workshopSettings.templates.importTitle')"
      max-width="480"
    >
      <p class="import-hint">{{ t('settings.workshopSettings.templates.importHint') }}</p>
      <ELoadingState
        v-if="platformTemplatesLoading"
        variant="inline"
        :message="t('settings.workshopSettings.templates.loadingPlatform')"
      />
      <p v-else-if="importablePlatformTemplates.length === 0" class="field-hint">
        {{ t('settings.workshopSettings.templates.nothingToImport') }}
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

    <DepartmentRepairTemplateEditorDialog
      v-model="editorOpen"
      v-model:template="editingTemplate"
      :department-id="departmentId"
      @saved="onTemplateSaved"
    />

    <RepairSheetPreviewDialog
      v-model="previewOpen"
      v-model:template="previewTemplate"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import {
  getWorkshopSettings,
  saveWorkshopSettings,
  type WorkshopSettings,
} from '@/api/departmentSettings'
import {
  getDepartmentRepairTemplates,
  getPlatformRepairTemplates,
  importDepartmentRepairTemplate,
  type DepartmentRepairTemplate,
  type PlatformRepairTemplate,
} from '@/api/repairTemplates'
import DepartmentRepairTemplateEditorDialog from '@/components/workshop/DepartmentRepairTemplateEditorDialog.vue'
import RepairSheetPreviewDialog from '@/components/workshop/RepairSheetPreviewDialog.vue'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'

withDefaults(
  defineProps<{
    embedded?: boolean
  }>(),
  { embedded: false },
)

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => String(route.params.departmentId || ''))

const isLoading = ref(true)
const isSaving = ref(false)
const savedForm = ref<WorkshopSettings | null>(null)

const templatesLoading = ref(false)
const platformTemplatesLoading = ref(false)
const departmentTemplates = ref<DepartmentRepairTemplate[]>([])
const platformTemplates = ref<PlatformRepairTemplate[]>([])
const importDialogOpen = ref(false)
const editorOpen = ref(false)
const editingTemplate = ref<DepartmentRepairTemplate | null>(null)
const previewOpen = ref(false)
const previewTemplate = ref<DepartmentRepairTemplate | null>(null)
const importingKey = ref<string | null>(null)

const form = reactive<WorkshopSettings>({
  hourlyRateChf: '45.00',
  orderReminderDays: 7,
  orderReminderMode: 'days',
  sparePartsCategoryId: '',
})

const reminderModeItems = computed(() => [
  { title: t('settings.workshopSettings.reminderModes.days'), value: 'days' },
  { title: t('settings.workshopSettings.reminderModes.documentDate'), value: 'document_date' },
])

function editableWorkshopFields(settings: WorkshopSettings) {
  return {
    hourlyRateChf: settings.hourlyRateChf,
    orderReminderDays: settings.orderReminderDays,
    orderReminderMode: settings.orderReminderMode,
  }
}

const hasChanges = computed(() => {
  if (!savedForm.value) return false
  return (
    JSON.stringify(editableWorkshopFields(form)) !==
    JSON.stringify(editableWorkshopFields(savedForm.value))
  )
})

const importablePlatformTemplates = computed(() => {
  const imported = new Set(departmentTemplates.value.map((t) => t.template_key))
  return platformTemplates.value.filter((t) => t.is_active && !imported.has(t.template_key))
})

async function loadSettings() {
  isLoading.value = true
  try {
    const settings = await getWorkshopSettings(departmentId.value)
    Object.assign(form, settings)
    savedForm.value = { ...settings }
  } catch (err) {
    console.error('Workshop settings load failed:', err)
    toast.error(t('settings.workshopSettings.toastLoadError'))
  } finally {
    isLoading.value = false
  }
}

async function saveSettings() {
  isSaving.value = true
  try {
    const payload: WorkshopSettings = {
      hourlyRateChf: form.hourlyRateChf,
      orderReminderDays: Math.max(1, Math.min(365, Number(form.orderReminderDays) || 7)),
      orderReminderMode: form.orderReminderMode,
      sparePartsCategoryId: form.sparePartsCategoryId,
    }
    await saveWorkshopSettings(departmentId.value, payload)
    Object.assign(form, payload)
    savedForm.value = { ...payload }
    toast.success(t('settings.workshopSettings.toastSaved'))
  } catch (err: unknown) {
    console.error('Workshop settings save failed:', err)
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('settings.workshopSettings.toastSaveError'))
  } finally {
    isSaving.value = false
  }
}

function resetForm() {
  if (savedForm.value) {
    Object.assign(form, savedForm.value)
  }
}

async function loadTemplates() {
  templatesLoading.value = true
  try {
    departmentTemplates.value = await getDepartmentRepairTemplates(departmentId.value)
  } catch (err) {
    console.error('Department repair templates load failed:', err)
    toast.error(t('settings.workshopSettings.templates.toastLoadError'))
  } finally {
    templatesLoading.value = false
  }
}

async function loadPlatformTemplates() {
  platformTemplatesLoading.value = true
  try {
    platformTemplates.value = await getPlatformRepairTemplates()
  } catch (err) {
    console.error('Platform repair templates load failed:', err)
  } finally {
    platformTemplatesLoading.value = false
  }
}

async function importTemplate(templateKey: string) {
  importingKey.value = templateKey
  try {
    const imported = await importDepartmentRepairTemplate(departmentId.value, templateKey)
    departmentTemplates.value = [...departmentTemplates.value, imported].sort((a, b) =>
      a.name.localeCompare(b.name)
    )
    toast.success(t('settings.workshopSettings.templates.toastImported'))
    if (importablePlatformTemplates.value.length === 0) {
      importDialogOpen.value = false
    }
  } catch (err: unknown) {
    console.error('Repair template import failed:', err)
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('settings.workshopSettings.templates.toastImportError'))
  } finally {
    importingKey.value = null
  }
}

function openEditor(template: DepartmentRepairTemplate) {
  editingTemplate.value = template
  editorOpen.value = true
}

function openPreview(template: DepartmentRepairTemplate) {
  previewTemplate.value = template
  previewOpen.value = true
}

function onTemplateSaved(updated: DepartmentRepairTemplate) {
  departmentTemplates.value = departmentTemplates.value.map((t) =>
    t.template_key === updated.template_key ? updated : t
  )
}

watch(importDialogOpen, (open) => {
  if (open && platformTemplates.value.length === 0) {
    void loadPlatformTemplates()
  }
})

onMounted(() => {
  void loadSettings()
  void loadTemplates()
})
</script>

<style scoped>
.workshop-settings {
  min-height: 500px;
}

.settings-header {
  margin-bottom: 32px;
}

.settings-header h1 {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 8px;
}

.subtitle {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}

.settings-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.settings-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
}

.section-header {
  display: flex;
  gap: 14px;
  margin-bottom: 16px;
}

.section-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.section-icon.workshop {
  background: #eff6ff;
  color: #2563eb;
}

.section-icon.reminder {
  background: #fef3c7;
  color: #d97706;
}

.section-icon.parts {
  background: #f0fdf4;
  color: #16a34a;
}

.section-icon.templates {
  background: #f5f3ff;
  color: #7c3aed;
}

.section-header-text {
  flex: 1;
  min-width: 0;
}

.section-action {
  flex-shrink: 0;
  align-self: flex-start;
}

.section-header h3 {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.section-header p {
  margin: 0;
  font-size: 13px;
  color: #6b7280;
}

.setting-fields {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.field-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.field-hint {
  font-size: 12px;
  color: #6b7280;
  margin: 4px 0 0;
}

.warning-hint {
  color: #b45309;
}

.save-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border-top: 1px solid #e5e7eb;
  margin-top: 8px;
}

.unsaved-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #d97706;
  font-weight: 500;
}

.save-actions {
  display: flex;
  gap: 12px;
  margin-left: auto;
}

.template-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.template-card-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.template-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f9fafb;
}

.template-card-title {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

.template-card-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
}

.template-key {
  font-size: 11px;
  color: #6b7280;
  font-family: ui-monospace, monospace;
}

.status-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.inactive {
  background: #f3f4f6;
  color: #6b7280;
}

.import-hint {
  margin: 0 0 12px;
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
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  text-align: left;
}

.import-item:hover:not(:disabled) {
  border-color: #c4b5fd;
  background: #faf5ff;
}

.import-item:disabled {
  opacity: 0.6;
  cursor: wait;
}

.import-item-name {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

.import-item-key {
  font-size: 11px;
  color: #6b7280;
  font-family: ui-monospace, monospace;
}

.repair-parts-category-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.repair-parts-category-info .info-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
}

.repair-parts-category-info .info-value {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}
</style>
