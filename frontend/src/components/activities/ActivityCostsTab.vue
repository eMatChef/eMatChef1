<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useActivityTabLoad } from '@/composables/useActivityTabLoad'
import { useI18n } from 'vue-i18n'
import {
  getActivityIssues,
  getActivityItems,
  upsertActivityCollectionNote,
  type ActivityIssueReportRow,
  type ActivityItemRow,
} from '@/api/activities'
import type { ActivityApiType } from '@/api/activities'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import ActivityTabPanelShell from '@/components/activities/ActivityTabPanelShell.vue'
import {
  aggregateConsumableRows,
  aggregateRentalRows,
  consumableCostTotal,
  consumableDisplayName,
  consumableLineCost,
  consumableUsedQty,
  formatChf,
  formatChfLabel,
  replenishmentGroupsBySubmitterDepartment,
  replenishmentPurchaseRows,
  replenishmentPurchaseTotal,
  rentalCostTotal,
} from '@/components/activities/activityCosts'
import { getWorkshopTickets, type WorkshopTicket } from '@/api/workshop'
import {
  listActivityAcquisitionFollowups,
  type AccountingAcquisitionFollowUp,
} from '@/api/accountingAcquisitionFollowups'
import { departmentHasAccountingRole } from '@/composables/useCostBookingFollowUp'
import {
  accountingFollowUpKindKey,
  sortFollowUpsForDisplay,
} from '@/utils/accountingFollowUpLabels'
import { resolveActivityPrimaryChargeTarget } from '@/utils/activityChargeTarget'
import {
  DEFAULT_ACCOUNTING_SETTINGS,
  getAccountingSettings,
  type AccountingSettlementTiming,
} from '@/api/departmentSettings'
import EButton from '@/components/form/base/EButton.vue'

defineOptions({ name: 'ActivityCostsTab' })

const props = defineProps<{
  activityId: string
  departmentId: string
  activityType: ActivityApiType
  activityStatus: string
  reloadToken?: number
  groupName?: string | null
  externalCustomerLabel?: string | null
  activityName?: string | null
  costsReleased?: boolean
  costsReleaseLoading?: boolean
  /** false solange noch Material zum Einlagern offen ist */
  canReleaseCosts?: boolean
  collectionNote?: 'cash' | 'invoice' | null
  collectionNoteAmount?: number | null
  /** MW/DC: Vermerk setzen */
  canEditCollectionNote?: boolean
}>()

const emit = defineEmits<{
  'release-costs': [payload?: { total: number }]
  'costs-preview': [payload: { total: number }]
  'collection-note-updated': [payload: {
    collection_note: 'cash' | 'invoice' | null
    collection_note_amount: number | null
    collection_note_at: string | null
  }]
}>()

const { t, locale } = useI18n()
const { showFullLoading, isRefreshing, resetTabLoad, withTabLoad } = useActivityTabLoad()
const activityItems = ref<ActivityItemRow[]>([])
const issues = ref<ActivityIssueReportRow[]>([])
const workshopTickets = ref<WorkshopTicket[]>([])
const pendingAccounting = ref<AccountingAcquisitionFollowUp[]>([])
const recordedAccounting = ref<AccountingAcquisitionFollowUp[]>([])
const showAccountingLink = computed(() => departmentHasAccountingRole(props.departmentId))

const settlementTiming = ref<AccountingSettlementTiming>(
  DEFAULT_ACCOUNTING_SETTINGS.settlementTimingConsumable,
)
const noteDraft = ref<'cash' | 'invoice' | null>(null)
const noteAmountDraft = ref<number | null>(null)
const noteSaving = ref(false)
const noteError = ref('')

const showCostReleasePanel = computed(
  () =>
    ['returned', 'storing'].includes(props.activityStatus) &&
    props.canReleaseCosts !== false,
)

const hasNoCosts = computed(() => grandTotal.value <= 0)

const costsReleaseDone = computed(
  () => Boolean(props.costsReleased) || hasNoCosts.value,
)

const showCollectionNotePanel = computed(
  () =>
    Boolean(props.canEditCollectionNote) &&
    ['returned', 'storing', 'completed'].includes(props.activityStatus) &&
    !hasNoCosts.value,
)

const collectionNoteProminent = computed(
  () => settlementTiming.value === 'offer_at_activity',
)

const savedCollectionNote = computed(() => props.collectionNote ?? null)

const showAccountingTasks = computed(() => props.activityStatus === 'completed')

const allAccountingFollowUps = computed(() =>
  sortFollowUpsForDisplay([...pendingAccounting.value, ...recordedAccounting.value]),
)

const pendingAccountingSorted = computed(() =>
  sortFollowUpsForDisplay(pendingAccounting.value),
)

