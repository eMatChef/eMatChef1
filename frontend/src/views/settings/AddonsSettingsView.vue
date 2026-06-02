<template>
  <div class="addons-settings">
    <div class="header-section">
      <div>
        <h1>{{ t('settings.addons.title') }}</h1>
        <p class="description">{{ t('settings.addons.description') }}</p>
      </div>
    </div>

    <div v-if="userDepartments.length > 1" class="department-selector">
      <label for="department-select" class="selector-label">{{ t('settings.common.selectDepartment') }}:</label>
      <ESelect
        id="department-select"
        v-model="selectedDepartmentId"
        :items="departmentSelectItems"
        :label="t('settings.common.selectDepartment')"
        hide-details
        @update:model-value="onDepartmentChange"
      />
    </div>

    <div v-if="!canManageJoinCode" class="info-card">
      <p class="muted">{{ t('settings.addons.noPermission') }}</p>
    </div>

    <div v-else class="info-card">
      <div class="card-header">
        <h2>{{ t('settings.addons.calendarTitle') }}</h2>
      </div>
      <p class="muted">
        {{ t('settings.addons.calendarDescription') }}
      </p>
      <div class="field-row">
        <ETextField
          id="dept-fcal-geo"
          v-model="calendarFcalGeoId"
          inputmode="numeric"
          :label="t('settings.addons.geoIdLabel')"
          :placeholder="t('settings.addons.geoIdPlaceholder')"
          hide-details="auto"
        />
      </div>
      <EButton
        variant="primary"
        :disabled="!calendarDirty || isSavingCalendar"
        :loading="isSavingCalendar"
        @click="saveCalendarSettingsForDept"
      >
        {{ isSavingCalendar ? t('common.saving') : t('settings.addons.saveCalendar') }}
      </EButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { getCalendarSettings, saveCalendarSettings as saveCalendarSettingsApi } from '@/api/departmentSettings'
import { EButton, ETextField, ESelect } from '@/components/form/base'

const departmentSelectItems = computed(() =>
  userDepartments.value.map((dept) => ({
    title: dept.department?.name || dept.department_id,
    value: dept.department_id,
  })),
)

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const { t } = useI18n()

const selectedDepartmentId = ref<string | null>(null)
const calendarFcalGeoId = ref('')
const savedCalendarGeoId = ref('')
const isSavingCalendar = ref(false)

const userDepartments = computed(() => authStore.departments || [])
const currentRole = computed(() => {
  if (!selectedDepartmentId.value) return 'user'
  const dept = userDepartments.value.find((d) => d.department_id === selectedDepartmentId.value)
  return dept?.role || 'user'
})
const canManageJoinCode = computed(() => {
  const normalizedRole = String(currentRole.value || '').toLowerCase().trim()
  return ['dc', 'depchef', 'mw', 'matwart', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(normalizedRole)
})
const calendarDirty = computed(() => calendarFcalGeoId.value.trim() !== savedCalendarGeoId.value.trim())

async function loadCalendarSettings(deptId: string) {
  try {
    const c = await getCalendarSettings(deptId)
    calendarFcalGeoId.value = c.fcalGeoId
    savedCalendarGeoId.value = c.fcalGeoId
  } catch {
    calendarFcalGeoId.value = ''
    savedCalendarGeoId.value = ''
  }
}

async function onDepartmentChange() {
  if (!selectedDepartmentId.value) return
  const newDeptId = selectedDepartmentId.value
  await authStore.setActiveDepartment(newDeptId)
  const oldDeptId = route.params.departmentId as string | undefined
  if (oldDeptId && oldDeptId !== newDeptId) {
    const newPath = route.path.replace(`/${oldDeptId}`, `/${newDeptId}`)
    window.location.assign(newPath)
    return
  }
  await loadCalendarSettings(newDeptId)
}

async function saveCalendarSettingsForDept() {
  if (!selectedDepartmentId.value || isSavingCalendar.value || !calendarDirty.value) return
  isSavingCalendar.value = true
  try {
    await saveCalendarSettingsApi(selectedDepartmentId.value, {
      fcalGeoId: calendarFcalGeoId.value,
    })
    savedCalendarGeoId.value = calendarFcalGeoId.value.trim()
    toast.success(t('settings.addons.toastSaved'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.addons.toastSaveError'))
  } finally {
    isSavingCalendar.value = false
  }
}

onMounted(async () => {
  selectedDepartmentId.value = authStore.activeDepartmentId || (userDepartments.value[0]?.department_id ?? null)
  if (selectedDepartmentId.value) {
    await loadCalendarSettings(selectedDepartmentId.value)
  }
})
</script>

<style scoped>
.addons-settings { display: flex; flex-direction: column; gap: 20px; }
.header-section h1 { margin: 0; font-size: 24px; }
.description { margin: 4px 0 0; color: #6b7280; }
.department-selector, .info-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.selector-label, .field-label { display: block; font-size: 13px; color: #6b7280; margin-bottom: 8px; }
.department-select { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 14px; background: #fff; }
.card-header h2 { margin: 0; font-size: 18px; }
.muted { color: #6b7280; font-size: 14px; margin: 0; }
.field-row { margin-top: 14px; }
.save-btn { margin-top: 14px; border: none; border-radius: 8px; background: #2563eb; color: #fff; padding: 10px 14px; cursor: pointer; }
.save-btn:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
