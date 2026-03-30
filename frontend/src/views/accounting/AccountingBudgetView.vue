<template>
  <div class="accounting-subpage accounting-budget">
    <p class="description intro">
      Geplantes Budget (Soll) in CHF pro Kostenstelle und Kalenderjahr, Vergleich mit erfassten Buchungen (Ist). Export als CSV
      für Abgleich mit dem Vereins-Finanztool.
    </p>

    <div class="budget-toolbar">
      <label class="budget-year-label">
        Jahr
        <select v-model.number="year" class="filter-select" @change="reload">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </label>
      <div class="budget-toolbar-actions">
        <button type="button" class="btn btn-secondary btn-sm" :disabled="loading || !comparison" @click="exportCsv">
          CSV exportieren
        </button>
        <button type="button" class="btn btn-primary btn-sm" :disabled="!costCenters.length" @click="openCreateModal">
          Soll erfassen
        </button>
      </div>
    </div>

    <div v-if="loadError" class="budget-error">{{ loadError }}</div>
    <div v-else-if="loading" class="loading-inline">Laden…</div>
    <template v-else-if="comparison">
      <div v-if="comparison.totals.budget_chf" class="acc-kpi-grid budget-kpis">
        <div class="acc-kpi-card">
          <div class="acc-kpi-label">Soll (Summe)</div>
          <div class="acc-kpi-value">CHF {{ formatMoney(comparison.totals.budget_chf) }}</div>
        </div>
        <div class="acc-kpi-card">
          <div class="acc-kpi-label">Ist (Summe)</div>
          <div class="acc-kpi-value">CHF {{ formatMoney(comparison.totals.ist_chf) }}</div>
        </div>
        <div class="acc-kpi-card" :class="{ 'acc-kpi-card--warn': parseFloat(comparison.totals.remaining_chf || '0') < 0 }">
          <div class="acc-kpi-label">Rest (Soll − Ist)</div>
          <div class="acc-kpi-value">CHF {{ formatMoney(comparison.totals.remaining_chf || '0') }}</div>
        </div>
      </div>
      <p v-else class="muted-hint">
        Noch kein Budget-Soll für {{ year }} erfasst. Kostenstellen zeigen nur Ist-Werte.
      </p>

      <div class="cost-centers-table-wrap">
        <table class="cost-centers-table budget-table">
          <thead>
            <tr>
              <th>Kostenstelle</th>
              <th class="col-num">Soll CHF</th>
              <th class="col-num">Ist CHF</th>
              <th class="col-num">Rest</th>
              <th>Buchungen</th>
              <th class="col-actions">Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in comparison.rows" :key="row.cost_center_id">
              <td>{{ row.cost_center_name }}</td>
              <td class="col-num">{{ row.budget_amount_chf != null ? 'CHF ' + formatMoney(row.budget_amount_chf) : '–' }}</td>
              <td class="col-num">CHF {{ formatMoney(row.ist_amount_chf) }}</td>
              <td class="col-num">
                <span v-if="row.remaining_chf !== null">CHF {{ formatMoney(row.remaining_chf) }}</span>
                <span v-else class="muted">–</span>
              </td>
              <td>{{ row.booking_count }}</td>
              <td class="col-actions">
                <template v-if="row.budget_line_id">
                  <button type="button" class="acc-icon-btn" title="Bearbeiten" @click="openEditModal(row)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                  </button>
                  <button type="button" class="acc-icon-btn danger" title="Löschen" @click="onDeleteLine(row)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6" />
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                    </svg>
                  </button>
                </template>
                <button
                  v-else
                  type="button"
                  class="btn-outline btn-xs"
                  @click="openCreateModalForRow(row)"
                >
                  Soll setzen
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div v-if="modalOpen" class="acc-modal-overlay" @click.self="modalOpen = false">
      <div class="acc-modal budget-modal">
        <div class="acc-modal-body">
          <h3 class="acc-modal-title">{{ editingLineId ? 'Budget-Soll bearbeiten' : 'Budget-Soll erfassen' }}</h3>
          <label class="acc-field">
            <span>Kostenstelle</span>
            <select v-model="form.cost_center_id" class="filter-select" :disabled="!!editingLineId">
              <option value="">– wählen –</option>
              <option v-for="c in costCenters" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </label>
          <label class="acc-field">
            <span>Jahr</span>
            <input v-model.number="form.calendar_year" type="number" min="2000" max="2100" class="filter-select" :disabled="!!editingLineId" />
          </label>
          <label class="acc-field">
            <span>Soll (CHF)</span>
            <input v-model="form.amount_chf" type="text" inputmode="decimal" placeholder="z. B. 1500.00" class="filter-select" />
          </label>
          <label class="acc-field">
            <span>Notiz (optional)</span>
            <textarea v-model="form.notes" rows="2" class="filter-select" placeholder="Interne Hinweise" />
          </label>
          <p v-if="modalError" class="budget-error">{{ modalError }}</p>
        </div>
        <div class="acc-modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" @click="modalOpen = false">Abbrechen</button>
          <button type="button" class="btn btn-primary btn-sm" :disabled="saving" @click="submitModal">
            {{ saving ? 'Speichert…' : 'Speichern' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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

const route = useRoute()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()

const departmentId = computed(() => String(route.params.departmentId || ''))

const year = ref(new Date().getFullYear())
const yearOptions = computed(() => {
  const cy = new Date().getFullYear()
  const list: number[] = []
  for (let y = cy + 2; y >= cy - 5; y--) list.push(y)
  return list
})

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

async function loadComparison() {
  const id = departmentId.value
  if (!id) return
  loading.value = true
  loadError.value = ''
  try {
    comparison.value = await getBudgetComparison(id, year.value)
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    loadError.value = msg || 'Budget konnte nicht geladen werden.'
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
    toast.success('CSV heruntergeladen')
  } catch {
    toast.error('Export fehlgeschlagen')
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
    modalError.value = 'Betrag erforderlich'
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
      toast.success('Budget-Soll gespeichert')
    } else {
      if (!form.value.cost_center_id) {
        modalError.value = 'Kostenstelle wählen'
        saving.value = false
        return
      }
      await createBudgetLine(id, {
        cost_center_id: form.value.cost_center_id,
        calendar_year: form.value.calendar_year,
        amount_chf: amt,
        notes: form.value.notes.trim() || null,
      })
      toast.success('Budget-Soll erfasst')
    }
    modalOpen.value = false
    await loadComparison()
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    modalError.value = msg || 'Speichern fehlgeschlagen'
  } finally {
    saving.value = false
  }
}

async function onDeleteLine(row: BudgetComparisonRow) {
  if (!row.budget_line_id) return
  const ok = await confirmDialog({
    title: 'Budget-Soll löschen',
    message: 'Soll-Wert für diese Kostenstelle wirklich entfernen?',
    confirmText: 'Löschen',
    cancelText: 'Abbrechen',
    variant: 'danger',
  })
  if (!ok) return
  const id = departmentId.value
  if (!id) return
  try {
    await deleteBudgetLine(id, row.budget_line_id)
    toast.success('Gelöscht')
    await loadComparison()
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    toast.error(msg || 'Löschen fehlgeschlagen')
  }
}

watch(
  departmentId,
  () => {
    void loadCostCenters()
    void loadComparison()
  },
  { immediate: true }
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