const accountingHandoffTotal = computed(() =>
  allAccountingFollowUps.value.reduce((s, f) => s + (parseFloat(f.amount) || 0), 0),
)

const pendingAccountingTotal = computed(() =>
  pendingAccountingSorted.value.reduce((s, f) => s + (parseFloat(f.amount) || 0), 0),
)

const chargeTarget = computed(() =>
  resolveActivityPrimaryChargeTarget({
    activityType: props.activityType,
    groupName: props.groupName,
    externalCustomerLabel: props.externalCustomerLabel,
    activityName: props.activityName,
  }),
)

const chargeTargetLabel = computed(() => {
  const target = chargeTarget.value
  if (target.kind === 'group' && target.label) {
    return t('activities.costs.chargeGroup', { name: target.label })
  }
  if (target.kind === 'external_customer' && target.label) {
    return t('activities.costs.chargeExternal', { name: target.label })
  }
  return t('activities.costs.chargeUnknown')
})

const accountingLinkDepartmentId = computed(() => {
  const fu = allAccountingFollowUps.value[0] ?? pendingAccountingSorted.value[0]
  return fu?.department_id ?? props.departmentId
})

function followUpStatusLabel(status: AccountingAcquisitionFollowUp['status']): string {
  return status === 'recorded'
    ? t('activities.costs.accountingStatusRecorded')
    : t('activities.costs.accountingStatusPending')
}

function followUpMetaLine(fu: AccountingAcquisitionFollowUp): string {
  const parts: string[] = []
  if (fu.department_name && fu.department_id !== props.departmentId) {
    parts.push(t('activities.costs.accountingDept', { name: fu.department_name }))
  }
  if (fu.charge_target === 'external_customer' && fu.external_customer_label) {
    parts.push(t('activities.costs.chargeExternal', { name: fu.external_customer_label }))
  } else if (fu.charge_target === 'department' && fu.material_department_name) {
    parts.push(t('activities.costs.chargeMaterialDept', { name: fu.material_department_name }))
  }
  if (fu.reported_by_display_name) {
    parts.push(t('activities.costs.reportedBy', { name: fu.reported_by_display_name }))
  }
  return parts.join(' · ')
}

function reporterForTicket(ticket: WorkshopTicket): string {
  if (ticket.issue_report?.reported_by?.name) {
    return ticket.issue_report.reported_by.name
  }
  const issue = issues.value.find((i) => i.id === ticket.issue_report_id)
  return issue?.reported_by_display_name || ''
}

const consumableRows = computed(() => aggregateConsumableRows(activityItems.value))
const rentalRows = computed(() => aggregateRentalRows(activityItems.value))
const lossIssues = computed(() => issues.value.filter((i) => i.type === 'loss'))

const replenishmentRows = computed(() => replenishmentPurchaseRows(activityItems.value))
const replenishmentGroups = computed(() => replenishmentGroupsBySubmitterDepartment(replenishmentRows.value))
const replenishmentTotal = computed(() => replenishmentPurchaseTotal(replenishmentRows.value))
const consumableTotal = computed(() =>
  consumableCostTotal(activityItems.value, issues.value, { preferExternal: isExternal.value }),
)
const rentalTotal = computed(() => rentalCostTotal(rentalRows.value))

const workshopResolved = computed(() =>
  workshopTickets.value.filter(
    (t) => t.status === 'completed' && (t.resolution_action === 'repaired' || t.resolution_action === 'writeoff'),
  ),
)

const repairTickets = computed(() => workshopResolved.value.filter((t) => t.resolution_action === 'repaired'))
const writeoffTickets = computed(() => workshopResolved.value.filter((t) => t.resolution_action === 'writeoff'))

const repairTotal = computed(() =>
  repairTickets.value.reduce((s, t) => s + (parseFloat(t.actual_cost || '0') || 0), 0),
)
const writeoffTotal = computed(() =>
  writeoffTickets.value.reduce((s, t) => s + (parseFloat(t.actual_cost || '0') || 0), 0),
)

const openWorkshopTickets = computed(() =>
  workshopTickets.value.filter((t) => t.status !== 'completed' && t.status !== 'cancelled'),
)

const isExternal = computed(() => props.activityType === 'external')

const internalGrandTotal = computed(
  () => consumableTotal.value + repairTotal.value + writeoffTotal.value,
)
const externalGrandTotal = computed(
  () => consumableTotal.value + rentalTotal.value + repairTotal.value + writeoffTotal.value,
)
const grandTotal = computed(() => (isExternal.value ? externalGrandTotal.value : internalGrandTotal.value))

