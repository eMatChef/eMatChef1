<template>
  <div class="accounting-subpage bookings-page">
    <p class="description" style="margin-bottom: 16px">
      Ausgaben und Kostenfälle in CHF, Zuordnung zu Kostenstellen und optional zu Gruppen. Keine MwSt.-Ausweisung.
      Die Buchungs-ID (Präfix kb) enthält das Jahr des Buchungsdatums – wie bei Material-Batches; das Datum ist nach dem Erfassen nicht mehr änderbar.
    </p>

    <div class="bookings-subtabs filter-bar accounting-inner-tabs">
      <div class="filter-tabs">
        <button
          type="button"
          class="filter-tab"
          :class="{ active: bookingsSubTab === 'list' }"
          @click="bookingsSubTab = 'list'"
        >
          Buchungsliste
        </button>
        <button
          type="button"
          class="filter-tab"
          :class="{ active: bookingsSubTab === 'assign' }"
          @click="openAssignTab"
        >
          Neue Buchung zuordnen
          <span
            v-if="hasPendingBooking"
            class="bookings-pending-badge"
            title="Anschaffung aus Material erfasst"
          >{{ pendingFollowUps.length }}</span>
        </button>
      </div>
    </div>

    <div v-if="!costCenters.length && !ccLoading && !ccError" class="empty-hint empty-hint--cost-centers">
      <p>
        Ohne Kostenstelle kannst du keine Buchungen erfassen. Ausstehende Anschaffungen aus dem Material-Wizard erscheinen
        trotzdem unter <strong>Neue Buchung zuordnen</strong>, sobald Kostenstellen existieren.
      </p>
      <div class="empty-hint-actions">
        <router-link
          class="btn btn-primary"
          :to="{ name: 'AccountingCostCenters', params: { departmentId }, query: { openCreate: '1' } }"
        >
          Kostenstelle anlegen
        </router-link>
        <router-link class="btn btn-secondary" :to="{ name: 'AccountingCostCenters', params: { departmentId } }">
          Zu Kostenstellen
        </router-link>
      </div>
    </div>

    <template v-else>
      <div v-show="bookingsSubTab === 'list'">
        <div class="bookings-toolbar">
          <div class="bookings-filters">
            <label class="filter-label">
              Jahr
              <select v-model="filterYear" class="filter-select" @change="load">
                <option value="">Alle Jahre</option>
                <option v-for="y in bookingYears" :key="y" :value="String(y)">{{ y }}</option>
              </select>
            </label>
            <label class="filter-label">
              Kostenstelle
              <select v-model="filterCostCenterId" class="filter-select" @change="load">
                <option value="">Alle</option>
                <option v-for="c in costCenters" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>
          </div>
          <button type="button" class="btn btn-primary" :disabled="isLoading || !costCenters.length" @click="openCreate">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Neue Buchung
          </button>
        </div>

        <div v-if="isLoading" class="loading-inline">Laden…</div>
        <div v-else-if="loadError" class="error-inline">{{ loadError }}</div>
        <div v-else-if="items.length === 0" class="empty-hint">Noch keine Buchungen für die gewählten Filter.</div>
        <div v-else class="bookings-table-wrap">
      <table class="cost-centers-table bookings-table">
        <thead>
          <tr>
            <th>Datum</th>
            <th>Betrag</th>
            <th>Typ</th>
            <th>Kostenstelle</th>
            <th>Zahlungsart</th>
            <th>Gruppe</th>
            <th>Beleg</th>
            <th class="col-actions">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in items" :key="row.id">
            <td>{{ formatDate(row.booked_at) }}</td>
            <td><strong>CHF {{ formatMoney(row.amount) }}</strong></td>
            <td>{{ entryLabel(row.entry_type) }}</td>
            <td>{{ row.cost_center_name }}</td>
            <td>{{ paymentLabel(row.payment_method) }}</td>
            <td>{{ row.group_name || '–' }}</td>
            <td class="muted">{{ row.receipt_label || '–' }}</td>
            <td class="col-actions">
              <button type="button" class="acc-icon-btn" title="Bearbeiten" @click="openEdit(row)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
              </button>
              <button type="button" class="acc-icon-btn danger" title="Löschen" @click="onDelete(row)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
        </div>
      </div>

      <div v-show="bookingsSubTab === 'assign'" class="booking-assign-panel">
        <p v-if="!hasPendingBooking" class="empty-hint booking-assign-empty">
          Keine ausstehende Anschaffung. Wenn du Material mit Anschaffungspreis erfasst hast, erscheint der Betrag hier
          – dann wählst du die <strong>Kostenstelle</strong> und ergänzt Typ, Zahlungsart usw., bevor du speicherst.
        </p>
        <template v-else>
          <p class="booking-assign-lead">
            Betrag und Datum stammen aus der Anschaffung. Bitte <strong>Kostenstelle</strong> und ggf. weitere Felder
            zuordnen.
          </p>
          <div class="acc-modal-body booking-assign-form">
            <div class="acc-field-row">
              <div class="acc-field">
                <label for="ba-amt">Betrag (CHF) *</label>
                <input id="ba-amt" v-model="form.amount" type="text" inputmode="decimal" placeholder="z. B. 149.50" />
              </div>
              <div class="acc-field">
                <label for="ba-date">Buchungsdatum *</label>
                <input id="ba-date" v-model="form.booked_at" type="date" />
              </div>
            </div>
            <div class="acc-field">
              <label for="ba-cc">Kostenstelle *</label>
              <select id="ba-cc" v-model="form.cost_center_id">
                <option disabled value="">Bitte wählen</option>
                <option v-for="c in costCenters" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="ba-type">Buchungstyp *</label>
              <select id="ba-type" v-model="form.entry_type">
                <option v-for="opt in entryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="ba-pay">Zahlungsart (optional)</label>
              <select id="ba-pay" v-model="form.payment_method">
                <option value="">–</option>
                <option v-for="opt in paymentOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="ba-grp">Gruppe (optional)</label>
              <select id="ba-grp" v-model="form.group_id">
                <option value="">–</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="ba-rec">Beleg / Referenz (optional)</label>
              <input id="ba-rec" v-model="form.receipt_label" type="text" maxlength="255" placeholder="z. B. Rechnung Nr. …" />
            </div>
            <div class="acc-field">
              <label for="ba-notes">Notizen (optional)</label>
              <textarea id="ba-notes" v-model="form.notes" placeholder="Kurztext" />
            </div>
            <div class="booking-assign-actions">
              <button type="button" class="btn btn-primary" :disabled="saving" @click="save(true)">
                {{ saving ? 'Speichern…' : 'Buchung speichern' }}
              </button>
            </div>
          </div>
        </template>
      </div>
    </template>

    <Teleport to="body">
      <div v-if="modalOpen" class="acc-modal-backdrop" @click.self="closeModal">
        <div class="acc-modal acc-modal-wide" role="dialog" aria-modal="true">
          <div class="acc-modal-header">
            <h2>{{ editingId ? 'Buchung bearbeiten' : 'Neue Buchung' }}</h2>
            <button type="button" class="acc-icon-btn" aria-label="Schließen" @click="closeModal">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>
          <div class="acc-modal-body">
            <div class="acc-field-row">
              <div class="acc-field">
                <label for="b-amt">Betrag (CHF) *</label>
                <input id="b-amt" v-model="form.amount" type="text" inputmode="decimal" placeholder="z. B. 149.50" />
              </div>
              <div class="acc-field">
                <label for="b-date">Buchungsdatum *</label>
                <input
                  id="b-date"
                  v-model="form.booked_at"
                  type="date"
                  :disabled="!!editingId"
                  :title="editingId ? 'Nach dem Erfassen nicht änderbar (Jahr in der ID)' : ''"
                />
                <p v-if="editingId" class="acc-field-hint">Buchungsdatum ist fest (Jahr steckt in der ID).</p>
              </div>
            </div>
            <div class="acc-field">
              <label for="b-cc">Kostenstelle *</label>
              <select id="b-cc" v-model="form.cost_center_id">
                <option disabled value="">Bitte wählen</option>
                <option v-for="c in costCenters" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="b-type">Buchungstyp *</label>
              <select id="b-type" v-model="form.entry_type">
                <option v-for="opt in entryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="b-pay">Zahlungsart (optional)</label>
              <select id="b-pay" v-model="form.payment_method">
                <option value="">–</option>
                <option v-for="opt in paymentOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="b-grp">Gruppe (optional)</label>
              <select id="b-grp" v-model="form.group_id">
                <option value="">–</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="b-rec">Beleg / Referenz (optional)</label>
              <input id="b-rec" v-model="form.receipt_label" type="text" maxlength="255" placeholder="z. B. Rechnung Nr. …" />
            </div>
            <div class="acc-field">
              <label for="b-notes">Notizen (optional)</label>
              <textarea id="b-notes" v-model="form.notes" placeholder="Kurztext" />
            </div>
            <div class="acc-modal-actions">
              <button type="button" class="btn btn-secondary" @click="closeModal">Abbrechen</button>
              <button type="button" class="btn btn-primary" :disabled="saving" @click="save(false)">
                {{ saving ? 'Speichern…' : 'Speichern' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { listCostCenters, type AccountingCostCenter } from '@/api/accountingCostCenters'
import {
  listBookings,
  getBookingYears,
  createBooking,
  updateBooking,
  deleteBooking,
  type AccountingBooking
} from '@/api/accountingBookings'
import {
  listAcquisitionFollowups,
  type AccountingAcquisitionFollowUp
} from '@/api/accountingAcquisitionFollowups'
import { getGroups, type Group } from '@/api/groups'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()

const departmentId = computed(() => String(route.params.departmentId || ''))

const ENTRY_LABELS: Record<string, string> = {
  purchase: 'Einkauf',
  repair_external: 'Reparatur (extern)',
  repair_internal: 'Reparatur (intern)',
  amortization: 'Abschreibung',
  other: 'Sonstiges'
}

const PAYMENT_LABELS: Record<string, string> = {
  advance_mw: 'Vorzahlung (MW)',
  cash_group: 'Kasse / Gruppe',
  supplier_invoice: 'Lieferantenrechnung',
  association: 'Verein / Zentral',
  other: 'Sonstiges'
}

const entryOptions = Object.entries(ENTRY_LABELS).map(([value, label]) => ({ value, label }))
const paymentOptions = Object.entries(PAYMENT_LABELS).map(([value, label]) => ({ value, label }))

/** Jahre, in denen es bereits Buchungen gibt (pro Department). */
const bookingYears = ref<number[]>([])

/** Einmalig: Standardfilter Kalenderjahr, falls Buchungen in diesem Jahr existieren. */
const defaultYearFilterApplied = ref(false)

const filterYear = ref('')
const filterCostCenterId = ref('')

const costCenters = ref<AccountingCostCenter[]>([])
const ccLoading = ref(true)
const ccError = ref('')

const groups = ref<Group[]>([])

const items = ref<AccountingBooking[]>([])
const isLoading = ref(true)
const loadError = ref('')

const modalOpen = ref(false)
const editingId = ref<string | null>(null)
const saving = ref(false)

const bookingsSubTab = ref<'list' | 'assign'>('list')
const hasPendingBooking = ref(false)
const pendingFollowUps = ref<AccountingAcquisitionFollowUp[]>([])
/** Aktuell im Formular (ältester pending-Eintrag). */
const activeFollowUpId = ref<string | null>(null)
/** Letzte Speicherung kam aus Tab „Neue Buchung zuordnen“ (Anschaffung aus Material). */
const workingFromPending = ref(false)

async function refreshPendingFollowUps() {
  if (!departmentId.value) return
  try {
    pendingFollowUps.value = await listAcquisitionFollowups(departmentId.value, 'pending')
    hasPendingBooking.value = pendingFollowUps.value.length > 0
    if (pendingFollowUps.value.length > 0) {
      activeFollowUpId.value = pendingFollowUps.value[0].id
    } else {
      activeFollowUpId.value = null
    }
  } catch {
    pendingFollowUps.value = []
    hasPendingBooking.value = false
    activeFollowUpId.value = null
  }
}

function syncFormFromPending() {
  const p = pendingFollowUps.value[0]
  if (!p) return
  activeFollowUpId.value = p.id
  form.amount = p.amount
  form.booked_at = p.suggested_date
  form.receipt_label = p.receipt_label || ''
  form.cost_center_id = ''
  form.entry_type = 'purchase'
  form.payment_method = ''
  form.group_id = ''
  form.notes = ''
}

async function openAssignTab() {
  bookingsSubTab.value = 'assign'
  await refreshPendingFollowUps()
  if (hasPendingBooking.value) {
    syncFormFromPending()
  }
}


const form = reactive({
  amount: '',
  booked_at: '',
  cost_center_id: '',
  entry_type: 'purchase',
  payment_method: '' as string,
  group_id: '' as string,
  receipt_label: '',
  notes: ''
})

function entryLabel(k: string): string {
  return ENTRY_LABELS[k] || k
}

function paymentLabel(k: string | null): string {
  if (!k) return '–'
  return PAYMENT_LABELS[k] || k
}

function formatDate(iso: string): string {
  if (!iso) return '–'
  const d = new Date(iso + 'T12:00:00')
  return d.toLocaleDateString('de-CH')
}

function formatMoney(s: string): string {
  const n = parseFloat(s)
  if (Number.isNaN(n)) return s
  return n.toFixed(2)
}

async function loadCostCenters() {
  ccLoading.value = true
  ccError.value = ''
  try {
    costCenters.value = await listCostCenters(departmentId.value)
  } catch {
    ccError.value = 'Kostenstellen konnten nicht geladen werden.'
    costCenters.value = []
  } finally {
    ccLoading.value = false
  }
}

async function loadGroups() {
  try {
    groups.value = await getGroups(departmentId.value)
  } catch {
    groups.value = []
  }
}

async function loadBookingYears() {
  try {
    bookingYears.value = await getBookingYears(departmentId.value)
  } catch {
    bookingYears.value = []
  }
  if (!defaultYearFilterApplied.value) {
    const cy = new Date().getFullYear()
    filterYear.value = bookingYears.value.includes(cy) ? String(cy) : ''
    defaultYearFilterApplied.value = true
  } else if (filterYear.value && !bookingYears.value.includes(Number(filterYear.value))) {
    filterYear.value = ''
  }
}

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    const params: { year?: string; cost_center_id?: string } = {}
    if (filterYear.value) params.year = filterYear.value
    if (filterCostCenterId.value) params.cost_center_id = filterCostCenterId.value
    items.value = await listBookings(departmentId.value, params)
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    loadError.value = msg || 'Buchungen konnten nicht geladen werden.'
    items.value = []
  } finally {
    isLoading.value = false
  }
}

