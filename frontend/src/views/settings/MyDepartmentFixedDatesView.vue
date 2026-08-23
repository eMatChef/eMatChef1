<template>
  <div class="fixed-dates-settings">
    <div class="page-header">
      <h2 class="settings-title">{{ t('settings.fixedDates.title') }}</h2>
      <p class="settings-description">{{ pageDescription }}</p>
      <p v-if="isGrossanlassDept" class="settings-hint">{{ t('settings.fixedDates.grossanlassHint') }}</p>
    </div>

    <ECard v-if="!canManage">
      <p class="muted">{{ t('settings.fixedDates.noPermission') }}</p>
    </ECard>

    <template v-else>
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
            :department-id="departmentId"
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
            class="field-name"
            :label="t('settings.fixedDates.name')"
            :placeholder="namePlaceholder"
            maxlength="120"
            hide-details
          />
        </div>
        <p class="type-hint muted">{{ quickSelectHint }}</p>
        <div class="form-actions">
          <EButton v-if="editingId" variant="secondary" :disabled="saving" @click="cancelEdit">
            {{ t('common.cancel') }}
          </EButton>
          <EButton variant="primary" :disabled="saving || !canSubmit" :loading="saving" @click="submitForm">
            {{ saving ? t('common.saving') : (editingId ? t('common.save') : t('common.create')) }}
          </EButton>
        </div>
      </ECard>

      <ELoadingState v-if="loading" variant="inline" :message="t('settings.fixedDates.loading')" />

      <ECard v-else-if="periods.length === 0" class="empty-card">
        <p class="empty-title">{{ emptyTitle }}</p>
        <p v-if="emptyDescription" class="muted">{{ emptyDescription }}</p>
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
            <tr v-for="row in periods" :key="row.id" :class="{ 'period-row--active': editingId === row.id }">
              <td>{{ formatDisplayDate(row.start_date) }}</td>
              <td>{{ formatDisplayDate(row.end_date) }}</td>
              <td>
                <span class="type-tag" :class="'type-tag--' + row.label">{{ labelText(row.label) }}</span>
              </td>
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
import { computed, reactive, ref, watch } from 'vue'
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
  GROSSANLASS_TIME_MODULE_LABELS,
  type CalendarPeriodLabel,
  type DepartmentCalendarPeriod,
} from '@/api/calendarPeriods'

const GROSSANLASS_LABELS: CalendarPeriodLabel[] = [
  ...GROSSANLASS_TIME_MODULE_LABELS,
  'other',
  'department_break',
]
const MATERIAL_LABELS: CalendarPeriodLabel[] = ['school_vacation', 'department_break', 'camp_week', 'other']

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const { t, locale } = useI18n()
const { canManageMaterials } = useDepartmentMemberRole()

const canManage = canManageMaterials

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)

const isGrossanlassDept = computed(() => authStore.isDepartmentGrossanlass(departmentId.value))

const pageDescription = computed(() =>
  isGrossanlassDept.value
    ? t('settings.fixedDates.descriptionGrossanlass')
    : t('settings.fixedDates.description'),
)

const quickSelectHint = computed(() =>
  isGrossanlassDept.value
    ? t('settings.fixedDates.quickSelectHintGrossanlass')
    : t('settings.fixedDates.quickSelectHint'),
)

const namePlaceholder = computed(() =>
  isGrossanlassDept.value
    ? t('settings.fixedDates.namePlaceholderGrossanlass')
    : t('settings.fixedDates.namePlaceholder'),
)

const emptyTitle = computed(() =>
  isGrossanlassDept.value
    ? t('settings.fixedDates.emptyGrossanlassTitle')
    : t('settings.fixedDates.empty'),
)

const emptyDescription = computed(() =>
  isGrossanlassDept.value ? t('settings.fixedDates.emptyGrossanlassDescription') : '',
)

const periods = ref<DepartmentCalendarPeriod[]>([])
const loading = ref(false)
const saving = ref(false)
const editingId = ref<string | null>(null)

const defaultLabel = (): CalendarPeriodLabel =>
  isGrossanlassDept.value ? 'grossanlass' : 'school_vacation'

const emptyForm = () => ({
  label: defaultLabel(),
  name: isGrossanlassDept.value ? labelText(defaultLabel()) : '',
  start_date: '',
  end_date: '',
})

const form = reactive(emptyForm())

const labelOptions = computed(() => {
  const labels = isGrossanlassDept.value ? GROSSANLASS_LABELS : MATERIAL_LABELS
  return labels.map((value) => ({
    value,
    text: labelText(value),
  }))
})

