<template>
  <div class="fd-manager">
    <div class="fd-toolbar">
      <EButton v-if="canManage" variant="primary" size="small" @click="openCreate">
        {{ t('settings.fixedDates.createTitle') }}
      </EButton>
    </div>

    <ELoadingState v-if="loading" variant="inline" :message="t('settings.fixedDates.loading')" />

    <ECard v-else-if="periods.length === 0" class="empty-card">
      <p class="empty-title">{{ emptyTitle }}</p>
      <p v-if="emptyDescription" class="muted">{{ emptyDescription }}</p>
    </ECard>

    <ECard v-else class="table-card">
      <table class="period-table">
        <thead>
          <tr>
            <th>
              <button type="button" class="th-sort" @click="toggleSort('start')">
                {{ t('settings.fixedDates.colFrom') }}
                <span class="th-sort__mark">{{ sortMark('start') }}</span>
              </button>
            </th>
            <th>
              <button type="button" class="th-sort" @click="toggleSort('end')">
                {{ t('settings.fixedDates.colTo') }}
                <span class="th-sort__mark">{{ sortMark('end') }}</span>
              </button>
            </th>
            <th>
              <button type="button" class="th-sort" @click="toggleSort('type')">
                {{ t('settings.fixedDates.colType') }}
                <span class="th-sort__mark">{{ sortMark('type') }}</span>
              </button>
            </th>
            <th>
              <button type="button" class="th-sort" @click="toggleSort('name')">
                {{ t('settings.fixedDates.colName') }}
                <span class="th-sort__mark">{{ sortMark('name') }}</span>
              </button>
            </th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in sortedPeriods" :key="row.id">
            <td>{{ formatDisplayDateTime(row.start_date, row.start_time, '00:00') }}</td>
            <td>{{ formatDisplayDateTime(row.end_date, row.end_time, '23:59') }}</td>
            <td>
              <span class="type-tag" :class="'type-tag--' + row.label">{{ labelText(row.label) }}</span>
            </td>
            <td>{{ row.name }}</td>
            <td class="actions">
              <button v-if="canManage" type="button" class="btn-link" @click="openEdit(row)">
                {{ t('common.edit') }}
              </button>
              <button v-if="canManage" type="button" class="btn-link danger" @click="removePeriod(row.id)">
                {{ t('common.delete') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </ECard>

    <EDialog
      v-model="showForm"
      :title="editingId ? t('settings.fixedDates.editTitle') : t('settings.fixedDates.createTitle')"
      :max-width="640"
      :retain-focus="false"
      :z-index="2600"
    >
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
        <label class="fd-time-field">
          <span class="fd-time-field__label">{{ t('settings.fixedDates.startTime') }}</span>
          <input
            id="fd-start-time"
            v-model="form.start_time"
            type="time"
            step="60"
            class="fd-time-field__input"
          />
        </label>
        <label class="fd-time-field">
          <span class="fd-time-field__label">{{ t('settings.fixedDates.endTime') }}</span>
          <input
            id="fd-end-time"
            v-model="form.end_time"
            type="time"
            step="60"
            class="fd-time-field__input"
          />
        </label>
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
      <template #actions>
        <EButton variant="secondary" :disabled="saving" @click="closeForm">
          {{ t('common.cancel') }}
        </EButton>
        <EButton variant="primary" :disabled="saving || !canSubmit" :loading="saving" @click="submitForm">
          {{ saving ? t('common.saving') : (editingId ? t('common.save') : t('common.create')) }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { bumpCalendarPeriodsCache } from '@/composables/useCalendarPeriodsCache'
import {
  EButton,
  ECard,
  EDateRangeField,
  EDialog,
  ESelect,
  ETextField,
} from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  calendarPeriodSortStamp,
  calendarPeriodTime,
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

type SortKey = 'start' | 'end' | 'type' | 'name'

const props = defineProps<{
  departmentId: string
}>()

const emit = defineEmits<{
  changed: []
}>()

const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const { t, locale } = useI18n()
const { canManageMaterials } = useDepartmentMemberRole()
const canManage = canManageMaterials

const isGrossanlassDept = computed(() => authStore.isDepartmentGrossanlass(props.departmentId))

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
const showForm = ref(false)
const editingId = ref<string | null>(null)
const sortKey = ref<SortKey>('start')
const sortDir = ref<'asc' | 'desc'>('desc')

const defaultLabel = (): CalendarPeriodLabel =>
  isGrossanlassDept.value ? 'grossanlass' : 'school_vacation'

const emptyForm = () => ({
  label: defaultLabel(),
  name: isGrossanlassDept.value ? labelText(defaultLabel()) : '',
  start_date: '',
  end_date: '',
  start_time: '00:00',
  end_time: '23:59',
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

function formatDisplayDateTime(iso: string, time: string | undefined, fallback: string): string {
  const day = iso.slice(0, 10)
  const [y, m, d] = day.split('-').map((x) => parseInt(x, 10))
  if (!y || !m || !d) return iso
  const clock = calendarPeriodTime(time, fallback)
  const dateText = new Date(y, m - 1, d).toLocaleDateString(locale.value, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
  return `${dateText}, ${clock}`
}

function periodStamp(row: DepartmentCalendarPeriod, which: 'start' | 'end'): string {
  return which === 'start'
    ? calendarPeriodSortStamp(row.start_date, row.start_time, '00:00')
    : calendarPeriodSortStamp(row.end_date, row.end_time, '23:59')
}

const sortedPeriods = computed(() => {
  const dir = sortDir.value === 'asc' ? 1 : -1
  return periods.value.slice().sort((a, b) => {
    let cmp = 0
    if (sortKey.value === 'start') cmp = periodStamp(a, 'start').localeCompare(periodStamp(b, 'start'))
    else if (sortKey.value === 'end') cmp = periodStamp(a, 'end').localeCompare(periodStamp(b, 'end'))
    else if (sortKey.value === 'type') cmp = labelText(a.label).localeCompare(labelText(b.label), locale.value)
    else cmp = a.name.localeCompare(b.name, locale.value)
    if (cmp !== 0) return cmp * dir
    return periodStamp(b, 'start').localeCompare(periodStamp(a, 'start'))
  })
})

function toggleSort(key: SortKey) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    return
  }
  sortKey.value = key
  sortDir.value = key === 'name' || key === 'type' ? 'asc' : 'desc'
}

function sortMark(key: SortKey): string {
  if (sortKey.value !== key) return '↕'
  return sortDir.value === 'asc' ? '↑' : '↓'
}

const canSubmit = computed(() => {
  if (!form.name.trim() || !form.start_date || !form.end_date) return false
  const start = calendarPeriodSortStamp(form.start_date, form.start_time, '00:00')
  const end = calendarPeriodSortStamp(form.end_date, form.end_time, '23:59')
  return start <= end
})

async function loadPeriods() {
  if (!props.departmentId) return
  loading.value = true
  try {
    periods.value = await listDepartmentCalendarPeriods(props.departmentId)
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

function openCreate() {
  resetForm()
  showForm.value = true
}

function openEdit(row: DepartmentCalendarPeriod) {
  editingId.value = row.id
  form.name = row.name
  form.start_date = row.start_date
  form.end_date = row.end_date
  form.start_time = calendarPeriodTime(row.start_time, '00:00')
  form.end_time = calendarPeriodTime(row.end_time, '23:59')
  form.label = row.label
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  resetForm()
}

async function afterMutate() {
  bumpCalendarPeriodsCache()
  emit('changed')
  await loadPeriods()
}

async function submitForm() {
  if (!props.departmentId || !canSubmit.value) return
  saving.value = true
  const payload = {
    label: form.label,
    name: form.name.trim(),
    start_date: form.start_date,
    end_date: form.end_date,
    start_time: calendarPeriodTime(form.start_time, '00:00'),
    end_time: calendarPeriodTime(form.end_time, '23:59'),
  }
  try {
    if (editingId.value) {
      await updateDepartmentCalendarPeriod(props.departmentId, editingId.value, payload)
      toast.success(t('settings.fixedDates.toastUpdated'))
    } else {
      await createDepartmentCalendarPeriod(props.departmentId, payload)
      toast.success(t('settings.fixedDates.toastCreated'))
    }
    closeForm()
    await afterMutate()
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
  if (!props.departmentId) return
  const ok = await confirm.confirm({
    title: t('settings.fixedDates.confirmDeleteTitle'),
    message: t('settings.fixedDates.confirmDeleteMessage'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteDepartmentCalendarPeriod(props.departmentId, id)
    if (editingId.value === id) closeForm()
    toast.success(t('settings.fixedDates.toastDeleted'))
    await afterMutate()
  } catch (err: unknown) {
    const msg =
      err && typeof err === 'object' && 'response' in err
        ? (err as { response?: { data?: { error?: string } } }).response?.data?.error
        : undefined
    toast.error(msg || t('settings.fixedDates.toastDeleteError'))
  }
}

watch(
  () => props.departmentId,
  (deptId) => {
    if (!deptId) return
    resetForm()
    void loadPeriods()
  },
  { immediate: true },
)

defineExpose({ openCreate, reload: loadPeriods })
</script>

<style scoped>
.fd-manager {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.fd-toolbar {
  display: flex;
  justify-content: flex-end;
}

.muted {
  color: var(--color-text-muted, #6b7280);
  margin: 0;
}

.create-card,
.table-card,
.empty-card {
  padding: 4px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;
}

.field-period,
.field-name {
  grid-column: 1 / -1;
}

.fd-time-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.fd-time-field__label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
}

.fd-time-field__input {
  height: 44px;
  padding: 0 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font: inherit;
  color: #111827;
  background: #fff;
}

.type-hint {
  margin: 12px 0 0;
  font-size: 0.82rem;
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

.th-sort {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 0;
  background: transparent;
  padding: 0;
  font: inherit;
  color: inherit;
  cursor: pointer;
}

.th-sort__mark {
  font-size: 0.75rem;
  opacity: 0.7;
}

.period-table td {
  padding: 10px;
  border-bottom: 1px solid var(--color-border, #f3f4f6);
  vertical-align: middle;
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
