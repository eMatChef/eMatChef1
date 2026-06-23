<template>
  <div class="activity-consumables-tab">
    <ActivityTabHeader :title="t('activities.consumables.title')" />
    <div class="section-card activity-tab-panel-card">
      <ELoadingState
        v-if="isLoading"
        variant="inline"
        class="activity-consumables-loading"
        :message="t('activities.consumables.loading')"
      />
      <template v-else>
        <p class="consumable-hint text-muted">
          {{ t('activities.consumables.intro') }}
        </p>
        <p v-if="consumableAggregated.length === 0" class="text-muted">{{ t('activities.consumables.empty') }}</p>
        <div v-else class="consumables-list">
          <article
            v-for="row in consumableAggregated"
            :key="row.material_item_id"
            class="consumable-card"
          >
            <header class="consumable-card__head">
              <h4 class="consumable-card__title">{{ displayNameAgg(row) }}</h4>
              <v-chip size="small" variant="tonal" class="consumable-card__booked">
                {{ t('activities.consumables.booked', { n: row.quantity_booked }) }}
              </v-chip>
            </header>

            <div
              v-if="row.quantity_warehouse > 0 || row.quantity_replenishment > 0"
              class="consumable-card__sources"
            >
              <v-chip
                v-if="row.quantity_warehouse > 0"
                size="x-small"
                variant="tonal"
                color="info"
                class="consumable-chip consumable-chip--warehouse"
              >
                {{ t('activities.consumables.chipWarehouse', { n: row.quantity_warehouse }) }}
              </v-chip>
              <v-chip
                v-if="row.quantity_replenishment > 0"
                size="x-small"
                variant="tonal"
                color="orange"
                class="consumable-chip consumable-chip--replenishment"
              >
                {{ t('activities.consumables.chipReplenishment', { n: row.quantity_replenishment }) }}
              </v-chip>
            </div>

            <div class="consumable-card__stats">
              <div class="consumable-stat consumable-stat--used">
                <span class="consumable-stat__label">{{ t('activities.consumables.statUsed') }}</span>
                <span class="consumable-stat__value">{{ usedQty(row.material_item_id) }}</span>
              </div>
              <div
                class="consumable-stat"
                :class="
                  remainingQty(row.material_item_id) > 0
                    ? 'consumable-stat--remaining'
                    : 'consumable-stat--remaining-zero'
                "
              >
                <span class="consumable-stat__label">{{ t('activities.consumables.statRemaining') }}</span>
                <span class="consumable-stat__value">{{ remainingQty(row.material_item_id) }}</span>
              </div>
            </div>

            <div
              v-if="remainingQty(row.material_item_id) > 0"
              class="consumable-card__book"
            >
              <label class="consumable-book__label" :for="'consumable-qty-' + row.material_item_id">
                {{ t('activities.consumables.bookQtyLabel') }}
              </label>
              <div class="consumable-book__controls">
                <div class="consumable-qty-row">
                  <EButton
                    variant="secondary"
                    size="x-small"
                    class="consumable-qty-btn"
                    :disabled="(qtyInputs[row.material_item_id] ?? 1) <= 1"
                    @click="bumpQty(row.material_item_id, -1)"
                  >
                    −
                  </EButton>
                  <v-text-field
                    :id="'consumable-qty-' + row.material_item_id"
                    v-model.number="qtyInputs[row.material_item_id]"
                    type="number"
                    min="1"
                    :max="Math.max(1, remainingQty(row.material_item_id))"
                    density="compact"
                    variant="outlined"
                    hide-details
                    class="consumable-qty-input"
                    @change="clampQtyFor(row.material_item_id)"
                  />
                  <EButton
                    variant="secondary"
                    size="x-small"
                    class="consumable-qty-btn"
                    :disabled="
                      (qtyInputs[row.material_item_id] ?? 1) >= remainingQty(row.material_item_id)
                    "
                    @click="bumpQty(row.material_item_id, 1)"
                  >
                    +
                  </EButton>
                </div>
                <EButton
                  variant="primary"
                  size="small"
                  class="consumable-book__submit"
                  :disabled="!canCreate || postingId === row.material_item_id"
                  :loading="postingId === row.material_item_id"
                  @click="reportConsumption(row)"
                >
                  {{
                    postingId === row.material_item_id
                      ? t('activities.consumables.postingEllipsis')
                      : t('activities.consumables.posting')
                  }}
                </EButton>
              </div>
            </div>

            <div v-else class="consumable-card__blocked">
              <template v-if="canRequestNachbuchung">
                <p class="consumable-blocked-hint text-muted">
                  {{ t('activities.consumables.blockedHintWithNachbuchung') }}
                </p>
                <EButton variant="primary" size="small" @click="emitNachbuchung(row)">
                  {{ t('activities.consumables.addNachlieferung') }}
                </EButton>
              </template>
              <p v-else class="consumable-blocked-hint text-muted">
                {{ t('activities.consumables.blockedHintNoRights') }}
              </p>
            </div>

            <footer
              v-if="canRequestNachbuchung && remainingQty(row.material_item_id) > 0"
              class="consumable-card__footer"
            >
              <EButton
                variant="text"
                size="small"
                class="consumable-footer__nachbuchung"
                @click="emitNachbuchung(row)"
              >
                {{ t('activities.consumables.increaseBooked') }}
              </EButton>
            </footer>
          </article>
        </div>

        <section v-if="replenishmentHistory.length > 0" class="consumable-replenishment-section">
          <h3 class="consumable-history-title">{{ t('activities.consumables.replenishmentSectionTitle') }}</h3>
          <p class="consumable-replenishment-hint text-muted">{{ t('activities.consumables.replenishmentSectionHint') }}</p>
          <div class="consumable-history consumable-history--replenishment">
            <div
              v-for="row in replenishmentHistory"
              :key="'repl-' + row.id"
              class="consumable-history-item"
            >
              <span class="consumable-history-name">{{ row.material_name }}</span>
              <span class="consumable-history-qty">{{ t('activities.consumables.historyQty', { n: row.quantity }) }}</span>
              <span class="consumable-history-amount">{{
                t('activities.consumables.replenishmentHistoryPurchase', {
                  total: formatChfLabel(row.line_total ?? (row.unit_purchase ?? 0) * row.quantity),
                  unit: formatChfLabel(row.unit_purchase),
                })
              }}</span>
              <span v-if="replenishmentRecordedAt(row)" class="consumable-history-time">{{
                formatDateTime(replenishmentRecordedAt(row)!)
              }}</span>
            </div>
          </div>
        </section>

        <section v-if="consumableAggregated.length > 0" class="costs-section consumable-costs-section">
          <h3 class="costs-section-title">{{ t('activities.consumables.costsTitle') }}</h3>
          <p class="consumable-costs-hint text-muted">{{ t('activities.consumables.costsHint') }}</p>
          <div class="costs-table">
            <div class="costs-row costs-row-header">
              <span class="costs-col-name">{{ t('common.material') }}</span>
              <span class="costs-col-qty">{{ t('activities.consumables.costsColBooked') }}</span>
              <span class="costs-col-used">{{ t('activities.consumables.costsColUsed') }}</span>
              <span class="costs-col-price">{{ t('activities.consumables.costsColUnitPrice') }}</span>
              <span class="costs-col-total">{{ t('common.amount') }}</span>
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
            <span class="consumable-history-name">{{ cr.material_name || t('common.material') }}</span>
            <span class="consumable-history-qty">{{ t('activities.consumables.historyQty', { n: cr.quantity }) }}</span>
            <span class="consumable-history-time">{{ formatDateTime(cr.reported_at) }}</span>
            <span v-if="cr.description" class="consumable-history-desc">{{ cr.description }}</span>
            <div v-if="canManageConsumptionEntries" class="consumable-history-actions">
              <EButton variant="secondary" size="x-small" @click="emitEditConsumption(cr)">
                {{ t('activities.consumables.historyEdit') }}
              </EButton>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getPackItems, type ActivityPackItem } from '@/api/activityPackItems'
