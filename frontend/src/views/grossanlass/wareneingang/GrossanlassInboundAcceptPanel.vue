<template>
  <div class="inbound-accept">
    <header class="inbound-accept__head">
      <div>
        <h2 class="inbound-accept__title">{{ shipment.title }}</h2>
        <p class="inbound-accept__meta">
          <span>{{ groupLabel }}</span>
          <span v-if="arrivalLabel">· {{ arrivalLabel }}</span>
          <span v-if="transportLabel">· {{ transportLabel }}</span>
        </p>
        <p v-if="tourMeta" class="inbound-accept__meta">{{ tourMeta }}</p>
      </div>
      <EButton variant="text" size="small" @click="emit('close')">
        {{ t('common.close') }}
      </EButton>
    </header>

    <v-alert
      v-if="!placeItems.length"
      type="warning"
      variant="tonal"
      density="compact"
      class="inbound-accept__alert"
    >
      {{ t('grossanlass.material.wareneingang.workspace.noStoragePlaces') }}
      <RouterLink :to="locationsRoute" class="inbound-accept__link">
        {{ t('grossanlass.material.wareneingang.workspace.openLocationSettings') }}
      </RouterLink>
    </v-alert>

    <div v-else class="inbound-accept__place">
      <ESelect
        v-model="sharedPlaceId"
        :items="placeItems"
        item-title="title"
        item-value="value"
        :label="sharedPlaceLabel"
        hide-details
        density="compact"
      />
    </div>

    <ul class="inbound-list">
      <li
        v-for="pos in shipment.positions"
        :key="pos.commitment_id"
        class="inbound-card"
      >
        <div class="inbound-card__head">
          <div>
            <strong>{{ pos.name }}</strong>
            <span class="inbound-qty">{{ pos.supplier }}</span>
          </div>
          <span class="combo-type-badge physical_combo">
            {{ t(`grossanlass.material.wareneingang.mode.${pos.inbound_mode}`) }}
          </span>
        </div>
        <p v-if="pos.order_ref" class="inbound-meta">
          {{ t('grossanlass.beschaffung.bestellungen.orderRef') }}: {{ pos.order_ref }}
        </p>
        <dl class="inbound-numbers">
          <div>
            <dt>{{ t('grossanlass.material.wareneingang.numExpected') }}</dt>
            <dd>{{ pos.expected_qty }}</dd>
          </div>
          <div>
            <dt>{{ t('grossanlass.material.wareneingang.numArrived') }}</dt>
            <dd>{{ pos.received_qty }}</dd>
          </div>
          <div class="inbound-numbers__open">
            <dt>{{ t('grossanlass.material.wareneingang.numOpen') }}</dt>
            <dd>{{ pos.open_qty }}</dd>
          </div>
        </dl>
        <div v-if="pos.open_qty > 0" class="inbound-receive">
          <ESelect
            v-if="shipment.positions.length > 1 && placeItems.length"
            v-model="linePlace[pos.commitment_id]"
            :items="placeItems"
            item-title="title"
            item-value="value"
            :label="t('grossanlass.material.wareneingang.workspace.positionPlace')"
            hide-details
            density="compact"
          />
          <ETextField
            v-model="qty[pos.commitment_id]"
            type="number"
            min="0"
            :max="pos.open_qty"
            :label="t('grossanlass.material.wareneingang.receiveQty')"
            hide-details
            density="compact"
          />
          <ETextField
            v-model="note[pos.commitment_id]"
            :label="t('grossanlass.material.wareneingang.receiveNote')"
            hide-details
            density="compact"
          />
          <div class="inbound-quick">
            <EButton variant="secondary" size="small" @click="qty[pos.commitment_id] = pos.open_qty">
              {{ t('grossanlass.material.wareneingang.receiveRest', { n: pos.open_qty }) }}
            </EButton>
            <EButton variant="secondary" size="small" @click="qty[pos.commitment_id] = 0">
              {{ t('grossanlass.material.wareneingang.workspace.skipPosition') }}
            </EButton>
          </div>
        </div>
        <div v-if="pos.barcode" class="inbound-qr">
          <PublicQrTag
            :url="pos.barcode"
            :code="pos.barcode"
            :size="72"
            :image-label="pos.name"
            :image-entity-id="pos.commitment_id"
          />
          <span class="inbound-code">{{ pos.barcode }}</span>
        </div>
        <GrossanlassReceiptReversal
          v-if="commitmentOf(pos)"
          :commitment="commitmentOf(pos)!"
          :department-id="departmentId"
          :received="pos.received_qty"
          @reversed="onReversed"
        />
      </li>
    </ul>

    <footer class="inbound-accept__foot">
      <EButton variant="primary" :loading="busy" :disabled="!placeItems.length" @click="submit">
        {{ t('grossanlass.material.wareneingang.workspace.confirmReceipt') }}
      </EButton>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { GaStoragePlaceOption } from '@/views/grossanlass/wareneingang/gaStoragePlaces'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import GrossanlassReceiptReversal from '@/components/grossanlass/GrossanlassReceiptReversal.vue'
