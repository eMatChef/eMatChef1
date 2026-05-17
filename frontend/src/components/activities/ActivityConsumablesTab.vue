<template>
  <div class="activity-consumables-tab">
    <ActivityTabHeader :title="t('activities.consumables.title')" />
    <div class="section-card activity-tab-panel-card">
      <p v-if="isLoading" class="activity-inline-loading">
        <span class="spinner spinner-sm"></span>
        <span>{{ t('activities.consumables.loading') }}</span>
      </p>
      <template v-else>
        <p class="consumable-hint text-muted">
          {{ t('activities.consumables.intro') }}
        </p>
        <p v-if="consumableAggregated.length === 0" class="text-muted">{{ t('activities.consumables.empty') }}</p>
        <div v-else class="consumables-list">
          <div v-for="row in consumableAggregated" :key="row.material_item_id" class="consumable-card">
            <div class="consumable-info">
              <span class="consumable-name">{{ displayNameAgg(row) }}</span>
              <span class="consumable-ordered text-muted">{{ t('activities.consumables.booked', { n: row.quantity_booked }) }}</span>
              <span v-if="usedQty(row.material_item_id) > 0" class="consumable-used">
                {{ t('activities.consumables.used', { n: usedQty(row.material_item_id) }) }}
              </span>
              <span v-if="remainingQty(row.material_item_id) > 0" class="consumable-remaining text-muted">
                {{ t('activities.consumables.remaining', { n: remainingQty(row.material_item_id) }) }}
              </span>
              <span v-else class="consumable-remaining consumable-remaining--zero">{{ t('activities.consumables.noConsumptionLeft') }}</span>
            </div>
            <div v-if="remainingQty(row.material_item_id) > 0" class="consumable-actions">
              <div class="consumable-qty-row">
                <button
                  type="button"
                  class="btn-qty"
                  :disabled="(qtyInputs[row.material_item_id] ?? 1) <= 1"
                  @click="bumpQty(row.material_item_id, -1)"
                >
                  −
                </button>
                <input
                  v-model.number="qtyInputs[row.material_item_id]"
                  type="number"
                  min="1"
                  :max="Math.max(1, remainingQty(row.material_item_id))"
                  class="consumable-qty-input"
                  @change="clampQtyFor(row.material_item_id)"
                />
                <button
                  type="button"
                  class="btn-qty"
                  :disabled="
                    (qtyInputs[row.material_item_id] ?? 1) >= remainingQty(row.material_item_id)
                  "
                  @click="bumpQty(row.material_item_id, 1)"
                >
                  +
                </button>
              </div>
              <button
                type="button"
                class="btn btn-sm btn-success"
                :disabled="!canCreate || postingId === row.material_item_id"
                @click="reportConsumption(row)"
              >
                {{ postingId === row.material_item_id ? t('activities.consumables.postingEllipsis') : t('activities.consumables.posting') }}
              </button>
            </div>
            <div v-else class="consumable-actions consumable-actions--blocked">
              <template v-if="canAddActivityMaterial">
                <p class="consumable-blocked-hint text-muted">
                  {{ t('activities.consumables.blockedHintWithNachbuchung') }}
                </p>
                <button
                  type="button"
                  class="btn btn-sm btn-primary"
                  @click="emitNachbuchung(row)"
                >
                  {{ t('activities.consumables.addNachlieferung') }}
                </button>
              </template>
              <p v-else class="consumable-blocked-hint text-muted">
                {{ t('activities.consumables.blockedHintNoRights') }}
              </p>
            </div>
            <div
              v-if="canAddActivityMaterial && remainingQty(row.material_item_id) > 0"
              class="consumable-surplus-row"
            >
              <p class="consumable-surplus-hint text-muted">
                {{ t('activities.consumables.surplusHint', { n: remainingQty(row.material_item_id) }) }}
              </p>
              <button
                type="button"
                class="btn btn-sm btn-outline"
                :disabled="releasingId === row.material_item_id"
                @click="releaseSurplus(row)"
              >
                {{
                  releasingId === row.material_item_id
                    ? t('activities.consumables.surplusReleasing')
                    : t('activities.consumables.surplusRelease', { n: remainingQty(row.material_item_id) })
                }}
              </button>
            </div>
            <div
              v-if="canAddActivityMaterial"
              class="consumable-nachlieferung"
            >
              <button type="button" class="link-btn" @click="emitNachbuchung(row)">
                {{ t('activities.consumables.increaseBooked') }}
              </button>
            </div>
          </div>
        </div>

        <section v-if="consumableAggregated.length > 0" class="costs-section consumable-costs-section">
          <h3 class="costs-section-title">{{ t('activities.consumables.costsTitle') }}</h3>
          <p class="consumable-costs-hint text-muted">{{ t('activities.consumables.costsHint') }}</p>
          <div class="costs-table">
            <div class="costs-row costs-row-header">
              <span class="costs-col-name">{{ t('activities.consumables.costsColMaterial') }}</span>
              <span class="costs-col-qty">{{ t('activities.consumables.costsColBooked') }}</span>
              <span class="costs-col-used">{{ t('activities.consumables.costsColUsed') }}</span>
              <span class="costs-col-price">{{ t('activities.consumables.costsColUnitPrice') }}</span>
              <span class="costs-col-total">{{ t('activities.consumables.costsColAmount') }}</span>
            </div>
            <div v-for="row in consumableAggregated" :key="'cost-' + row.material_item_id" class="costs-row">
              <span class="costs-col-name">{{ displayNameAgg(row) }}</span>
              <span class="costs-col-qty">{{ row.quantity_booked }}</span>
              <span class="costs-col-used">{{ usedQty(row.material_item_id) || '–' }}</span>
              <span class="costs-col-price">{{ formatUnitPrice(row.sale_price) }}</span>
              <span class="costs-col-total">{{ formatLineAmount(row) }}</span>
            </div>
          </div>
          <div class="costs-subtotal">
            <span>{{ t('activities.consumables.costsTotal') }}</span>
            <strong>CHF {{ formatChf(consumableCostTotalValue) }}</strong>
          </div>
          <p v-if="consumableCostTotalValue <= 0" class="consumable-costs-none text-muted">
            {{ t('activities.consumables.costsNoneYet') }}
          </p>
        </section>

        <div v-if="consumptionHistory.length > 0" class="consumable-history">
          <h3 class="consumable-history-title">{{ t('activities.consumables.historyTitle') }}</h3>
          <div
            v-for="cr in consumptionHistory"
            :key="cr.id"
            class="consumable-history-item"
          >
            <span class="consumable-history-name">{{ cr.material_name || t('activities.common.material') }}</span>
            <span class="consumable-history-qty">{{ t('activities.consumables.historyQty', { n: cr.quantity }) }}</span>
            <span class="consumable-history-time">{{ formatDateTime(cr.reported_at) }}</span>
            <span v-if="cr.description" class="consumable-history-desc">{{ cr.description }}</span>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  createActivityIssue,
  getActivityIssues,
  getActivityItems,
  releaseConsumableSurplus,
  type ActivityIssueReportRow,
  type ActivityItemRow,
} from '@/api/activities'
import {
  consumableChargeableCost,
  consumableCostTotal,
  consumableDisplayName,
  formatChf,
  formatChfLabel,
  parseMoney,
} from '@/components/activities/activityCosts'
import { useToast } from '@/composables/useToast'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'

