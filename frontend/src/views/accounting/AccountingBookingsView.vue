<template>
  <div class="accounting-subpage bookings-page">
    <p class="description" style="margin-bottom: 16px">
      {{ t('accounting.bookings.intro') }}
    </p>

    <v-tabs
      :model-value="bookingsSubTab"
      class="accounting-inner-tabs"
      color="primary"
      @update:model-value="onBookingsSubTabChange"
    >
      <v-tab value="list">{{ t('accounting.bookings.tabList') }}</v-tab>
      <v-tab value="assign">
        {{ t('accounting.bookings.tabAssign') }}
        <span
          v-if="hasPendingBooking"
          class="accounting-tab-badge"
          :title="t('accounting.bookings.badgePendingTitle')"
        >{{ pendingFollowUps.length }}</span>
      </v-tab>
    </v-tabs>

    <EEmptyState
      v-if="!costCenters.length && !ccLoading && !ccError"
      :title="`${t('accounting.bookings.emptyNoCcBefore')}${t('accounting.bookings.tabAssign')}${t('accounting.bookings.emptyNoCcAfter')}`"
    >
      <template #actions>
        <EButton
          variant="primary"
          :to="{ name: 'AccountingCostCenters', params: { departmentId }, query: { openCreate: '1' } }"
        >
          {{ t('accounting.bookings.createCostCenter') }}
        </EButton>
        <EButton
          variant="secondary"
          :to="{ name: 'AccountingCostCenters', params: { departmentId } }"
        >
          {{ t('accounting.bookings.goToCostCenters') }}
        </EButton>
      </template>
    </EEmptyState>

    <template v-else>
      <div v-show="bookingsSubTab === 'list'">
        <div class="bookings-toolbar">
          <div class="bookings-filters">
            <ESelect
              v-model="filterYear"
              :items="yearFilterItems"
              :label="t('accounting.bookings.filterYear')"
              hide-details="auto"
              class="bookings-filter-select"
              @update:model-value="load"
            />
            <ESelect
              v-model="filterCostCenterId"
              :items="costCenterFilterItems"
              :label="t('accounting.bookings.filterCostCenter')"
              hide-details="auto"
              class="bookings-filter-select"
              @update:model-value="load"
            />
          </div>
          <EButton variant="primary" :disabled="isLoading || !costCenters.length" @click="openCreate">
            <v-icon icon="mdi-plus" start size="20" />
            {{ t('accounting.bookings.newBooking') }}
          </EButton>
          <EButton variant="secondary" :disabled="isLoading" @click="exportCsv">
            {{ t('accounting.bookings.exportCsv') }}
          </EButton>
        </div>

        <ELoadingState v-if="isLoading" variant="inline" :message="t('accounting.common.loading')" />
        <p v-else-if="loadError" class="error-inline">{{ loadError }}</p>
        <EEmptyState v-else-if="items.length === 0" :title="t('accounting.bookings.emptyFiltered')" />
        <div v-else class="bookings-table-wrap">
      <table class="cost-centers-table bookings-table">
        <thead>
          <tr>
            <th>{{ t('common.date') }}</th>
            <th>{{ t('common.amount') }}</th>
            <th>{{ t('accounting.common.type') }}</th>
            <th>{{ t('accounting.common.costCenter') }}</th>
            <th>{{ t('common.material') }}</th>
            <th>{{ t('accounting.common.paymentMethod') }}</th>
            <th>{{ t('accounting.bookings.colPaymentStatus') }}</th>
            <th>{{ t('common.group') }}</th>
            <th>{{ t('accounting.bookings.colSource') }}</th>
            <th>{{ t('accounting.common.receipt') }}</th>
            <th>{{ t('accounting.bookings.colAttachments') }}</th>
            <th class="col-actions">{{ t('common.actions') }}</th>
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
            <td>{{ paymentStatusLabel(row.payment_status) }}</td>
            <td>{{ row.group_name || t('accounting.common.dash') }}</td>
            <td class="source-cell">
              <template v-if="row.source?.activity_id">
                <router-link
                  :to="{ name: 'ActivityDetail', params: { departmentId, activityId: row.source.activity_id } }"
                >
                  {{ t('accounting.bookings.sourceActivity') }}
                </router-link>
              </template>
              <template v-else-if="row.source?.material_batch_id && row.material_item_id">
                <router-link
                  :to="{ name: 'MaterialDetail', params: { departmentId, materialId: row.material_item_id } }"
                >
                  {{ t('accounting.bookings.sourceBatch') }}
                </router-link>
              </template>
              <template v-else-if="row.source?.workshop_ticket_id">
                <router-link :to="{ name: 'Workshop', params: { departmentId } }">
                  {{ t('accounting.bookings.sourceWorkshop') }}
                </router-link>
              </template>
              <span v-else class="muted">{{ t('accounting.common.dash') }}</span>
            </td>
            <td class="muted">{{ row.receipt_label || t('accounting.common.dash') }}</td>
            <td>
              <span v-if="row.receipts?.length">{{ row.receipts.length }}</span>
              <span v-else class="muted">{{ t('accounting.common.dash') }}</span>
            </td>
            <td class="col-actions">
              <EButton variant="text" size="small" :title="t('common.edit')" @click="openEdit(row)">
                <v-icon icon="mdi-pencil-outline" size="20" />
              </EButton>
              <EButton variant="text" size="small" color="error" :title="t('common.delete')" @click="onDelete(row)">
                <v-icon icon="mdi-delete-outline" size="20" />
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>
        </div>
      </div>

      <div v-show="bookingsSubTab === 'assign'" class="booking-assign-panel">
        <EEmptyState
          v-if="!hasPendingBooking"
          :title="`${t('accounting.bookings.assignEmptyBefore')}${t('accounting.common.costCenter')}${t('accounting.bookings.assignEmptyAfter')}`"
        />
        <template v-else>
          <p class="booking-assign-lead">
            {{ t('accounting.bookings.assignLeadBefore') }}<strong>{{ t('accounting.common.costCenter') }}</strong>{{ t('accounting.bookings.assignLeadAfter') }}
          </p>
          <p
            v-if="pendingFollowUps[assignTabIndex]?.activity_name"
            class="booking-assign-activity-hint text-muted"
          >
            {{ t('accounting.bookings.assignFromActivity', { name: pendingFollowUps[assignTabIndex].activity_name }) }}
          </p>
          <div v-if="batchAssignCandidates.length > 1" class="batch-assign-panel">
            <p class="batch-assign-hint">{{ t('accounting.bookings.batchAssignHint') }}</p>
            <div class="batch-assign-checks">
              <label v-for="fu in batchAssignCandidates" :key="fu.id" class="batch-assign-check">
                <input v-model="batchSelectedIds" type="checkbox" :value="fu.id" />
                {{ t(accountingFollowUpKindKey(fu.source_kind)) }} · CHF {{ formatMoney(fu.amount) }}
              </label>
            </div>
            <EButton
              variant="secondary"
              size="small"
              :loading="batchSaving"
              :disabled="batchSelectedIds.length === 0"
              @click="runBatchAssign"
            >
              {{ t('accounting.bookings.batchAssignButton', { count: batchSelectedIds.length }) }}
            </EButton>
          </div>
          <v-tabs
            v-if="pendingFollowUps.length > 1"
            :model-value="String(assignTabIndex)"
            class="accounting-inner-tabs booking-assign-followup-tabs"
            color="primary"
            :aria-label="t('accounting.bookings.pendingTabsAria')"
            @update:model-value="onAssignTabChange"
          >
            <v-tab
              v-for="(fu, idx) in pendingFollowUps"
              :key="fu.id"
              :value="String(idx)"
            >
              {{ t(accountingFollowUpKindKey(fu.source_kind)) }}
              <span class="booking-assign-tab-meta">· CHF {{ formatMoney(fu.amount) }}</span>
              <v-icon
                v-if="fu.receipts?.length"
                icon="mdi-paperclip"
                size="14"
                class="booking-assign-tab-receipt"
              />
            </v-tab>
          </v-tabs>
          <div class="booking-assign-form">
            <div class="booking-assign-form-row">
              <ETextField
                v-model="form.amount"
                :label="t('accounting.bookings.labelAmountChf')"
                :placeholder="t('accounting.bookings.placeholderAmount')"
                inputmode="decimal"
                hide-details="auto"
              />
              <ETextField
                v-model="form.booked_at"
                type="date"
                :label="t('accounting.bookings.labelBookingDate')"
                hide-details="auto"
              />
            </div>
            <ESelect
              v-model="form.cost_center_id"
              :items="costCenterSelectItems"
              :label="t('accounting.bookings.labelCostCenterStar')"
              :placeholder="t('accounting.common.pleaseSelect')"
              hide-details="auto"
              class="mt-3"
            />
            <ESelect
              v-model="form.entry_type"
              :items="entryOptions"
              item-title="label"
              item-value="value"
              :label="t('accounting.bookings.labelEntryTypeStar')"
              hide-details="auto"
              class="mt-3"
            />
            <ESelect
              v-model="form.payment_method"
              :items="paymentSelectItems"
              item-title="label"
              item-value="value"
              :label="t('accounting.bookings.labelPaymentOptional')"
              hide-details="auto"
              class="mt-3"
            />
            <ESelect
              v-model="form.payment_status"
              :items="paymentStatusSelectItems"
              item-title="label"
              item-value="value"
              :label="t('accounting.bookings.labelPaymentStatus')"
              hide-details="auto"
              class="mt-3"
            />
            <ESelect
              v-model="form.group_id"
              :items="groupSelectItems"
              item-title="label"
              item-value="value"
              :label="t('accounting.bookings.labelGroupOptional')"
              hide-details="auto"
              class="mt-3"
            />
            <ETextField
              v-model="form.receipt_label"
              class="mt-3"
              :label="t('accounting.bookings.labelReceiptOptional')"
              :placeholder="t('accounting.bookings.placeholderReceipt')"
              maxlength="255"
              hide-details="auto"
            />
            <div class="mt-3">
              <label class="booking-field-label">{{ t('accounting.bookings.labelMaterialOptional') }}</label>
              <p class="acc-field-hint">{{ t('accounting.bookings.materialAssignFromWizardHint') }}</p>
              <MaterialLookupInput
                v-model="materialLookupDisplay"
                :fetcher="bookingMaterialLookupFetcher"
                :min-chars="1"
                :max-suggestions="12"
                :placeholder="t('accounting.bookings.placeholderMaterialSearch')"
                :get-result-key="(item) => item.id"
                @select="onBookingMaterialSelect"
              />
              <EButton
                v-if="form.material_item_id"
                variant="text"
                size="small"
                class="booking-clear-material"
                @click="clearBookingMaterial"
              >
                {{ t('accounting.bookings.clearMaterialLink') }}
              </EButton>
            </div>
            <ETextarea
              v-model="form.notes"
              class="mt-3"
              :label="t('accounting.bookings.labelNotesOptional')"
              :placeholder="t('accounting.bookings.placeholderNotesShort')"
              rows="3"
              hide-details="auto"
            />
            <div v-if="activeFollowUpId" class="mt-4 booking-receipts-section">
              <label class="booking-field-label">{{ t('accounting.bookings.receiptAttachmentsLabel') }}</label>
              <p class="acc-field-hint">{{ t('accounting.bookings.followUpReceiptHint') }}</p>
              <BookingReceiptAttachments
                :department-id="departmentId"
                :follow-up-id="activeFollowUpId"
                :receipts="assignFollowUpReceipts"
                :show-empty="true"
                @update:receipts="onAssignFollowUpReceiptsUpdate"
              />
            </div>
            <div class="booking-assign-actions">
              <EButton variant="primary" :loading="saving" @click="save(true)">
                {{ saving ? t('accounting.bookings.saveAssignSaving') : t('accounting.bookings.saveAssign') }}
              </EButton>
            </div>
          </div>
        </template>
      </div>
    </template>

    <EDialog
      v-model="modalOpen"
      :max-width="640"
      :title="editingId ? t('accounting.bookings.modalEditTitle') : t('accounting.bookings.modalCreateTitle')"
    >
      <div class="booking-assign-form-row">
        <ETextField
          v-model="form.amount"
          :label="t('accounting.bookings.labelAmountChf')"
          :placeholder="t('accounting.bookings.placeholderAmount')"
          inputmode="decimal"
          hide-details="auto"
        />
        <ETextField
          v-model="form.booked_at"
          type="date"
          :label="t('accounting.bookings.labelBookingDate')"
          :disabled="!!editingId"
          :hint="editingId ? t('accounting.bookings.dateFixedHint') : undefined"
          hide-details="auto"
        />
      </div>
      <ESelect
        v-model="form.cost_center_id"
        :items="costCenterSelectItems"
        :label="t('accounting.bookings.labelCostCenterStar')"
        :placeholder="t('accounting.common.pleaseSelect')"
        hide-details="auto"
        class="mt-3"
      />
      <ESelect
        v-model="form.entry_type"
        :items="entryOptions"
        item-title="label"
        item-value="value"
        :label="t('accounting.bookings.labelEntryTypeStar')"
        hide-details="auto"
        class="mt-3"
      />
      <ESelect
        v-model="form.payment_method"
        :items="paymentSelectItems"
        item-title="label"
        item-value="value"
        :label="t('accounting.bookings.labelPaymentOptional')"
        hide-details="auto"
        class="mt-3"
      />
      <ESelect
        v-model="form.payment_status"
        :items="paymentStatusSelectItems"
        item-title="label"
        item-value="value"
        :label="t('accounting.bookings.labelPaymentStatus')"
        hide-details="auto"
        class="mt-3"
      />
      <ESelect
        v-model="form.group_id"
        :items="groupSelectItems"
        item-title="label"
        item-value="value"
        :label="t('accounting.bookings.labelGroupOptional')"
        hide-details="auto"
        class="mt-3"
      />
      <ETextField
        v-model="form.receipt_label"
        class="mt-3"
        :label="t('accounting.bookings.labelReceiptOptional')"
        :placeholder="t('accounting.bookings.placeholderReceipt')"
        maxlength="255"
        hide-details="auto"
      />
      <div class="mt-3">
        <label class="booking-field-label">{{ t('accounting.bookings.labelMaterialOptional') }}</label>
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
        <EButton
          v-if="form.material_item_id"
          variant="text"
          size="small"
          class="booking-clear-material"
          @click="clearBookingMaterial"
        >
          {{ t('accounting.bookings.clearMaterialLink') }}
        </EButton>
      </div>
      <ETextarea
        v-model="form.notes"
        class="mt-3"
        :label="t('accounting.bookings.labelNotesOptional')"
        :placeholder="t('accounting.bookings.placeholderNotesShort')"
        rows="3"
        hide-details="auto"
      />
      <div class="mt-4 booking-receipts-section">
        <label class="booking-field-label">{{ t('accounting.bookings.receiptAttachmentsLabel') }}</label>
        <BookingReceiptAttachments
          :department-id="departmentId"
          :booking-id="editingId"
          :receipts="modalReceipts"
          :show-empty="!!editingId"
          @update:receipts="onModalReceiptsUpdate"
        />
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeModal">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :loading="saving" @click="save(false)">
          {{ saving ? t('accounting.common.saving') : t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { listCostCenters, type AccountingCostCenter } from '@/api/accountingCostCenters'
import {
  listBookings,
  createBooking,
  updateBooking,
  deleteBooking,
  exportBookingsCsv,
  type AccountingBooking
} from '@/api/accountingBookings'
import {
  listAcquisitionFollowups,
  batchRecordFollowUps,
  type AccountingAcquisitionFollowUp
} from '@/api/accountingAcquisitionFollowups'
import { listCostCenterRules, type AccountingCostCenterRule } from '@/api/accountingCostCenterRules'
import { getGroups, type Group } from '@/api/groups'
import type { Material } from '@/api/materials'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useAccountingBookingYears } from '@/composables/useAccountingBookingYears'
import { createBasicMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import {
  accountingFollowUpKindKey,
  sortFollowUpsForDisplay,
} from '@/utils/accountingFollowUpLabels'
import {
  suggestCostCenterId,
  suggestPaymentMethodForFollowUp,
  suggestEntryTypeForFollowUp,
} from '@/utils/accountingCostCenterSuggest'
import BookingReceiptAttachments from '@/components/accounting/BookingReceiptAttachments.vue'
import type { MediaPhoto } from '@/api/media'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'
import '@/styles/views/accounting-tabs.css'

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
  payment_status: 'paid' as string,
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
const PAYMENT_STATUS_KEYS = ['open', 'paid', 'cancelled'] as const

const costCenterRules = ref<AccountingCostCenterRule[]>([])
const batchSelectedIds = ref<string[]>([])
const batchSaving = ref(false)

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

const yearFilterItems = computed(() => [
  { title: t('accounting.common.allYears'), value: '' },
  ...bookingYears.value.map((y) => ({ title: String(y), value: String(y) })),
])

const costCenterFilterItems = computed(() => [
  { title: t('accounting.common.all'), value: '' },
  ...costCenters.value.map((c) => ({ title: c.name, value: c.id })),
])

const costCenterSelectItems = computed(() =>
  costCenters.value.map((c) => ({ title: c.name, value: c.id }))
)

const paymentSelectItems = computed(() => [
  { label: t('accounting.common.dash'), value: '' },
  ...paymentOptions.value,
])

const paymentStatusSelectItems = computed(() =>
  PAYMENT_STATUS_KEYS.map((value) => ({
    label: t(`accounting.paymentStatus.${value}`),
    value,
  }))
)

const batchAssignCandidates = computed(() => {
  const current = pendingFollowUps.value[assignTabIndex.value]
  if (!current?.activity_id) return []
  return pendingFollowUps.value.filter((f) => f.activity_id === current.activity_id)
})

const groupSelectItems = computed(() => [
  { label: t('accounting.common.dash'), value: '' },
  ...groups.value.map((g) => ({ label: g.name, value: g.id })),
])

const { years: bookingYears, refreshYears: loadBookingYears } = useAccountingBookingYears(departmentId)

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
const modalReceipts = ref<MediaPhoto[]>([])
const assignFollowUpReceipts = ref<MediaPhoto[]>([])
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
      material_item_id: string
      material_lookup_display: string
    }
  >
>({})
/** Letzte Speicherung kam aus Tab „Neue Buchung zuordnen“ (Anschaffung aus Material). */
const workingFromPending = ref(false)

async function refreshPendingFollowUps() {
  if (!departmentId.value) return
  try {
    const activityFilter = String(route.query.activity_id || '').trim()
    let rows = await listAcquisitionFollowups(departmentId.value, 'pending')
    if (activityFilter) {
      rows = rows.filter((f) => f.activity_id === activityFilter)
    }
    pendingFollowUps.value = sortFollowUpsForDisplay(rows)
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
    material_item_id: form.material_item_id,
    material_lookup_display: materialLookupDisplay.value,
  }
}

function paymentStatusLabel(k: string | null | undefined): string {
  if (!k) return t('accounting.common.dash')
  const key = `accounting.paymentStatus.${k}`
  return te(key) ? t(key) : k
}

function defaultEntryTypeForFollowUp(p: AccountingAcquisitionFollowUp): string {
  return suggestEntryTypeForFollowUp(p, costCenterRules.value)
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
    form.material_item_id = draft.material_item_id
    materialLookupDisplay.value = draft.material_lookup_display
  } else {
    form.amount = p.amount
    form.booked_at = p.suggested_date
    form.receipt_label = p.receipt_label || ''
    form.entry_type = defaultEntryTypeForFollowUp(p)
    const suggestedCc = suggestCostCenterId(p, costCenters.value, costCenterRules.value)
    form.cost_center_id = suggestedCc || costCenters.value[0]?.id || ''
    const suggestedPay = suggestPaymentMethodForFollowUp(p, costCenterRules.value)
    form.payment_method = suggestedPay || ''
    form.payment_status =
      p.charge_target === 'external_customer' || p.activity_type === 'external' ? 'open' : 'paid'
    const chargeTarget = p.charge_target ?? (p.activity_type === 'external' ? 'external_customer' : 'group')
    if (chargeTarget === 'group') {
      form.group_id = p.suggested_group_id || p.activity_group_id || ''
    } else {
      form.group_id = ''
    }

    const noteParts: string[] = []
    if (p.activity_id) {
      noteParts.push(`Aktivität: ${p.activity_name || p.activity_id}`)
    }
    if (chargeTarget === 'external_customer' && p.external_customer_label) {
      noteParts.push(`Kunde: ${p.external_customer_label}`)
    }
    if (chargeTarget === 'department' && p.material_department_name) {
      noteParts.push(`Verrechnung Material-Dep.: ${p.material_department_name}`)
    }
    if (p.reported_by_display_name) {
      noteParts.push(`Gemeldet von: ${p.reported_by_display_name}`)
    }
    form.notes = noteParts.join(' · ')

    if (chargeTarget === 'external_customer' && !form.receipt_label && p.external_customer_label) {
      form.receipt_label = p.external_customer_label
    }

    if (p.material_item_id) {
      form.material_item_id = p.material_item_id
      materialLookupDisplay.value = p.material_name || ''
    } else {
      form.material_item_id = ''
      materialLookupDisplay.value = ''
    }
  }
  assignFollowUpReceipts.value = [...(p.receipts ?? [])]
}