import {
  createActivityIssue,
  getActivityIssues,
  getActivityItems,
  type ActivityIssueReportRow,
  type ActivityItemRow,
} from '@/api/activities'
import {
  aggregateConsumableRows,
  consumableChargeableCost,
  consumableCostTotal,
  consumableDisplayName,
  formatChf,
  formatChfLabel,
  replenishmentPurchaseRows,
} from '@/components/activities/activityCosts'
import { useToast } from '@/composables/useToast'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import { EButton } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import type { ConsumptionModalPreset } from '@/components/activities/ActivityConsumptionModal.vue'

defineOptions({ name: 'ActivityConsumablesTab' })

const { t, locale } = useI18n()

const props = defineProps<{
  activityId: string
  reloadToken?: number
  canCreate: boolean
  /** Materialwart / DC: addActivityItem */
  canAddActivityMaterial?: boolean
  /** Nachlieferung Verbrauchsmaterial (Gruppe/Ersteller ab «Am Event» oder MW/DC) */
  canRequestConsumableReplenishment?: boolean
  /** Pipeline-Stufe für Nachlieferung (z. B. packed_at_event) */
  replenishmentPackStage?: string | null
}>()

const emit = defineEmits<{
  requestNachbuchung: [
    payload: {
      materialItemId: string
      materialLabel: string
      packSize?: number | null
      packUnit?: string | null
      packStage?: string
    },
  ]
  /** Nach erfolgreicher Verbrauchsmeldung: Parent lädt Meldungen neu (Kosten-Tab, Reparaturen/Verluste). */
  consumptionBooked: []
  editConsumption: [payload: ConsumptionModalPreset]
}>()

