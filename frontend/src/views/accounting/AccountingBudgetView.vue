<template>
  <div class="accounting-subpage accounting-budget">
    <p class="description intro">
      {{ t('accounting.budget.intro') }}
    </p>

    <div class="budget-toolbar">
      <ESelect
        v-model="year"
        :items="yearSelectItems"
        :label="t('accounting.common.year')"
        hide-details="auto"
        class="budget-year-select"
        @update:model-value="reload"
      />
      <div class="budget-toolbar-actions">
        <EButton variant="secondary" size="small" :disabled="loading || !comparison" @click="exportCsv">
          {{ t('accounting.budget.exportCsv') }}
        </EButton>
        <EButton variant="primary" size="small" :disabled="!costCenters.length" @click="openCreateModal">
          {{ t('accounting.budget.recordTarget') }}
        </EButton>
      </div>
    </div>

    <EEmptyState
      v-if="!yearOptions.length && !loading"
      :title="t('accounting.common.noBookingYears')"
    />
    <p v-else-if="loadError" class="budget-error">{{ loadError }}</p>
    <ELoadingState v-else-if="loading" variant="inline" :message="t('accounting.common.loading')" />
    <template v-else-if="comparison">
      <div v-if="comparison.totals.budget_chf" class="acc-kpi-grid budget-kpis">
        <div class="acc-kpi-card">
          <div class="acc-kpi-label">{{ t('accounting.budget.kpiTargetSum') }}</div>
          <div class="acc-kpi-value">CHF {{ formatMoney(comparison.totals.budget_chf) }}</div>
        </div>
        <div class="acc-kpi-card">
          <div class="acc-kpi-label">{{ t('accounting.budget.kpiActualSum') }}</div>
          <div class="acc-kpi-value">CHF {{ formatMoney(comparison.totals.ist_chf) }}</div>
        </div>
        <div class="acc-kpi-card" :class="{ 'acc-kpi-card--warn': parseFloat(comparison.totals.remaining_chf || '0') < 0 }">
          <div class="acc-kpi-label">{{ t('accounting.budget.kpiRemaining') }}</div>
          <div class="acc-kpi-value">CHF {{ formatMoney(comparison.totals.remaining_chf || '0') }}</div>
        </div>
      </div>
      <p v-else class="muted-hint">
        {{ t('accounting.budget.noBudgetYet', { year }) }}
      </p>

      <div class="cost-centers-table-wrap">
        <table class="cost-centers-table budget-table">
          <thead>
            <tr>
              <th>{{ t('accounting.budget.colCostCenter') }}</th>
              <th class="col-num">{{ t('accounting.budget.colTargetChf') }}</th>
              <th class="col-num">{{ t('accounting.budget.colActualChf') }}</th>
              <th class="col-num">{{ t('accounting.budget.colRemaining') }}</th>
              <th>{{ t('accounting.budget.colBookings') }}</th>
              <th class="col-actions">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in comparison.rows" :key="row.cost_center_id">
              <td>{{ row.cost_center_name }}</td>
              <td class="col-num">{{ row.budget_amount_chf != null ? 'CHF ' + formatMoney(row.budget_amount_chf) : t('accounting.common.dash') }}</td>
              <td class="col-num">CHF {{ formatMoney(row.ist_amount_chf) }}</td>
              <td class="col-num">
                <span v-if="row.remaining_chf !== null">CHF {{ formatMoney(row.remaining_chf) }}</span>
                <span v-else class="muted">{{ t('accounting.common.dash') }}</span>
              </td>
              <td>{{ row.booking_count }}</td>
              <td class="col-actions">
                <template v-if="row.budget_line_id">
                  <EButton variant="text" size="small" :title="t('common.edit')" @click="openEditModal(row)">
                    <v-icon icon="mdi-pencil-outline" size="20" />
                  </EButton>
                  <EButton variant="text" size="small" color="error" :title="t('common.delete')" @click="onDeleteLine(row)">
                    <v-icon icon="mdi-delete-outline" size="20" />
                  </EButton>
                </template>
                <EButton
                  v-else
                  variant="secondary"
                  size="small"
                  @click="openCreateModalForRow(row)"
                >
                  {{ t('accounting.budget.setTarget') }}
                </EButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <EDialog
      v-model="modalOpen"
      :max-width="480"
      :title="editingLineId ? t('accounting.budget.modalEditTitle') : t('accounting.budget.modalCreateTitle')"
    >
      <ESelect
        v-model="form.cost_center_id"
        :items="costCenterSelectItems"
        :label="t('accounting.budget.fieldCostCenter')"
        :placeholder="t('accounting.budget.choosePlaceholder')"
        :disabled="!!editingLineId"
        hide-details="auto"
      />
      <ETextField
        v-model.number="form.calendar_year"
        class="mt-3"
        type="number"
        :label="t('accounting.budget.fieldYear')"
        :disabled="!!editingLineId"
        hide-details="auto"
      />
      <ETextField
        v-model="form.amount_chf"
        class="mt-3"
        :label="t('accounting.budget.fieldTargetChf')"
        :placeholder="t('accounting.budget.amountPlaceholder')"
        inputmode="decimal"
        hide-details="auto"
      />
      <ETextarea
        v-model="form.notes"
        class="mt-3"
        :label="t('accounting.budget.fieldNoteOptional')"
        :placeholder="t('accounting.budget.notesPlaceholder')"
        rows="2"
        hide-details="auto"
      />
      <p v-if="modalError" class="budget-error mt-3">{{ modalError }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="modalOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :loading="saving" @click="submitModal">
          {{ saving ? t('accounting.common.savingAlt') : t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import {
  getBudgetComparison,
  downloadBudgetCsv,
  createBudgetLine,
  updateBudgetLine,
  deleteBudgetLine,
  type BudgetComparison,
  type BudgetComparisonRow,
} from '@/api/accountingBudget'
import { listCostCenters, type AccountingCostCenter } from '@/api/accountingCostCenters'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useAccountingBookingYears } from '@/composables/useAccountingBookingYears'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()

const departmentId = computed(() => String(route.params.departmentId || ''))

const { years: yearOptions, refreshYears, defaultYear } = useAccountingBookingYears(departmentId)
const year = ref(new Date().getFullYear())

const yearSelectItems = computed(() =>
  yearOptions.value.map((y) => ({ title: String(y), value: y }))
)

const costCenterSelectItems = computed(() =>
  costCenters.value.map((c) => ({ title: c.name, value: c.id }))
)

const loading = ref(true)
const loadError = ref('')
const comparison = ref<BudgetComparison | null>(null)
const costCenters = ref<AccountingCostCenter[]>([])

const modalOpen = ref(false)
const editingLineId = ref<string | null>(null)
const modalError = ref('')
const saving = ref(false)
const form = ref({
  cost_center_id: '',
  calendar_year: new Date().getFullYear(),
  amount_chf: '',
  notes: '',
})

function formatMoney(s: string): string {
  const n = parseFloat(s)
  if (Number.isNaN(n)) return s
  return n.toFixed(2)
}

async function loadCostCenters() {
  const id = departmentId.value
  if (!id) return
  try {
    costCenters.value = await listCostCenters(id)
  } catch {
    costCenters.value = []
  }
}

async function bootstrapYears() {
  await refreshYears()
  const dy = defaultYear()
  if (dy != null) year.value = dy
}

async function loadComparison() {
  const id = departmentId.value
  if (!id) return
  if (!yearOptions.value.length) {
    comparison.value = null
    loading.value = false
    return
  }
  loading.value = true
  loadError.value = ''
  try {
    comparison.value = await getBudgetComparison(id, year.value)
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    loadError.value = msg || t('accounting.budget.loadError')
    comparison.value = null
  } finally {
    loading.value = false
  }
}

function reload() {
  void loadComparison()
}

async function exportCsv() {
  const id = departmentId.value
  if (!id) return
  try {
    await downloadBudgetCsv(id, year.value)
    toast.success(t('accounting.budget.csvDownloaded'))
  } catch {
    toast.error(t('accounting.budget.exportFailed'))
  }
}

function openCreateModal() {
  editingLineId.value = null
  modalError.value = ''
  form.value = {
    cost_center_id: '',
    calendar_year: year.value,
    amount_chf: '',
    notes: '',
  }
  modalOpen.value = true
}

function openCreateModalForRow(row: BudgetComparisonRow) {
  editingLineId.value = null
  modalError.value = ''
  form.value = {
    cost_center_id: row.cost_center_id,
    calendar_year: year.value,
    amount_chf: '',
    notes: '',
  }
  modalOpen.value = true
}

function openEditModal(row: BudgetComparisonRow) {
  if (!row.budget_line_id) return
  editingLineId.value = row.budget_line_id
  modalError.value = ''
  form.value = {
    cost_center_id: row.cost_center_id,
    calendar_year: year.value,
    amount_chf: row.budget_amount_chf || '',
    notes: row.budget_notes || '',
  }
  modalOpen.value = true
}

async function submitModal() {
  const id = departmentId.value
  if (!id) return
  const amt = String(form.value.amount_chf || '').trim()
  if (!amt) {
    modalError.value = t('accounting.budget.modalAmountRequired')
    return
  }
  modalError.value = ''
  saving.value = true
  try {
    if (editingLineId.value) {
      await updateBudgetLine(id, editingLineId.value, {
        amount_chf: amt,
        notes: form.value.notes.trim() || null,
      })
      toast.success(t('accounting.budget.toastTargetSaved'))
    } else {
      if (!form.value.cost_center_id) {
        modalError.value = t('accounting.budget.modalCostCenterRequired')
        saving.value = false
        return
      }
      await createBudgetLine(id, {
        cost_center_id: form.value.cost_center_id,
        calendar_year: form.value.calendar_year,
        amount_chf: amt,
        notes: form.value.notes.trim() || null,
      })
      toast.success(t('accounting.budget.toastTargetCreated'))
    }
    modalOpen.value = false
    await loadComparison()
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    modalError.value = msg || t('accounting.common.saveFailed')
  } finally {
    saving.value = false
  }
}

async function onDeleteLine(row: BudgetComparisonRow) {
  if (!row.budget_line_id) return
  const ok = await confirmDialog({
    title: t('accounting.budget.deleteTitle'),
    message: t('accounting.budget.deleteMessage'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  const id = departmentId.value
  if (!id) return
  try {
    await deleteBudgetLine(id, row.budget_line_id)
    toast.success(t('accounting.common.deleted'))
    await loadComparison()
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    toast.error(msg || t('accounting.common.deleteFailed'))
  }
}

watch(
  departmentId,
  async () => {
    await bootstrapYears()
    void loadCostCenters()
    await loadComparison()
  },
  { immediate: true },
)
</script>

<style scoped>
@import '@/styles/accounting-view.css';

.intro {
  margin-bottom: 16px;
}

.budget-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.budget-toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.budget-year-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.filter-select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  min-width: 120px;
}

.acc-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.acc-kpi-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
}

.acc-kpi-card--warn {
  border-color: #f87171;
  background: #fef2f2;
}

.acc-kpi-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
}

.acc-kpi-value {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin-top: 6px;
}

.muted-hint {
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 16px;
}

.muted {
  color: #9ca3af;
}

.budget-table .col-num {
  text-align: right;
}

.budget-error {
  color: #b91c1c;
  font-size: 13px;
  margin: 0;
}

.loading-inline {
  padding: 20px;
  color: #6b7280;
}

.acc-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.acc-modal {
  background: #fff;
  border-radius: 12px;
  max-width: 440px;
  width: 100%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.acc-modal.budget-modal .acc-modal-body {
  padding: 20px 22px;
}

.acc-modal-title {
  margin: 0 0 16px;
  font-size: 17px;
}

.acc-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 14px;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.acc-field textarea {
  resize: vertical;
  min-height: 56px;
}

.acc-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 6px 22px 18px;
}
</style>