defineOptions({ name: 'ActivityConsumablesTab' })

const { t, locale } = useI18n()

const props = defineProps<{
  activityId: string
  reloadToken?: number
  canCreate: boolean
  /** Materialwart / DC: addActivityItem */
  canAddActivityMaterial?: boolean
}>()

const emit = defineEmits<{
  requestNachbuchung: [
    payload: {
      materialItemId: string
      materialLabel: string
      packSize?: number | null
      packUnit?: string | null
    },
  ]
  /** Nach erfolgreicher Verbrauchsmeldung: Parent lädt Meldungen neu (Kosten-Tab, Reparaturen/Verluste). */
  consumptionBooked: []
}>()

const toast = useToast()
const isLoading = ref(false)
const activityItems = ref<ActivityItemRow[]>([])
const issues = ref<ActivityIssueReportRow[]>([])
const qtyInputs = ref<Record<string, number>>({})
const postingId = ref<string | null>(null)

/** Pro Material aggregiert (mehrere Aktivitätszeilen gleicher material_item_id) */
const consumableAggregated = computed(() => {
  const map = new Map<
    string,
    {
      material_item_id: string
      material_name: string
      linked_container_label?: string | null
      quantity_booked: number
      pack_size?: number | null
      pack_unit?: string | null
      sale_price: number | null
    }
  >()
  for (const r of activityItems.value.filter((x) => x.is_consumable === true)) {
    const ex = map.get(r.material_item_id)
    const price = parseMoney(r.sale_price)
    if (ex) {
      ex.quantity_booked += r.quantity
      if (!ex.pack_size && r.pack_size) {
        ex.pack_size = r.pack_size
        ex.pack_unit = r.pack_unit ?? null
      }
      if (ex.sale_price == null && price != null) {
        ex.sale_price = price
      }
    } else {
      map.set(r.material_item_id, {
        material_item_id: r.material_item_id,
        material_name: r.material_name,
        linked_container_label: r.linked_container_label,
        quantity_booked: r.quantity,
        pack_size: r.pack_size ?? null,
        pack_unit: r.pack_unit ?? null,
        sale_price: price,
      })
    }
  }
  return [...map.values()]
})