const toast = useToast()
const isLoading = ref(false)
const activityItems = ref<ActivityItemRow[]>([])
const packItems = ref<ActivityPackItem[]>([])
const issues = ref<ActivityIssueReportRow[]>([])
const qtyInputs = ref<Record<string, number>>({})
const postingId = ref<string | null>(null)

/** Pro Material aggregiert (Lager vs. Nachlieferung / Zukauf). */
const consumableAggregated = computed(() => {
  const rows = aggregateConsumableRows(activityItems.value)
  return rows.map((row) => {
    const raw = activityItems.value.find(
      (r) => r.material_item_id === row.material_item_id && r.is_consumable === true,
    )
    return {
      ...row,
      pack_size: raw?.pack_size ?? null,
      pack_unit: raw?.pack_unit ?? null,
    }
  })
})

const replenishmentRows = computed(() => replenishmentPurchaseRows(activityItems.value))

const replenishmentHistory = computed(() =>
  [...replenishmentRows.value].sort((a, b) => {
    const ta = new Date(replenishmentRecordedAt(a) ?? 0).getTime()
    const tb = new Date(replenishmentRecordedAt(b) ?? 0).getTime()
    if (tb !== ta) return tb - ta
    return b.id.localeCompare(a.id)
  }),
)

function replenishmentRecordedAt(row: { recorded_at?: string | null }): string | null {
  const raw = row.recorded_at?.trim()
  return raw || null
}

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

const canRequestNachbuchung = computed(
  () =>
    Boolean(props.canRequestConsumableReplenishment) || Boolean(props.canAddActivityMaterial),
)

const canManageConsumptionEntries = computed(
  () => props.canCreate || canRequestNachbuchung.value,
)

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

function materialMetaForIssue(cr: ActivityIssueReportRow): {
  pack_size: number | null
  pack_unit: string | null
  linked_container_label: string | null
} {
  const raw = activityItems.value.find((r) => r.material_item_id === cr.material_item_id)
  return {
    pack_size: raw?.pack_size ?? null,
    pack_unit: raw?.pack_unit ?? null,
    linked_container_label: raw?.linked_container_label ?? null,
  }
}