function onAssignFollowUpReceiptsUpdate(receipts: MediaPhoto[]) {
  assignFollowUpReceipts.value = receipts
  const id = activeFollowUpId.value
  if (!id) return
  const idx = pendingFollowUps.value.findIndex((f) => f.id === id)
  if (idx >= 0) {
    pendingFollowUps.value[idx] = { ...pendingFollowUps.value[idx], receipts }
  }
}

function selectAssignTab(idx: number) {
  if (idx < 0 || idx >= pendingFollowUps.value.length || idx === assignTabIndex.value) return
  persistCurrentAssignDraft()
  assignTabIndex.value = idx
  const fu = pendingFollowUps.value[idx]
  if (fu) loadAssignFormForFollowUp(fu)
}

async function onBookingsSubTabChange(tab: unknown) {
  if (tab === 'assign') {
    bookingsSubTab.value = 'assign'
    await refreshPendingFollowUps()
  } else {
    bookingsSubTab.value = 'list'
  }
}

async function openAssignTab() {
  await onBookingsSubTabChange('assign')
}

function onAssignTabChange(tab: unknown) {
  const idx = Number.parseInt(String(tab), 10)
  if (!Number.isNaN(idx)) selectAssignTab(idx)
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
    costCenterRules.value = await listCostCenterRules(departmentId.value)
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

async function applyBookingYearsFilter() {
  await loadBookingYears()
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
  const q = route.query
  if (String(q.sub || '') !== 'assign' && String(q.assign || '') !== '1') return
  bookingsSubTab.value = 'assign'
  await refreshPendingFollowUps()
  const nextQuery: Record<string, string> = {}
  const actId = String(q.activity_id || '').trim()
  if (actId) nextQuery.activity_id = actId
  await router.replace({
    name: 'AccountingBookings',
    params: { departmentId: departmentId.value },
    query: nextQuery,
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
  form.payment_status = 'paid'
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
  modalReceipts.value = []
  modalOpen.value = true
}

function onModalReceiptsUpdate(receipts: MediaPhoto[]) {
  modalReceipts.value = receipts
  if (editingId.value) {
    const idx = items.value.findIndex((b) => b.id === editingId.value)
    if (idx >= 0) {
      items.value[idx] = { ...items.value[idx], receipts }
    }
  }
}

function openEdit(row: AccountingBooking) {
  workingFromPending.value = false
  activeFollowUpId.value = null
  editingId.value = row.id
  modalReceipts.value = [...(row.receipts ?? [])]
  form.amount = row.amount
  form.booked_at = row.booked_at
  form.cost_center_id = row.cost_center_id
  form.entry_type = row.entry_type
  form.payment_method = row.payment_method || ''
  form.payment_status = row.payment_status || 'paid'
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
    payment_status: form.payment_status || 'paid',
    group_id: form.group_id || null,
    receipt_label: form.receipt_label.trim() || null,
    notes: form.notes.trim() || null,
    material_item_id: form.material_item_id || null,
  }

  saving.value = true
  try {
    if (editingId.value) {
      const updated = await updateBooking(departmentId.value, editingId.value, payloadBase)
      modalReceipts.value = updated.receipts ?? modalReceipts.value
      toast.success(t('accounting.bookings.toastSaved'))
    } else {
      let created: AccountingBooking
      if (fromAssignTab && activeFollowUpId.value) {
        created = await createBooking(departmentId.value, {
          ...payloadBase,
          booked_at: form.booked_at,
          acquisition_follow_up_id: activeFollowUpId.value,
        })
      } else {
        created = await createBooking(departmentId.value, {
          ...payloadBase,
          booked_at: form.booked_at,
        })
      }
      editingId.value = created.id
      modalReceipts.value = created.receipts ?? []
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
    if (!fromAssignTab && editingId.value) {
      // Modal offen lassen für Beleg-Upload nach dem ersten Speichern
    } else {
      closeModal()
    }
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

async function exportCsv() {
  if (!filterYear.value) {
    toast.error(t('accounting.bookings.exportCsvYearRequired'))
    return
  }
  try {
    const blob = await exportBookingsCsv(departmentId.value, Number(filterYear.value))
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `buchungen-${departmentId.value}-${filterYear.value}.csv`
    a.click()
    URL.revokeObjectURL(url)
    toast.success(t('accounting.bookings.csvDownloaded'))
  } catch {
    toast.error(t('accounting.bookings.exportFailed'))
  }
}

async function runBatchAssign() {
  if (batchSelectedIds.value.length === 0) {
    toast.error(t('accounting.bookings.batchAssignNone'))
    return
  }
  if (!form.cost_center_id || !form.entry_type) {
    toast.error(t('accounting.bookings.toastCostCenterRequired'))
    return
  }
  batchSaving.value = true
  try {
    const result = await batchRecordFollowUps(departmentId.value, {
      follow_up_ids: batchSelectedIds.value,
      cost_center_id: form.cost_center_id,
      entry_type: form.entry_type,
      payment_method: form.payment_method || null,
      payment_status: form.payment_status || null,
    })
    toast.success(t('accounting.bookings.batchAssignSuccess', { count: result.count }))
    batchSelectedIds.value = []
    await refreshPendingFollowUps()
    await loadBookingYears()
    await load()
    headerNotificationsStore.requestRefresh()
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    toast.error(msg || t('accounting.common.saveFailed'))
  } finally {
    batchSaving.value = false
  }
}

function deleteConfirmMessage(row: AccountingBooking): string {
  const parts = [
    t('accounting.bookings.deleteConfirmMessage', {
      date: formatDate(row.booked_at),
      amount: formatMoney(row.amount),
    }),
    t('accounting.bookings.deleteConfirmWarning'),
  ]
  if (row.source?.follow_up_id) {
    parts.push(t('accounting.bookings.deleteConfirmFollowUpWarning'))
  }
  return parts.join(' ')
}

async function onDelete(row: AccountingBooking) {
  const ok = await confirmDialog({
    title: t('accounting.bookings.deleteConfirmTitle'),
    message: deleteConfirmMessage(row),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteBooking(departmentId.value, row.id)
    toast.success(t('accounting.common.deleted'))
    await loadBookingYears()
    await load()
    headerNotificationsStore.requestRefresh()
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

.bookings-filter-select {
  min-width: 180px;
}

.booking-assign-form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.booking-field-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

.bookings-table-wrap {
  overflow-x: auto;
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.bookings-table {
  min-width: 1100px;
}

.bookings-page {
  max-width: none;
}

.bookings-table .col-actions {
  position: sticky;
  right: 0;
  z-index: 2;
  width: 108px;
  min-width: 108px;
  text-align: right;
  white-space: nowrap;
  background: #fff;
  box-shadow: -6px 0 10px -8px rgba(15, 23, 42, 0.35);
}

.bookings-table thead .col-actions {
  background: #f9fafb;
  z-index: 3;
}

.batch-assign-panel {
  margin: 12px 0 16px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
}

.batch-assign-hint {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 10px;
}

.batch-assign-checks {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 10px;
}

.batch-assign-check {
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.source-cell a {
  font-size: 13px;
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

.error-inline {
  padding: 16px;
  border-radius: 8px;
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

.booking-assign-tab-receipt {
  margin-left: 4px;
  opacity: 0.85;
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
