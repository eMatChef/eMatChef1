<template>
  <div class="fixed-dates-settings">
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.fixedDates.title') }}</h2>
        <p class="settings-description">{{ pageDescription }}</p>
      </div>
      <div v-if="isGrossanlassDept" class="page-banner">
        <v-icon icon="mdi-calendar-star" size="20" class="page-banner__icon" />
        <p>{{ t('settings.fixedDates.grossanlassHint') }}</p>
      </div>
    </div>

    <ECard v-if="!canManage">
      <p class="muted">{{ t('settings.fixedDates.noPermission') }}</p>
    </ECard>

    <template v-else>
      <ECard class="create-card">
        <form class="create-form" @submit.prevent="submitForm">
          <div class="create-form__header">
            <h3 class="section-heading">
              {{ editingId ? t('settings.fixedDates.editTitle') : t('settings.fixedDates.createTitle') }}
            </h3>
            <span v-if="editingId" class="edit-badge">{{ t('settings.fixedDates.editingBadge') }}</span>
          </div>

          <div class="create-form__body">
            <div class="create-form__period">
              <EDateRangeField
                id="fd-period"
                v-model:start="form.start_date"
                v-model:end="form.end_date"
                :label="t('settings.fixedDates.period')"
                :department-id="departmentId"
                :allow-past="true"
                :block-closed-dates="false"
                :show-presets="false"
                :show-markers="true"
              />
            </div>

            <div class="create-form__meta">
              <ESelect
                id="fd-label"
                v-model="form.label"
                class="create-form__type"
                :label="t('settings.fixedDates.typeLabel')"
                :items="labelOptions"
                item-title="text"
                item-value="value"
                hide-details
              />
              <ETextField
                id="fd-name"
                v-model="form.name"
                class="create-form__name"
                :label="t('settings.fixedDates.name')"
                :placeholder="namePlaceholder"
                maxlength="120"
                hide-details
              />
            </div>

            <div class="info-callout">
              <v-icon icon="mdi-information-outline" size="18" class="info-callout__icon" />
              <span>{{ t('settings.fixedDates.quickSelectHint') }}</span>
            </div>
          </div>

          <div class="create-form__actions">
            <EButton v-if="editingId" type="button" variant="secondary" :disabled="saving" @click="cancelEdit">
              {{ t('common.cancel') }}
            </EButton>
            <EButton type="submit" variant="primary" :disabled="saving || !canSubmit" :loading="saving">
              {{ saving ? t('common.saving') : (editingId ? t('common.save') : t('common.create')) }}
            </EButton>
          </div>
        </form>
      </ECard>

      <ELoadingState v-if="loading" variant="inline" :message="t('settings.fixedDates.loading')" />

      <ECard v-else-if="periods.length === 0" class="empty-card">
        <v-icon icon="mdi-calendar-range" size="40" class="empty-icon" />
        <p class="empty-title">{{ emptyTitle }}</p>
        <p v-if="emptyDescription" class="muted">{{ emptyDescription }}</p>
      </ECard>

      <div v-else class="period-list">
        <ECard v-for="row in periods" :key="row.id" class="period-row" :class="{ 'period-row--active': editingId === row.id }">
          <div class="period-row__main">
            <span class="type-tag" :class="'type-tag--' + row.label">{{ labelText(row.label) }}</span>
            <span class="period-row__name">{{ row.name }}</span>
            <span class="period-row__range">
              {{ formatDisplayDate(row.start_date) }} – {{ formatDisplayDate(row.end_date) }}
            </span>
          </div>
          <div class="period-row__actions">
            <EButton variant="text" size="small" @click="startEdit(row)">{{ t('common.edit') }}</EButton>
            <EButton variant="text" size="small" class="btn-danger-text" @click="removePeriod(row.id)">
              {{ t('common.delete') }}
            </EButton>
          </div>
        </ECard>
      </div>
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

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)

