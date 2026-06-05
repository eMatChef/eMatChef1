<template>
  <div class="fixed-dates-settings">
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.fixedDates.title') }}</h2>
        <p class="settings-description">{{ t('settings.fixedDates.description') }}</p>
      </div>
    </div>

    <ECard v-if="userDepartments.length > 1" class="department-card">
      <ESelect
        id="department-select"
        v-model="selectedDepartmentId"
        :label="t('settings.common.selectDepartment')"
        :items="departmentSelectItems"
        item-title="text"
        item-value="value"
        hide-details
        @update:model-value="onDepartmentChange"
      />
    </ECard>

    <template v-if="canManage">
      <ECard class="create-card">
        <h3 class="section-heading">
          {{ editingId ? t('settings.fixedDates.editTitle') : t('settings.fixedDates.createTitle') }}
        </h3>
        <div class="form-grid">
          <EDateRangeField
            id="fd-period"
            v-model:start="form.start_date"
            v-model:end="form.end_date"
            class="field-period"
            :label="t('settings.fixedDates.period')"
            :department-id="selectedDepartmentId"
            :allow-past="true"
            :block-closed-dates="false"
            :show-presets="false"
            :show-markers="true"
          />
          <ESelect
            id="fd-label"
            v-model="form.label"
            :label="t('settings.fixedDates.typeLabel')"
            :items="labelOptions"
            item-title="text"
            item-value="value"
            hide-details
          />
          <ETextField
            id="fd-name"
            v-model="form.name"
            class="field-wide"
            :label="t('settings.fixedDates.name')"
            :placeholder="t('settings.fixedDates.namePlaceholder')"
            maxlength="120"
            hide-details
          />
        </div>
        <div class="form-actions">
          <EButton v-if="editingId" variant="secondary" :disabled="saving" @click="cancelEdit">
            {{ t('common.cancel') }}
          </EButton>
          <EButton variant="primary" :disabled="saving || !canSubmit" @click="submitForm">
            {{ saving ? t('common.saving') : (editingId ? t('common.save') : t('common.create')) }}
          </EButton>
        </div>
      </ECard>

      <ELoadingState v-if="loading" variant="inline" :message="t('settings.fixedDates.loading')" />

      <ECard v-else-if="periods.length === 0">
        <p class="muted">{{ t('settings.fixedDates.empty') }}</p>
      </ECard>

      <ECard v-else class="table-card">
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
                <button type="button" class="btn-link danger" @click="removePeriod(row.id)">
                  {{ t('common.delete') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </ECard>
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
  EButton,
  ECard,
  EDateRangeField,
  ESelect,
  ETextField,
} from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
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

const departmentSelectItems = computed(() =>
  userDepartments.value.map((dept) => ({
    text: dept.department?.name || dept.department_id,
    value: dept.department_id,
  })),
)

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
.fixed-dates-settings {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.settings-title {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
  color: var(--color-text, #111827);
}

.settings-description,
.muted {
  color: var(--color-text-muted, #6b7280);
  margin: 0;
}

.section-heading {
  margin: 0 0 12px;
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text, #111827);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 4px 12px;
}

.field-period,
.field-wide {
  grid-column: 1 / -1;
}

.form-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
}

.period-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.period-table th {
  text-align: left;
  padding: 8px;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  color: var(--color-text-muted, #6b7280);
  font-weight: 500;
}

.period-table td {
  padding: 10px 8px;
  border-bottom: 1px solid var(--color-border, #f3f4f6);
  vertical-align: middle;
}

.actions {
  white-space: nowrap;
  text-align: right;
}

.btn-link {
  border: none;
  background: none;
  color: var(--color-primary, #059669);
  cursor: pointer;
  padding: 0 6px;
  font-size: 14px;
}

.btn-link.danger {
  color: var(--color-error, #dc2626);
}

.department-card {
  max-width: 400px;
}
</style>