function syncNoteDraftFromProps() {
  noteDraft.value = props.collectionNote ?? null
  noteAmountDraft.value =
    props.collectionNoteAmount != null && !Number.isNaN(props.collectionNoteAmount)
      ? props.collectionNoteAmount
      : null
  noteError.value = ''
}

watch(
  () => [props.collectionNote, props.collectionNoteAmount] as const,
  () => syncNoteDraftFromProps(),
  { immediate: true },
)

watch(grandTotal, (total) => {
  if (noteDraft.value && (noteAmountDraft.value == null || noteAmountDraft.value === 0)) {
    noteAmountDraft.value = Math.round(total * 100) / 100
  }
})

async function loadSettlementTiming() {
  try {
    const settings = await getAccountingSettings(props.departmentId)
    settlementTiming.value = isExternal.value
      ? settings.settlementTimingExternal
      : settings.settlementTimingConsumable
  } catch {
    settlementTiming.value = DEFAULT_ACCOUNTING_SETTINGS.settlementTimingConsumable
  }
}

async function saveCollectionNote(note: 'cash' | 'invoice' | null) {
  noteSaving.value = true
  noteError.value = ''
  try {
    const amount =
      note == null
        ? null
        : noteAmountDraft.value != null && !Number.isNaN(noteAmountDraft.value)
          ? noteAmountDraft.value
          : grandTotal.value
    const updated = await upsertActivityCollectionNote(props.activityId, {
      note,
      amount: note == null ? null : amount,
    })
    emit('collection-note-updated', {
      collection_note: updated.collection_note ?? null,
      collection_note_amount: updated.collection_note_amount ?? null,
      collection_note_at: updated.collection_note_at ?? null,
    })
    noteDraft.value = updated.collection_note ?? null
    noteAmountDraft.value = updated.collection_note_amount ?? null
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    noteError.value = ax.response?.data?.error || t('activities.costs.collectionNoteError')
  } finally {
    noteSaving.value = false
  }
}

function usedFor(materialItemId: string): number {
  return consumableUsedQty(materialItemId, issues.value)
}

function lineAmount(row: (typeof consumableRows.value)[number]): string {
  const amount = consumableLineCost(
    row,
    usedFor(row.material_item_id),
    activityItems.value,
    issues.value,
    { preferExternal: isExternal.value },
  )
  if (amount <= 0 && usedFor(row.material_item_id) <= 0) return 'CHF 0.00'
  return formatChfLabel(amount)
}

