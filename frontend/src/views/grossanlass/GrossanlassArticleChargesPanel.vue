<template>
  <section class="section-card">
    <h2 class="section-title">{{ t('grossanlass.materials.detailTabStock') }}</h2>
    <p class="panel-intro">{{ t('grossanlass.materials.chargeIntro') }}</p>
    <div class="stock-summary">
      <div class="stock-stat warehouse">
        <span class="stock-number">{{ totalQty }}</span>
        <span class="stock-label">{{ t('materialsView.colTotal') }}</span>
      </div>
      <div class="stock-stat available">
        <span class="stock-number">{{ hereQty }}</span>
        <span class="stock-label">{{ t('grossanlass.materials.chargeFlag.here') }}</span>
      </div>
    </div>
    <ul class="charge-list">
      <li v-for="row in charges" :key="row.id" class="charge-card">
        <div class="charge-card__head">
          <span class="combo-type-badge" :class="row.origin === 'loan' ? 'virtual_combo' : 'physical_combo'">
            {{ t(`grossanlass.materials.originBadge.${originBadgeKey(row.origin)}`) }}
          </span>
          <strong>{{ row.source }}</strong>
          <span>{{ t('grossanlass.materials.zusage.qtyShort', { n: row.quantity }) }}</span>
        </div>
        <p class="charge-flags">
          <span v-for="flag in chargeFlags(row)" :key="flag" class="charge-flag">
            {{ t(`grossanlass.materials.chargeFlag.${flag}`) }}
          </span>
        </p>
        <p v-if="row.origin === 'loan' && row.handover_from" class="charge-meta">
          {{ t('grossanlass.beschaffung.zusagen.handoverReturn', {
            handover: formatIso(row.handover_from),
            giveback: formatIso(row.return_from || row.present_to || ''),
          }) }}
        </p>
        <p v-else-if="row.present_from" class="charge-meta">
          {{ t('grossanlass.materials.chargeDelivery', { when: formatIso(row.present_from) }) }}
        </p>
        <p v-if="row.item_details?.order_ref" class="charge-meta">
          {{ t('grossanlass.beschaffung.bestellungen.orderRef') }}: {{ row.item_details.order_ref }}
        </p>
        <div class="charge-qr">
          <PublicQrTag
            v-if="row.barcode"
            :url="row.barcode"
            :code="row.barcode"
            :size="72"
            :image-label="row.name"
            :image-entity-id="row.id"
          />
          <span class="charge-code">{{ row.barcode || '—' }}</span>
        </div>
        <ESwitch
          v-if="row.origin === 'loan'"
          :model-value="row.released"
          :label="t('grossanlass.materials.zusage.fieldRelease')"
          hide-details
          @update:model-value="onRelease(row, Boolean($event))"
        />
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ESwitch } from '@/components/form/base'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import {
  chargeFlags,
  inboundStatus,
  originBadgeKey,
} from '@/views/grossanlass/gaCharge'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'

const props = defineProps<{
  charges: GrossanlassCommitment[]
}>()

const emit = defineEmits<{
  release: [row: GrossanlassCommitment, released: boolean]
}>()

const { t, locale } = useI18n()

const totalQty = computed(() => props.charges.reduce((sum, row) => sum + (row.quantity || 0), 0))
const hereQty = computed(() =>
  props.charges
    .filter((row) => inboundStatus(row) === 'here')
    .reduce((sum, row) => sum + (row.quantity || 0), 0),
)

function formatIso(iso: string): string {
  if (!iso) return '—'
  return formatGaIsoLabel(iso, locale.value)
}

function onRelease(row: GrossanlassCommitment, released: boolean) {
  emit('release', row, released)
}
</script>

<style scoped>
.panel-intro { margin: 0 0 12px; font-size: 0.85rem; color: #64748b; }
.stock-summary {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}
.stock-stat { display: flex; flex-direction: column; }
.stock-number { font-size: 1.4rem; font-weight: 700; }
.stock-label { font-size: 0.75rem; color: #64748b; }
.charge-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.charge-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px;
  display: grid;
  gap: 6px;
}
.charge-card__head { display: flex; flex-wrap: wrap; gap: 8px; align-items: baseline; }
.charge-flags { margin: 0; display: flex; flex-wrap: wrap; gap: 6px; }
.charge-flag {
  font-size: 0.72rem;
  font-weight: 700;
  background: #e2e8f0;
  color: #334155;
  border-radius: 999px;
  padding: 2px 8px;
}
.charge-meta { margin: 0; font-size: 0.82rem; color: #475569; }
.charge-qr { display: flex; align-items: center; gap: 12px; }
.charge-code { font-family: ui-monospace, monospace; font-size: 0.85rem; }
</style>