async function bootstrapBookingsView() {
  await loadCostCenters()
  await loadGroups()
  await loadBookingYears()
  await load()
  await refreshPendingFollowUps()
}

/** Query `sub=assign`: Tab „Neue Buchung zuordnen“ (ausstehende Anschaffungen). */
async function applyAssignTabFromRoute() {
  if (String(route.query.sub || '') !== 'assign') return
  bookingsSubTab.value = 'assign'
  await refreshPendingFollowUps()
  if (hasPendingBooking.value) {
    syncFormFromPending()
  }
  await router.replace({
    name: 'AccountingBookings',
    params: { departmentId: departmentId.value },
    query: {},
  })
}

/** Nach Navigation mit Query: Formular „Neue Buchung“ mit Betrag/Datum/Beleg vorbefüllen (Modal). */
async function applyBookingPrefillFromRoute() {
  if (String(route.query.b_open || '') !== '1') return
  await nextTick()
  bookingsSubTab.value = 'list'
  workingFromPending.value = false
  activeFollowUpId.value = null
  resetForm()
  const amt = String(route.query.b_amount || '').trim()
  const date = String(route.query.b_date || '').trim()
  const receipt = String(route.query.b_receipt || '').trim()
  if (amt) {
    const n = parseFloat(amt.replace(/\s/g, '').replace(',', '.'))
    if (Number.isFinite(n) && n > 0) form.amount = n.toFixed(2)
  }
  if (date && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
    form.booked_at = date
  }
  if (receipt) form.receipt_label = receipt
  form.entry_type = 'purchase'
  editingId.value = null
  modalOpen.value = true
  await router.replace({
    name: 'AccountingBookings',
    params: { departmentId: departmentId.value },
    query: {},
  })
}

