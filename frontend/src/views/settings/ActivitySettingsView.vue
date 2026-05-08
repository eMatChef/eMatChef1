<template>
  <div class="activity-settings">
    <div class="settings-header">
      <div>
        <h1>{{ t('settings.activitySettings.title') }}</h1>
        <p class="subtitle">{{ t('settings.activitySettings.subtitle') }}</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>{{ t('settings.activitySettings.loading') }}</p>
    </div>

    <!-- Settings Form -->
    <div v-else class="settings-form">

      <!-- Abschnitt 1: Aktivität (Einzeltag) -->
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon activity">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            </svg>
          </div>
          <div>
            <h3>{{ t('settings.activitySettings.sections.singleDay.title') }}</h3>
            <p>{{ t('settings.activitySettings.sections.singleDay.description') }}</p>
          </div>
        </div>
        <div class="setting-fields">
          <div class="field-row">
            <div class="field-group">
              <label>{{ t('settings.activitySettings.fields.defaultStart') }}</label>
              <input v-model="form.defaultTimeStart" type="time" step="900" class="form-input" />
              <span class="field-hint">{{ t('settings.activitySettings.hints.defaultStart') }}</span>
            </div>
            <div class="field-group">
              <label>{{ t('settings.activitySettings.fields.defaultEnd') }}</label>
              <input v-model="form.defaultTimeEnd" type="time" step="900" class="form-input" />
              <span class="field-hint">{{ t('settings.activitySettings.hints.defaultEnd') }}</span>
            </div>
          </div>
          <div class="time-preview">
            <span class="preview-label">{{ t('settings.activitySettings.preview.label') }}</span>
            <span class="preview-value">{{ t('settings.activitySettings.preview.nextSaturday', { start: form.defaultTimeStart, end: form.defaultTimeEnd }) }}</span>
          </div>
        </div>
      </div>

      <!-- Abschnitt 2: Material-Vorlauf/Nachlauf für Aktivitäten -->
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon material">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <div>
            <h3>{{ t('settings.activitySettings.sections.materialMinutes.title') }}</h3>
            <p>{{ t('settings.activitySettings.sections.materialMinutes.description') }}</p>
          </div>
        </div>
        <div class="setting-fields">
          <div class="field-row">
            <div class="field-group">
              <label>{{ t('settings.activitySettings.fields.materialLeadMinutes') }}</label>
              <div class="input-with-suffix">
                <input v-model.number="form.materialLeadMinutes" type="number" min="0" max="480" step="15" class="form-input" />
                <span class="input-suffix">{{ t('settings.activitySettings.units.minutes') }}</span>
              </div>
              <span class="field-hint">{{ t('settings.activitySettings.hints.materialLeadMinutes') }}</span>
            </div>
            <div class="field-group">
              <label>{{ t('settings.activitySettings.fields.materialLagMinutes') }}</label>
              <div class="input-with-suffix">
                <input v-model.number="form.materialLagMinutes" type="number" min="0" max="480" step="15" class="form-input" />
                <span class="input-suffix">{{ t('settings.activitySettings.units.minutes') }}</span>
              </div>
              <span class="field-hint">{{ t('settings.activitySettings.hints.materialLagMinutes') }}</span>
            </div>
          </div>
          <div class="time-preview">
            <span class="preview-label">{{ t('settings.activitySettings.preview.example') }}</span>
            <span class="preview-value">
              {{ t('settings.activitySettings.preview.materialWindow', { start: form.defaultTimeStart, end: form.defaultTimeEnd, lead: computeLeadTime, lag: computeLagTime }) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Abschnitt 3: Material-Vorlauf/Nachlauf für Lager/Events -->
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon camp">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
              <rect x="1" y="3" width="15" height="13" rx="2" ry="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
          </div>
          <div>
            <h3>{{ t('settings.activitySettings.sections.materialDays.title') }}</h3>
            <p>{{ t('settings.activitySettings.sections.materialDays.description') }}</p>
          </div>
        </div>
        <div class="setting-fields">
          <div class="field-row">
            <div class="field-group">
              <label>{{ t('settings.activitySettings.fields.campLeadDays') }}</label>
              <div class="input-with-suffix">
                <input v-model.number="form.campMaterialLeadDays" type="number" min="0" max="14" step="1" class="form-input" />
                <span class="input-suffix">{{ t('settings.activitySettings.units.daysBefore') }}</span>
              </div>
              <span class="field-hint">{{ t('settings.activitySettings.hints.campLeadDays') }}</span>
            </div>
            <div class="field-group">
              <label>{{ t('settings.activitySettings.fields.campLagDays') }}</label>
              <div class="input-with-suffix">
                <input v-model.number="form.campMaterialLagDays" type="number" min="0" max="14" step="1" class="form-input" />
                <span class="input-suffix">{{ t('settings.activitySettings.units.daysAfter') }}</span>
              </div>
              <span class="field-hint">{{ t('settings.activitySettings.hints.campLagDays') }}</span>
            </div>
          </div>
          <div class="time-preview">
            <span class="preview-label">{{ t('settings.activitySettings.preview.example') }}</span>
            <span class="preview-value">
              {{ t('settings.activitySettings.preview.campWindow', { lead: computeCampLeadExample, lag: computeCampLagExample }) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="save-bar">
        <div v-if="hasChanges" class="unsaved-hint">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          {{ t('settings.activitySettings.unsavedChanges') }}
        </div>
        <div class="save-actions">
          <button class="btn-secondary" @click="resetForm" :disabled="!hasChanges">
            {{ t('settings.activitySettings.reset') }}
          </button>
          <button class="btn-primary" @click="saveSettings" :disabled="!hasChanges || isSaving">
            {{ isSaving ? t('settings.activitySettings.saving') : t('common.save') }}
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { getActivityDefaults, saveActivityDefaults, type ActivityDefaults } from '@/api/departmentSettings'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const departmentId = computed(() => route.params.departmentId as string)

const isLoading = ref(true)
const isSaving = ref(false)

// Gespeicherte Werte (für dirty-check)
const savedForm = ref<ActivityDefaults>({
  defaultTimeStart: '14:00',
  defaultTimeEnd: '17:00',
  materialLeadMinutes: 60,
  materialLagMinutes: 60,
  campMaterialLeadDays: 1,
  campMaterialLagDays: 1,
})

// Aktuelle Form-Werte
const form = reactive<ActivityDefaults>({
  defaultTimeStart: '14:00',
  defaultTimeEnd: '17:00',
  materialLeadMinutes: 60,
  materialLagMinutes: 60,
  campMaterialLeadDays: 1,
  campMaterialLagDays: 1,
})

// Dirty-Check
const hasChanges = computed(() => {
  return form.defaultTimeStart !== savedForm.value.defaultTimeStart ||
    form.defaultTimeEnd !== savedForm.value.defaultTimeEnd ||
    form.materialLeadMinutes !== savedForm.value.materialLeadMinutes ||
    form.materialLagMinutes !== savedForm.value.materialLagMinutes ||
    form.campMaterialLeadDays !== savedForm.value.campMaterialLeadDays ||
    form.campMaterialLagDays !== savedForm.value.campMaterialLagDays
})

// Berechnete Vorschau: Material-Zeiten für Aktivität
const computeLeadTime = computed(() => {
  const [h, m] = form.defaultTimeStart.split(':').map(Number)
  const totalMin = h * 60 + m - form.materialLeadMinutes
  const hh = Math.floor(Math.abs(totalMin) / 60)
  const mm = Math.abs(totalMin) % 60
  return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`
})

const computeLagTime = computed(() => {
  const [h, m] = form.defaultTimeEnd.split(':').map(Number)
  const totalMin = h * 60 + m + form.materialLagMinutes
  const hh = Math.floor(totalMin / 60) % 24
  const mm = totalMin % 60
  return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`
})

// Berechnete Vorschau: Camp/Event Material
const computeCampLeadExample = computed(() => {
  const day = 15 - form.campMaterialLeadDays
  return `${String(day).padStart(2, '0')}.03.`
})

const computeCampLagExample = computed(() => {
  const day = 22 + form.campMaterialLagDays
  return `${String(day).padStart(2, '0')}.03.`
})

// Daten laden
async function loadSettings() {
  isLoading.value = true
  try {
    const defaults = await getActivityDefaults(departmentId.value)
    Object.assign(form, defaults)
    savedForm.value = { ...defaults }
  } catch (err) {
    console.error('Fehler beim Laden der Settings:', err)
  } finally {
    isLoading.value = false
  }
}

// Speichern
async function saveSettings() {
  isSaving.value = true
  try {
    await saveActivityDefaults(departmentId.value, { ...form })
    savedForm.value = { ...form }
    toast.success(t('settings.activitySettings.toastSaved'))
  } catch (err) {
    console.error('Fehler beim Speichern:', err)
    toast.error(t('settings.activitySettings.toastSaveError'))
  } finally {
    isSaving.value = false
  }
}

// Zurücksetzen
function resetForm() {
  Object.assign(form, savedForm.value)
}

onMounted(() => {
  loadSettings()
})
</script>

<style scoped>
.activity-settings {
  min-height: 500px;
}

.settings-header {
  margin-bottom: 32px;
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

/* Settings Section */
.settings-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
}

.section-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 20px;
}

.section-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  flex-shrink: 0;
}

.section-icon.activity { background: #dbeafe; color: #2563eb; }
.section-icon.material { background: #fef3c7; color: #d97706; }
.section-icon.camp { background: #d1fae5; color: #059669; }


.section-header h3 {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.section-header p {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

/* Fields */
.setting-fields {
  padding-left: 54px; /* icon + gap */
}

.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 12px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-group label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

/* Form input base uses shared ui/forms.css */

.input-with-suffix {
  display: flex;
  align-items: center;
  gap: 8px;
}

.input-with-suffix .form-input {
  width: 100px;
  text-align: center;
}

.input-suffix {
  font-size: 13px;
  color: #6b7280;
  white-space: nowrap;
}

.field-hint {
  font-size: 12px;
  color: #9ca3af;
}

/* Time Preview */
.time-preview {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 8px;
  font-size: 13px;
  margin-top: 4px;
}

.preview-label {
  font-weight: 500;
  color: #0369a1;
}

.preview-value {
  color: #0c4a6e;
}

/* Save Bar */
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

/* Buttons use shared ui/buttons.css */

/* Success Message */
.success-message {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  color: #166534;
  font-size: 14px;
  font-weight: 500;
  margin-top: 12px;
}

/* Loading uses shared ui/states.css */

/* Transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