function formatRecordedAt(iso: string | null | undefined): string {
  if (!iso?.trim()) return '–'
  return new Date(iso).toLocaleString(locale.value, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function load(opts?: { forceFull?: boolean }) {
  await withTabLoad(async () => {
    try {
      const [items, iss, tickets] = await Promise.all([
        getActivityItems(props.activityId),
        getActivityIssues(props.activityId),
        getWorkshopTickets(props.departmentId, { activity_id: props.activityId }),
        loadSettlementTiming(),
      ])
      activityItems.value = items
      issues.value = iss
      workshopTickets.value = tickets
      if (showAccountingTasks.value) {
        try {
          const [pending, recorded] = await Promise.all([
            listActivityAcquisitionFollowups(props.activityId, 'pending'),
            listActivityAcquisitionFollowups(props.activityId, 'recorded'),
          ])
          pendingAccounting.value = pending
          recordedAccounting.value = recorded
        } catch {
          pendingAccounting.value = []
          recordedAccounting.value = []
        }
      } else {
        pendingAccounting.value = []
        recordedAccounting.value = []
      }
    } catch {
      activityItems.value = []
      issues.value = []
      workshopTickets.value = []
      pendingAccounting.value = []
      recordedAccounting.value = []
    }
  }, opts)
}

watch(
  () => [props.activityId, props.reloadToken ?? 0] as const,
  (curr, prev) => {
    const [activityId] = curr
    const prevActivityId = prev?.[0]
    if (prevActivityId != null && activityId !== prevActivityId) {
      resetTabLoad()
    }
    void load()
  },
  { immediate: true },
)

watch(
  [grandTotal, () => props.activityStatus],
  ([total, status]) => {
    if (['returned', 'storing', 'completed'].includes(status)) {
      emit('costs-preview', { total })
    }
  },
)
</script>

<template>
  <div class="activity-costs-tab">
    <ActivityTabHeader :title="t('activities.detail.tabCosts')" />
    <ActivityTabPanelShell
      :loading="showFullLoading"
      :refreshing="isRefreshing"
      :loading-message="t('activities.costs.loading')"
      loading-class="activity-costs-loading"
    >
      <div class="costs-overview">
        <section
          v-if="showCostReleasePanel"
          class="costs-release-panel"
          :class="{ 'costs-release-panel--done': costsReleaseDone }"
        >
          <h3 class="costs-section-title">
            {{ t('activities.costs.releaseSectionTitle') }}
          </h3>
          <template v-if="hasNoCosts">
            <p class="costs-release-done">
              ✓ {{ t('activities.costs.releaseNone') }}
            </p>
          </template>
          <template v-else>
            <p class="costs-release-lead text-muted">{{ t('activities.costs.releaseLead') }}</p>
            <p class="costs-release-charge">{{ chargeTargetLabel }}</p>
            <div class="costs-release-total">
              <span>{{ t('activities.costs.releaseTotal') }}</span>
              <strong>CHF {{ formatChf(grandTotal) }}</strong>
            </div>
            <p class="costs-release-hint text-muted">{{ t('activities.costs.releaseHint') }}</p>
            <EButton
              v-if="!costsReleased"
              variant="primary"
              size="default"
              :loading="costsReleaseLoading"
              @click="emit('release-costs', { total: grandTotal })"
            >
              {{ t('activities.costs.releaseAction') }}
            </EButton>
            <p v-else class="costs-release-done">
              ✓ {{ t('activities.costs.releaseDone') }}
            </p>
          </template>
        </section>

        <section
          v-if="showCollectionNotePanel"
          class="costs-collection-note"
          :class="{
            'costs-collection-note--offer': collectionNoteProminent,
            'costs-collection-note--done': !!savedCollectionNote,
          }"
        >
          <h3 class="costs-section-title">
            {{
              collectionNoteProminent
                ? t('activities.costs.collectionNoteOfferTitle')
                : t('activities.costs.collectionNoteTitle')
            }}
          </h3>
          <p class="costs-collection-lead text-muted">
            {{
              collectionNoteProminent
                ? t('activities.costs.collectionNoteOfferLead')
                : t('activities.costs.collectionNoteLead')
            }}
          </p>
          <p v-if="savedCollectionNote" class="costs-release-done">
            ✓
            {{
              savedCollectionNote === 'cash'
                ? t('activities.costs.collectionNoteCashSaved', {
                    amount: formatChf(props.collectionNoteAmount ?? grandTotal),
                  })
                : t('activities.costs.collectionNoteInvoiceSaved', {
                    amount: formatChf(props.collectionNoteAmount ?? grandTotal),
                  })
            }}
          </p>
          <div class="costs-collection-amount">
            <label class="costs-collection-amount-label" for="collection-note-amount">
              {{ t('activities.costs.collectionNoteAmount') }}
            </label>
            <input
              id="collection-note-amount"
              v-model.number="noteAmountDraft"
              type="number"
              min="0"
              step="0.05"
              class="costs-collection-amount-input"
              :disabled="noteSaving"
            />
            <button
              type="button"
              class="costs-collection-amount-fill"
              :disabled="noteSaving"
              @click="noteAmountDraft = Math.round(grandTotal * 100) / 100"
            >
              {{ t('activities.costs.collectionNoteUseTotal') }}
            </button>
          </div>
          <div class="costs-collection-actions">
            <EButton
              :variant="collectionNoteProminent ? 'primary' : 'secondary'"
              size="small"
              :loading="noteSaving && noteDraft === 'cash'"
              :disabled="noteSaving"
              @click="saveCollectionNote('cash')"
            >
              {{ t('activities.costs.collectionNoteCash') }}
            </EButton>
            <EButton
              variant="secondary"
              size="small"
              :loading="noteSaving && noteDraft === 'invoice'"
              :disabled="noteSaving"
              @click="saveCollectionNote('invoice')"
            >
              {{ t('activities.costs.collectionNoteInvoice') }}
            </EButton>
            <EButton
              v-if="savedCollectionNote"
              variant="secondary"
              size="small"
              :disabled="noteSaving"
              @click="saveCollectionNote(null)"
            >
              {{ t('activities.costs.collectionNoteClear') }}
            </EButton>
          </div>
          <p v-if="noteError" class="costs-collection-error">{{ noteError }}</p>
          <p class="costs-collection-hint text-muted">{{ t('activities.costs.collectionNoteHint') }}</p>
        </section>

        <section
          v-if="showAccountingTasks"
          class="costs-accounting-tasks"
        >
          <h3 class="costs-section-title">
            {{ t('activities.costs.sectionAccountingHandoff') }}
          </h3>
          <p class="costs-accounting-lead text-muted">{{ t('activities.costs.accountingHandoffLead') }}</p>
          <p v-if="allAccountingFollowUps.length === 0" class="costs-empty">
            {{ t('activities.costs.accountingHandoffEmpty') }}
          </p>
          <template v-else>
            <ul class="costs-accounting-list">
              <li v-for="fu in allAccountingFollowUps" :key="fu.id" class="costs-accounting-item">
                <span class="costs-accounting-kind">{{ t(accountingFollowUpKindKey(fu.source_kind)) }}</span>
                <span
                  class="costs-accounting-status"
                  :class="{
                    'costs-accounting-status--pending': fu.status === 'pending',
                    'costs-accounting-status--recorded': fu.status === 'recorded',
                  }"
                >
                  {{ followUpStatusLabel(fu.status) }}
                </span>
                <span class="costs-accounting-amount">CHF {{ formatChf(parseFloat(fu.amount) || 0) }}</span>
                <span v-if="fu.material_name" class="costs-accounting-label text-muted">{{ fu.material_name }}</span>
                <span v-else-if="fu.receipt_label" class="costs-accounting-label text-muted">{{ fu.receipt_label }}</span>
                <span v-if="followUpMetaLine(fu)" class="costs-accounting-meta text-muted">{{ followUpMetaLine(fu) }}</span>
              </li>
            </ul>
            <p class="costs-accounting-footer">
              {{ t('activities.costs.accountingHandoffCount', { n: allAccountingFollowUps.length }) }}
              · {{ t('activities.costs.accountingHandoffSum') }} CHF {{ formatChf(accountingHandoffTotal) }}
              <span v-if="pendingAccountingSorted.length > 0" class="costs-accounting-pending-note">
                · {{ t('activities.costs.accountingPending', { n: pendingAccountingSorted.length }) }}
                (CHF {{ formatChf(pendingAccountingTotal) }})
              </span>
              <router-link
                v-if="showAccountingLink"
                class="costs-accounting-link"
                :to="{
                  name: 'AccountingBookings',
                  params: { departmentId: accountingLinkDepartmentId },
                  query: { sub: 'assign', activity_id: activityId },
                }"
              >
                {{ t('activities.costs.accountingLink') }}
              </router-link>
            </p>
          </template>
        </section>

        <!-- Verbrauchsmaterial -->
        <section class="costs-section">
          <h3 class="costs-section-title">
            <span class="costs-icon" aria-hidden="true">🔥</span>
            {{ t('activities.costs.sectionConsumables') }}
          </h3>
          <p v-if="consumableRows.length === 0" class="costs-empty">{{ t('activities.costs.emptyConsumables') }}</p>
          <template v-else>
            <div class="costs-table">
              <div class="costs-row costs-row-header">
                <span class="costs-col-name">{{ t('common.material') }}</span>
                <span class="costs-col-qty">{{ t('activities.costs.colBooked') }}</span>
                <span class="costs-col-used">{{ t('activities.costs.colUsed') }}</span>
                <span class="costs-col-price">{{ t('activities.costs.colUnitPrice') }}</span>
                <span class="costs-col-total">{{ t('common.amount') }}</span>
              </div>
              <div v-for="row in consumableRows" :key="row.material_item_id" class="costs-row">
                <span class="costs-col-name">{{ consumableDisplayName(row) }}</span>
                <span class="costs-col-qty">{{ row.quantity_booked }}</span>
                <span class="costs-col-used">{{ usedFor(row.material_item_id) || '–' }}</span>
                <span class="costs-col-price">{{ formatChfLabel(row.sale_price) }}</span>
                <span class="costs-col-total">{{ lineAmount(row) }}</span>
              </div>
            </div>
            <div class="costs-subtotal">
              <span>{{ t('activities.costs.subtotalConsumables') }}</span>
              <strong>CHF {{ formatChf(consumableTotal) }}</strong>
            </div>
          </template>
        </section>

        <section v-if="replenishmentRows.length > 0" class="costs-section">
          <h3 class="costs-section-title">
            <span class="costs-icon" aria-hidden="true">🛒</span>
            {{ t('activities.costs.sectionPurchases') }}
          </h3>
          <div
            v-for="group in replenishmentGroups"
            :key="group.department_id"
            class="costs-replenishment-dept-block"
          >
            <h4 class="costs-dept-subtitle">
              {{ t('activities.costs.replenishmentDeptTitle', { name: group.department_name }) }}
              <span v-if="group.submitter_names.length" class="costs-dept-submitters text-muted">
                — {{ t('activities.costs.replenishmentSubmittedBy', { names: group.submitter_names.join(', ') }) }}
              </span>
            </h4>
            <div class="costs-table costs-table--replenishment">
              <div class="costs-row costs-row-header">
                <span class="costs-col-name">{{ t('common.material') }}</span>
                <span class="costs-col-material-dept">{{ t('activities.costs.colMaterialDept') }}</span>
                <span class="costs-col-qty">{{ t('activities.costs.colQty') }}</span>
                <span class="costs-col-recorded">{{ t('activities.costs.colRecorded') }}</span>
                <span class="costs-col-submitter">{{ t('activities.costs.colSubmittedBy') }}</span>
                <span class="costs-col-price">{{ t('activities.costs.colUnitPrice') }}</span>
                <span class="costs-col-total">{{ t('common.amount') }}</span>
              </div>
              <div v-for="row in group.rows" :key="row.id" class="costs-row">
                <span class="costs-col-name">{{ row.material_name }}</span>
                <span class="costs-col-material-dept text-muted">{{ row.source_department_name || '–' }}</span>
                <span class="costs-col-qty">{{ row.quantity }}</span>
                <span class="costs-col-recorded text-muted">{{ formatRecordedAt(row.recorded_at) }}</span>
                <span class="costs-col-submitter">{{ row.created_by_display_name || '–' }}</span>
                <span class="costs-col-price">{{ formatChfLabel(row.unit_purchase) }}</span>
                <span class="costs-col-total">{{ formatChfLabel(row.line_total ?? (row.unit_purchase ?? 0) * row.quantity) }}</span>
              </div>
            </div>
            <div class="costs-subtotal costs-subtotal--inline">
              <span>{{ t('activities.costs.subtotalDeptReplenishment', { name: group.department_name }) }}</span>
              <strong>CHF {{ formatChf(group.total) }}</strong>
            </div>
          </div>
          <p class="costs-purchase-note text-muted">{{ t('activities.costs.purchasesNote') }}</p>
        </section>

        <!-- Ausleihmaterial (extern) -->
        <section v-if="isExternal" class="costs-section">
          <h3 class="costs-section-title">
            <span class="costs-icon" aria-hidden="true">📦</span>
            {{ t('activities.costs.sectionRental') }}
          </h3>
          <p v-if="rentalRows.length === 0" class="costs-empty">{{ t('activities.costs.emptyRental') }}</p>
          <template v-else>
            <div class="costs-table">
              <div class="costs-row costs-row-header">
                <span class="costs-col-name">{{ t('common.material') }}</span>
                <span class="costs-col-qty">{{ t('activities.costs.colQty') }}</span>
                <span class="costs-col-used"></span>
                <span class="costs-col-price">{{ t('activities.costs.colUnitPrice') }}</span>
                <span class="costs-col-total">{{ t('common.amount') }}</span>
              </div>
              <div v-for="row in rentalRows" :key="row.material_item_id" class="costs-row">
                <span class="costs-col-name">{{ row.material_name }}</span>
                <span class="costs-col-qty">{{ row.quantity }}</span>
                <span class="costs-col-used"></span>
                <span class="costs-col-price">{{ formatChfLabel(row.unit_price) }}</span>
                <span class="costs-col-total">{{ formatChfLabel(row.line_total) }}</span>
              </div>
            </div>
            <div class="costs-subtotal">
              <span>{{ t('activities.costs.subtotalRental') }}</span>
              <strong>CHF {{ formatChf(rentalTotal) }}</strong>
            </div>
          </template>
        </section>

        <!-- Verluste -->
        <section v-if="lossIssues.length > 0" class="costs-section costs-section-warn">
          <h3 class="costs-section-title">
            <span class="costs-icon" aria-hidden="true">⚠️</span>
            {{ t('activities.costs.sectionLosses') }}
          </h3>
          <div class="costs-table">
            <div class="costs-row costs-row-header">
              <span class="costs-col-name">{{ t('common.material') }}</span>
              <span class="costs-col-qty">{{ t('activities.costs.colQty') }}</span>
              <span class="costs-col-reporter">{{ t('activities.costs.colReportedBy') }}</span>
              <span class="costs-col-price"></span>
              <span class="costs-col-total">{{ t('common.description') }}</span>
            </div>
            <div v-for="loss in lossIssues" :key="loss.id" class="costs-row">
              <span class="costs-col-name">{{ loss.material_name || '–' }}</span>
              <span class="costs-col-qty">{{ loss.quantity }}</span>
              <span class="costs-col-reporter">{{ loss.reported_by_display_name || '–' }}</span>
              <span class="costs-col-price"></span>
              <span class="costs-col-total costs-loss-desc">{{ loss.description || '–' }}</span>
            </div>
          </div>
          <p class="costs-purchase-note text-muted">{{ t('activities.costs.lossWorkshopHint') }}</p>
        </section>

        <section v-if="openWorkshopTickets.length > 0" class="costs-section costs-section-warn">
          <h3 class="costs-section-title">
            <span class="costs-icon" aria-hidden="true">🔧</span>
            {{ t('activities.costs.sectionWorkshopOpen') }}
          </h3>
          <div class="costs-table">
            <div v-for="ticket in openWorkshopTickets" :key="ticket.id" class="costs-row">
              <span class="costs-col-name">{{ ticket.title }}</span>
              <span class="costs-col-qty">{{ ticket.status_label }}</span>
              <span class="costs-col-used"></span>
              <span class="costs-col-price"></span>
              <span class="costs-col-total costs-loss-desc">{{ t('activities.costs.workshopOpenPending') }}</span>
            </div>
          </div>
        </section>

        <!-- Werkstatt Reparatur -->
        <section v-if="repairTickets.length > 0" class="costs-section">
          <h3 class="costs-section-title">
            <span class="costs-icon" aria-hidden="true">🔧</span>
            {{ t('activities.costs.sectionWorkshopRepair') }}
          </h3>
          <div class="costs-table">
            <div class="costs-row costs-row-header">
              <span class="costs-col-name">{{ t('activities.costs.colTicket') }}</span>
              <span class="costs-col-qty">{{ t('common.status') }}</span>
              <span class="costs-col-reporter">{{ t('activities.costs.colReportedBy') }}</span>
              <span class="costs-col-price"></span>
              <span class="costs-col-total">{{ t('activities.costs.colCost') }}</span>
            </div>
            <div v-for="ticket in repairTickets" :key="ticket.id" class="costs-row">
              <span class="costs-col-name">{{ ticket.title }}</span>
              <span class="costs-col-qty">{{ ticket.status_label }}</span>
              <span class="costs-col-reporter">{{ reporterForTicket(ticket) || '–' }}</span>
              <span class="costs-col-price"></span>
              <span class="costs-col-total">{{ formatChfLabel(parseFloat(ticket.actual_cost || '0') || 0) }}</span>
            </div>
          </div>
          <div class="costs-subtotal">
            <span>{{ t('activities.costs.subtotalRepair') }}</span>
            <strong>CHF {{ formatChf(repairTotal) }}</strong>
          </div>
        </section>

        <!-- Werkstatt Abschreibung -->
        <section v-if="writeoffTickets.length > 0" class="costs-section costs-section-warn">
          <h3 class="costs-section-title">
            <span class="costs-icon" aria-hidden="true">🗑️</span>
            {{ t('activities.costs.sectionWorkshopWriteoff') }}
          </h3>
          <div class="costs-table">
            <div class="costs-row costs-row-header">
              <span class="costs-col-name">{{ t('activities.costs.colTicket') }}</span>
              <span class="costs-col-qty">{{ t('common.status') }}</span>
              <span class="costs-col-reporter">{{ t('activities.costs.colReportedBy') }}</span>
              <span class="costs-col-price"></span>
              <span class="costs-col-total">{{ t('activities.costs.colCost') }}</span>
            </div>
            <div v-for="ticket in writeoffTickets" :key="ticket.id" class="costs-row">
              <span class="costs-col-name">{{ ticket.title }}</span>
              <span class="costs-col-qty">{{ ticket.status_label }}</span>
              <span class="costs-col-reporter">{{ reporterForTicket(ticket) || '–' }}</span>
              <span class="costs-col-price"></span>
              <span class="costs-col-total">{{ formatChfLabel(parseFloat(ticket.actual_cost || '0') || 0) }}</span>
            </div>
          </div>
          <div class="costs-subtotal">
            <span>{{ t('activities.costs.subtotalWriteoff') }}</span>
            <strong>CHF {{ formatChf(writeoffTotal) }}</strong>
          </div>
        </section>

        <!-- Gesamt (Kostenübersicht; Buchhaltung siehe oben) -->
        <section class="costs-total-section" :class="{ 'costs-final': showAccountingTasks }">
          <div class="costs-total-label">
            <strong>{{ t('activities.costs.costOverviewTotal') }}</strong>
            <span v-if="!showAccountingTasks" class="costs-total-hint">{{ t('activities.costs.interimHint') }}</span>
          </div>
          <div class="costs-total-rows">
            <div v-if="consumableTotal > 0" class="costs-total-row">
              <span>{{ t('activities.costs.sectionConsumables') }}</span>
              <span>CHF {{ formatChf(consumableTotal) }}</span>
            </div>
            <div v-if="isExternal && rentalTotal > 0" class="costs-total-row">
              <span>{{ t('activities.costs.sectionRental') }}</span>
              <span>CHF {{ formatChf(rentalTotal) }}</span>
            </div>
            <div v-if="repairTotal > 0" class="costs-total-row">
              <span>{{ t('activities.costs.rowWorkshopRepair') }}</span>
              <span>CHF {{ formatChf(repairTotal) }}</span>
            </div>
            <div v-if="writeoffTotal > 0" class="costs-total-row">
              <span>{{ t('activities.costs.rowWorkshopWriteoff') }}</span>
              <span>CHF {{ formatChf(writeoffTotal) }}</span>
            </div>
            <div class="costs-total-row costs-grand-total">
              <span>{{
                isExternal ? t('activities.costs.grandTotalExternal') : t('activities.costs.grandTotalInternal')
              }}</span>
              <span>CHF {{ formatChf(grandTotal) }}</span>
            </div>
          </div>
        </section>
      </div>
    </ActivityTabPanelShell>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/detail-workflow.css';