const isGrossanlassDept = computed(() => authStore.isDepartmentGrossanlass(departmentId.value))

const pageDescription = computed(() =>
  isGrossanlassDept.value
    ? t('settings.fixedDates.descriptionGrossanlass')
    : t('settings.fixedDates.description'),
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
  isGrossanlassDept.value ? 'other' : 'school_vacation'

const emptyForm = () => ({
  label: defaultLabel(),
  name: '',
  start_date: '',
  end_date: '',
})

const form = reactive(emptyForm())

const grossanlassLabelOrder: CalendarPeriodLabel[] = ['camp_week', 'other', 'school_vacation', 'department_break']
const standardLabelOrder: CalendarPeriodLabel[] = [
  'school_vacation',
  'department_break',
  'camp_week',
  'other',
]

const labelOptions = computed(() => {
  const order = isGrossanlassDept.value ? grossanlassLabelOrder : standardLabelOrder
  return order.map((value) => ({
    value,
    text: labelText(value),
  }))
})

function labelText(label: CalendarPeriodLabel): string {
  return t(`settings.fixedDates.labels.${label}`)
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
  gap: 20px;
  max-width: 720px;
}

.page-header {
  display: flex;
  flex-direction: column;
  gap: 12px;
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

.settings-description {
  margin-top: 6px;
  line-height: 1.5;
}

.page-banner {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 14px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  color: #1e40af;
  font-size: 0.88rem;
  line-height: 1.45;
}

.page-banner p {
  margin: 0;
}

.page-banner__icon {
  flex-shrink: 0;
  margin-top: 1px;
  opacity: 0.85;
}

.create-form {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.create-form__header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.section-heading {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text, #111827);
}

.edit-badge {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 3px 8px;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
}

.create-form__body {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.create-form__period {
  max-width: 320px;
}

.create-form__meta {
  display: grid;
  grid-template-columns: minmax(160px, 200px) minmax(0, 1fr);
  gap: 12px;
  align-items: start;
}

.create-form__type,
.create-form__name {
  min-width: 0;
}

.info-callout {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  font-size: 0.82rem;
  line-height: 1.45;
  color: #6b7280;
}

.info-callout__icon {
  flex-shrink: 0;
  margin-top: 1px;
  color: #9ca3af;
}

.create-form__actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #f3f4f6;
}

.empty-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 32px 20px;
  gap: 8px;
}

.empty-icon {
  color: #d1d5db;
  margin-bottom: 4px;
}

.empty-title {
  margin: 0;
  font-weight: 500;
  color: var(--color-text, #111827);
}

.period-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.period-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 16px !important;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.period-row--active {
  border-color: #fcd34d !important;
  box-shadow: 0 0 0 1px #fcd34d;
}

.period-row__main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
  min-width: 0;
}

.period-row__name {
  font-weight: 600;
  color: #111827;
}

.period-row__range {
  font-size: 0.85rem;
  color: #6b7280;
}

.period-row__actions {
  display: flex;
  gap: 2px;
  flex-shrink: 0;
}

.btn-danger-text :deep(.v-btn) {
  color: #dc2626 !important;
}

.type-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
  background: #f3f4f6;
  color: #374151;
  white-space: nowrap;
}

.type-tag--camp_week {
  background: #d1fae5;
  color: #065f46;
}

.type-tag--other {
  background: #e0e7ff;
  color: #3730a3;
}

.type-tag--school_vacation {
  background: #fef3c7;
  color: #92400e;
}

.type-tag--department_break {
  background: #fee2e2;
  color: #991b1b;
}

@media (max-width: 600px) {
  .create-form__meta {
    grid-template-columns: 1fr;
  }

  .create-form__period {
    max-width: none;
  }

  .period-row {
    flex-direction: column;
    align-items: stretch;
  }

  .period-row__actions {
    justify-content: flex-end;
  }
}
</style>