onMounted(() => {
  void (async () => {
    await bootstrapBookingsView()
    await applyBookingPrefillFromRoute()
    await applyAssignTabFromRoute()
  })()
})

watch(departmentId, (id, prev) => {
  if (!id || prev === undefined || id === prev) return
  defaultYearFilterApplied.value = false
  filterCostCenterId.value = ''
  items.value = []
  bookingsSubTab.value = 'list'
  void bootstrapBookingsView()
})

function resetForm() {
  const today = new Date().toISOString().slice(0, 10)
  form.amount = ''
  form.booked_at = today
  form.cost_center_id = costCenters.value[0]?.id || ''
  form.entry_type = 'purchase'
  form.payment_method = ''
  form.group_id = ''
  form.receipt_label = ''
  form.notes = ''
  editingId.value = null
}

function openCreate() {
  workingFromPending.value = false
  activeFollowUpId.value = null
  resetForm()
  modalOpen.value = true
}

function openEdit(row: AccountingBooking) {
  workingFromPending.value = false
  activeFollowUpId.value = null
  editingId.value = row.id
  form.amount = row.amount
  form.booked_at = row.booked_at
  form.cost_center_id = row.cost_center_id
  form.entry_type = row.entry_type
  form.payment_method = row.payment_method || ''
  form.group_id = row.group_id || ''
  form.receipt_label = row.receipt_label || ''
  form.notes = row.notes || ''
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
}