import { useToast } from '@/composables/useToast'
import {
  receiveGrossanlassInboundBatch,
  type GaInboundShipment,
} from '@/api/grossanlassInbound'
import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'

const props = defineProps<{
  departmentId: string
  shipment: GaInboundShipment
  storagePlaces: GaStoragePlaceOption[]
  drivers: Record<string, string>
  vehicles: Record<string, string>
  commitments: Record<string, GrossanlassCommitment>
}>()

const emit = defineEmits<{
  close: []
  received: []
  'commitment-updated': [GrossanlassCommitment]
}>()

const { t, locale } = useI18n()
const toast = useToast()
const busy = ref(false)
const sharedPlaceId = ref<string | null>(null)
const linePlace = ref<Record<string, string | null>>({})
const qty = ref<Record<string, string | number>>({})
const note = ref<Record<string, string>>({})

const placeItems = computed(() =>
  props.storagePlaces.map((place) => ({ title: place.name, value: place.id })),
)

const locationsRoute = computed(() => ({
  name: 'GrossanlassEinstellungenStandorte',
  params: { departmentId: props.departmentId },
}))

const sharedPlaceLabel = computed(() =>
  props.shipment.positions.length > 1
    ? t('grossanlass.material.wareneingang.workspace.sharedPlace')
    : t('grossanlass.material.wareneingang.receivePlace'),
)

function resolvePresetPlaceId(shipment: GaInboundShipment): string | null {
  const candidates = [
    shipment.target_place_id,
    ...shipment.positions.map((p) => p.target_place_id),
  ]
  for (const id of candidates) {
    if (id && props.storagePlaces.some((p) => p.id === id)) {
      return id
    }
  }
  if (props.storagePlaces.length === 1) {
    return props.storagePlaces[0].id
  }
  return null
}

watch(
  () => [props.shipment, props.storagePlaces] as const,
  ([shipment]) => {
    const preset = resolvePresetPlaceId(shipment)
    sharedPlaceId.value = preset
    for (const pos of shipment.positions) {
      const posPreset =
        (pos.target_place_id && props.storagePlaces.some((p) => p.id === pos.target_place_id)
          ? pos.target_place_id
          : null) ?? preset
      linePlace.value[pos.commitment_id] = posPreset
      if (qty.value[pos.commitment_id] === undefined) {
        qty.value[pos.commitment_id] = pos.open_qty > 0 ? pos.open_qty : 0
      }
    }
  },
  { immediate: true },
)

watch(sharedPlaceId, (id) => {
  if (!id || props.shipment.positions.length <= 1) {
    return
  }
  for (const pos of props.shipment.positions) {
    linePlace.value[pos.commitment_id] = id
  }
})

const groupLabel = computed(() =>
  t(`grossanlass.material.wareneingang.workspace.group.${props.shipment.group}`),
)

const arrivalLabel = computed(() => {
  const iso = props.shipment.arrives_at
  if (!iso) return ''
  return formatGaIsoLabel(iso, locale.value)
})