const consumableCostTotalValue = computed(() =>
  consumableCostTotal(activityItems.value, issues.value),
)

function formatUnitPrice(price: number | null): string {
  return formatChfLabel(price)
}

function formatLineAmount(row: { material_item_id: string; sale_price: number | null }): string {
  const amount = consumableChargeableCost(row.material_item_id, activityItems.value, issues.value)
  return formatChfLabel(amount)
}

const releasingId = ref<string | null>(null)

const consumptionReports = computed(() => issues.value.filter((i) => i.type === 'consumption'))

const consumptionHistory = computed(() =>
  [...consumptionReports.value].sort((a, b) => {
    const ta = new Date(a.reported_at).getTime()
    const tb = new Date(b.reported_at).getTime()
    if (tb !== ta) return tb - ta
    return b.id.localeCompare(a.id)
  }),
)

function displayNameAgg(row: {
  material_name: string
  linked_container_label?: string | null
}): string {
  const l = row.linked_container_label?.trim()
  return l ? `${l} — ${row.material_name}` : row.material_name
}

function emitNachbuchung(row: {
  material_item_id: string
  material_name: string
  linked_container_label?: string | null
  pack_size?: number | null
  pack_unit?: string | null
}) {
  emit('requestNachbuchung', {
    materialItemId: row.material_item_id,
    materialLabel: displayNameAgg(row),
    packSize: row.pack_size ?? null,
    packUnit: row.pack_unit ?? null,
  })
}

function usedQty(materialItemId: string): number {
  return consumptionReports.value
    .filter((c) => c.material_item_id === materialItemId)
    .reduce((s, c) => s + c.quantity, 0)
}

function bookedQty(materialItemId: string): number {
  return (
    consumableAggregated.value.find((r) => r.material_item_id === materialItemId)?.quantity_booked ?? 0
  )
}

function remainingQty(materialItemId: string): number {
  return Math.max(0, bookedQty(materialItemId) - usedQty(materialItemId))
}

function clampQtyFor(materialItemId: string) {
  const rem = remainingQty(materialItemId)
  let n = Number(qtyInputs.value[materialItemId])
  if (!Number.isFinite(n)) n = 1
  if (rem < 1) {
    qtyInputs.value[materialItemId] = 1
    return
  }
  qtyInputs.value[materialItemId] = Math.min(rem, Math.max(1, Math.floor(n)))
}

