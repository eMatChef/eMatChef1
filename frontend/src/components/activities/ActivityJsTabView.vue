<template>
  <div class="activity-js-tab">
    <ActivityTabHeader :title="t('activities.jsMaterial.tabTitle')" />

    <div v-if="loading" class="activity-js-tab-loading">
      <ELoadingState variant="inline" :message="t('activities.jsMaterial.order.loadingShort')" />
    </div>

    <div v-else-if="loadError" class="section-card activity-tab-panel-card">
      <p class="text-muted">{{ loadError }}</p>
      <EButton variant="secondary" size="small" @click="loadOrder">{{ t('common.retry') }}</EButton>
    </div>

    <template v-else>
      <div class="section-card activity-tab-panel-card activity-js-tab-summary">
        <div class="activity-js-tab-summary-row">
          <span class="activity-js-tag">{{ t('activities.common.jsBadge') }}</span>
          <span class="activity-js-tab-status">{{ statusLabel }}</span>
          <span v-if="deliveryDateLabel" class="text-muted">· {{ deliveryDateLabel }}</span>
        </div>
        <p v-if="workflow" class="activity-js-tab-metrics text-muted">
          {{ t('activities.jsMaterial.tab.metrics', {
            received: workflow.items_received_complete,
            total: workflow.items_total,
            returned: workflow.items_return_complete,
          }) }}
        </p>
        <p v-if="workflow && (workflow.missing_on_receive > 0 || workflow.missing_on_return > 0)" class="activity-js-tab-warn">
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
          <div class="activity-js-tab-actions">
            <EButton variant="primary" :disabled="!canEdit || actionBusy" @click="openModal">
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

    <ActivityJsOrderModal
      :is-open="showModal"
      :activity-id="activityId"
      :department-id="departmentId"
      :read-only="!canEdit"
      @close="onModalClose"
      @saved="onOrderSaved"
      @autosaved="onOrderAutosaved"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import ActivityJsOrderModal from '@/components/activities/ActivityJsOrderModal.vue'
import { EButton } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { useToast } from '@/composables/useToast'
import { usePrompt } from '@/composables/usePrompt'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import {
  confirmJsOrderReturn,
  cloneJsOrderFormData,
  fetchActivityJsOrderPdfBlob,
  getActivityJsOrder,
  jsOrderStatusLabelKey,
  markJsOrderOrdered,
  patchJsOrderItem,
  saveActivityJsOrder,
  sendJsOrderCoachEmail,
  submitJsOrderToCoach,
  type ActivityJsOrderApi,
  type ActivityJsOrderItemApi,
} from '@/api/activityJsOrder'

const props = defineProps<{
  activityId: string
  departmentId: string
  canEdit: boolean
}>()

const { t } = useI18n()
const toast = useToast()
const { prompt: promptDialog } = usePrompt()
const { isMaterialwart } = useDepartmentMemberRole()

const loading = ref(true)
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

const workflow = computed(() => order.value?.workflow_summary)
const statusLabel = computed(() => t(jsOrderStatusLabelKey(order.value?.status)))

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

async function loadOrder() {
  loading.value = true
  loadError.value = null
  try {
    order.value = await getActivityJsOrder(props.activityId)
    syncDraftsFromOrder(order.value)
    syncAccordionDefaults()
  } catch {
    loadError.value = t('activities.jsMaterial.order.loadError')
  } finally {
    loading.value = false
  }
}

function openModal() {
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
          to: { name: 'SettingsActivities', params: { departmentId: props.departmentId } },
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
  () => {
    void loadOrder()
  },
)

onMounted(() => {
  void loadOrder()
})
</script>

<style scoped>
.activity-js-tab-summary-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
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
}

.activity-js-accordion--disabled .activity-js-accordion-toggle {
  color: #64748b;
}

.activity-js-tab-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.activity-js-tab-meta {
  margin-top: 8px;
  font-size: 0.875rem;
}

.activity-js-check-table-wrap {
  overflow-x: auto;
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
