<template>
  <div class="ga-inbound">
    <p class="tab-intro">{{ t('grossanlass.material.wareneingang.intro') }}</p>

    <div class="inbound-search">
      <ESearchField
        v-model="search"
        :label="t('grossanlass.material.wareneingang.scanLabel')"
        :placeholder="t('grossanlass.material.wareneingang.scanPlaceholder')"
        autofocus
        @keydown.enter="onScanEnter"
      />
    </div>

    <div class="inbound-toolbar">
      <EButton
        v-for="chip in rangeChips"
        :key="chip.id"
        :variant="range === chip.id ? 'primary' : 'secondary'"
        size="small"
        @click="range = chip.id"
      >
        {{ chip.label }}
      </EButton>
      <EButton
        v-for="chip in modeChips"
        :key="chip.id"
        :variant="modeFilter === chip.id ? 'primary' : 'secondary'"
        size="small"
        @click="modeFilter = chip.id"
      >
        {{ chip.label }}
      </EButton>
    </div>

    <ELoadingState v-if="catalog.loading.value" variant="list" :message="t('common.loading')" />

    <EEmptyState
      v-else-if="visibleRows.length === 0"
      variant="default"
      icon="mdi-truck-delivery-outline"
      :title="t('grossanlass.material.wareneingang.emptyTitle')"
      :description="t('grossanlass.material.wareneingang.emptyDescription')"
    />

    <ul v-else class="inbound-list">
      <li v-for="row in visibleRows" :key="row.id" class="inbound-card">
        <div class="inbound-card__head">
          <div>
            <strong>{{ row.name }}</strong>
            <span class="inbound-qty">{{ t('grossanlass.material.wareneingang.orderedQty', { n: orderedQty(row) }) }}</span>
            <span
              v-if="row.quantity !== orderedQty(row)"
              class="inbound-qty inbound-qty--sub"
            >
              {{ t('grossanlass.material.wareneingang.thisDelivery', { n: row.quantity }) }}
            </span>
          </div>
          <span class="combo-type-badge" :class="row.origin === 'loan' ? 'virtual_combo' : 'physical_combo'">
            {{ t(`grossanlass.materials.originBadge.${originBadgeKey(row.origin)}`) }}
          </span>
        </div>
        <p class="inbound-meta">
          {{ row.source }}
          · {{ t(`grossanlass.material.wareneingang.mode.${arrivalMode(row)}`) }}
          · {{ expectedLabel(row) }}
        </p>
        <p v-if="row.item_details?.order_ref" class="inbound-meta">
          {{ t('grossanlass.beschaffung.bestellungen.orderRef') }}: {{ row.item_details.order_ref }}
        </p>
        <div class="inbound-check">
          <ECheckbox
            :model-value="Boolean(row.item_details?.qty_checked)"
            :label="t('grossanlass.material.wareneingang.orderedCheck', { n: orderedQty(row) })"
            hide-details
            :disabled="busyId === row.id"
            @update:model-value="toggleQtyChecked(row, Boolean($event))"
          />
          <dl class="inbound-numbers" :class="`inbound-numbers--${inboundState(row)}`">
            <div>
              <dt>{{ t('grossanlass.material.wareneingang.numExpected') }}</dt>
              <dd>{{ row.quantity }}</dd>
            </div>
            <div>
              <dt>{{ t('grossanlass.material.wareneingang.numArrived') }}</dt>
              <dd>{{ receivedQty(row) }}</dd>
            </div>
            <div class="inbound-numbers__open">
              <dt>{{ t('grossanlass.material.wareneingang.numOpen') }}</dt>
              <dd>{{ missingQty(row) }}</dd>
            </div>
          </dl>
        </div>
        <div v-if="missingQty(row) > 0" class="inbound-receive">
          <ETextField
            v-model="receiveQty[row.id]"
            type="number"
            min="1"
            :max="missingQty(row)"
            :label="t('grossanlass.material.wareneingang.receiveQty')"
            hide-details
            density="compact"
          />
          <ESelect
            v-model="receivePlace[row.id]"
            :items="placeItems"
            item-title="title"
            item-value="value"
            :label="t('grossanlass.material.wareneingang.receivePlace')"
            clearable
            hide-details
            density="compact"
          />
          <ETextField
            v-model="receiveNote[row.id]"
            :label="t('grossanlass.material.wareneingang.receiveNote')"
            hide-details
            density="compact"
          />
          <div class="inbound-quick">
            <EButton variant="secondary" size="small" @click="receiveQty[row.id] = missingQty(row)">
              {{ t('grossanlass.material.wareneingang.receiveRest', { n: missingQty(row) }) }}
            </EButton>
            <EButton v-if="missingQty(row) > 1" variant="secondary" size="small" @click="receiveQty[row.id] = 1">
              +1
            </EButton>
          </div>
          <EButton
            variant="primary"
            size="small"
            :loading="busyId === row.id"
            @click="receive(row)"
          >
            {{ t('grossanlass.material.wareneingang.receiveSubmit') }}
          </EButton>
        </div>
        <div v-if="arrivedNow[row.id] || receivedQty(row) > 0" class="inbound-label">
          <span v-if="arrivedNow[row.id]">
            {{ t('grossanlass.material.wareneingang.justBooked', { n: arrivedNow[row.id] }) }}
          </span>
          <span v-else>{{ t('grossanlass.material.wareneingang.alreadyArrived', { n: receivedQty(row) }) }}</span>
          <EButton :variant="arrivedNow[row.id] ? 'primary' : 'secondary'" size="small" @click="openLabel(row)">
            <v-icon icon="mdi-printer" start size="16" />
            {{ t('grossanlass.material.wareneingang.printLabel') }}
          </EButton>
        </div>
        <div class="inbound-docs">
          <a
            v-if="isBuyOrder(row) && orderPdfUrl(row)"
            :href="orderPdfUrl(row)!"
            target="_blank"
            rel="noopener"
            class="inbound-pdf"
          >
            {{ t('grossanlass.material.wareneingang.openPdf') }}
          </a>
          <EButton
            v-else-if="isBuyOrder(row) && orderLineOf(row)"
            variant="text"
            size="small"
            @click="openOrder(row)"
          >
            {{ t('grossanlass.material.wareneingang.openOrder') }}
          </EButton>
          <EButton variant="text" size="small" @click="openHistory(row)">
            {{ t('grossanlass.material.wareneingang.openHistory') }}
          </EButton>
        </div>
        <div class="inbound-qr">
          <PublicQrTag
            v-if="row.barcode"
            :url="row.barcode"
            :code="row.barcode"
            :size="72"
            :image-label="row.name"
            :image-entity-id="row.id"
          />
          <span class="inbound-code">{{ row.barcode || t('grossanlass.material.wareneingang.noQr') }}</span>
        </div>
        <div v-if="needsOf(row).length" class="inbound-need">
          <strong>{{ t('grossanlass.material.wareneingang.needTitle') }}</strong>
          <ul>
            <li v-for="need in needsOf(row)" :key="need.id">
              {{ need.text }}
              <EButton variant="text" size="small" @click="goNeed(need)">
                {{ need.action }}
              </EButton>
            </li>
          </ul>
        </div>
        <div v-if="inboundState(row) === 'complete'" class="inbound-actions">
          <span class="inbound-here">{{ t('grossanlass.materials.chargeFlag.here') }}</span>
        </div>
      </li>
    </ul>

    <GrossanlassLabelPrintDialog
      v-model="labelOpen"
      :code="labelCode"
      :title="labelTitle"
      :lines="labelLines"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, ESearchField, ESelect, ETextField } from '@/components/form/base'
