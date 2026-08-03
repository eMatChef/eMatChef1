<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { ActivityApiType, ActivityCompletionBlockers } from '@/api/activities'
import { accountingFollowUpKindKey } from '@/utils/accountingFollowUpLabels'
import { departmentHasAccountingRole } from '@/composables/useCostBookingFollowUp'
import { resolveActivityPrimaryChargeTarget } from '@/utils/activityChargeTarget'
import EButton from '@/components/form/base/EButton.vue'

defineOptions({ name: 'ActivityCompletionChecklist' })

const props = defineProps<{
  blockers: ActivityCompletionBlockers
  activityId: string
  hostDepartmentId: string
  activityStatus?: string
  activityType?: ActivityApiType | string
  groupName?: string | null
  externalCustomerLabel?: string | null
  activityName?: string | null
  costsReleased?: boolean
  costsReleaseLoading?: boolean
  /** Vorschau-Summe aus Kosten-Tab; 0 = Schritt automatisch erledigt */
  costsTotal?: number | null
}>()

const emit = defineEmits<{
  'go-tab': [tab: 'packs' | 'issues' | 'costs']
  'release-costs': []
}>()

const { t } = useI18n()
const router = useRouter()

const isPreCompletionPhase = computed(() =>
  ['returned', 'storing'].includes(props.activityStatus ?? ''),
)

const storageComplete = computed(() => (props.blockers.unstored_pack_items_count ?? 0) === 0)

const hasNoCosts = computed(
  () => props.costsTotal != null && !Number.isNaN(props.costsTotal) && props.costsTotal <= 0,
)

const costsReleasedDone = computed(
  () =>
    Boolean(props.costsReleased) ||
    Boolean(props.blockers.costs_released) ||
    hasNoCosts.value,
)

/** Kosten-Freigabe erst, wenn nichts mehr zum Einlagern offen ist. */
const showCostsReleaseItem = computed(
  () => isPreCompletionPhase.value && storageComplete.value,
)

const chargeTarget = computed(() =>
  resolveActivityPrimaryChargeTarget({
    activityType: props.activityType ?? 'activity',
    groupName: props.groupName,
    externalCustomerLabel: props.externalCustomerLabel,
    activityName: props.activityName,
  }),
)

const chargeTargetLabel = computed(() => {
  const target = chargeTarget.value
  if (target.kind === 'group' && target.label) {
    return t('activities.completion.chargeGroup', { name: target.label })
  }
  if (target.kind === 'external_customer' && target.label) {
    return t('activities.completion.chargeExternal', { name: target.label })
  }
  return t('activities.completion.chargeUnknown')
})

const showIssuesInfo = computed(() => (props.blockers.open_issue_reports_count ?? 0) > 0)
const showWorkshopInfo = computed(() => (props.blockers.open_workshop_tickets_count ?? 0) > 0)
const showAccountingInfo = computed(
  () => (props.blockers.pending_accounting_followups_count ?? 0) > 0,
)

/** Nur Einlagern blockiert den Aktivitäts-Abschluss (#7 Phase 1). */
const hasBlockers = computed(() => !storageComplete.value)

const allClear = computed(() => !hasBlockers.value)

function formatChf(amount: string): string {
  const n = parseFloat(amount)
  if (Number.isNaN(n)) return amount
  return n.toFixed(2)
}

function openWorkshop(ticketId?: string) {
  const query: Record<string, string> = { activity_id: props.activityId }
  if (ticketId) query.ticket_id = ticketId
  void router.push({
    name: 'Workshop',
    params: { departmentId: props.hostDepartmentId },
    query,
  })
}

function openAccounting(fu?: { department_id: string }) {
  const deptId = fu?.department_id || props.hostDepartmentId
  if (!departmentHasAccountingRole(deptId)) return
  void router.push({
    name: 'AccountingBookings',
    params: { departmentId: deptId },
    query: { sub: 'assign', activity_id: props.activityId },
  })
}
</script>