.activity-costs-tab {
  max-width: 900px;
}

.activity-costs-loading {
  padding: 8px 0;
}

.costs-overview {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.costs-release-panel {
  padding: 14px 16px;
  border: 1px solid #fcd34d;
  border-radius: 10px;
  background: #fffbeb;
}

.costs-release-panel--done {
  border-color: #86efac;
  background: #f0fdf4;
}

.costs-release-lead {
  margin: 0 0 8px;
  font-size: 13px;
}

.costs-release-charge {
  margin: 0 0 10px;
  font-size: 15px;
  font-weight: 600;
}

.costs-release-total {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
  font-size: 14px;
}

.costs-release-hint {
  margin: 0 0 12px;
  font-size: 12px;
}

.costs-release-done {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #16a34a;
}

.costs-collection-note {
  padding: 14px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
}

.costs-collection-note--offer {
  border-color: #93c5fd;
  background: #eff6ff;
}

.costs-collection-note--done {
  border-color: #86efac;
  background: #f0fdf4;
}

.costs-collection-lead {
  margin: 0 0 10px;
  font-size: 13px;
}

.costs-collection-amount {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 10px 0 12px;
}

.costs-collection-amount-label {
  font-size: 13px;
  font-weight: 500;
}

.costs-collection-amount-input {
  width: 7.5rem;
  padding: 6px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 14px;
}

.costs-collection-amount-fill {
  border: none;
  background: none;
  color: #2563eb;
  font-size: 12px;
  text-decoration: underline;
  cursor: pointer;
  padding: 0;
}

.costs-collection-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 8px;
}