function labelText(label: CalendarPeriodLabel): string {
  return t(`settings.fixedDates.labels.${label}`)
}

watch(
  () => form.label,
  (next, prev) => {
    if (!isGrossanlassDept.value) return
    const prevDefault = prev ? labelText(prev) : ''
    if (!form.name.trim() || form.name.trim() === prevDefault) {
      form.name = labelText(next)
    }
  },
)

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
  form.name = row.name
  form.start_date = row.start_date
  form.end_date = row.end_date
  form.label = row.label
}

function cancelEdit() {
  resetForm()
}

async function submitForm() {
  if (!departmentId.value || !canSubmit.value) return
  saving.value = true
  const payload = {
    label: form.label,
    name: form.name.trim(),
    start_date: form.start_date,
    end_date: form.end_date,
  }
  try {
    if (editingId.value) {
      await updateDepartmentCalendarPeriod(departmentId.value, editingId.value, payload)
      toast.success(t('settings.fixedDates.toastUpdated'))
    } else {
      await createDepartmentCalendarPeriod(departmentId.value, payload)
      toast.success(t('settings.fixedDates.toastCreated'))
    }
    resetForm()
    await loadPeriods(departmentId.value)
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
  if (!departmentId.value) return
  const ok = await confirm.confirm({
    title: t('settings.fixedDates.confirmDeleteTitle'),
    message: t('settings.fixedDates.confirmDeleteMessage'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteDepartmentCalendarPeriod(departmentId.value, id)
    if (editingId.value === id) resetForm()
    await loadPeriods(departmentId.value)
    toast.success(t('settings.fixedDates.toastDeleted'))
  } catch (err: unknown) {
    const msg =
      err && typeof err === 'object' && 'response' in err
        ? (err as { response?: { data?: { error?: string } } }).response?.data?.error
        : undefined
    toast.error(msg || t('settings.fixedDates.toastDeleteError'))
  }
}

watch(
  departmentId,
  (deptId) => {
    if (!deptId || !canManage.value) return
    resetForm()
    void loadPeriods(deptId)
  },
  { immediate: true },
)
</script>

<style scoped>
.fixed-dates-settings {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 960px;
  padding: 4px 8px 16px;
  box-sizing: border-box;
}

.page-header {
  padding: 0 4px;
}

.settings-title {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
  color: var(--color-text, #111827);
}

.settings-description,
.settings-hint,
.muted {
  color: var(--color-text-muted, #6b7280);
  margin: 0;
}

.settings-description {
  margin-top: 6px;
}

.settings-hint {
  margin-top: 10px;
  font-size: 0.88rem;
  padding: 8px 12px;
  background: #eff6ff;
  border-radius: 8px;
  border: 1px solid #bfdbfe;
  color: #1e40af;
}

.create-card,
.table-card,
.empty-card {
  padding: 4px;
}

.section-heading {
  margin: 0 0 14px;
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text, #111827);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;
}

.field-period {
  grid-column: 1 / -1;
}

.field-name {
  grid-column: 1 / -1;
}

@media (min-width: 640px) {
  .form-grid {
    grid-template-columns: minmax(180px, 220px) minmax(0, 1fr);
  }

  .field-period {
    grid-column: 1 / -1;
  }

  .field-name {
    grid-column: 2;
  }
}

.type-hint {
  margin: 12px 0 0;
  font-size: 0.82rem;
}

.form-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 14px;
}

.empty-card {
  text-align: center;
  padding: 24px 16px;
}

.empty-title {
  margin: 0 0 6px;
  font-weight: 500;
  color: var(--color-text, #111827);
}

.period-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.period-table th {
  text-align: left;
  padding: 8px 10px;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  color: var(--color-text-muted, #6b7280);
  font-weight: 500;
}

.period-table td {
  padding: 10px;
  border-bottom: 1px solid var(--color-border, #f3f4f6);
  vertical-align: middle;
}

.period-row--active td {
  background: #fffbeb;
}

.type-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 500;
  background: #f3f4f6;
  color: #374151;
}

.type-tag--camp_week {
  background: #d1fae5;
  color: #065f46;
}

.type-tag--other {
  background: #e0e7ff;
  color: #3730a3;
}

.type-tag--grossanlass {
  background: #dbeafe;
  color: #1d4ed8;
}

.type-tag--aufbau {
  background: #ffedd5;
  color: #9a3412;
}

.type-tag--abbau {
  background: #fae8ff;
  color: #86198f;
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
</style>