import GrossanlassLabelPrintDialog from '@/views/grossanlass/packen/GrossanlassLabelPrintDialog.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import { useToast } from '@/composables/useToast'
import { resolveMediaPreviewUrl } from '@/api/media'
import {
  recordGrossanlassChargeMovement,
  updateGrossanlassCommitment,
  type GrossanlassCommitment,
} from '@/api/grossanlassCommitments'
import {
  listGrossanlassProcurementLines,
  type GrossanlassProcurementLine,
} from '@/api/grossanlassProcurement'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { gaBestandArtikelPath } from '@/views/grossanlass/gaBestandPaths'
import {
  commitmentStemKey,
  expectedAtIso,
  inboundMode,
  inboundState,
  inboundStatus,
  missingQty,
  originBadgeKey,
  receivedQty,
} from '@/views/grossanlass/gaCharge'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'

type RangeId = 'today' | 'week' | 'expected' | 'here'
type ModeId = 'all' | 'pickup' | 'delivery'
type NeedLink = { id: string; text: string; action: string; to: string }

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const catalog = useGaCommitmentCatalog()
const uebersicht = useGaUebersicht()
const busyId = ref<string | null>(null)
const range = ref<RangeId>('expected')
const modeFilter = ref<ModeId>('all')
const search = ref('')
/** Lokal: gerade eingebuchte Menge, damit «Label drucken» direkt nach dem Eingang erscheint. */
const arrivedNow = ref<Record<string, number>>({})
const labelOpen = ref(false)
const labelRow = ref<GrossanlassCommitment | null>(null)
const procurementLines = ref<GrossanlassProcurementLine[]>([])
const receiveQty = ref<Record<string, string | number>>({})
const receivePlace = ref<Record<string, string | null>>({})
const receiveNote = ref<Record<string, string>>({})