function emitEditConsumption(cr: ActivityIssueReportRow) {
  if (!canManageConsumptionEntries.value || !cr.material_item_id) return
  const meta = materialMetaForIssue(cr)
  emit('editConsumption', {
    materialItemId: cr.material_item_id,
    materialName: cr.material_name ?? t('common.material'),
    packSize: meta.pack_size,
    packUnit: meta.pack_unit,
    linkedContainerLabel: meta.linked_container_label,
    editIssueId: cr.id,
    editQuantity: cr.quantity,
    editDescription: cr.description ?? null,
  })
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
    packStage: props.replenishmentPackStage?.trim() || undefined,
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

function returnedQty(materialItemId: string): number {
  return packItems.value
    .filter((p) => p.materialItemId === materialItemId)
    .reduce((s, p) => s + (p.quantityReturned ?? 0), 0)
}

function remainingQty(materialItemId: string): number {
  return Math.max(0, bookedQty(materialItemId) - usedQty(materialItemId) - returnedQty(materialItemId))
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
    const [items, iss, pack] = await Promise.all([
      getActivityItems(props.activityId),
      getActivityIssues(props.activityId),
      getPackItems(props.activityId).catch(() => []),
    ])
    activityItems.value = items
    issues.value = iss
    packItems.value = pack
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
    packItems.value = []
    issues.value = []
    toast.error(t('activities.consumables.toastLoadFailed'))
  } finally {
    isLoading.value = false
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

.activity-consumables-loading {
  padding: 8px 0;
}

.consumable-hint {
  margin: 0 0 14px;
  font-size: 13px;
  line-height: 1.45;
  max-width: 40rem;
}

.consumables-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.consumable-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 0;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 1px 2px rgb(15 23 42 / 4%);
}

.consumable-card__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px 12px;
  padding: 14px 16px 10px;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
}

.consumable-card__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.3;
  color: #0f172a;
  flex: 1;
  min-width: 0;
}

.consumable-card__booked {
  flex-shrink: 0;
}

.consumable-card__sources {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px 16px 0;
}

.consumable-chip {
  font-weight: 500;
}

.consumable-card__stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 12px 16px;
}

.consumable-stat {
  padding: 10px 12px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.consumable-stat__label {
  display: block;
  font-size: 11px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #64748b;
  margin-bottom: 4px;
}

.consumable-stat__value {
  font-size: 1.25rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
}

.consumable-stat__value::after {
  content: ' Stk.';
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
}

.consumable-stat--used .consumable-stat__value {
  color: #b45309;
}

.consumable-stat--remaining .consumable-stat__value {
  color: #0f766e;
}

.consumable-stat--remaining-zero .consumable-stat__value {
  color: #b91c1c;
}

.consumable-card__book {
  padding: 0 16px 14px;
}

.consumable-book__label {
  display: block;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  margin-bottom: 8px;
}

.consumable-book__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 12px;
}

.consumable-qty-row {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.consumable-qty-btn {
  min-width: 2rem !important;
  padding-inline: 0 !important;
}

.consumable-qty-input {
  flex: 0 0 3.5rem;
  max-width: 3.5rem;
}

.consumable-qty-input :deep(.v-field) {
  font-size: 15px;
  font-weight: 600;
}

.consumable-qty-input :deep(input) {
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.consumable-book__submit {
  flex: 1;
  min-width: 10rem;
  justify-content: center;
}

.consumable-card__blocked {
  padding: 0 16px 14px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
}

.consumable-blocked-hint {
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
  max-width: 36rem;
}

.consumable-card__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px 16px;
  padding: 12px 16px;
  border-top: 1px solid #f1f5f9;
  background: #fafbfc;
}

.consumable-footer__nachbuchung {
  flex-shrink: 0;
  align-self: center;
}

@media (max-width: 520px) {
  .consumable-card__stats {
    grid-template-columns: 1fr;
  }

  .consumable-book__submit {
    width: 100%;
  }

  .consumable-card__footer {
    flex-direction: column;
    align-items: stretch;
  }

  .consumable-footer__nachbuchung {
    align-self: flex-start;
  }
}

.consumable-replenishment-section {
  margin-top: 20px;
}

.consumable-replenishment-hint {
  margin: 0 0 12px;
  font-size: 12px;
  line-height: 1.45;
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
  grid-template-columns: 1fr auto auto;
  gap: 4px 12px;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13px;
}

.consumable-history-actions {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}

.consumable-history-item:last-child {
  border-bottom: none;
}

.consumable-history-name {
  font-weight: 500;
}

.consumable-history-qty {
  font-variant-numeric: tabular-nums;
  color: #b45309;
  font-weight: 500;
}

.consumable-history-amount {
  font-variant-numeric: tabular-nums;
  font-size: 12px;
  color: #475569;
  text-align: right;
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