<template>
  <section class="activity-completion-checklist" :class="{ 'activity-completion-checklist--ok': allClear }">
    <h3 class="activity-completion-checklist__title">
      {{ t('activities.completion.title') }}
    </h3>
    <p class="activity-completion-checklist__lead text-muted">
      {{ allClear ? t('activities.completion.allClear') : t('activities.completion.lead') }}
    </p>

    <ul class="activity-completion-checklist__list">
      <li
        class="activity-completion-checklist__item"
        :class="{ 'activity-completion-checklist__item--done': storageComplete }"
      >
        <span class="activity-completion-checklist__icon" aria-hidden="true">
          {{ storageComplete ? '✓' : '○' }}
        </span>
        <div class="activity-completion-checklist__body">
          <strong>{{ t('activities.completion.itemStorage') }}</strong>
          <span v-if="!storageComplete" class="text-muted">
            — {{ t('activities.completion.itemStoragePending', { n: blockers.unstored_pack_items_count }) }}
          </span>
          <ul v-if="blockers.unstored_pack_items?.length" class="activity-completion-checklist__sub">
            <li v-for="pi in blockers.unstored_pack_items" :key="pi.id">
              {{ pi.material_name }} ({{ pi.pending_store }} {{ t('activities.completion.piecesOpen') }})
            </li>
          </ul>
          <p v-if="!storageComplete" class="activity-completion-checklist__hint text-muted">
            {{ t('activities.completion.itemStorageHint') }}
          </p>
        </div>
        <button
          v-if="!storageComplete"
          type="button"
          class="btn-outline btn-sm"
          @click="emit('go-tab', 'packs')"
        >
          {{ t('activities.completion.actionPacks') }}
        </button>
      </li>

      <li
        v-if="showIssuesInfo"
        class="activity-completion-checklist__item activity-completion-checklist__item--info"
      >
        <span class="activity-completion-checklist__icon" aria-hidden="true">ℹ</span>
        <div class="activity-completion-checklist__body">
          <strong>{{ t('activities.completion.itemIssues') }}</strong>
          <span class="text-muted"> — {{ blockers.open_issue_reports_count }}</span>
          <p class="activity-completion-checklist__hint text-muted">
            {{ t('activities.completion.itemIssuesHint') }}
          </p>
          <ul v-if="blockers.open_issue_reports?.length" class="activity-completion-checklist__sub">
            <li v-for="ir in blockers.open_issue_reports" :key="ir.id">
              {{ ir.material_name || '–' }} · {{ ir.type }}
            </li>
          </ul>
        </div>
        <button
          type="button"
          class="btn-outline btn-sm"
          @click="emit('go-tab', 'issues')"
        >
          {{ t('activities.completion.actionIssues') }}
        </button>
      </li>

      <li
        v-if="showWorkshopInfo"
        class="activity-completion-checklist__item activity-completion-checklist__item--info"
      >
        <span class="activity-completion-checklist__icon" aria-hidden="true">ℹ</span>
        <div class="activity-completion-checklist__body">
          <strong>{{ t('activities.completion.itemWorkshop') }}</strong>
          <span class="text-muted"> — {{ blockers.open_workshop_tickets_count }}</span>
          <p class="activity-completion-checklist__hint text-muted">
            {{ t('activities.completion.itemWorkshopHint') }}
          </p>
          <div
            v-if="blockers.open_workshop_tickets?.length"
            class="activity-completion-checklist__chips"
          >
            <button
              v-for="ticket in blockers.open_workshop_tickets"
              :key="ticket.id"
              type="button"
              class="btn-outline btn-sm"
              @click="openWorkshop(ticket.id)"
            >
              {{ ticket.title || ticket.id }}
            </button>
          </div>
        </div>
        <button
          type="button"
          class="btn-outline btn-sm"
          @click="openWorkshop()"
        >
          {{ t('activities.completion.actionWorkshop') }}
        </button>
      </li>

      <li
        v-if="showCostsReleaseItem"
        class="activity-completion-checklist__item"
        :class="{ 'activity-completion-checklist__item--done': costsReleasedDone }"
      >
        <span class="activity-completion-checklist__icon" aria-hidden="true">
          {{ costsReleasedDone ? '✓' : '○' }}
        </span>
        <div class="activity-completion-checklist__body">
          <strong>
            {{
              hasNoCosts
                ? t('activities.completion.itemCostsNone')
                : costsReleasedDone
                  ? t('activities.completion.itemCostsReleased')
                  : t('activities.completion.itemCostsReview')
            }}
          </strong>
          <p v-if="!hasNoCosts" class="activity-completion-checklist__charge">{{ chargeTargetLabel }}</p>
          <p v-if="hasNoCosts" class="activity-completion-checklist__hint text-muted">
            {{ t('activities.completion.itemCostsNoneHint') }}
          </p>
          <p v-else-if="!costsReleasedDone" class="activity-completion-checklist__hint text-muted">
            {{ t('activities.completion.itemCostsReviewHint') }}
          </p>
          <p v-if="!hasNoCosts" class="activity-completion-checklist__hint text-muted">
            {{ t('activities.completion.releaseHint') }}
          </p>
        </div>
        <EButton
          v-if="!costsReleasedDone"
          variant="primary"
          size="small"
          :loading="costsReleaseLoading"
          @click="emit('release-costs')"
        >
          {{ t('activities.completion.releaseAction') }}
        </EButton>
      </li>

      <li
        v-if="isPreCompletionPhase || showAccountingInfo"
        class="activity-completion-checklist__item activity-completion-checklist__item--info"
        :class="{ 'activity-completion-checklist__item--done': !showAccountingInfo }"
      >
        <span class="activity-completion-checklist__icon" aria-hidden="true">
          {{ showAccountingInfo ? 'ℹ' : '✓' }}
        </span>
        <div class="activity-completion-checklist__body">
          <strong>{{ t('activities.completion.itemAccounting') }}</strong>
          <span v-if="showAccountingInfo" class="text-muted">
            — {{ blockers.pending_accounting_followups_count }}
          </span>
          <p
            v-if="isPreCompletionPhase && !showAccountingInfo"
            class="activity-completion-checklist__hint text-muted"
          >
            {{ t('activities.completion.itemAccountingAfterCloseHint') }}
          </p>
          <p v-else-if="showAccountingInfo" class="activity-completion-checklist__hint text-muted">
            {{ t('activities.completion.itemAccountingHint') }}
          </p>
          <ul v-if="blockers.pending_accounting_followups?.length" class="activity-completion-checklist__sub">
            <li v-for="fu in blockers.pending_accounting_followups" :key="fu.id">
              {{ t(accountingFollowUpKindKey(fu.source_kind)) }} · CHF {{ formatChf(fu.amount) }}
              <span v-if="fu.department_name" class="text-muted">({{ fu.department_name }})</span>
              <button
                v-if="departmentHasAccountingRole(fu.department_id)"
                type="button"
                class="activity-completion-checklist__inline-link"
                @click="openAccounting(fu)"
              >
                {{ t('activities.completion.actionAccountingAssign') }}
              </button>
            </li>
          </ul>
        </div>
        <button
          v-if="showAccountingInfo"
          type="button"
          class="btn-outline btn-sm"
          @click="emit('go-tab', 'costs')"
        >
          {{ t('activities.completion.actionCosts') }}
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.activity-completion-checklist {
  margin: 0 0 16px;
  padding: 14px 16px;
  border: 1px solid #fcd34d;
  border-radius: 10px;
  background: #fffbeb;
}