function bumpQty(materialItemId: string, delta: number) {
  const rem = remainingQty(materialItemId)
  if (rem < 1) return
  const cur = qtyInputs.value[materialItemId] ?? 1
  qtyInputs.value[materialItemId] = Math.min(rem, Math.max(1, Math.floor(cur) + delta))
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString(locale.value, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function load() {
  isLoading.value = true
  try {
    const [items, iss] = await Promise.all([
      getActivityItems(props.activityId),
      getActivityIssues(props.activityId),
    ])
    activityItems.value = items
    issues.value = iss
    for (const r of items.filter((x) => x.is_consumable === true)) {
      if (qtyInputs.value[r.material_item_id] == null) {
        qtyInputs.value[r.material_item_id] = 1
      }
    }
    for (const row of [...new Set(items.filter((x) => x.is_consumable).map((x) => x.material_item_id))]) {
      const rem = remainingQty(row)
      if (rem < 1) continue
      const cur = qtyInputs.value[row] ?? 1
      qtyInputs.value[row] = Math.min(rem, Math.max(1, cur))
    }
  } catch {
    activityItems.value = []
    issues.value = []
    toast.error(t('activities.consumables.toastLoadFailed'))
  } finally {
    isLoading.value = false
  }
}

async function releaseSurplus(row: { material_item_id: string }) {
  const rem = remainingQty(row.material_item_id)
  if (rem < 1 || releasingId.value) return
  releasingId.value = row.material_item_id
  try {
    await releaseConsumableSurplus(props.activityId, {
      material_item_id: row.material_item_id,
      quantity: rem,
    })
    toast.success(t('activities.consumables.toastSurplusReleased', { n: rem }))
    emit('consumptionBooked')
    await load()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.consumables.toastSurplusFailed'))
  } finally {
    releasingId.value = null
  }
}

async function reportConsumption(row: {
  material_item_id: string
  material_name: string
  quantity_booked: number
}) {
  if (!props.canCreate || postingId.value) return
  clampQtyFor(row.material_item_id)
  const rem = remainingQty(row.material_item_id)
  const q = qtyInputs.value[row.material_item_id] ?? 1
  if (q < 1 || q > rem) {
    toast.error(rem < 1 ? t('activities.consumables.toastNoRemaining') : t('activities.consumables.toastMaxPieces', { n: rem }))
    return
  }
  postingId.value = row.material_item_id
  try {
    await createActivityIssue(props.activityId, {
      material_item_id: row.material_item_id,
      type: 'consumption',
      quantity: q,
      description: null,
    })
    toast.success(t('activities.consumables.toastBooked'))
    qtyInputs.value[row.material_item_id] = 1
    emit('consumptionBooked')
    await load()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.consumables.toastBookFailed'))
  } finally {
    postingId.value = null
  }
}

watch(
  () => [props.activityId, props.reloadToken ?? 0] as const,
  () => {
    void load()
  },
  { immediate: true },
)
</script>

<style scoped>
@import '@/styles/views/activities/detail-workflow.css';

.consumable-hint {
  margin: 0 0 14px;
  font-size: 13px;
  line-height: 1.45;
  max-width: 40rem;
}

.consumables-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.consumable-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  background: #fafafa;
}

.consumable-info {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px 14px;
  margin-bottom: 10px;
}

.consumable-name {
  font-weight: 600;
  flex: 1;
  min-width: 0;
}

.consumable-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.consumable-qty-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-qty {
  width: 32px;
  height: 34px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
}

.btn-qty:hover {
  background: #f1f5f9;
}

.consumable-qty-input {
  width: 3.5rem;
  text-align: center;
  padding: 6px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}

.consumable-used {
  font-size: 13px;
  color: #b45309;
  font-weight: 500;
}

.consumable-remaining {
  font-size: 13px;
}

.consumable-remaining--zero {
  color: #b91c1c;
  font-weight: 500;
}

.consumable-actions--blocked {
  flex-direction: column;
  align-items: flex-start;
}

.consumable-blocked-hint {
  margin: 0 0 8px;
  font-size: 13px;
  line-height: 1.45;
  max-width: 36rem;
}

.consumable-surplus-row {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed #e5e7eb;
}

.consumable-surplus-hint {
  margin: 0 0 8px;
  font-size: 12px;
  line-height: 1.45;
}

.consumable-nachlieferung {
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px dashed #e5e7eb;
}

.link-btn {
  background: none;
  border: none;
  padding: 0;
  color: #2563eb;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  text-decoration: underline;
}

.link-btn:hover {
  color: #1d4ed8;
}

.consumable-costs-section {
  margin-top: 20px;
}

.consumable-costs-hint {
  margin: 0 0 12px;
  font-size: 12px;
  line-height: 1.45;
}

.consumable-costs-none {
  margin: 8px 0 0;
  font-size: 12px;
}

.consumable-history {
  margin-top: 22px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.consumable-history-title {
  margin: 0 0 10px;
  font-size: 0.95rem;
  font-weight: 600;
}

.consumable-history-item {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 4px 12px;
  padding: 8px 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13px;
}

.consumable-history-item:last-child {
  border-bottom: none;
}

.consumable-history-name {
  font-weight: 500;
}

.consumable-history-qty {
  font-variant-numeric: tabular-nums;
}

.consumable-history-time {
  grid-column: 1 / -1;
  font-size: 12px;
  color: #64748b;
}

.consumable-history-desc {
  grid-column: 1 / -1;
  color: #475569;
  font-size: 12px;
}
</style>