const transportLabel = computed(() => {
  const status = props.shipment.transport_status
  if (!status) return ''
  return t(`grossanlass.material.wareneingang.workspace.transport.${status}`, status)
})

const tourMeta = computed(() => {
  if (props.shipment.group !== 'tour') return ''
  const parts: string[] = []
  const driver = props.shipment.chauffeur_user_id
    ? props.drivers[props.shipment.chauffeur_user_id]
    : ''
  const vehicle = props.shipment.vehicle_id ? props.vehicles[props.shipment.vehicle_id] : ''
  if (driver) parts.push(t('grossanlass.material.wareneingang.workspace.chauffeur', { name: driver }))
  if (vehicle) parts.push(t('grossanlass.material.wareneingang.workspace.vehicle', { name: vehicle }))
  return parts.join(' · ')
})

function commitmentOf(pos: { commitment_id: string }): GrossanlassCommitment | undefined {
  return props.commitments[pos.commitment_id]
}

function onReversed(commitment: GrossanlassCommitment) {
  emit('commitment-updated', commitment)
}

async function submit() {
  if (!placeItems.value.length) {
    toast.error(t('grossanlass.material.wareneingang.workspace.noStoragePlaces'))
    return
  }
  const lines = props.shipment.positions.map((pos) => {
    const raw = qty.value[pos.commitment_id]
    const quantity = Number(raw === undefined || raw === '' ? 0 : raw)
    const placeForLine =
      linePlace.value[pos.commitment_id] ?? sharedPlaceId.value ?? null
    return {
      commitment_id: pos.commitment_id,
      quantity,
      note: note.value[pos.commitment_id]?.trim() || null,
      skip: quantity <= 0,
      place_id: quantity > 0 ? placeForLine : null,
    }
  })
  const active = lines.filter((line) => !line.skip)
  if (!active.length) {
    toast.error(t('grossanlass.material.wareneingang.receiveQtyInvalid'))
    return
  }
  if (active.some((line) => !line.place_id)) {
    toast.error(t('grossanlass.material.wareneingang.workspace.placeRequired'))
    return
  }
  busy.value = true
  try {
    const result = await receiveGrossanlassInboundBatch(props.departmentId, {
      place_id: sharedPlaceId.value,
      lines,
    })
    for (const row of result.results) {
      emit('commitment-updated', row.commitment)
    }
    toast.success(t('grossanlass.material.wareneingang.workspace.receiptDone'))
    emit('received')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.inbound-accept { display: grid; gap: 12px; }
.inbound-accept__head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.inbound-accept__title { margin: 0; font-size: 1.1rem; }
.inbound-accept__meta { margin: 4px 0 0; color: #64748b; font-size: 0.85rem; }
.inbound-accept__place { max-width: 360px; }
.inbound-accept__alert { margin: 0; }
.inbound-accept__link { margin-left: 6px; font-weight: 600; }
.inbound-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
.inbound-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  background: #fff;
  display: grid;
  gap: 8px;
}
.inbound-card__head { display: flex; justify-content: space-between; gap: 8px; }
.inbound-qty { color: #64748b; font-size: 0.85rem; margin-left: 8px; }
.inbound-meta { margin: 0; color: #475569; font-size: 0.82rem; }
.inbound-numbers {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.inbound-numbers > div {
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}
.inbound-numbers dt { font-size: 0.7rem; text-transform: uppercase; color: #64748b; }
.inbound-numbers dd { margin: 0; font-size: 1.2rem; font-weight: 700; }
.inbound-numbers__open dd { color: #b45309; }
.inbound-receive { display: grid; gap: 8px; }
.inbound-quick { display: flex; flex-wrap: wrap; gap: 6px; }
.inbound-qr { display: flex; align-items: center; gap: 12px; }
.inbound-code { font-family: ui-monospace, monospace; font-size: 0.85rem; }
.inbound-accept__foot { display: flex; justify-content: flex-end; padding-top: 8px; }
</style>
