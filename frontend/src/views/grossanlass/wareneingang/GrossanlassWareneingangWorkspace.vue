<template>
  <div class="ga-inbound-workspace">
    <p class="tab-intro">{{ t('grossanlass.material.wareneingang.workspace.intro') }}</p>

    <div class="inbound-search">
      <ESearchField
        v-model="search"
        :label="t('grossanlass.material.wareneingang.scanLabel')"
        :placeholder="t('grossanlass.material.wareneingang.scanPlaceholder')"
        autofocus
        @keydown.enter="onScanEnter"
      />
    </div>

    <v-tabs v-model="phase" class="wareneingang-phase-tabs" color="primary" density="compact">
      <v-tab value="expected">{{ t('grossanlass.material.wareneingang.workspace.phase.expected') }}</v-tab>
      <v-tab value="acceptance">{{ t('grossanlass.material.wareneingang.workspace.phase.acceptance') }}</v-tab>
      <v-tab value="received">{{ t('grossanlass.material.wareneingang.workspace.phase.received') }}</v-tab>
    </v-tabs>

    <div class="inbound-toolbar">
      <EButton
        v-for="chip in groupChips"
        :key="chip.id"
        :variant="groupFilter === chip.id ? 'primary' : 'secondary'"
        size="small"
        @click="groupFilter = chip.id"
      >
        {{ chip.label }}
      </EButton>
      <ESelect
        v-model="placeFilter"
        :items="placeFilterItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.material.wareneingang.workspace.filterPlace')"
        clearable
        hide-details
        density="compact"
        class="inbound-toolbar__place"
      />
    </div>

    <v-alert
      v-if="!loading && !storagePlaces.length"
      type="warning"
      variant="tonal"
      density="compact"
      class="inbound-storage-hint"
    >
      {{ t('grossanlass.material.wareneingang.workspace.noStoragePlaces') }}
      <RouterLink :to="locationsRoute" class="inbound-storage-hint__link">
        {{ t('grossanlass.material.wareneingang.workspace.openLocationSettings') }}
      </RouterLink>
    </v-alert>

    <ELoadingState v-if="loading" variant="list" :message="t('common.loading')" />

    <EEmptyState
      v-else-if="visibleShipments.length === 0"
      variant="default"
      icon="mdi-truck-delivery-outline"
      :title="t('grossanlass.material.wareneingang.emptyTitle')"
      :description="t('grossanlass.material.wareneingang.emptyDescription')"
    />

    <ul v-else class="shipment-list">
      <li v-for="row in visibleShipments" :key="row.id" class="shipment-row">
        <div class="shipment-row__main">
          <div>
            <strong>{{ row.title }}</strong>
            <span class="shipment-row__badge">{{ groupLabel(row.group) }}</span>
          </div>
          <p class="shipment-row__meta">
            <span v-if="arrivalLabel(row)">{{ arrivalLabel(row) }}</span>
            <span>· {{ t('grossanlass.material.wareneingang.workspace.positions', { n: row.position_count }) }}</span>
            <span>· {{ progressLabel(row) }}</span>
          </p>
          <p v-if="targetPlaceName(row)" class="shipment-row__meta">
            {{ t('grossanlass.material.wareneingang.workspace.targetPlace', { name: targetPlaceName(row) }) }}
          </p>
        </div>
        <EButton variant="primary" size="small" @click="openShipment(row)">
          {{ t('grossanlass.material.wareneingang.workspace.open') }}
        </EButton>
      </li>
    </ul>

    <v-dialog v-model="detailOpen" max-width="920" scrollable>
      <v-card v-if="activeShipment">
        <v-card-text>
          <GrossanlassInboundAcceptPanel
            :department-id="departmentId"
            :shipment="activeShipment"
            :storage-places="storagePlaces"
            :drivers="workspace?.drivers ?? {}"
            :vehicles="workspace?.vehicles ?? {}"
            :commitments="commitmentMap"
            @close="detailOpen = false"
            @received="onReceived"
            @commitment-updated="onCommitmentUpdated"
          />
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { listGrossanlassPlaces } from '@/api/grossanlassLogistics'
import { matplaetzeFromPlaces, type GaStoragePlaceOption } from '@/views/grossanlass/wareneingang/gaStoragePlaces'
import { useI18n } from 'vue-i18n'
import { EButton, ESearchField, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassInboundAcceptPanel from '@/views/grossanlass/wareneingang/GrossanlassInboundAcceptPanel.vue'
import {
  getGrossanlassInboundWorkspace,
  type GaInboundPhase,
  type GaInboundShipment,
  type GaInboundShipmentGroup,
  type GaInboundWorkspace,
} from '@/api/grossanlassInbound'
import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import { useGrossanlassCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useToast } from '@/composables/useToast'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'

type GroupFilter = 'all' | GaInboundShipmentGroup

const { t, locale } = useI18n()
const route = useRoute()
const catalog = useGrossanlassCommitmentCatalog()
const toast = useToast()
const loading = ref(false)
const workspace = ref<GaInboundWorkspace | null>(null)
const storagePlaces = ref<GaStoragePlaceOption[]>([])
const phase = ref<GaInboundPhase>('expected')
const groupFilter = ref<GroupFilter>('all')
const placeFilter = ref<string | null>(null)
const search = ref('')
const detailOpen = ref(false)
const activeShipment = ref<GaInboundShipment | null>(null)
const commitmentMap = ref<Record<string, GrossanlassCommitment>>({})

const departmentId = computed(() => String(route.params.departmentId || ''))

const groupChips = computed(() => [
  { id: 'all' as const, label: t('grossanlass.material.wareneingang.modeAll') },
  { id: 'tour' as const, label: t('grossanlass.material.wareneingang.workspace.group.tour') },
  { id: 'trip' as const, label: t('grossanlass.material.wareneingang.workspace.group.trip') },
  { id: 'direct' as const, label: t('grossanlass.material.wareneingang.workspace.group.direct') },
  { id: 'unassigned' as const, label: t('grossanlass.material.wareneingang.workspace.group.unassigned') },
])

const locationsRoute = computed(() => ({
  name: 'GrossanlassEinstellungenStandorte',
  params: { departmentId: departmentId.value },
}))

const placeFilterItems = computed(() =>
  storagePlaces.value.map((place) => ({ title: place.name, value: place.id })),
)

const visibleShipments = computed(() => {
  const rows = workspace.value?.phases[phase.value] ?? []
  const q = search.value.trim().toLowerCase()
  return rows
    .filter((row) => groupFilter.value === 'all' || row.group === groupFilter.value)
    .filter((row) => !placeFilter.value || row.target_place_id === placeFilter.value)
    .filter((row) => {
      if (!q) return true
      const hay = [
        row.title,
        ...row.positions.map((p) => [p.name, p.supplier, p.barcode, p.order_ref].join(' ')),
      ].join(' ').toLowerCase()
      return hay.includes(q)
    })
    .sort((a, b) => (a.arrives_at || '').localeCompare(b.arrives_at || ''))
})

function groupLabel(group: GaInboundShipmentGroup): string {
  return t(`grossanlass.material.wareneingang.workspace.group.${group}`)
}

function arrivalLabel(row: GaInboundShipment): string {
  if (!row.arrives_at) return t('grossanlass.material.wareneingang.noDate')
  return formatGaIsoLabel(row.arrives_at, locale.value)
}

function progressLabel(row: GaInboundShipment): string {
  const { received, open, expected } = row.progress
  return t('grossanlass.material.wareneingang.receivedProgress', {
    received,
    expected,
    missing: open,
  })
}

function targetPlaceName(row: GaInboundShipment): string {
  const id = row.target_place_id
  if (!id) return ''
  return storagePlaces.value.find((p) => p.id === id)?.name ?? ''
}

function openShipment(row: GaInboundShipment) {
  activeShipment.value = row
  detailOpen.value = true
}

function onScanEnter() {
  const q = search.value.trim().toLowerCase()
  if (!q) return
  const hit = workspace.value?.shipments.find((row) =>
    row.positions.some((p) => (p.barcode || '').toLowerCase() === q),
  )
  if (hit) {
    phase.value = hit.phase
    openShipment(hit)
  }
}

function syncCommitmentMap() {
  const map: Record<string, GrossanlassCommitment> = {}
  for (const row of catalog.commitments.value) {
    map[row.id] = row
  }
  commitmentMap.value = map
}

async function load() {
  const id = departmentId.value
  if (!id) return
  loading.value = true
  try {
    const [ws, places] = await Promise.all([
      getGrossanlassInboundWorkspace(id),
      listGrossanlassPlaces(id),
    ])
    workspace.value = ws
    storagePlaces.value = matplaetzeFromPlaces(places)
    syncCommitmentMap()
  } catch (e: unknown) {
    workspace.value = null
    storagePlaces.value = []
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    loading.value = false
  }
}

function onReceived() {
  detailOpen.value = false
  activeShipment.value = null
  void load()
}

function onCommitmentUpdated(commitment: GrossanlassCommitment) {
  catalog.upsert(commitment)
  syncCommitmentMap()
}

watch(departmentId, () => { void load() })

onMounted(() => {
  void catalog.load()
  void load()
})
</script>

<style scoped>
.ga-inbound-workspace { padding: 4px 0 24px; }
.tab-intro { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.inbound-search { margin-bottom: 12px; max-width: 520px; }
.wareneingang-phase-tabs { margin-bottom: 12px; }
.inbound-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin-bottom: 16px;
}
.inbound-toolbar__place { min-width: 200px; max-width: 280px; }
.inbound-storage-hint { margin-bottom: 12px; }
.inbound-storage-hint__link { margin-left: 6px; font-weight: 600; }
.shipment-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.shipment-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  background: #fff;
}
.shipment-row__main { min-width: 0; }
.shipment-row__badge {
  margin-left: 8px;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  color: #0f766e;
}
.shipment-row__meta { margin: 4px 0 0; font-size: 0.82rem; color: #475569; }
@media (max-width: 640px) {
  .shipment-row { flex-direction: column; align-items: stretch; }
}
</style>
