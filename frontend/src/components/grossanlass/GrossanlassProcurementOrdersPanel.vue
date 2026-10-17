<template>
  <section class="orders-panel" :data-testid="`orders-panel-${line.id}`">
    <h4 class="orders-panel__title">{{ t('grossanlass.beschaffung.bestellungen.orders.title') }}</h4>

    <dl class="orders-panel__sums">
      <div><dt>{{ t('grossanlass.beschaffung.bestellungen.orders.need') }}</dt><dd>{{ line.quantity }}</dd></div>
      <div><dt>{{ t('grossanlass.beschaffung.bestellungen.orders.ordered') }}</dt><dd>{{ line.quantity_ordered ?? 0 }}</dd></div>
      <div v-if="(line.quantity_loaned ?? 0) > 0"><dt>{{ t('grossanlass.beschaffung.bestellungen.orders.loaned') }}</dt><dd>{{ line.quantity_loaned }}</dd></div>
      <div v-if="(line.quantity_donated ?? 0) > 0"><dt>{{ t('grossanlass.beschaffung.bestellungen.orders.donated') }}</dt><dd>{{ line.quantity_donated }}</dd></div>
      <div><dt>{{ t('grossanlass.beschaffung.bestellungen.orders.received') }}</dt><dd>{{ line.quantity_received ?? 0 }}</dd></div>
      <div :class="{ 'is-warn': (line.quantity_open ?? 0) > 0 }">
        <dt>{{ t('grossanlass.beschaffung.bestellungen.orders.open') }}</dt><dd>{{ line.quantity_open ?? 0 }}</dd>
      </div>
      <div v-if="(line.quantity_over ?? 0) > 0" class="is-warn" data-testid="orders-over">
        <dt>{{ t('grossanlass.beschaffung.bestellungen.orders.over') }}</dt><dd>{{ line.quantity_over }}</dd>
      </div>
    </dl>

    <ul v-if="activeOrders.length" class="orders-list">
      <li v-for="order in activeOrders" :key="order.id" class="orders-list__item" :data-testid="`order-${order.id}`">
        <div class="orders-list__main">
          <strong>{{ order.supplier || t('grossanlass.beschaffung.offerten.supplier') }}</strong>
          <span class="chip">{{ order.agreement_kind === 'phone'
            ? t('grossanlass.beschaffung.bestellungen.orders.agreementPhone')
            : t('grossanlass.beschaffung.bestellungen.orders.agreementQuote') }}</span>
          <span>{{ order.ordered_quantity }} {{ line.quantity_unit }} · {{ formatChf(order.cost_chf) }}</span>
          <span v-if="order.order_ref" class="muted">{{ order.order_ref }}</span>
          <span v-if="order.delivery_at" class="muted">{{ t('grossanlass.beschaffung.bestellungen.deliveryAt') }} {{ order.delivery_at.slice(0, 10) }}</span>
        </div>
        <div class="orders-list__progress">
          {{ t('grossanlass.beschaffung.bestellungen.orders.receivedOf', { received: order.received_quantity ?? 0, ordered: order.ordered_quantity ?? 0 }) }}
        </div>
        <div class="orders-list__actions">
          <EButton variant="text" size="small" @click="startEdit(order)">{{ t('common.edit') }}</EButton>
          <EButton variant="text" size="small" @click="startCancel(order)">{{ t('grossanlass.beschaffung.bestellungen.orders.cancel') }}</EButton>
        </div>
      </li>
    </ul>
    <p v-else class="muted">{{ t('grossanlass.beschaffung.bestellungen.orders.none') }}</p>

    <details v-if="cancelledOrders.length" class="orders-cancelled">
      <summary>{{ t('grossanlass.beschaffung.bestellungen.orders.cancelledTitle', { count: cancelledOrders.length }) }}</summary>
      <ul>
        <li v-for="order in cancelledOrders" :key="order.id">
          {{ order.supplier }} · {{ order.quantity }} · {{ order.cancel_reason }}
        </li>
      </ul>
    </details>

    <form class="order-form" @submit.prevent="submit(false)">
      <h5 class="order-form__title">{{ editingId
        ? t('grossanlass.beschaffung.bestellungen.orders.editTitle')
        : t('grossanlass.beschaffung.bestellungen.orders.newTitle') }}</h5>
      <ESelect
        v-model="form.base"
        :label="t('grossanlass.beschaffung.bestellungen.orders.basis')"
        :items="baseItems"
        hide-details
        density="compact"
        :disabled="!!editingId && hasReceipt"
      />
      <ETextField
        v-if="form.base === PHONE"
        v-model="form.supplier"
        :label="t('grossanlass.beschaffung.bestellungen.orders.supplier')"
        hide-details
        density="compact"
      />
      <ETextField
        v-model="form.quantity"
        type="number"
        min="1"
        :label="t('grossanlass.beschaffung.bestellungen.orders.quantity')"
        :hint="t('grossanlass.beschaffung.bestellungen.orders.quantityHint', { open: openForNew })"
        persistent-hint
        density="compact"
      />
      <ETextField
        v-model="form.cost_chf"
        type="number"
        min="0"
        step="0.05"
        :label="t('grossanlass.beschaffung.bestellungen.costChf')"
        hide-details
        density="compact"
      />
      <ETextField v-model="form.order_ref" :label="t('grossanlass.beschaffung.bestellungen.orderRef')" hide-details density="compact" />
      <ETextField v-model="form.ordered_at" type="date" :label="t('grossanlass.beschaffung.bestellungen.orderedAt')" hide-details density="compact" />
      <ETextField v-model="form.delivery_at" type="date" :label="t('grossanlass.beschaffung.bestellungen.deliveryAt')" hide-details density="compact" />
      <ESelect
        v-model="form.inbound_mode"
        :label="t('grossanlass.beschaffung.bestellungen.orders.inboundMode')"
        :items="inboundItems"
        hide-details
        density="compact"
      />
      <ECheckbox
        v-model="form.return_needed"
        :label="t('grossanlass.beschaffung.bestellungen.orders.returnNeeded')"
        hide-details
      />
      <ETextField v-model="form.notes" :label="t('grossanlass.beschaffung.bestellungen.notes')" hide-details density="compact" />
      <div class="order-form__actions">
        <EButton variant="primary" size="small" type="submit" :loading="saving" :disabled="!canSubmit">
          {{ editingId ? t('common.save') : t('grossanlass.beschaffung.bestellungen.placeOrder') }}
        </EButton>
        <EButton v-if="editingId" variant="text" size="small" @click="resetForm">{{ t('common.cancel') }}</EButton>
      </div>
    </form>

    <EDialog v-model="overcoverageOpen" :max-width="480" :title="t('grossanlass.beschaffung.bestellungen.orders.overcoverageTitle')">
      <p v-if="overcoverage" data-testid="overcoverage-text">
        {{ t('grossanlass.beschaffung.bestellungen.orders.overcoverageText', {
          need: overcoverage.need,
          covered: overcoverage.already_covered,
          requested: overcoverage.requested,
          over: overcoverage.over,
        }) }}
      </p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="overcoverageOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" data-testid="overcoverage-confirm" @click="submit(true)">
          {{ t('grossanlass.beschaffung.bestellungen.orders.overcoverageConfirm') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="cancelOpen" :max-width="480" :title="t('grossanlass.beschaffung.bestellungen.orders.cancelTitle')">
      <p class="muted">{{ t('grossanlass.beschaffung.bestellungen.orders.cancelHint') }}</p>
      <ETextField v-model="cancelReason" :label="t('grossanlass.beschaffung.bestellungen.orders.cancelReason')" density="compact" />
      <template #actions>
        <EButton variant="secondary" size="small" @click="cancelOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!cancelReason.trim()" :loading="saving" @click="confirmCancel">
          {{ t('grossanlass.beschaffung.bestellungen.orders.cancelConfirm') }}
        </EButton>
      </template>
    </EDialog>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { formatWallClock, useBusinessClockStore } from '@/stores/businessClock'
import { EButton, ECheckbox, EDialog, ESelect, ETextField } from '@/components/form/base'
import {
  cancelGrossanlassProcurementOrder,
  createGrossanlassProcurementOrder,
  formatChf,
  overcoverageInfoFromError,
  updateGrossanlassProcurementOrder,
  type GrossanlassOvercoverageInfo,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementOrder,
} from '@/api/grossanlassProcurement'

const props = defineProps<{ line: GrossanlassProcurementLine; departmentId: string }>()
const emit = defineEmits<{ changed: [line: GrossanlassProcurementLine] }>()

const PHONE = '__phone__'
const { t } = useI18n()
const toast = useToast()
const clock = useBusinessClockStore()
/** Bestelldatum folgt der Fachzeit (Demo-Uhr), nicht der Systemzeit. */
const businessToday = () => formatWallClock(clock.displayNow).slice(0, 10)

const saving = ref(false)
const editingId = ref<string | null>(null)
const overcoverage = ref<GrossanlassOvercoverageInfo | null>(null)
const overcoverageOpen = ref(false)
const cancelOpen = ref(false)
const cancelReason = ref('')
const cancelTarget = ref<GrossanlassProcurementOrder | null>(null)

const form = reactive({
  base: PHONE,
  supplier: '',
  quantity: '',
  cost_chf: '',
  order_ref: '',
  ordered_at: businessToday(),
  delivery_at: '',
  notes: '',
  // Leer = wie die Offerte; Rückgabepflicht bei Miete/Leihe (z. B. Fahrzeuge).
  inbound_mode: '' as '' | 'delivery' | 'pickup',
  return_needed: false,
})

const allOrders = computed(() => props.line.orders ?? (props.line.order ? [props.line.order] : []))
const activeOrders = computed(() => allOrders.value.filter((o) => o.status !== 'cancelled'))
const cancelledOrders = computed(() => allOrders.value.filter((o) => o.status === 'cancelled'))
const openForNew = computed(() => Math.max(0, props.line.quantity_open ?? 0))
const editingOrder = computed(() => allOrders.value.find((o) => o.id === editingId.value) ?? null)
const hasReceipt = computed(() => (editingOrder.value?.received_quantity ?? 0) > 0)
const canSubmit = computed(() => form.cost_chf !== '' && (form.base !== PHONE || form.supplier.trim() !== ''))

const inboundItems = computed(() => [
  { title: t('grossanlass.beschaffung.bestellungen.orders.inboundDefault'), value: '' },
  { title: t('grossanlass.materials.zusage.inboundDelivery'), value: 'delivery' },
  { title: t('grossanlass.materials.zusage.inboundPickup'), value: 'pickup' },
])

const baseItems = computed(() => [
  ...props.line.quotes.map((quote) => ({
    title: `${quote.supplier} · ${formatChf(quote.amount_chf)}`,
    value: quote.id,
  })),
  { title: t('grossanlass.beschaffung.bestellungen.orders.basisPhone'), value: PHONE },
])

function resetForm() {
  editingId.value = null
  // Vorbelegung der ersten Bestellung: gewählte Offerte, sonst die einzige vorhandene Offerte; weitere Bestellungen starten leer.
  const first = activeOrders.value.length === 0
  const pick = first ? (props.line.quotes.find((quote) => quote.selected) ?? (props.line.quotes.length === 1 ? props.line.quotes[0] : undefined)) : undefined
  form.base = pick ? pick.id : PHONE
  form.supplier = ''
  form.quantity = ''
  form.cost_chf = pick ? String(pick.amount_chf) : ''
  form.order_ref = ''
  form.ordered_at = businessToday()
  form.delivery_at = pick?.delivery_at ? pick.delivery_at.slice(0, 10) : ''
  form.notes = ''
  form.inbound_mode = ''
  form.return_needed = (pick ? pick.return_needed : props.line.return_needed) ?? false
}

function startEdit(order: GrossanlassProcurementOrder) {
  editingId.value = order.id
  form.base = order.quote_id ?? PHONE
  form.supplier = order.quote_id ? '' : (order.supplier ?? '')
  form.quantity = String(order.ordered_quantity ?? order.quantity ?? '')
  form.cost_chf = String(order.cost_chf)
  form.order_ref = order.order_ref ?? ''
  form.ordered_at = order.ordered_at.slice(0, 10)
  form.delivery_at = order.delivery_at?.slice(0, 10) ?? ''
  form.notes = order.notes ?? ''
  form.inbound_mode = order.inbound_mode ?? ''
  form.return_needed = order.return_needed ?? false
}

function startCancel(order: GrossanlassProcurementOrder) {
  cancelTarget.value = order
  cancelReason.value = ''
  cancelOpen.value = true
}

async function submit(confirmOvercoverage: boolean) {
  if (!canSubmit.value) return
  saving.value = true
  try {
    const payload = {
      ...(form.base === PHONE ? { supplier: form.supplier.trim(), quote_id: null } : { quote_id: form.base }),
      ...(form.quantity !== '' ? { quantity: Number(form.quantity) } : {}),
      cost_chf: Number(form.cost_chf),
      order_ref: form.order_ref.trim() || null,
      notes: form.notes.trim() || null,
      ordered_at: `${form.ordered_at}T12:00:00`,
      delivery_at: form.delivery_at ? `${form.delivery_at}T12:00:00` : null,
      inbound_mode: form.inbound_mode,
      return_needed: form.return_needed,
      ...(confirmOvercoverage ? { confirm_overcoverage: true } : {}),
    }
    const updated = editingId.value
      ? await updateGrossanlassProcurementOrder(props.departmentId, props.line.id, editingId.value, payload)
      : await createGrossanlassProcurementOrder(props.departmentId, props.line.id, payload)
    overcoverageOpen.value = false
    toast.success(t('grossanlass.beschaffung.bestellungen.saveSuccess'))
    resetForm()
    emit('changed', updated)
  } catch (e: unknown) {
    const info = overcoverageInfoFromError(e)
    if (info) {
      overcoverage.value = info
      overcoverageOpen.value = true
    } else {
      toast.error((e as { response?: { data?: { error?: string } } })?.response?.data?.error || t('grossanlass.beschaffung.bestellungen.errorSave'))
    }
  } finally {
    saving.value = false
  }
}

async function confirmCancel() {
  if (!cancelTarget.value || !cancelReason.value.trim()) return
  saving.value = true
  try {
    const updated = await cancelGrossanlassProcurementOrder(props.departmentId, props.line.id, cancelTarget.value.id, cancelReason.value.trim())
    cancelOpen.value = false
    toast.success(t('grossanlass.beschaffung.bestellungen.orders.cancelled'))
    emit('changed', updated)
  } catch (e: unknown) {
    toast.error((e as { response?: { data?: { error?: string } } })?.response?.data?.error || t('grossanlass.beschaffung.bestellungen.errorSave'))
  } finally {
    saving.value = false
  }
}

watch(() => props.line.id, resetForm, { immediate: true })
</script>

<style scoped>
.orders-panel { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #e5e7eb; display: grid; gap: 10px; }
.orders-panel__title, .order-form__title { margin: 0; font-size: 0.9rem; }
.orders-panel__sums { display: flex; flex-wrap: wrap; gap: 6px 16px; margin: 0; font-size: 0.85rem; }
.orders-panel__sums div { display: flex; gap: 6px; }
.orders-panel__sums dt { color: #64748b; }
.orders-panel__sums dd { margin: 0; font-weight: 600; }
.orders-panel__sums .is-warn dd { color: #b45309; }
.orders-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.orders-list__item { display: grid; gap: 4px; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 8px; }
.orders-list__main { display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: baseline; }
.orders-list__progress { font-size: 0.8rem; color: #475569; }
.orders-list__actions { display: flex; gap: 4px; }
.chip { font-size: 0.7rem; padding: 1px 6px; border-radius: 999px; background: #e0f2fe; color: #075985; }
.muted { color: #64748b; font-size: 0.8rem; margin: 0; }
.orders-cancelled { font-size: 0.8rem; color: #64748b; }
.order-form { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; align-items: start; }
.order-form__title, .order-form__actions { grid-column: 1 / -1; }
.order-form__actions { display: flex; gap: 6px; }
</style>
