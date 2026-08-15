<template>
  <div class="general-settings" :class="{ 'general-settings--embedded': embedded }">
    <div v-if="!embedded" class="settings-header">
      <div>
        <h1>{{ t('settings.generalSettings.title') }}</h1>
        <p class="subtitle">{{ t('settings.generalSettings.subtitle') }}</p>
      </div>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="inline"
      :message="t('settings.generalSettings.loading')"
    />

    <!-- Settings Form -->
    <div v-else class="settings-form">

      <!-- Abschnitt: Zeitzone -->
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon timezone">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
              <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
          </div>
          <div>
            <h3>{{ t('settings.generalSettings.sections.timezone.title') }}</h3>
            <p>{{ t('settings.generalSettings.sections.timezone.description') }}</p>
          </div>
        </div>
        <div class="setting-fields">
          <div class="field-row">
            <div class="field-group field-wide">
              <label>{{ t('settings.generalSettings.fields.timezone') }}</label>
              <ESelect
                v-model="form.timezone"
                :items="timezoneSelectItems"
                :label="t('settings.generalSettings.fields.timezone')"
                hide-details="auto"
              />
              <span class="field-hint">{{ t('settings.generalSettings.hints.timezone') }}</span>
            </div>
          </div>

          <!-- Aktuelle-Zeit-Vorschau -->
          <div class="time-preview">
            <span class="preview-label">{{ t('settings.generalSettings.preview.currentTime') }}</span>
            <span class="preview-value">{{ currentTimePreview }}</span>
          </div>
        </div>
      </div>

      <!-- Abschnitt: Zeitnotation -->
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon format">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <div>
            <h3>{{ t('settings.generalSettings.sections.format.title') }}</h3>
            <p>{{ t('settings.generalSettings.sections.format.description') }}</p>
          </div>
        </div>
        <div class="setting-fields">
          <div class="field-row">
            <div class="field-group">
              <label>{{ t('settings.generalSettings.fields.dateFormat') }}</label>
              <ESelect
                v-model="form.dateFormat"
                :items="dateFormatSelectItems"
                :label="t('settings.generalSettings.fields.dateFormat')"
                hide-details="auto"
              />
              <span class="field-hint">{{ t('settings.generalSettings.hints.dateFormat') }}</span>
            </div>
            <div class="field-group">
              <label>{{ t('settings.generalSettings.fields.timeFormat') }}</label>
              <ESelect
                v-model="form.timeFormat"
                :items="timeFormatSelectItems"
                :label="t('settings.generalSettings.fields.timeFormat')"
                hide-details="auto"
              />
              <span class="field-hint">{{ t('settings.generalSettings.hints.timeFormat') }}</span>
            </div>
          </div>
          <div class="time-preview">
            <span class="preview-label">{{ t('settings.generalSettings.preview.label') }}</span>
            <span class="preview-value">{{ dateFormatPreview }}, {{ timeFormatPreview }}</span>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="save-bar">
        <div v-if="hasChanges" class="unsaved-hint">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          {{ t('settings.generalSettings.unsavedChanges') }}
        </div>
        <div class="save-actions">
          <EButton variant="secondary" :disabled="!hasChanges" @click="resetForm">
            {{ t('settings.generalSettings.reset') }}
          </EButton>
          <EButton variant="primary" :disabled="!hasChanges || isSaving" :loading="isSaving" @click="saveSettings">
            {{ isSaving ? t('settings.generalSettings.saving') : t('common.save') }}
          </EButton>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { getGeneralSettings, saveGeneralSettings, type GeneralSettings } from '@/api/departmentSettings'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ESelect } from '@/components/form/base'

withDefaults(
  defineProps<{
    embedded?: boolean
  }>(),
  { embedded: false },
)

const timezoneSelectItems = [
  { title: 'Europe/Zurich (CET/CEST)', value: 'Europe/Zurich' },
  { title: 'Europe/Berlin (CET/CEST)', value: 'Europe/Berlin' },
  { title: 'Europe/Vienna (CET/CEST)', value: 'Europe/Vienna' },
  { title: 'Europe/Paris (CET/CEST)', value: 'Europe/Paris' },
  { title: 'Europe/Rome (CET/CEST)', value: 'Europe/Rome' },
  { title: 'Europe/London (GMT/BST)', value: 'Europe/London' },
  { title: 'Europe/Amsterdam (CET/CEST)', value: 'Europe/Amsterdam' },
  { title: 'Europe/Brussels (CET/CEST)', value: 'Europe/Brussels' },
  { title: 'Europe/Madrid (CET/CEST)', value: 'Europe/Madrid' },
  { title: 'Europe/Stockholm (CET/CEST)', value: 'Europe/Stockholm' },
  { title: 'Europe/Warsaw (CET/CEST)', value: 'Europe/Warsaw' },
  { title: 'Europe/Prague (CET/CEST)', value: 'Europe/Prague' },
  { title: 'Europe/Budapest (CET/CEST)', value: 'Europe/Budapest' },
  { title: 'Europe/Athens (EET/EEST)', value: 'Europe/Athens' },
  { title: 'Europe/Helsinki (EET/EEST)', value: 'Europe/Helsinki' },
  { title: 'Europe/Moscow (MSK)', value: 'Europe/Moscow' },
  { title: 'UTC', value: 'UTC' },
  { title: 'US/Eastern (EST/EDT)', value: 'US/Eastern' },
  { title: 'US/Central (CST/CDT)', value: 'US/Central' },
  { title: 'US/Pacific (PST/PDT)', value: 'US/Pacific' },
  { title: 'Asia/Tokyo (JST)', value: 'Asia/Tokyo' },
  { title: 'Asia/Shanghai (CST)', value: 'Asia/Shanghai' },
  { title: 'Australia/Sydney (AEST/AEDT)', value: 'Australia/Sydney' },
]