/** Annahmeort: Lager (Matplatz) und Unterlager zuerst, sonst alle GA-Orte. */
const placeItems = computed(() => {
  const places = uebersicht.data.value?.places ?? []
  const storage = places.filter((place) => place.kind === 'matplatz' || place.kind === 'unterlager')
  return (storage.length ? storage : places).map((place) => ({ title: place.name, value: place.id }))
})

const departmentId = computed(() => String(route.params.departmentId || ''))

const rangeChips = computed(() => [
  { id: 'today' as const, label: t('grossanlass.material.wareneingang.rangeToday') },
  { id: 'week' as const, label: t('grossanlass.material.wareneingang.rangeWeek') },
  { id: 'expected' as const, label: t('grossanlass.material.wareneingang.rangeExpected') },
  { id: 'here' as const, label: t('grossanlass.material.wareneingang.rangeHere') },
])

const modeChips = computed(() => [
  { id: 'all' as const, label: t('grossanlass.material.wareneingang.modeAll') },
  { id: 'pickup' as const, label: t('grossanlass.material.wareneingang.mode.pickup') },
  { id: 'delivery' as const, label: t('grossanlass.material.wareneingang.mode.delivery') },
])

function todayKey(): string {
  const d = new Date()
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

function addDays(isoDay: string, days: number): string {
  const d = new Date(`${isoDay}T12:00:00`)
  d.setDate(d.getDate() + days)
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

const visibleRows = computed(() => {
  const today = todayKey()
  const weekEnd = addDays(today, 6)
  return catalog.commitments.value
    .filter((row) => {
      const status = inboundStatus(row)
      const day = (expectedAtIso(row) || '').slice(0, 10)
      if (range.value === 'here') return status === 'here'
      if (range.value === 'expected') return status === 'expected'
      if (range.value === 'today') return status === 'expected' && day === today
      return status === 'expected' && day >= today && day <= weekEnd
    })
    .filter((row) => modeFilter.value === 'all' || arrivalMode(row) === modeFilter.value)
    .filter((row) => matchesSearch(row))
    .sort((a, b) => (expectedAtIso(a) || '').localeCompare(expectedAtIso(b) || ''))
})

const lineById = computed(() => {
  const map = new Map<string, GrossanlassProcurementLine>()
  for (const line of procurementLines.value) map.set(line.id, line)
  return map
})

function orderLineOf(row: GrossanlassCommitment): GrossanlassProcurementLine | undefined {
  const id = row.procurement_line_id || row.item_details?.from_line_id
  return id ? lineById.value.get(id) : undefined
}

/** Abholen oder Lieferung: die gewählte Offerte entscheidet, sonst der gespeicherte Eingangsweg. */
function arrivalMode(row: GrossanlassCommitment): 'pickup' | 'delivery' {
  const quotes = orderLineOf(row)?.quotes ?? []
  const quoteId = row.item_details?.quote_id
  const quote = (quoteId ? quotes.find((item) => item.id === quoteId) : undefined)
    ?? quotes.find((item) => item.selected)
  const mode = quote?.inbound_mode
  if (mode === 'pickup' || mode === 'delivery') return mode
  return inboundMode(row)
}

function matchesSearch(row: GrossanlassCommitment): boolean {
  const q = search.value.trim().toLowerCase()
  if (!q) return true
  const needs = needsOf(row).map((need) => need.text).join(' ')
  const haystack = [row.name, row.source, row.barcode, row.item_details?.order_ref, needs]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
  return haystack.includes(q)
}

/** Scan (QR/Barcode) oder Enter: bei genau einem Treffer direkt Restmenge vorbelegen. */
function onScanEnter() {
  const q = search.value.trim().toLowerCase()
  if (!q) return
  const exact = catalog.commitments.value.find((row) => (row.barcode || '').toLowerCase() === q)
  if (exact) {
    range.value = 'expected'
    modeFilter.value = 'all'
    receiveQty.value[exact.id] = missingQty(exact)
    return
  }
  if (visibleRows.value.length === 1) {
    const only = visibleRows.value[0]
    if (only && missingQty(only) > 0) receiveQty.value[only.id] = missingQty(only)
  }
}

function labelWhere(row: GrossanlassCommitment): string[] {
  const needs = needsOf(row).map((need) => need.text)
  return [row.source, ...needs].filter(Boolean)
}

const labelCode = computed(() => labelRow.value?.barcode || labelRow.value?.id || '')
const labelTitle = computed(() => labelRow.value?.name || '')
const labelLines = computed(() => (labelRow.value ? labelWhere(labelRow.value) : []))

function openLabel(row: GrossanlassCommitment) {
  labelRow.value = row
  labelOpen.value = true
}

function orderedQty(row: GrossanlassCommitment): number {
  if (row.origin === 'loan') return row.quantity
  const line = orderLineOf(row)
  return line?.quantity_ordered || line?.quantity || row.quantity
}

function isBuyOrder(row: GrossanlassCommitment): boolean {
  return row.origin !== 'loan' && Boolean(orderLineOf(row) || row.item_details?.order_id || row.item_details?.order_ref)
}

function orderPdfUrl(row: GrossanlassCommitment): string | null {
  const line = orderLineOf(row)
  const quote = line?.quotes.find((item) => item.selected && item.pdf_url)
    || line?.quotes.find((item) => item.pdf_url)
  if (!quote?.pdf_url) return null
  return resolveMediaPreviewUrl(quote.pdf_url)
}

function expectedLabel(row: GrossanlassCommitment): string {
  const iso = expectedAtIso(row)
  if (!iso) return t('grossanlass.material.wareneingang.noDate')
  return formatGaIsoLabel(iso, locale.value)
}

function needsOf(row: GrossanlassCommitment): NeedLink[] {
  const stem = commitmentStemKey(row)
  const ids = new Set(
    catalog.commitments.value
      .filter((item) => commitmentStemKey(item) === stem)
      .map((item) => item.id),
  )
  const out: NeedLink[] = []
  for (const einsatz of uebersicht.bookingRows()) {
    if (!ids.has(einsatz.objectId)) continue
    if (einsatz.status === 'returned') continue
    out.push({
      id: `e-${einsatz.id}`,
      text: t('grossanlass.material.wareneingang.needEinsatz', {
        who: einsatz.ressort || einsatz.who,
        n: einsatz.qty,
      }),
      action: t('grossanlass.material.wareneingang.openEinsatz'),
      to: `/${departmentId.value}/planung/belegung`,
    })
  }
  for (const pack of uebersicht.data.value?.pack ?? []) {
    if (!ids.has(pack.id) || pack.packed) continue
    out.push({
      id: `p-${pack.id}`,
      text: t('grossanlass.material.wareneingang.needPack', {
        name: pack.name,
        n: pack.qty,
      }),
      action: t('grossanlass.material.wareneingang.openPack'),
      to: `/${departmentId.value}/material/pack`,
    })
  }
  return out
}

function goNeed(need: NeedLink) {
  void router.push(need.to)
}


function openHistory(row: GrossanlassCommitment) {
  const id = departmentId.value
  if (!id) return
  void router.push({
    ...gaBestandArtikelPath(id, row.id, 'uebersicht'),
    query: { from: 'uebersicht', tab: 'usage' },
  })
}

function openOrder(row: GrossanlassCommitment) {
  const id = departmentId.value
  const lineId = row.item_details?.from_line_id
  if (!id || !lineId) return
  void router.push({
    path: `/${id}/beschaffung/bestellungen`,
    query: { line: lineId },
  })
}

async function toggleQtyChecked(row: GrossanlassCommitment, on: boolean) {
  await patchDetails(row, { qty_checked: on })
}

async function patchDetails(row: GrossanlassCommitment, patch: Record<string, unknown>): Promise<boolean> {
  const id = departmentId.value
  if (!id) return false
  busyId.value = row.id
  try {
    const updated = await updateGrossanlassCommitment(id, row.id, {
      item_details: {
        ...row.item_details,
        ...patch,
      },
      barcode: row.barcode || `ZS-${row.id.toUpperCase()}`,
    })
    catalog.upsert(updated)
    return true
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
    return false
  } finally {
    busyId.value = null
  }
}





async function receive(row: GrossanlassCommitment) {
  const id = departmentId.value
  if (!id) return
  const raw = receiveQty.value[row.id]
  const quantity = Number(raw === undefined || raw === '' ? missingQty(row) : raw)
  if (!Number.isInteger(quantity) || quantity <= 0) {
    toast.error(t('grossanlass.material.wareneingang.receiveQtyInvalid'))
    return
  }
  busyId.value = row.id
  try {
    const result = await recordGrossanlassChargeMovement(id, row.id, {
      quantity,
      place_id: receivePlace.value[row.id] || null,
      note: receiveNote.value[row.id]?.trim() || null,
    })
    catalog.upsert(result.commitment)
    delete receiveQty.value[row.id]
    arrivedNow.value[row.id] = quantity
    receiveNote.value[row.id] = ''
    toast.success(
      result.inbound.state === 'complete'
        ? t('grossanlass.material.wareneingang.markedHere')
        : t('grossanlass.material.wareneingang.receivedPartial', {
          received: result.inbound.received,
          expected: result.inbound.expected,
        }),
    )
    if (result.commitment.procurement_line_id) void loadProcurementLines()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    busyId.value = null
  }
}

function loadProcurementLines() {
  const id = departmentId.value
  if (!id) return Promise.resolve()
  return listGrossanlassProcurementLines(id)
    .then((rows) => { procurementLines.value = rows })
    .catch(() => { procurementLines.value = [] })
}

onMounted(() => {
  void loadProcurementLines()
})
</script>

<style scoped>
.ga-inbound { padding: 4px 0 24px; }
.tab-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.inbound-toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.inbound-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
.inbound-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  background: #fff;
  display: grid;
  gap: 8px;
}
.inbound-card__head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: flex-start;
}
.inbound-card__head > div { display: flex; flex-wrap: wrap; gap: 8px; align-items: baseline; }
.inbound-qty { color: #64748b; font-size: 0.85rem; }
.inbound-qty--sub { color: #94a3b8; }
.inbound-search {
  margin-bottom: 12px;
  max-width: 520px;
}
.inbound-numbers {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin: 0;
}
.inbound-numbers > div {
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}
.inbound-numbers dt {
  font-size: 0.7rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
}
.inbound-numbers dd {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
}
.inbound-numbers__open dd {
  color: #b45309;
}
.inbound-numbers--complete .inbound-numbers__open dd {
  color: #15803d;
}
.inbound-quick {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.inbound-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
  padding: 10px 12px;
  border-radius: 10px;
  background: #ecfdf5;
  color: #065f46;
  font-size: 0.88rem;
}
.inbound-meta { margin: 0; color: #475569; font-size: 0.82rem; }
.inbound-check {
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f8fafc;
}
.inbound-progress {
  margin: 2px 0 0;
  font-size: 0.78rem;
  font-weight: 600;
  color: #334155;
}
.inbound-docs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 8px;
  align-items: center;
}
.inbound-pdf {
  font-size: 0.82rem;
  font-weight: 600;
  color: #0f766e;
  text-decoration: none;
}
.inbound-pdf:hover { text-decoration: underline; }
.inbound-qr { display: flex; align-items: center; gap: 12px; }
.inbound-code { font-family: ui-monospace, monospace; font-size: 0.85rem; }
.inbound-need { font-size: 0.82rem; }
.inbound-need ul { margin: 6px 0 0; padding: 0; list-style: none; display: grid; gap: 4px; }
.inbound-need li { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.inbound-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.inbound-here { font-size: 0.82rem; font-weight: 700; color: #166534; }
.inbound-progress--partial { color: #b45309; }
.inbound-progress--complete { color: #166534; }
.inbound-receive {
  display: grid;
  grid-template-columns: minmax(90px, 120px) minmax(140px, 1fr) minmax(140px, 1fr) auto;
  gap: 8px;
  align-items: center;
}
@media (max-width: 640px) {
  .inbound-receive { grid-template-columns: 1fr; }
}
</style>