async function save(fromAssignTab = false) {
  const amount = form.amount.trim()
  if (!amount) {
    toast.error('Bitte einen Betrag eingeben.')
    return
  }
  if (!form.booked_at) {
    toast.error('Bitte ein Buchungsdatum wählen.')
    return
  }
  if (!form.cost_center_id) {
    toast.error('Bitte eine Kostenstelle wählen.')
    return
  }

  if (fromAssignTab) {
    workingFromPending.value = true
  }

  const payloadBase = {
    amount,
    cost_center_id: form.cost_center_id,
    entry_type: form.entry_type,
    payment_method: form.payment_method || null,
    group_id: form.group_id || null,
    receipt_label: form.receipt_label.trim() || null,
    notes: form.notes.trim() || null
  }

  saving.value = true
  try {
    if (editingId.value) {
      await updateBooking(departmentId.value, editingId.value, payloadBase)
      toast.success('Buchung gespeichert.')
    } else {
      await createBooking(departmentId.value, {
        ...payloadBase,
        booked_at: form.booked_at,
        ...(fromAssignTab && activeFollowUpId.value
          ? { acquisition_follow_up_id: activeFollowUpId.value }
          : {})
      })
      toast.success('Buchung erfasst.')
      if (workingFromPending.value) {
        workingFromPending.value = false
        activeFollowUpId.value = null
        await refreshPendingFollowUps()
        bookingsSubTab.value = 'list'
      }
    }
    closeModal()
    await loadBookingYears()
    await load()
  } catch (e: unknown) {
    if (fromAssignTab) {
      workingFromPending.value = false
    }
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    toast.error(msg || 'Speichern fehlgeschlagen.')
  } finally {
    saving.value = false
  }
}

