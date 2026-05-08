<template>
  <div class="accounting-subpage bookings-page">
    <p class="description" style="margin-bottom: 16px">
      {{ t('accounting.bookings.intro') }}
    </p>

    <div class="bookings-subtabs filter-bar accounting-inner-tabs">
      <div class="filter-tabs">
        <button
          type="button"
          class="filter-tab"
          :class="{ active: bookingsSubTab === 'list' }"
          @click="bookingsSubTab = 'list'"
        >
          {{ t('accounting.bookings.tabList') }}
        </button>
        <button
          type="button"
          class="filter-tab"
          :class="{ active: bookingsSubTab === 'assign' }"
          @click="openAssignTab"
        >
          {{ t('accounting.bookings.tabAssign') }}
          <span
            v-if="hasPendingBooking"
            class="bookings-pending-badge"
            :title="t('accounting.bookings.badgePendingTitle')"
          >{{ pendingFollowUps.length }}</span>
        </button>
      </div>
    </div>

    <div v-if="!costCenters.length && !ccLoading && !ccError" class="empty-hint empty-hint--cost-centers">
      <p>
        {{ t('accounting.bookings.emptyNoCcBefore') }}<strong>{{ t('accounting.bookings.tabAssign') }}</strong>{{ t('accounting.bookings.emptyNoCcAfter') }}
      </p>
      <div class="empty-hint-actions">
        <router-link
          class="btn btn-primary"
          :to="{ name: 'AccountingCostCenters', params: { departmentId }, query: { openCreate: '1' } }"
        >
          {{ t('accounting.bookings.createCostCenter') }}
        </router-link>
        <router-link class="btn btn-secondary" :to="{ name: 'AccountingCostCenters', params: { departmentId } }">
          {{ t('accounting.bookings.goToCostCenters') }}
        </router-link>
      </div>
    </div>

    <template v-else>
      <div v-show="bookingsSubTab === 'list'">
        <div class="bookings-toolbar">
          <div class="bookings-filters">
            <label class="filter-label">
              {{ t('accounting.bookings.filterYear') }}
              <select v-model="filterYear" class="filter-select" @change="load">
                <option value="">{{ t('accounting.common.allYears') }}</option>
                <option v-for="y in bookingYears" :key="y" :value="String(y)">{{ y }}</option>
              </select>
            </label>
            <label class="filter-label">
              {{ t('accounting.bookings.filterCostCenter') }}
              <select v-model="filterCostCenterId" class="filter-select" @change="load">
                <option value="">{{ t('accounting.common.all') }}</option>
                <option v-for="c in costCenters" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>
          </div>
          <button type="button" class="btn btn-primary" :disabled="isLoading || !costCenters.length" @click="openCreate">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            {{ t('accounting.bookings.newBooking') }}
          </button>
        </div>

        <div v-if="isLoading" class="loading-inline">{{ t('accounting.common.loading') }}</div>
        <div v-else-if="loadError" class="error-inline">{{ loadError }}</div>
        <div v-else-if="items.length === 0" class="empty-hint">{{ t('accounting.bookings.emptyFiltered') }}</div>
        <div v-else class="bookings-table-wrap">
      <table class="cost-centers-table bookings-table">
        <thead>
          <tr>
            <th>{{ t('accounting.common.date') }}</th>
            <th>{{ t('accounting.common.amount') }}</th>
            <th>{{ t('accounting.common.type') }}</th>
            <th>{{ t('accounting.common.costCenter') }}</th>
            <th>{{ t('accounting.common.material') }}</th>
            <th>{{ t('accounting.common.paymentMethod') }}</th>
            <th>{{ t('accounting.common.group') }}</th>
            <th>{{ t('accounting.common.receipt') }}</th>
            <th class="col-actions">{{ t('accounting.common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in items" :key="row.id">
            <td>{{ formatDate(row.booked_at) }}</td>
            <td><strong>CHF {{ formatMoney(row.amount) }}</strong></td>
            <td>{{ entryLabel(row.entry_type) }}</td>
            <td>{{ row.cost_center_name }}</td>
            <td class="muted">{{ row.material_name || t('accounting.common.dash') }}</td>
            <td>{{ paymentLabel(row.payment_method) }}</td>
            <td>{{ row.group_name || t('accounting.common.dash') }}</td>
            <td class="muted">{{ row.receipt_label || t('accounting.common.dash') }}</td>
            <td class="col-actions">
              <button type="button" class="acc-icon-btn" :title="t('accounting.common.edit')" @click="openEdit(row)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
              </button>
              <button type="button" class="acc-icon-btn danger" :title="t('accounting.common.delete')" @click="onDelete(row)">
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
          {{ t('accounting.bookings.assignEmptyBefore') }}<strong>{{ t('accounting.common.costCenter') }}</strong>{{ t('accounting.bookings.assignEmptyAfter') }}
        </p>
        <template v-else>
          <p class="booking-assign-lead">
            {{ t('accounting.bookings.assignLeadBefore') }}<strong>{{ t('accounting.common.costCenter') }}</strong>{{ t('accounting.bookings.assignLeadAfter') }}
          </p>
          <div
            v-if="pendingFollowUps.length > 1"
            class="bookings-subtabs booking-assign-followup-tabs filter-bar accounting-inner-tabs"
          >
            <div class="filter-tabs" role="tablist" :aria-label="t('accounting.bookings.pendingTabsAria')">
              <button
                v-for="(fu, idx) in pendingFollowUps"
                :key="fu.id"
                type="button"
                class="filter-tab"
                role="tab"
                :aria-selected="assignTabIndex === idx"
                :class="{ active: assignTabIndex === idx }"
                @click="selectAssignTab(idx)"
              >
                {{ t('accounting.bookings.assignTabBooking', { n: idx + 1 }) }}
                <span class="booking-assign-tab-meta">· CHF {{ formatMoney(fu.amount) }}</span>
              </button>
            </div>
          </div>
          <div class="acc-modal-body booking-assign-form">
            <div class="acc-field-row">
              <div class="acc-field">
                <label for="ba-amt">{{ t('accounting.bookings.labelAmountChf') }}</label>
                <input id="ba-amt" v-model="form.amount" type="text" inputmode="decimal" :placeholder="t('accounting.bookings.placeholderAmount')" />
              </div>
              <div class="acc-field">
                <label for="ba-date">{{ t('accounting.bookings.labelBookingDate') }}</label>
                <input id="ba-date" v-model="form.booked_at" type="date" />
              </div>
            </div>
            <div class="acc-field">
              <label for="ba-cc">{{ t('accounting.bookings.labelCostCenterStar') }}</label>
              <select id="ba-cc" v-model="form.cost_center_id">
                <option disabled value="">{{ t('accounting.common.pleaseSelect') }}</option>
                <option v-for="c in costCenters" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="ba-type">{{ t('accounting.bookings.labelEntryTypeStar') }}</label>
              <select id="ba-type" v-model="form.entry_type">
                <option v-for="opt in entryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="ba-pay">{{ t('accounting.bookings.labelPaymentOptional') }}</label>
              <select id="ba-pay" v-model="form.payment_method">
                <option value="">{{ t('accounting.common.dash') }}</option>
                <option v-for="opt in paymentOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="ba-grp">{{ t('accounting.bookings.labelGroupOptional') }}</label>
              <select id="ba-grp" v-model="form.group_id">
                <option value="">{{ t('accounting.common.dash') }}</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="ba-rec">{{ t('accounting.bookings.labelReceiptOptional') }}</label>
              <input id="ba-rec" v-model="form.receipt_label" type="text" maxlength="255" :placeholder="t('accounting.bookings.placeholderReceipt')" />
            </div>
            <div class="acc-field">
              <label for="ba-notes">{{ t('accounting.bookings.labelNotesOptional') }}</label>
              <textarea id="ba-notes" v-model="form.notes" :placeholder="t('accounting.bookings.placeholderNotesShort')" />
            </div>
            <div class="booking-assign-actions">
              <button type="button" class="btn btn-primary" :disabled="saving" @click="save(true)">
                {{ saving ? t('accounting.bookings.saveAssignSaving') : t('accounting.bookings.saveAssign') }}
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
            <h2>{{ editingId ? t('accounting.bookings.modalEditTitle') : t('accounting.bookings.modalCreateTitle') }}</h2>
            <button type="button" class="acc-icon-btn" :aria-label="t('accounting.common.close')" @click="closeModal">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>
          <div class="acc-modal-body">
            <div class="acc-field-row">
              <div class="acc-field">
                <label for="b-amt">{{ t('accounting.bookings.labelAmountChf') }}</label>
                <input id="b-amt" v-model="form.amount" type="text" inputmode="decimal" :placeholder="t('accounting.bookings.placeholderAmount')" />
              </div>
              <div class="acc-field">
                <label for="b-date">{{ t('accounting.bookings.labelBookingDate') }}</label>
                <input
                  id="b-date"
                  v-model="form.booked_at"
                  type="date"
                  :disabled="!!editingId"
                  :title="editingId ? t('accounting.bookings.dateDisabledHint') : ''"
                />
                <p v-if="editingId" class="acc-field-hint">{{ t('accounting.bookings.dateFixedHint') }}</p>
              </div>
            </div>
            <div class="acc-field">
              <label for="b-cc">{{ t('accounting.bookings.labelCostCenterStar') }}</label>
              <select id="b-cc" v-model="form.cost_center_id">
                <option disabled value="">{{ t('accounting.common.pleaseSelect') }}</option>
                <option v-for="c in costCenters" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="b-type">{{ t('accounting.bookings.labelEntryTypeStar') }}</label>
              <select id="b-type" v-model="form.entry_type">
                <option v-for="opt in entryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="b-pay">{{ t('accounting.bookings.labelPaymentOptional') }}</label>
              <select id="b-pay" v-model="form.payment_method">
                <option value="">{{ t('accounting.common.dash') }}</option>
                <option v-for="opt in paymentOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="b-grp">{{ t('accounting.bookings.labelGroupOptional') }}</label>
              <select id="b-grp" v-model="form.group_id">
                <option value="">{{ t('accounting.common.dash') }}</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
            </div>
            <div class="acc-field">
              <label for="b-rec">{{ t('accounting.bookings.labelReceiptOptional') }}</label>
              <input id="b-rec" v-model="form.receipt_label" type="text" maxlength="255" :placeholder="t('accounting.bookings.placeholderReceipt')" />
            </div>
            <div class="acc-field">
              <label for="b-mat">{{ t('accounting.bookings.labelMaterialOptional') }}</label>
              <p class="acc-field-hint">{{ t('accounting.bookings.materialFieldHint') }}</p>
              <MaterialLookupInput
                v-model="materialLookupDisplay"
                :fetcher="bookingMaterialLookupFetcher"
                :min-chars="1"
                :max-suggestions="12"
                :placeholder="t('accounting.bookings.placeholderMaterialSearch')"
                :get-result-key="(item) => item.id"
                @select="onBookingMaterialSelect"
              />
              <button
                v-if="form.material_item_id"
                type="button"
                class="booking-clear-material"
                @click="clearBookingMaterial"
              >
                {{ t('accounting.bookings.clearMaterialLink') }}
              </button>
            </div>
            <div class="acc-field">
              <label for="b-notes">{{ t('accounting.bookings.labelNotesOptional') }}</label>
              <textarea id="b-notes" v-model="form.notes" :placeholder="t('accounting.bookings.placeholderNotesShort')" />
            </div>
            <div class="acc-modal-actions">
              <button type="button" class="btn btn-secondary" @click="closeModal">{{ t('accounting.common.cancel') }}</button>
              <button type="button" class="btn btn-primary" :disabled="saving" @click="save(false)">
                {{ saving ? t('accounting.common.saving') : t('accounting.common.save') }}
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
import { useI18n } from 'vue-i18n'
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
import type { Material } from '@/api/materials'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { createBasicMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'

const route = useRoute()
const headerNotificationsStore = useHeaderNotificationsStore()
const router = useRouter()
const { t, te, locale } = useI18n()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()

const departmentId = computed(() => String(route.params.departmentId || ''))

const bookingMaterialLookupFetcher = createBasicMaterialLookupFetcher(() => departmentId.value)
const materialLookupDisplay = ref('')

const form = reactive({
  amount: '',
  booked_at: '',
  cost_center_id: '',
  entry_type: 'purchase',
  payment_method: '' as string,
  group_id: '' as string,
  receipt_label: '',
  notes: '',
  material_item_id: '' as string,
})

function onBookingMaterialSelect(item: Material) {
  form.material_item_id = item.id
  materialLookupDisplay.value = item.name || ''
}

function clearBookingMaterial() {
  form.material_item_id = ''
  materialLookupDisplay.value = ''
}

const ENTRY_KEYS = ['purchase', 'repair_external', 'repair_internal', 'amortization', 'other'] as const
const PAYMENT_KEYS = ['advance_mw', 'cash_group', 'supplier_invoice', 'association', 'other'] as const

const entryOptions = computed(() =>
  ENTRY_KEYS.map((value) => ({
    value,
    label: t(`accounting.entryType.${value}`),
  }))
)
const paymentOptions = computed(() =>
  PAYMENT_KEYS.map((value) => ({
    value,
    label: t(`accounting.paymentMethod.${value}`),
  }))
)

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
/** Aktuell im Formular (gewählte ausstehende Anschaffung). */
const activeFollowUpId = ref<string | null>(null)
/** Bei mehreren Pending-Follow-ups: welcher Unter-Tab aktiv ist. */
const assignTabIndex = ref(0)
/** Entwürfe pro Follow-up-ID, damit beim Tab-Wechsel nichts verloren geht. */
const assignDrafts = reactive<
  Record<
    string,
    {
      amount: string
      booked_at: string
      cost_center_id: string
      entry_type: string
      payment_method: string
      group_id: string
      receipt_label: string
      notes: string
    }
  >
>({})
/** Letzte Speicherung kam aus Tab „Neue Buchung zuordnen“ (Anschaffung aus Material). */
const workingFromPending = ref(false)

async function refreshPendingFollowUps() {
  if (!departmentId.value) return
  try {
    pendingFollowUps.value = await listAcquisitionFollowups(departmentId.value, 'pending')
    hasPendingBooking.value = pendingFollowUps.value.length > 0
    if (pendingFollowUps.value.length > 0) {
      const prevId = activeFollowUpId.value
      const stillExists =
        prevId && pendingFollowUps.value.some((f) => f.id === prevId)
      if (stillExists) {
        assignTabIndex.value = pendingFollowUps.value.findIndex((f) => f.id === prevId)
      } else {
        assignTabIndex.value = Math.min(
          assignTabIndex.value,
          pendingFollowUps.value.length - 1
        )
      }
      assignTabIndex.value = Math.max(0, assignTabIndex.value)
      const p = pendingFollowUps.value[assignTabIndex.value]
      if (p) loadAssignFormForFollowUp(p)
    } else {
      activeFollowUpId.value = null
      assignTabIndex.value = 0
    }
  } catch {
    pendingFollowUps.value = []
    hasPendingBooking.value = false
    activeFollowUpId.value = null
    assignTabIndex.value = 0
  }
}

function persistCurrentAssignDraft() {
  const id = activeFollowUpId.value
  if (!id) return
  assignDrafts[id] = {
    amount: form.amount,
    booked_at: form.booked_at,
    cost_center_id: form.cost_center_id,
    entry_type: form.entry_type,
    payment_method: form.payment_method,
    group_id: form.group_id,
    receipt_label: form.receipt_label,
    notes: form.notes,
  }
}

function loadAssignFormForFollowUp(p: AccountingAcquisitionFollowUp) {
  activeFollowUpId.value = p.id
  const draft = assignDrafts[p.id]
  if (draft) {
    form.amount = draft.amount
    form.booked_at = draft.booked_at
    form.cost_center_id = draft.cost_center_id
    form.entry_type = draft.entry_type
    form.payment_method = draft.payment_method
    form.group_id = draft.group_id
    form.receipt_label = draft.receipt_label
    form.notes = draft.notes
  } else {
    form.amount = p.amount
    form.booked_at = p.suggested_date
    form.receipt_label = p.receipt_label || ''
    form.cost_center_id = ''
    form.entry_type = 'purchase'
    form.payment_method = ''
    form.group_id = ''
    form.notes = ''
  }
  form.material_item_id = ''
  materialLookupDisplay.value = ''
}

function selectAssignTab(idx: number) {
  if (idx < 0 || idx >= pendingFollowUps.value.length || idx === assignTabIndex.value) return
  persistCurrentAssignDraft()
  assignTabIndex.value = idx
  const fu = pendingFollowUps.value[idx]
  if (fu) loadAssignFormForFollowUp(fu)
}

async function openAssignTab() {
  bookingsSubTab.value = 'assign'
  await refreshPendingFollowUps()
}

function entryLabel(k: string): string {
  const key = `accounting.entryType.${k}`
  return te(key) ? t(key) : k
}

function paymentLabel(k: string | null): string {
  if (!k) return t('accounting.common.dash')
  const key = `accounting.paymentMethod.${k}`
  return te(key) ? t(key) : k
}

function bookingDateFormatLocale(): string {
  const l = locale.value
  if (l === 'en') return 'en-GB'
  if (l === 'fr') return 'fr-CH'
  if (l === 'it') return 'it-CH'
  return 'de-CH'
}

function formatDate(iso: string): string {
  if (!iso) return t('accounting.common.dash')
  const d = new Date(iso + 'T12:00:00')
  return d.toLocaleDateString(bookingDateFormatLocale())
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
    ccError.value = t('accounting.bookings.ccLoadError')
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
    loadError.value = msg || t('accounting.bookings.listLoadError')
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
  assignTabIndex.value = 0
  Object.keys(assignDrafts).forEach((k) => delete assignDrafts[k])
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
  form.material_item_id = ''
  materialLookupDisplay.value = ''
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
  form.material_item_id = row.material_item_id || ''
  materialLookupDisplay.value = row.material_name || ''
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
}

async function save(fromAssignTab = false) {
  const amount = form.amount.trim()
  if (!amount) {
    toast.error(t('accounting.bookings.toastAmountRequired'))
    return
  }
  if (!form.booked_at) {
    toast.error(t('accounting.bookings.toastDateRequired'))
    return
  }
  if (!form.cost_center_id) {
    toast.error(t('accounting.bookings.toastCostCenterRequired'))
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
    notes: form.notes.trim() || null,
    material_item_id: form.material_item_id || null,
  }

  saving.value = true
  try {
    if (editingId.value) {
      await updateBooking(departmentId.value, editingId.value, payloadBase)
      toast.success(t('accounting.bookings.toastSaved'))
    } else {
      if (fromAssignTab && activeFollowUpId.value) {
        const { material_item_id: _omitMat, ...withoutMat } = payloadBase
        await createBooking(departmentId.value, {
          ...withoutMat,
          booked_at: form.booked_at,
          acquisition_follow_up_id: activeFollowUpId.value,
        })
      } else {
        await createBooking(departmentId.value, {
          ...payloadBase,
          booked_at: form.booked_at,
        })
      }
      toast.success(t('accounting.bookings.toastCreated'))
      if (workingFromPending.value) {
        const savedFuId = activeFollowUpId.value
        if (savedFuId) delete assignDrafts[savedFuId]
        workingFromPending.value = false
        activeFollowUpId.value = null
        await refreshPendingFollowUps()
        if (!hasPendingBooking.value) {
          bookingsSubTab.value = 'list'
        }
      }
    }
    closeModal()
    await loadBookingYears()
    await load()
    headerNotificationsStore.requestRefresh()
  } catch (e: unknown) {
    if (fromAssignTab) {
      workingFromPending.value = false
    }
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    toast.error(msg || t('accounting.common.saveFailed'))
  } finally {
    saving.value = false
  }
}

async function onDelete(row: AccountingBooking) {
  const ok = await confirmDialog({
    title: t('accounting.bookings.deleteConfirmTitle'),
    message: t('accounting.bookings.deleteConfirmMessage', {
      date: formatDate(row.booked_at),
      amount: formatMoney(row.amount),
    }),
    confirmText: t('accounting.bookings.confirmDelete'),
    cancelText: t('accounting.common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteBooking(departmentId.value, row.id)
    toast.success(t('accounting.common.deleted'))
    await loadBookingYears()
    await load()
  } catch {
    toast.error(t('accounting.common.deleteFailed'))
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

.booking-assign-followup-tabs {
  margin-bottom: 12px;
}

.booking-assign-tab-meta {
  margin-left: 4px;
  font-weight: 500;
  font-size: 12px;
  color: #6b7280;
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
