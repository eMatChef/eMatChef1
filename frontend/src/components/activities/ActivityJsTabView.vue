<template>
  <div class="activity-js-tab">
    <ActivityTabHeader :title="t('activities.jsMaterial.tabTitle')" />

    <ActivityTabPanelShell
      :loading="showFullLoading"
      :refreshing="isRefreshing"
      :loading-message="t('activities.jsMaterial.order.loadingShort')"
      loading-class="activity-js-tab-loading"
    >
      <div v-if="loadError" class="activity-js-tab-error">
        <p class="text-muted">{{ loadError }}</p>
        <EButton variant="secondary" size="small" @click="loadOrder({ forceFull: true })">{{ t('common.retry') }}</EButton>
      </div>

      <template v-else>
      <div class="section-card activity-tab-panel-card activity-js-tab-summary">
        <div class="activity-js-tab-summary-row">
          <span class="activity-js-tag">{{ t('activities.common.jsBadge') }}</span>
          <span class="activity-js-tab-status">{{ progressCurrentLabel }}</span>
          <span v-if="deliveryDateLabel" class="text-muted">· {{ deliveryDateLabel }}</span>
        </div>

        <ol
          class="activity-js-progress"
          :aria-label="t('activities.jsMaterial.tab.progressAria')"
        >
          <li
            v-for="(step, idx) in progressSteps"
            :key="step.id"
            class="activity-js-progress-step"
            :class="{
              'is-done': step.state === 'done',
              'is-current': step.state === 'current',
              'is-upcoming': step.state === 'upcoming',
            }"
          >
            <span class="activity-js-progress-marker" aria-hidden="true">
              <span v-if="step.state === 'done'">✓</span>
              <span v-else>{{ idx + 1 }}</span>
            </span>
            <span class="activity-js-progress-label">{{ step.label }}</span>
            <span
              v-if="idx < progressSteps.length - 1"
              class="activity-js-progress-connector"
              aria-hidden="true"
            />
          </li>
        </ol>

        <p class="activity-js-tab-progress-hint text-muted">
          {{ t('activities.jsMaterial.tab.progressCurrentHint', { step: progressCurrentLabel }) }}
        </p>

        <p v-if="workflow && workflow.items_total > 0" class="activity-js-tab-metrics text-muted">
          {{ t('activities.jsMaterial.tab.metrics', {
            received: workflow.items_received_complete,
            total: workflow.items_total,
            returned: workflow.items_return_complete,
          }) }}
        </p>
        <p
          v-if="progressFlags.ordered && workflow && (workflow.missing_on_receive > 0 || workflow.missing_on_return > 0)"
          class="activity-js-tab-warn"
        >
          {{ t('activities.jsMaterial.tab.discrepancyHint', {
            missingReceive: workflow.missing_on_receive,
            missingReturn: workflow.missing_on_return,
          }) }}
        </p>
      </div>

      <!-- Accordion 1: Bestellung -->
      <div class="section-card activity-tab-panel-card activity-js-accordion">
        <button type="button" class="activity-js-accordion-toggle" @click="orderExpanded = !orderExpanded">
          <span class="activity-js-accordion-chevron" aria-hidden="true">{{ orderExpanded ? '▾' : '▸' }}</span>
          <span>{{ t('activities.jsMaterial.tab.accordionOrder') }}</span>
          <span v-if="order?.submitted_to_coach_at" class="activity-js-accordion-badge">{{ t('activities.jsMaterial.tab.submittedBadge') }}</span>
        </button>
        <div v-show="orderExpanded" class="activity-js-accordion-body">
          <p class="text-muted">{{ t('activities.jsMaterial.order.cardHint') }}</p>

          <div
            v-if="canEdit && !prerequisitesMet"
            class="activity-js-prereq"
            role="status"
          >
            <p class="activity-js-prereq-title">
              {{ t('activities.jsMaterial.order.openFormPrerequisitesTitle') }}
            </p>
            <p class="activity-js-prereq-hint text-muted">
              {{ t('activities.jsMaterial.order.openFormPrerequisitesInlineHint') }}
            </p>

            <div class="activity-js-prereq-item">
              <div class="activity-js-prereq-item-head">
                <span
                  class="activity-js-prereq-status"
                  :class="hasParticipantCount ? 'is-ok' : 'is-missing'"
                  aria-hidden="true"
                >{{ hasParticipantCount ? '✓' : '!' }}</span>
                <span class="activity-js-prereq-label">
                  {{ t('activities.jsMaterial.order.openFormNeedParticipantCount') }}
                </span>
              </div>
              <div v-if="!hasParticipantCount" class="activity-js-prereq-inline">
                <ETextField
                  v-model="tnDraft"
                  type="number"
                  min="1"
                  step="1"
                  :label="t('activities.jsMaterial.participantCountLabel')"
                  :placeholder="t('activities.jsMaterial.participantCountPlaceholder')"
                  hide-details="auto"
                  class="activity-js-prereq-tn-field"
                />
                <EButton
                  variant="primary"
                  size="small"
                  :loading="tnSaving"
                  :disabled="tnSaving"
                  @click="saveParticipantCount"
                >
                  {{ t('common.save') }}
                </EButton>
              </div>
              <p v-else class="activity-js-prereq-ok text-muted">
                {{ t('activities.jsMaterial.participantCountSummary', { count: localParticipantCount }) }}
              </p>
            </div>

            <div class="activity-js-prereq-item">
              <div class="activity-js-prereq-item-head">
                <span
                  class="activity-js-prereq-status"
                  :class="hasDeliveryAddress ? 'is-ok' : 'is-missing'"
                  aria-hidden="true"
                >{{ hasDeliveryAddress ? '✓' : '!' }}</span>
                <span class="activity-js-prereq-label">
                  {{ t('activities.jsMaterial.order.openFormNeedDeliveryAddress') }}
                </span>
              </div>
              <template v-if="!hasDeliveryAddress">
                <p v-if="!venueAddressId" class="activity-js-prereq-hint text-muted">
                  {{ t('activities.jsMaterial.order.openFormNeedVenueFirst') }}
                  <button
                    type="button"
                    class="activity-js-prereq-link"
                    @click="emit('go-overview')"
                  >
                    {{ t('activities.jsMaterial.order.openFormGoOverview') }}
                  </button>
                </p>
                <EButton
                  v-else
                  variant="secondary"
                  size="small"
                  @click="openVenueDeliveryModal"
                >
                  {{ t('activities.jsMaterial.order.openFormEditDelivery') }}
                </EButton>
              </template>
              <p v-else class="activity-js-prereq-ok text-muted">
                {{ t('activities.jsMaterial.order.openFormDeliveryOk') }}
              </p>
            </div>
          </div>

          <div class="activity-js-tab-actions">
            <EButton
              variant="primary"
              :disabled="!canEdit || actionBusy || !prerequisitesMet"
              :title="prerequisitesMet ? undefined : t('activities.jsMaterial.order.openFormPrerequisitesTitle')"
              @click="openModal"
            >
              {{ t('activities.jsMaterial.order.openFormButton') }}
            </EButton>
            <EButton
              v-if="order?.generated_pdf_url"
              variant="secondary"
              :disabled="actionBusy"
              @click="openPdf"
            >
              {{ t('activities.jsMaterial.order.openPdfButton') }}
            </EButton>
            <EButton
              variant="secondary"
              :disabled="!canEdit || actionBusy || !canSubmitToCoach"
              @click="onSubmitToCoach"
            >
              {{ t('activities.jsMaterial.tab.submitToCoach') }}
            </EButton>
            <EButton
              variant="secondary"
              :disabled="!canEdit || actionBusy || !order?.generated_pdf_url"
              @click="onSendCoachEmail"
            >
              {{ t('activities.jsMaterial.tab.sendCoachEmail') }}
            </EButton>
            <EButton
              v-if="order?.submitted_to_coach_at && order.status !== 'ordered' && order.status !== 'fulfilled'"
              variant="secondary"
              :disabled="!canEdit || actionBusy"
              @click="onMarkOrdered"
            >
              {{ t('activities.jsMaterial.tab.markOrdered') }}
            </EButton>
          </div>
          <p v-if="order?.coach_email_sent_at" class="text-muted activity-js-tab-meta">
            {{ t('activities.jsMaterial.tab.coachEmailSentAt', { date: formatDateTime(order.coach_email_sent_at) }) }}
          </p>
        </div>
      </div>

      <!-- Accordion 2: Empfang -->
      <div
        class="section-card activity-tab-panel-card activity-js-accordion"
        :class="{ 'activity-js-accordion--disabled': !receiveUnlocked }"
      >
        <button type="button" class="activity-js-accordion-toggle" @click="receiveExpanded = !receiveExpanded">
          <span class="activity-js-accordion-chevron" aria-hidden="true">{{ receiveExpanded ? '▾' : '▸' }}</span>
          <span>{{ t('activities.jsMaterial.tab.accordionReceive') }}</span>
        </button>
        <div v-show="receiveExpanded" class="activity-js-accordion-body">
          <p v-if="!receiveUnlocked" class="text-muted">
            {{ receiveLockedReason }}
          </p>
          <template v-else>
            <div v-if="!order?.items?.length" class="text-muted">{{ t('activities.jsMaterial.order.noPositionsYet') }}</div>
            <div v-else class="activity-js-check-table-wrap">
              <table class="activity-js-check-table">
                <thead>
                  <tr>
                    <th>{{ t('common.material') }}</th>
                    <th>{{ t('activities.jsMaterial.tab.colOrdered') }}</th>
                    <th>{{ t('activities.jsMaterial.tab.colReceived') }}</th>
                    <th>{{ t('activities.jsMaterial.tab.colNotes') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in order.items" :key="item.id">
                    <td>{{ item.material_name || item.material_item_id }}</td>
                    <td>{{ item.quantity_ordered }}</td>
                    <td>
                      <input
                        v-model.number="draftReceived[item.id]"
                        type="number"
                        min="0"
                        :max="item.quantity_ordered"
                        class="form-input activity-js-qty-input"
                        :disabled="checksReadonly || savingItemId === item.id"
                        @change="saveItemReceived(item)"
                      />
                    </td>
                    <td>
                      <input
                        v-model="draftNotes[item.id]"
                        type="text"
                        class="form-input"
                        :placeholder="t('activities.jsMaterial.tab.notesPlaceholder')"
                        :disabled="checksReadonly || savingItemId === item.id"
                        @blur="saveItemNotes(item)"
                      />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>
      </div>

      <!-- Accordion 3: Retour -->
      <div
        class="section-card activity-tab-panel-card activity-js-accordion"
        :class="{ 'activity-js-accordion--disabled': !returnUnlocked }"
      >
        <button type="button" class="activity-js-accordion-toggle" @click="returnExpanded = !returnExpanded">
          <span class="activity-js-accordion-chevron" aria-hidden="true">{{ returnExpanded ? '▾' : '▸' }}</span>
          <span>{{ t('activities.jsMaterial.tab.accordionReturn') }}</span>
        </button>
        <div v-show="returnExpanded" class="activity-js-accordion-body">
          <p v-if="!returnUnlocked" class="text-muted">{{ t('activities.jsMaterial.tab.returnLocked') }}</p>
          <template v-else>
            <div class="activity-js-check-table-wrap">
              <table class="activity-js-check-table">
                <thead>
                  <tr>
                    <th>{{ t('common.material') }}</th>
                    <th>{{ t('activities.jsMaterial.tab.colReceived') }}</th>
                    <th>{{ t('activities.jsMaterial.tab.colReturned') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in order?.items ?? []" :key="item.id">
                    <td>{{ item.material_name || item.material_item_id }}</td>
                    <td>{{ item.quantity_received }}</td>
                    <td>
                      <input
                        v-model.number="draftReturned[item.id]"
                        type="number"
                        min="0"
                        :max="item.quantity_received"
                        class="form-input activity-js-qty-input"
                        :disabled="checksReadonly || savingItemId === item.id"
                        @change="saveItemReturned(item)"
                      />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="!checksReadonly" class="activity-js-tab-actions">
              <EButton variant="primary" :disabled="actionBusy" @click="onConfirmReturn">
                {{ t('activities.jsMaterial.tab.confirmReturn') }}
              </EButton>
            </div>
            <p v-else-if="order?.return_confirmed_at" class="text-muted">
              {{ t('activities.jsMaterial.tab.returnConfirmedAt', { date: formatDateTime(order.return_confirmed_at) }) }}
            </p>
          </template>
        </div>
      </div>
    </template>
    </ActivityTabPanelShell>

    <ActivityJsOrderModal
      :is-open="showModal"
      :activity-id="activityId"
      :department-id="departmentId"
      :activity-participant-count="localParticipantCount"
      :read-only="!canEdit"
      @close="onModalClose"
      @saved="onOrderSaved"
      @autosaved="onOrderAutosaved"
    />

    <v-dialog
      v-model="showVenueContactModal"
      class="contact-create-dialog"
      max-width="960"
      scrollable
      content-class="contact-create-dialog__content"
      :z-index="2400"
    >
      <v-card class="contact-create-dialog__card" rounded="lg">
        <v-card-text class="contact-create-dialog__body">
          <ContactDetailView
            v-if="showVenueContactModal && venueAddressId"
            :key="`venue-${venueAddressId}`"
            mode="view"
            as-modal
            :department-id="departmentId"
            :contact-id="venueAddressId"
            default-type="event"
            initial-focus="locations"
            highlight-delivery
            @close="closeVenueContactModal"
            @updated="onVenueContactUpdated"
            @deleted="closeVenueContactModal"
          />
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import ActivityTabPanelShell from '@/components/activities/ActivityTabPanelShell.vue'
import { useActivityTabLoad } from '@/composables/useActivityTabLoad'
import ActivityJsOrderModal from '@/components/activities/ActivityJsOrderModal.vue'
import ContactDetailView from '@/components/contacts/ContactDetailView.vue'
import { EButton, ETextField } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { usePrompt } from '@/composables/usePrompt'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { getAddress } from '@/api/addresses'
import { patchActivity } from '@/api/activities'
import {
  confirmJsOrderReturn,
  cloneJsOrderFormData,
  fetchActivityJsOrderPdfBlob,
  getActivityJsOrder,
  markJsOrderOrdered,
  patchJsOrderItem,
  saveActivityJsOrder,
  sendJsOrderCoachEmail,
  submitJsOrderToCoach,
  type ActivityJsOrderApi,
  type ActivityJsOrderItemApi,
} from '@/api/activityJsOrder'
import '@/styles/contacts-view.css'

const props = defineProps<{
  activityId: string
  departmentId: string
  canEdit: boolean
  /** TN-Zahl aus Aktivität (für Dotation / Vorbedingung). */
  participantCount?: number | null
  venueAddressId?: string | null
  jsDeliveryAddressId?: string | null
}>()

const emit = defineEmits<{
  'activity-updated': []
  'go-overview': []
}>()

const { t } = useI18n()
const toast = useToast()
const { prompt: promptDialog } = usePrompt()
const { isMaterialwart } = useDepartmentMemberRole()

const { showFullLoading, isRefreshing, resetTabLoad, withTabLoad } = useActivityTabLoad()
const loadError = ref<string | null>(null)
const order = ref<ActivityJsOrderApi | null>(null)
const actionBusy = ref(false)
const showModal = ref(false)
const savingItemId = ref<string | null>(null)

const draftReceived = reactive<Record<string, number>>({})
const draftReturned = reactive<Record<string, number>>({})
const draftNotes = reactive<Record<string, string>>({})

const orderExpanded = ref(true)
const receiveExpanded = ref(false)
const returnExpanded = ref(false)

const localParticipantCount = ref<number | null>(null)
const tnDraft = ref('')
const tnSaving = ref(false)
const hasDeliveryAddress = ref(false)
const deliveryCheckBusy = ref(false)
const showVenueContactModal = ref(false)

const hasParticipantCount = computed(
  () => localParticipantCount.value != null && localParticipantCount.value >= 1,
)
const prerequisitesMet = computed(() => hasParticipantCount.value && hasDeliveryAddress.value)

function syncParticipantFromProps() {
  const count =
    props.participantCount != null && props.participantCount >= 1 ? props.participantCount : null
  localParticipantCount.value = count
  if (count == null) {
    tnDraft.value = ''
  } else {
    tnDraft.value = String(count)
  }
}

async function refreshDeliveryPresence() {
  deliveryCheckBusy.value = true
  try {
    if (props.jsDeliveryAddressId) {
      hasDeliveryAddress.value = true
      return
    }
    const venueId = props.venueAddressId
    if (!venueId) {
      hasDeliveryAddress.value = false
      return
    }
    const detail = await getAddress(venueId)
    hasDeliveryAddress.value = (detail.child_addresses ?? []).some((a) => a.type === 'event_delivery')
  } catch {
    hasDeliveryAddress.value = false
  } finally {
    deliveryCheckBusy.value = false
  }
}

async function saveParticipantCount() {
  const n = Number.parseInt(String(tnDraft.value).trim(), 10)
  if (!Number.isFinite(n) || n < 1) {
    toast.error(t('activities.jsMaterial.order.participantCountInvalid'))
    return
  }
  tnSaving.value = true
  try {
    await patchActivity(props.activityId, {
      participant_count: n,
      viewer_department_id: props.departmentId,
    })
    localParticipantCount.value = n
    toast.success(t('activities.jsMaterial.order.participantCountSaved'))
    emit('activity-updated')
  } catch (err: unknown) {
    const msg =
      (err as { response?: { data?: { error?: string } } })?.response?.data?.error ||
      t('activities.jsMaterial.order.saveError')
    toast.error(msg)
  } finally {
    tnSaving.value = false
  }
}

function openVenueDeliveryModal() {
  if (!props.venueAddressId) {
    emit('go-overview')
    return
  }
  showVenueContactModal.value = true
}

async function closeVenueContactModal() {
  showVenueContactModal.value = false
  await refreshDeliveryPresence()
  emit('activity-updated')
}

async function onVenueContactUpdated() {
  await refreshDeliveryPresence()
  emit('activity-updated')
}

watch(
  () => props.participantCount,
  () => syncParticipantFromProps(),
  { immediate: true },
)

watch(
  () => [props.venueAddressId, props.jsDeliveryAddressId] as const,
  () => {
    void refreshDeliveryPresence()
  },
  { immediate: true },
)

onMounted(() => {
  void refreshDeliveryPresence()
})

const workflow = computed(() => order.value?.workflow_summary)

type JsProgressState = 'done' | 'current' | 'upcoming'
type JsProgressStep = { id: string; label: string; state: JsProgressState }

const progressFlags = computed(() => {
  const o = order.value
  const w = workflow.value
  const status = o?.status ?? null
  const cancelled = status === 'cancelled'
  const hasDraft =
    !!o &&
    (status === 'ready' ||
      status === 'ordered' ||
      status === 'fulfilled' ||
      (o.items?.length ?? 0) > 0 ||
      !!o.generated_pdf_url ||
      !!(o.form_data?.block2?.delivery_date?.trim()))
  const submitted = !!(o?.submitted_to_coach_at || w?.submitted_to_coach)
  const ordered = status === 'ordered' || status === 'fulfilled' || !!o?.ordered_at
  const receiveDone =
    status === 'fulfilled' ||
    (!!w && w.items_total > 0 && w.items_received_complete >= w.items_total)
  const returnDone = status === 'fulfilled' || !!(o?.return_confirmed_at || w?.return_confirmed)
  return { cancelled, hasDraft, submitted, ordered, receiveDone, returnDone }
})

const progressSteps = computed((): JsProgressStep[] => {
  const f = progressFlags.value
  const defs: { id: string; labelKey: string; done: boolean }[] = [
    { id: 'draft', labelKey: 'activities.jsMaterial.tab.progress.draft', done: f.hasDraft },
    { id: 'coach', labelKey: 'activities.jsMaterial.tab.progress.coach', done: f.submitted },
    { id: 'ordered', labelKey: 'activities.jsMaterial.tab.progress.ordered', done: f.ordered },
    { id: 'receive', labelKey: 'activities.jsMaterial.tab.progress.receive', done: f.receiveDone },
    { id: 'return', labelKey: 'activities.jsMaterial.tab.progress.return', done: f.returnDone },
  ]

  if (f.cancelled) {
    return defs.map((d) => ({
      id: d.id,
      label: t(d.labelKey),
      state: 'upcoming' as const,
    }))
  }

  let currentSet = false
  return defs.map((d) => {
    if (d.done) {
      return { id: d.id, label: t(d.labelKey), state: 'done' as const }
    }
    if (!currentSet) {
      currentSet = true
      return { id: d.id, label: t(d.labelKey), state: 'current' as const }
    }
    return { id: d.id, label: t(d.labelKey), state: 'upcoming' as const }
  })
})

const progressCurrentLabel = computed(() => {
  if (progressFlags.value.cancelled) {
    return t('activities.jsMaterial.order.status.cancelled')
  }
  if (progressFlags.value.returnDone) {
    return t('activities.jsMaterial.tab.progress.complete')
  }
  const current = progressSteps.value.find((s) => s.state === 'current')
  if (current?.id === 'draft' && !progressFlags.value.hasDraft) {
    return t('activities.jsMaterial.order.statusNotStarted')
  }
  return current?.label ?? t('activities.jsMaterial.order.statusNotStarted')
})

const deliveryDateLabel = computed(() => {
  const d = order.value?.form_data.block2.delivery_date || workflow.value?.delivery_date
  if (!d) return ''
  return t('activities.jsMaterial.tab.deliveryOn', { date: formatDate(d) })
})

const submittedToCoach = computed(
  () => !!order.value?.submitted_to_coach_at || !!workflow.value?.submitted_to_coach,
)

const receiveUnlocked = computed(
  () => submittedToCoach.value && (workflow.value?.delivery_reached ?? false),
)

const receiveLockedReason = computed(() => {
  if (!submittedToCoach.value) return t('activities.jsMaterial.tab.receiveNeedsSubmit')
  if (!workflow.value?.delivery_reached) {
    const d = workflow.value?.delivery_date || order.value?.form_data.block2.delivery_date
    return d
      ? t('activities.jsMaterial.tab.receiveNeedsDeliveryDate', { date: formatDate(d) })
      : t('activities.jsMaterial.tab.receiveNeedsDeliveryDateGeneric')
  }
  return ''
})

const returnUnlocked = computed(() => {
  if (!receiveUnlocked.value) return false
  const w = workflow.value
  if (!w || w.items_total < 1) return false
  return w.items_received_complete >= w.items_total
})

const checksReadonly = computed(
  () => order.value?.status === 'fulfilled' || !!order.value?.return_confirmed_at,
)

const canSubmitToCoach = computed(
  () => (order.value?.items?.length ?? 0) > 0 && !!order.value?.form_data.block2.delivery_date,
)

function syncDraftsFromOrder(o: ActivityJsOrderApi | null) {
  if (!o) return
  for (const item of o.items) {
    draftReceived[item.id] = item.quantity_received
    draftReturned[item.id] = item.quantity_returned
    draftNotes[item.id] = item.notes ?? ''
  }
}

function syncAccordionDefaults() {
  if (submittedToCoach.value) {
    orderExpanded.value = false
    if (receiveUnlocked.value && !order.value?.return_confirmed_at) {
      receiveExpanded.value = true
    }
    if (returnUnlocked.value && !order.value?.return_confirmed_at) {
      returnExpanded.value = true
    }
  } else {
    orderExpanded.value = true
  }
}

async function loadOrder(opts?: { forceFull?: boolean }) {
  await withTabLoad(async () => {
    loadError.value = null
    try {
      order.value = await getActivityJsOrder(props.activityId)
      syncDraftsFromOrder(order.value)
      syncAccordionDefaults()
    } catch {
      loadError.value = t('activities.jsMaterial.order.loadError')
    }
  }, opts)
}

function openModal() {
  if (!prerequisitesMet.value) return
  showModal.value = true
}

function onModalClose() {
  showModal.value = false
}

function syncOrderFromModal(saved: ActivityJsOrderApi) {
  order.value = saved
  syncDraftsFromOrder(saved)
}

function onOrderAutosaved(saved: ActivityJsOrderApi) {
  syncOrderFromModal(saved)
}

function onOrderSaved(saved: ActivityJsOrderApi) {
  syncOrderFromModal(saved)
}

async function openPdf() {
  const url = order.value?.generated_pdf_url
  if (!url) return
  try {
    const blob = await fetchActivityJsOrderPdfBlob(url)
    window.open(URL.createObjectURL(blob), '_blank', 'noopener')
  } catch {
    toast.error(t('activities.jsMaterial.order.openPdfError'))
  }
}

function isValidEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())
}

function isCoachEmailError(message: string): boolean {
  return message.includes('Coach-E-Mail')
}

function extractApiError(err: unknown): string {
  const data = (err as { response?: { data?: { error?: string; code?: string } } })?.response?.data
  if (data?.code === 'mail_transport_missing') {
    return t('activities.jsMaterial.tab.coachEmailMailNotConfigured')
  }
  if (data?.error?.includes('MAILER_DSN')) {
    return t('activities.jsMaterial.tab.coachEmailMailNotConfigured')
  }
  return data?.error || t('activities.jsMaterial.order.saveError')
}

async function ensureCoachEmailOnOrder(): Promise<boolean> {
  const currentOrder = order.value
  if (!currentOrder) return false

  const formData = cloneJsOrderFormData(currentOrder.form_data)
  const raw = await promptDialog({
    title: t('activities.jsMaterial.tab.coachEmailPromptTitle'),
    message: isMaterialwart.value
      ? t('activities.jsMaterial.tab.coachEmailPromptHintMw')
      : t('activities.jsMaterial.tab.coachEmailPromptHintUser'),
    settingsLink: isMaterialwart.value
      ? {
          to: { name: 'SettingsModule', params: { departmentId: props.departmentId } },
          label: t('activities.jsMaterial.tab.coachEmailPromptSettingsLink'),
        }
      : undefined,
    placeholder: t('activities.jsMaterial.order.fields.coachEmail'),
    defaultValue: formData.block2.coach_email.trim(),
    required: true,
    confirmText: t('activities.jsMaterial.tab.coachEmailPromptConfirm'),
  })
  if (!raw) return false

  const email = raw.trim()
  if (!isValidEmail(email)) {
    toast.error(t('activities.jsMaterial.tab.coachEmailInvalid'))
    return false
  }

  formData.block2.coach_email = email
  if (!formData.block2.user_overridden.includes('coach_email')) {
    formData.block2.user_overridden.push('coach_email')
  }

  try {
    order.value = await saveActivityJsOrder(props.activityId, {
      form_data: formData,
      participant_count: currentOrder.participant_count,
      delivery_type: currentOrder.delivery_type,
      status: currentOrder.status === 'ready' ? 'ready' : 'draft',
    })
    syncDraftsFromOrder(order.value)
    return true
  } catch {
    toast.error(t('activities.jsMaterial.order.saveError'))
    return false
  }
}

async function sendCoachEmailWithPrompt(): Promise<void> {
  if (workflow.value?.coach_email_ready === false) {
    const saved = await ensureCoachEmailOnOrder()
    if (!saved) return
  }

  try {
    order.value = await sendJsOrderCoachEmail(props.activityId)
    syncDraftsFromOrder(order.value)
    syncAccordionDefaults()
    toast.success(t('activities.jsMaterial.tab.emailSuccess'))
  } catch (err: unknown) {
    const msg = extractApiError(err)
    if (!isCoachEmailError(msg)) {
      toast.error(msg)
      return
    }

    const saved = await ensureCoachEmailOnOrder()
    if (!saved) return

    try {
      order.value = await sendJsOrderCoachEmail(props.activityId)
      syncDraftsFromOrder(order.value)
      syncAccordionDefaults()
      toast.success(t('activities.jsMaterial.tab.emailSuccess'))
    } catch (retryErr: unknown) {
      toast.error(extractApiError(retryErr))
    }
  }
}

async function runAction(fn: () => Promise<ActivityJsOrderApi>, successKey: string) {
  actionBusy.value = true
  try {
    order.value = await fn()
    syncDraftsFromOrder(order.value)
    syncAccordionDefaults()
    toast.success(t(successKey))
  } catch (err: unknown) {
    const msg =
      (err as { response?: { data?: { error?: string } } })?.response?.data?.error ||
      t('activities.jsMaterial.order.saveError')
    toast.error(msg)
  } finally {
    actionBusy.value = false
  }
}

function onSubmitToCoach() {
  void runAction(() => submitJsOrderToCoach(props.activityId), 'activities.jsMaterial.tab.submitSuccess')
}

function onSendCoachEmail() {
  actionBusy.value = true
  void sendCoachEmailWithPrompt().finally(() => {
    actionBusy.value = false
  })
}

function onMarkOrdered() {
  void runAction(() => markJsOrderOrdered(props.activityId), 'activities.jsMaterial.tab.markOrderedSuccess')
}

async function saveItemReceived(item: ActivityJsOrderItemApi) {
  if (checksReadonly.value) return
  const qty = Math.max(0, Math.min(item.quantity_ordered, Number(draftReceived[item.id]) || 0))
  draftReceived[item.id] = qty
  savingItemId.value = item.id
  try {
    order.value = await patchJsOrderItem(props.activityId, item.id, { quantity_received: qty })
    syncDraftsFromOrder(order.value)
  } catch {
    toast.error(t('activities.jsMaterial.order.saveError'))
  } finally {
    savingItemId.value = null
  }
}

async function saveItemReturned(item: ActivityJsOrderItemApi) {
  if (checksReadonly.value) return
  const max = item.quantity_received
  const qty = Math.max(0, Math.min(max, Number(draftReturned[item.id]) || 0))
  draftReturned[item.id] = qty
  savingItemId.value = item.id
  try {
    order.value = await patchJsOrderItem(props.activityId, item.id, { quantity_returned: qty })
    syncDraftsFromOrder(order.value)
  } catch {
    toast.error(t('activities.jsMaterial.order.saveError'))
  } finally {
    savingItemId.value = null
  }
}

async function saveItemNotes(item: ActivityJsOrderItemApi) {
  if (checksReadonly.value) return
  const notes = (draftNotes[item.id] ?? '').trim()
  if ((item.notes ?? '') === notes) return
  savingItemId.value = item.id
  try {
    order.value = await patchJsOrderItem(props.activityId, item.id, { notes: notes || null })
    syncDraftsFromOrder(order.value)
  } catch {
    toast.error(t('activities.jsMaterial.order.saveError'))
  } finally {
    savingItemId.value = null
  }
}

function onConfirmReturn() {
  void runAction(() => confirmJsOrderReturn(props.activityId), 'activities.jsMaterial.tab.returnSuccess')
}

function formatDate(iso: string): string {
  if (!iso) return ''
  const [y, m, d] = iso.split('-')
  if (!y || !m || !d) return iso
  return `${d}.${m}.${y}`
}

function formatDateTime(iso: string): string {
  try {
    return new Date(iso).toLocaleString('de-CH', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}

watch(
  () => props.activityId,
  (activityId, prevActivityId) => {
    if (prevActivityId != null && activityId !== prevActivityId) {
      resetTabLoad()
    }
    void loadOrder()
  },
  { immediate: true },
)
</script>

<style scoped>
.activity-js-tab {
  min-width: 0;
  max-width: 100%;
  overflow-x: hidden;
}

.activity-js-tab :deep(.activity-tab-panel-card),
.activity-js-tab :deep(.section-card) {
  min-width: 0;
  max-width: 100%;
}

.activity-js-tab-summary-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.activity-js-tab-status {
  font-weight: 600;
  color: #0f172a;
}

.activity-js-progress {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0;
  margin: 14px 0 0;
  padding: 0;
  list-style: none;
}

.activity-js-progress-step {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1 1 4.5rem;
  min-width: 4.25rem;
  max-width: 7rem;
  gap: 6px;
  text-align: center;
}

.activity-js-progress-marker {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  border: 2px solid #cbd5e1;
  background: #fff;
  color: #64748b;
  z-index: 1;
}

.activity-js-progress-step.is-done .activity-js-progress-marker {
  border-color: #059669;
  background: #059669;
  color: #fff;
}

.activity-js-progress-step.is-current .activity-js-progress-marker {
  border-color: #2563eb;
  background: #eff6ff;
  color: #1d4ed8;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
}

.activity-js-progress-label {
  font-size: 0.7rem;
  font-weight: 600;
  line-height: 1.25;
  color: #64748b;
}

.activity-js-progress-step.is-done .activity-js-progress-label {
  color: #047857;
}

.activity-js-progress-step.is-current .activity-js-progress-label {
  color: #1d4ed8;
}

.activity-js-progress-connector {
  position: absolute;
  top: 0.875rem;
  left: calc(50% + 0.95rem);
  width: calc(100% - 1.9rem);
  height: 2px;
  background: #e2e8f0;
  z-index: 0;
}

.activity-js-progress-step.is-done .activity-js-progress-connector {
  background: #059669;
}

.activity-js-tab-progress-hint {
  margin: 10px 0 0;
  font-size: 0.8125rem;
}

.activity-js-tag {
  display: inline-flex;
  padding: 2px 8px;
  border-radius: 4px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 0.75rem;
  font-weight: 600;
}

.activity-js-tab-metrics {
  margin: 8px 0 0;
  font-size: 0.9rem;
}

.activity-js-tab-warn {
  margin: 6px 0 0;
  color: #b45309;
  font-size: 0.875rem;
}

.activity-js-accordion {
  min-width: 0;
  max-width: 100%;
  overflow-x: hidden;
}

.activity-js-accordion-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 0;
  border: none;
  background: none;
  font-size: 1rem;
  font-weight: 600;
  color: #0f172a;
  cursor: pointer;
  text-align: left;
}

.activity-js-accordion-chevron {
  width: 1rem;
  flex-shrink: 0;
}

.activity-js-accordion-badge {
  margin-left: auto;
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
}

.activity-js-accordion-body {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #e2e8f0;
  min-width: 0;
  max-width: 100%;
  overflow-x: hidden;
}

.activity-js-prereq {
  margin: 12px 0 4px;
  padding: 12px 14px;
  border: 1px solid #fcd34d;
  border-radius: 10px;
  background: #fffbeb;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.activity-js-prereq-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 650;
  color: #92400e;
}

.activity-js-prereq-hint {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.4;
}

.activity-js-prereq-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 8px;
  border-top: 1px solid #fde68a;
}

.activity-js-prereq-item:first-of-type {
  border-top: none;
  padding-top: 0;
}

.activity-js-prereq-item-head {
  display: flex;
  align-items: center;
  gap: 8px;
}

.activity-js-prereq-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}

.activity-js-prereq-status.is-missing {
  background: #fed7aa;
  color: #c2410c;
}

.activity-js-prereq-status.is-ok {
  background: #d1fae5;
  color: #047857;
}

.activity-js-prereq-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #78350f;
}

.activity-js-prereq-inline {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 8px;
}

.activity-js-prereq-tn-field {
  flex: 1 1 10rem;
  min-width: 8rem;
  max-width: 14rem;
}

.activity-js-prereq-ok {
  margin: 0;
  font-size: 0.8125rem;
}

.activity-js-prereq-link {
  display: inline;
  margin: 0;
  padding: 0;
  border: none;
  background: none;
  color: #047857;
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

.activity-js-prereq-link:hover {
  color: #065f46;
}

.activity-js-accordion--disabled .activity-js-accordion-toggle {
  color: #64748b;
}

.activity-js-tab-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
  max-width: 100%;
  min-width: 0;
}

.activity-js-tab-meta {
  margin-top: 8px;
  font-size: 0.875rem;
}

.activity-js-check-table-wrap {
  overflow-x: auto;
  max-width: 100%;
}

.activity-js-check-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.activity-js-check-table th,
.activity-js-check-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: middle;
}

.activity-js-check-table th {
  font-weight: 600;
  color: #64748b;
  font-size: 0.8rem;
}

.activity-js-qty-input {
  width: 4.5rem;
  min-width: 4rem;
}
</style>