.costs-collection-hint {
  margin: 0;
  font-size: 12px;
}

.costs-collection-error {
  margin: 0 0 8px;
  font-size: 13px;
  color: #b91c1c;
}

.costs-accounting-banner {
  margin: 0 0 4px;
  padding: 10px 12px;
  font-size: 13px;
  line-height: 1.45;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  color: #1e3a5f;
}

.costs-accounting-link {
  margin-left: 6px;
  font-weight: 600;
  color: #2563eb;
}

.costs-accounting-tasks {
  padding: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.costs-accounting-lead {
  margin: 0 0 8px;
  font-size: 13px;
}

.costs-accounting-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.costs-accounting-item {
  display: grid;
  grid-template-columns: minmax(120px, 1fr) auto auto;
  gap: 4px 12px;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #e2e8f0;
  font-size: 13px;
}

.costs-accounting-item:last-child {
  border-bottom: none;
}

.costs-accounting-kind {
  font-weight: 600;
}

.costs-accounting-status {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  white-space: nowrap;
}

.costs-accounting-status--pending {
  background: #fff7ed;
  color: #c2410c;
}

.costs-accounting-status--recorded {
  background: #f0fdf4;
  color: #15803d;
}

.costs-accounting-amount {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.costs-accounting-label,
.costs-accounting-meta {
  grid-column: 1 / -1;
  font-size: 12px;
}

.costs-col-reporter {
  min-width: 6rem;
  font-size: 0.9rem;
}

.costs-accounting-footer {
  margin: 10px 0 0;
  font-size: 13px;
}

.costs-replenishment-dept-block {
  margin-bottom: 16px;
}

.costs-replenishment-dept-block:last-of-type {
  margin-bottom: 8px;
}

.costs-dept-subtitle {
  margin: 0 0 8px;
  font-size: 14px;
  font-weight: 600;
}

.costs-dept-submitters {
  font-weight: 400;
}

.costs-table--replenishment .costs-row {
  grid-template-columns: 1.4fr 0.9fr 0.45fr 1.05fr 0.85fr 0.7fr 0.75fr;
}

.costs-table--replenishment .costs-col-material-dept,
.costs-table--replenishment .costs-col-submitter,
.costs-table--replenishment .costs-col-recorded {
  font-size: 12px;
  color: #6b7280;
}

.costs-table--replenishment .costs-col-qty,
.costs-table--replenishment .costs-col-price {
  text-align: center;
}

.costs-table--replenishment .costs-col-total {
  text-align: right;
}

.costs-subtotal--inline {
  margin-top: 6px;
}
</style>