async function onDelete(row: AccountingBooking) {
  const ok = await confirmDialog({
    title: 'Buchung löschen?',
    message: `Eintrag vom ${formatDate(row.booked_at)} über CHF ${formatMoney(row.amount)} wirklich löschen?`,
    confirmText: 'Löschen',
    variant: 'danger'
  })
  if (!ok) return
  try {
    await deleteBooking(departmentId.value, row.id)
    toast.success('Gelöscht.')
    await loadBookingYears()
    await load()
  } catch {
    toast.error('Löschen fehlgeschlagen.')
  }
}
</script>

<style scoped>
@import '@/styles/accounting-view.css';

.bookings-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.bookings-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-end;
}

.filter-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.filter-select {
  min-width: 160px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: #fff;
}

.bookings-table-wrap {
  overflow-x: auto;
}

.bookings-table {
  min-width: 720px;
}

.acc-field select {
  width: 100%;
  box-sizing: border-box;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: #fff;
}

.acc-field-hint {
  margin: 6px 0 0;
  font-size: 12px;
  color: #6b7280;
}

.loading-inline,
.error-inline,
.empty-hint {
  padding: 16px;
  border-radius: 8px;
  background: #f9fafb;
  color: #6b7280;
}

.error-inline {
  background: #fef2f2;
  color: #b91c1c;
}

.empty-hint a {
  color: #2563eb;
}

.accounting-inner-tabs {
  margin-bottom: 8px;
}

.bookings-pending-badge {
  display: inline-flex;
  min-width: 1.25em;
  justify-content: center;
  margin-left: 6px;
  padding: 2px 7px;
  font-size: 11px;
  font-weight: 700;
  background: #f59e0b;
  color: #fff;
  border-radius: 999px;
  vertical-align: middle;
}

.booking-assign-panel {
  margin-bottom: 24px;
}

.booking-assign-lead {
  margin-bottom: 16px;
  color: #374151;
  line-height: 1.5;
}

.booking-assign-empty {
  max-width: 52ch;
  line-height: 1.5;
}

.booking-assign-form {
  margin-top: 8px;
  padding: 20px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}

.booking-assign-actions {
  margin-top: 20px;
}

.empty-hint--cost-centers p {
  margin: 0 0 14px;
  max-width: 52ch;
  line-height: 1.5;
}

.empty-hint-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}
</style>
