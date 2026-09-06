<template>
  <div class="ga-inbound">
    <p class="tab-intro">{{ t('grossanlass.materialUebersicht.wareneingang.intro') }}</p>

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
      :title="t('grossanlass.materialUebersicht.wareneingang.emptyTitle')"
      :description="t('grossanlass.materialUebersicht.wareneingang.emptyDescription')"
    />

    <ul v-else class="inbound-list">
      <li v-for="row in visibleRows" :key="row.id" class="inbound-card">
        <div class="inbound-card__head">
          <div>
            <strong>{{ row.name }}</strong>
            <span class="inbound-qty">{{ t('grossanlass.materials.zusage.qtyShort', { n: row.quantity }) }}</span>
          </div>
          <span class="combo-type-badge" :class="row.origin === 'loan' ? 'virtual_combo' : 'physical_combo'">
            {{ t(`grossanlass.materials.originBadge.${originBadgeKey(row.origin)}`) }}
          </span>
        </div>
        <p class="inbound-meta">
          {{ row.source }}
          · {{ t(`grossanlass.materialUebersicht.wareneingang.mode.${inboundMode(row)}`) }}
          · {{ expectedLabel(row) }}
        </p>
        <p v-if="row.item_details?.order_ref || row.item_details?.quote_id" class="inbound-meta">
          <template v-if="row.item_details?.order_ref">
            {{ t('grossanlass.beschaffung.bestellungen.orderRef') }}: {{ row.item_details.order_ref }}
          </template>
        </p>
        <div class="inbound-qr">
          <PublicQrTag
            v-if="row.barcode"
            :url="row.barcode"
            :code="row.barcode"
            :size="72"
            :image-label="row.name"
            :image-entity-id="row.id"
          />
          <span class="inbound-code">{{ row.barcode || t('grossanlass.materialUebersicht.wareneingang.noQr') }}</span>
        </div>
        <div v-if="needsOf(row).length" class="inbound-need">
          <strong>{{ t('grossanlass.materialUebersicht.wareneingang.needTitle') }}</strong>
          <ul>
            <li v-for="need in needsOf(row)" :key="need.id">
              {{ need.text }}
              <EButton variant="text" size="small" @click="goNeed(need)">
                {{ need.action }}
              </EButton>
            </li>
          </ul>
        </div>
        <div class="inbound-actions">
          <EButton
            variant="secondary"
            size="small"
            @click="toggleMode(row)"
          >
            {{ inboundMode(row) === 'delivery'
              ? t('grossanlass.materialUebersicht.wareneingang.setPickup')
              : t('grossanlass.materialUebersicht.wareneingang.setDelivery') }}
          </EButton>
          <EButton
            v-if="inboundStatus(row) !== 'here'"
            variant="primary"
            size="small"
            :loading="busyId === row.id"
            @click="markHere(row)"
          >
            {{ t('grossanlass.materialUebersicht.wareneingang.markHere') }}
          </EButton>
          <span v-else class="inbound-here">{{ t('grossanlass.materials.chargeFlag.here') }}</span>
          <EButton variant="text" size="small" @click="openArticle(row)">
            {{ t('grossanlass.beschaffung.zusagen.openArticle') }}
          </EButton>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import { useToast } from '@/composables/useToast'
import { updateGrossanlassCommitment, type GrossanlassCommitment } from '@/api/grossanlassCommitments'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import {
  commitmentStemKey,
  expectedAtIso,
  inboundMode,
  inboundStatus,
  originBadgeKey,
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

const departmentId = computed(() => String(route.params.departmentId || ''))

const rangeChips = computed(() => [
  { id: 'today' as const, label: t('grossanlass.materialUebersicht.wareneingang.rangeToday') },
  { id: 'week' as const, label: t('grossanlass.materialUebersicht.wareneingang.rangeWeek') },
  { id: 'expected' as const, label: t('grossanlass.materialUebersicht.wareneingang.rangeExpected') },
  { id: 'here' as const, label: t('grossanlass.materialUebersicht.wareneingang.rangeHere') },
])

const modeChips = computed(() => [
  { id: 'all' as const, label: t('grossanlass.materialUebersicht.wareneingang.modeAll') },
  { id: 'pickup' as const, label: t('grossanlass.materialUebersicht.wareneingang.mode.pickup') },
  { id: 'delivery' as const, label: t('grossanlass.materialUebersicht.wareneingang.mode.delivery') },
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
    .filter((row) => modeFilter.value === 'all' || inboundMode(row) === modeFilter.value)
    .sort((a, b) => (expectedAtIso(a) || '').localeCompare(expectedAtIso(b) || ''))
})

function expectedLabel(row: GrossanlassCommitment): string {
  const iso = expectedAtIso(row)
  if (!iso) return t('grossanlass.materialUebersicht.wareneingang.noDate')
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
      text: t('grossanlass.materialUebersicht.wareneingang.needEinsatz', {
        who: einsatz.ressort || einsatz.who,
        n: einsatz.qty,
      }),
      action: t('grossanlass.materialUebersicht.wareneingang.openEinsatz'),
      to: `/${departmentId.value}/material-uebersicht/einsaetze`,
    })
  }
  for (const pack of uebersicht.data.value?.pack ?? []) {
    if (!ids.has(pack.id) || pack.packed) continue
    out.push({
      id: `p-${pack.id}`,
      text: t('grossanlass.materialUebersicht.wareneingang.needPack', {
        name: pack.name,
        n: pack.qty,
      }),
      action: t('grossanlass.materialUebersicht.wareneingang.openPack'),
      to: `/${departmentId.value}/material-uebersicht/pack`,
    })
  }
  return out
}

function goNeed(need: NeedLink) {
  void router.push(need.to)
}

function openArticle(row: GrossanlassCommitment) {
  const id = departmentId.value
  if (!id) return
  void router.push({
    path: `/${id}/materialien/artikel/${row.id}`,
    query: { from: 'uebersicht', tab: 'stock' },
  })
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

async function toggleMode(row: GrossanlassCommitment) {
  const next = inboundMode(row) === 'delivery' ? 'pickup' : 'delivery'
  await patchDetails(row, { inbound_mode: next })
}

async function markHere(row: GrossanlassCommitment) {
  const ok = await patchDetails(row, { inbound_status: 'here', inbound_mode: inboundMode(row) })
  if (ok) toast.success(t('grossanlass.materialUebersicht.wareneingang.markedHere'))
}
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
.inbound-meta { margin: 0; color: #475569; font-size: 0.82rem; }
.inbound-qr { display: flex; align-items: center; gap: 12px; }
.inbound-code { font-family: ui-monospace, monospace; font-size: 0.85rem; }
.inbound-need { font-size: 0.82rem; }
.inbound-need ul { margin: 6px 0 0; padding: 0; list-style: none; display: grid; gap: 4px; }
.inbound-need li { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.inbound-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.inbound-here { font-size: 0.82rem; font-weight: 700; color: #166534; }
</style>
