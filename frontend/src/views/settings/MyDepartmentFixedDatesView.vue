<template>
  <div class="settings-page">
    <div class="header">
      <h1>{{ t('settings.fixedDates.title') }}</h1>
      <p class="description">{{ t('settings.fixedDates.description') }}</p>
    </div>

    <div v-if="userDepartments.length > 1" class="card">
      <label class="field-label" for="department-select">{{ t('settings.common.selectDepartment') }}:</label>
      <select id="department-select" v-model="selectedDepartmentId" class="input" @change="onDepartmentChange">
        <option v-for="dept in userDepartments" :key="dept.department_id" :value="dept.department_id">
          {{ dept.department?.name || dept.department_id }}
        </option>
      </select>
    </div>

    <template v-if="canManage">
      <div class="card create-card">
        <h3 class="section-heading">{{ editingId ? t('settings.fixedDates.editTitle') : t('settings.fixedDates.createTitle') }}</h3>
        <div class="form-grid">
          <div class="field">
            <label class="field-label" for="fd-start">{{ t('settings.fixedDates.startDate') }}</label>
            <input id="fd-start" v-model="form.start_date" type="date" class="input" />
          </div>
          <div class="field">
            <label class="field-label" for="fd-end">{{ t('settings.fixedDates.endDate') }}</label>
            <input id="fd-end" v-model="form.end_date" type="date" class="input" />
          </div>
          <div class="field">
            <label class="field-label" for="fd-label">{{ t('settings.fixedDates.typeLabel') }}</label>
            <select id="fd-label" v-model="form.label" class="input">
              <option v-for="opt in labelOptions" :key="opt.value" :value="opt.value">{{ opt.text }}</option>
            </select>
          </div>
          <div class="field field-wide">
            <label class="field-label" for="fd-name">{{ t('settings.fixedDates.name') }}</label>
            <input id="fd-name" v-model="form.name" type="text" class="input" maxlength="120" :placeholder="t('settings.fixedDates.namePlaceholder')" />
          </div>
        </div>
        <div class="form-actions">
          <button v-if="editingId" type="button" class="btn btn-secondary" :disabled="saving" @click="cancelEdit">
            {{ t('common.cancel') }}
          </button>
          <button type="button" class="btn" :disabled="saving || !canSubmit" @click="submitForm">
            {{ saving ? t('common.saving') : (editingId ? t('common.save') : t('common.create')) }}
          </button>
        </div>
      </div>

      <p v-if="loading" class="muted">{{ t('settings.fixedDates.loading') }}</p>

      <div v-else-if="periods.length === 0" class="card">
        <p class="muted">{{ t('settings.fixedDates.empty') }}</p>
      </div>

      <div v-else class="card table-card">
        <table class="period-table">
          <thead>
            <tr>
              <th>{{ t('settings.fixedDates.colFrom') }}</th>
              <th>{{ t('settings.fixedDates.colTo') }}</th>
              <th>{{ t('settings.fixedDates.colType') }}</th>
              <th>{{ t('settings.fixedDates.colName') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in periods" :key="row.id">
              <td>{{ formatDisplayDate(row.start_date) }}</td>
              <td>{{ formatDisplayDate(row.end_date) }}</td>
              <td>{{ labelText(row.label) }}</td>
              <td>{{ row.name }}</td>
              <td class="actions">
                <button type="button" class="btn-link" @click="startEdit(row)">{{ t('common.edit') }}</button>
                <button type="button" class="btn-link danger" @click="removePeriod(row.id)">{{ t('common.delete') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  createDepartmentCalendarPeriod,
  deleteDepartmentCalendarPeriod,
  listDepartmentCalendarPeriods,
  updateDepartmentCalendarPeriod,
  type CalendarPeriodLabel,
  type DepartmentCalendarPeriod,
} from '@/api/calendarPeriods'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const { t, locale } = useI18n()
const { canManageMaterials } = useDepartmentMemberRole()

const canManage = canManageMaterials

const userDepartments = computed(() => authStore.departments || [])
const selectedDepartmentId = ref<string | null>(null)
const periods = ref<DepartmentCalendarPeriod[]>([])
const loading = ref(false)
const saving = ref(false)
const editingId = ref<string | null>(null)

const emptyForm = (): {
  label: CalendarPeriodLabel
  name: string
  start_date: string
  end_date: string
} => ({
  label: 'school_vacation',
  name: '',
  start_date: '',
  end_date: '',
})

const form = reactive(emptyForm())

const labelOptions = computed(() =>
  (['school_vacation', 'department_break', 'camp_week', 'other'] as const).map((value) => ({
    value,
    text: labelText(value),
  })),
)

function labelText(label: CalendarPeriodLabel): string {
  const key = `settings.fixedDates.labels.${label}` as const
  return t(key)
}

function formatDisplayDate(iso: string): string {
  const [y, m, d] = iso.split('-').map((x) => parseInt(x, 10))
  if (!y || !m || !d) return iso
  return new Date(y, m - 1, d).toLocaleDateString(locale.value, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

const canSubmit = computed(
  () =>
    form.name.trim().length > 0 &&
    form.start_date !== '' &&
    form.end_date !== '' &&
    form.start_date <= form.end_date,
)

async function loadPeriods(deptId: string) {
  loading.value = true
  try {
    periods.value = await listDepartmentCalendarPeriods(deptId)
  } catch {
    periods.value = []
    toast.error(t('settings.fixedDates.toastLoadError'))
  } finally {
    loading.value = false
  }
}

function resetForm() {
  Object.assign(form, emptyForm())
  editingId.value = null
}

function startEdit(row: DepartmentCalendarPeriod) {
  editingId.value = row.id
  form.label = row.label
  form.name = row.name
  form.start_date = row.start_date
  form.end_date = row.end_date
}

function cancelEdit() {
  resetForm()
}

async function submitForm() {
  if (!selectedDepartmentId.value || !canSubmit.value) return
  saving.value = true
  const payload = {
    label: form.label,
    name: form.name.trim(),
    start_date: form.start_date,
    end_date: form.end_date,
  }
  try {
    if (editingId.value) {
      await updateDepartmentCalendarPeriod(selectedDepartmentId.value, editingId.value, payload)
      toast.success(t('settings.fixedDates.toastUpdated'))
    } else {
      await createDepartmentCalendarPeriod(selectedDepartmentId.value, payload)
      toast.success(t('settings.fixedDates.toastCreated'))
    }
    resetForm()
    await loadPeriods(selectedDepartmentId.value)
  } catch (err: unknown) {
    const msg =
      err && typeof err === 'object' && 'response' in err
        ? (err as { response?: { data?: { error?: string } } }).response?.data?.error
        : undefined
    toast.error(msg || t('settings.fixedDates.toastSaveError'))
  } finally {
    saving.value = false
  }
}

async function removePeriod(id: string) {
  if (!selectedDepartmentId.value) return
  const ok = await confirm.confirm({
    title: t('settings.fixedDates.confirmDeleteTitle'),
    message: t('settings.fixedDates.confirmDeleteMessage'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteDepartmentCalendarPeriod(selectedDepartmentId.value, id)
    if (editingId.value === id) resetForm()
    await loadPeriods(selectedDepartmentId.value)
    toast.success(t('settings.fixedDates.toastDeleted'))
  } catch (err: unknown) {
    const msg =
      err && typeof err === 'object' && 'response' in err
        ? (err as { response?: { data?: { error?: string } } }).response?.data?.error
        : undefined
    toast.error(msg || t('settings.fixedDates.toastDeleteError'))
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
  resetForm()
  await loadPeriods(newDeptId)
}

onMounted(async () => {
  selectedDepartmentId.value = authStore.activeDepartmentId || (userDepartments.value[0]?.department_id ?? null)
  if (selectedDepartmentId.value && canManage.value) {
    await loadPeriods(selectedDepartmentId.value)
  }
})
</script>

<style scoped>
.settings-page { display: flex; flex-direction: column; gap: 16px; }
.header h1 { margin: 0; font-size: 24px; }
.description, .muted { color: #6b7280; margin: 0; }
.card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.section-heading { margin: 0 0 12px; font-size: 16px; }
.field-label { display: block; margin-bottom: 6px; font-size: 13px; color: #6b7280; }
.input { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; background: #fff; box-sizing: border-box; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.field-wide { grid-column: 1 / -1; }
.form-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px; }
.btn { border: none; border-radius: 8px; background: #2563eb; color: #fff; padding: 8px 14px; cursor: pointer; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #e5e7eb; color: #374151; }
.period-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.period-table th { text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-weight: 500; }
.period-table td { padding: 10px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.actions { white-space: nowrap; text-align: right; }
.btn-link { border: none; background: none; color: #2563eb; cursor: pointer; padding: 0 6px; font-size: 14px; }
.btn-link.danger { color: #dc2626; }
</style>