const dateFormatSelectItems = [
  { title: '10.02.2026 (dd.MM.yyyy)', value: 'dd.MM.yyyy' },
  { title: '10/02/2026 (dd/MM/yyyy)', value: 'dd/MM/yyyy' },
  { title: '2026-02-10 (yyyy-MM-dd)', value: 'yyyy-MM-dd' },
  { title: '02/10/2026 (MM/dd/yyyy)', value: 'MM/dd/yyyy' },
]

const timeFormatSelectItems = [
  { title: '14:30 (24-Stunden)', value: 'HH:mm' },
  { title: '02:30 PM (12-Stunden)', value: 'hh:mm a' },
]

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const departmentId = computed(() => route.params.departmentId as string)

const isLoading = ref(true)
const isSaving = ref(false)

// Gespeicherte Werte (für dirty-check)
const savedForm = ref<GeneralSettings>({
  timezone: 'Europe/Zurich',
  dateFormat: 'dd.MM.yyyy',
  timeFormat: 'HH:mm',
})

// Aktuelle Form-Werte
const form = reactive<GeneralSettings>({
  timezone: 'Europe/Zurich',
  dateFormat: 'dd.MM.yyyy',
  timeFormat: 'HH:mm',
})

// Dirty-Check
const hasChanges = computed(() => {
  return form.timezone !== savedForm.value.timezone ||
    form.dateFormat !== savedForm.value.dateFormat ||
    form.timeFormat !== savedForm.value.timeFormat
})

// Live-Vorschau der aktuellen Zeit
const now = ref(new Date())
let clockInterval: ReturnType<typeof setInterval> | null = null

onMounted(async () => {
  clockInterval = setInterval(() => { now.value = new Date() }, 1000)
  await loadSettings()
})

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval)
})

const currentTimePreview = computed(() => {
  try {
    return now.value.toLocaleString('de-CH', {
      timeZone: form.timezone,
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit',
    })
  } catch {
    return now.value.toLocaleString('de-CH')
  }
})

const dateFormatPreview = computed(() => {
  const d = now.value
  const pad = (n: number) => String(n).padStart(2, '0')
  const day = pad(d.getDate())
  const month = pad(d.getMonth() + 1)
  const year = d.getFullYear()

  switch (form.dateFormat) {
    case 'dd.MM.yyyy': return `${day}.${month}.${year}`
    case 'dd/MM/yyyy': return `${day}/${month}/${year}`
    case 'yyyy-MM-dd': return `${year}-${month}-${day}`
    case 'MM/dd/yyyy': return `${month}/${day}/${year}`
    default: return `${day}.${month}.${year}`
  }
})

const timeFormatPreview = computed(() => {
  const d = now.value
  if (form.timeFormat === 'hh:mm a') {
    const h = d.getHours() % 12 || 12
    const m = String(d.getMinutes()).padStart(2, '0')
    const ampm = d.getHours() >= 12 ? 'PM' : 'AM'
    return `${String(h).padStart(2, '0')}:${m} ${ampm}`
  }
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
})

async function loadSettings() {
  isLoading.value = true
  try {
    const data = await getGeneralSettings(departmentId.value)
    Object.assign(form, data)
    savedForm.value = { ...data }
  } catch (err) {
    console.error('Fehler beim Laden der Einstellungen:', err)
  } finally {
    isLoading.value = false
  }
}

function resetForm() {
  Object.assign(form, savedForm.value)
}

async function saveSettings() {
  isSaving.value = true
  try {
    await saveGeneralSettings(departmentId.value, { ...form })
    savedForm.value = { ...form }
    toast.success(t('settings.generalSettings.toastSaved'))
  } catch (err) {
    console.error('Fehler beim Speichern:', err)
    toast.error(t('settings.generalSettings.toastSaveError'))
  } finally {
    isSaving.value = false
  }
}
</script>

<style scoped>
.general-settings {
  padding: 0;
}

.settings-header h1 {
  font-size: 24px;
  font-weight: 600;
  margin-bottom: 4px;
  color: #1e293b;
}

.subtitle {
  color: #64748b;
  margin-bottom: 32px;
}

/* Loading uses shared ui/states.css */

/* Sections */
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
  gap: 16px;
  margin-bottom: 20px;
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

.section-icon.timezone {
  background: #dbeafe;
  color: #2563eb;
}

.section-icon.format {
  background: #fef3c7;
  color: #d97706;
}

.section-header h3 {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 2px;
}

.section-header p {
  font-size: 13px;
  color: #64748b;
}

.setting-fields {
  padding-left: 56px;
}

.field-row {
  display: flex;
  gap: 20px;
  margin-bottom: 16px;
}

.field-group {
  flex: 1;
}

.field-group.field-wide {
  flex: 2;
}

.field-group label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

/* Form input base uses shared ui/forms.css */

.field-hint {
  display: block;
  font-size: 12px;
  color: #94a3b8;
  margin-top: 4px;
}

/* Vorschau */
.time-preview {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
}

.preview-label {
  color: #64748b;
  margin-right: 8px;
}

.preview-value {
  color: #1e293b;
  font-weight: 500;
}

/* Save Bar */
.save-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 0;
  margin-top: 8px;
}

.unsaved-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #d97706;
  font-size: 13px;
  font-weight: 500;
}

.save-actions {
  display: flex;
  gap: 10px;
}

/* Buttons use shared ui/buttons.css */

/* Success */
.success-message {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 8px;
  color: #166534;
  font-size: 14px;
  font-weight: 500;
  margin-top: 12px;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

@media (max-width: 600px) {
  .field-row {
    flex-direction: column;
  }
  .setting-fields {
    padding-left: 0;
  }
}
</style>