.activity-completion-checklist--ok {
  border-color: #86efac;
  background: #f0fdf4;
}

.activity-completion-checklist__title {
  margin: 0 0 6px;
  font-size: 15px;
  font-weight: 600;
}

.activity-completion-checklist__lead {
  margin: 0 0 12px;
  font-size: 13px;
}

.activity-completion-checklist__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.activity-completion-checklist__item {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 8px 12px;
  font-size: 14px;
}

.activity-completion-checklist__item--done .activity-completion-checklist__icon {
  color: #16a34a;
}

.activity-completion-checklist__item--info .activity-completion-checklist__icon {
  color: #2563eb;
}

.activity-completion-checklist__icon {
  flex: 0 0 1.25rem;
  font-weight: 700;
  color: #b45309;
}

.activity-completion-checklist__body {
  flex: 1 1 12rem;
  min-width: 0;
}

.activity-completion-checklist__sub {
  margin: 4px 0 0;
  padding-left: 1rem;
  font-size: 12px;
  color: #64748b;
}

.activity-completion-checklist__hint {
  margin: 6px 0 0;
  font-size: 12px;
}

.activity-completion-checklist__charge {
  margin: 4px 0 0;
  font-size: 13px;
  font-weight: 500;
}

.activity-completion-checklist__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
}

.activity-completion-checklist__inline-link {
  margin-left: 6px;
  padding: 0;
  border: none;
  background: none;
  color: var(--color-primary, #2563eb);
  font-size: 12px;
  cursor: pointer;
  text-decoration: underline;
}
</style>
