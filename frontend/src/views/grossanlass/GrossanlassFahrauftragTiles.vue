<template>
  <div class="ga-fahr-tiles">
    <section v-if="trips.length" data-testid="trip-tiles">
      <h3 class="ga-fahr-tiles__title">
        {{ t('grossanlass.material.tilesTrips') }} <span>{{ trips.length }}</span>
      </h3>
      <div class="ga-fahr-tiles__grid">
        <article
          v-for="row in trips"
          :key="row.id"
          class="ga-tile"
          :class="`ga-tile--${row.status}`"
          tabindex="0"
          role="button"
          :data-testid="`trip-tile-${row.id}`"
          @click="emit('open', row)"
          @keydown.enter.self="emit('open', row)"
        >
          <header class="ga-tile__head">
            <span class="ga-tile__kind"><v-icon icon="mdi-truck-delivery-outline" size="16" />{{ t('grossanlass.aufgaben.kind.logistik') }}</span>
            <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[row.status] ?? 'default'">
              {{ t(`grossanlass.material.status.${row.status}`) }}
            </v-chip>
          </header>
          <p class="ga-tile__origin">{{ originOf(row) }}</p>
          <h4 class="ga-tile__name">{{ row.objectName || row.who }}</h4>
          <p class="ga-tile__meta">
            <v-icon icon="mdi-clock-outline" size="14" />{{ formatHelperWhenLabel(row.fromIso, row.toIso, locale) }}
          </p>
          <p v-if="vehicleOf(row) || destinationOf(row)" class="ga-tile__meta">
            <v-icon icon="mdi-truck-outline" size="14" />
            <span v-if="vehicleOf(row) && vehicleOf(row) !== row.objectName">{{ vehicleOf(row) }}</span>
            <span v-if="destinationOf(row)">→ {{ destinationOf(row) }}</span>
          </p>
          <p class="ga-tile__meta">
            <v-icon icon="mdi-steering" size="14" />
            <template v-if="chauffeurOf(row)">{{ chauffeurOf(row) }}</template>
            <strong v-else class="ga-tile__warn">{{ t('grossanlass.aufgaben.noChauffeur') }}</strong>
          </p>
          <div class="ga-tile__flags">
            <span v-if="row.packed" class="ga-tile__badge">{{ t('grossanlass.material.tripsPacked') }}</span>
            <span v-if="row.tripReleased" class="ga-tile__badge ga-tile__badge--ok">{{ t('grossanlass.material.tripsReleased') }}</span>
            <span v-if="!row.destinationPlaceId" class="ga-tile__badge ga-tile__badge--warn">{{ t('grossanlass.material.tripsNoDestination') }}</span>
          </div>
          <div class="ga-tile__flags" data-testid="trip-tile-dispo">
            <span v-if="row.tour" class="ga-tile__badge" :class="{ 'ga-tile__badge--ok': row.tour.status === 'confirmed' }">
              <v-icon icon="mdi-source-branch" size="12" /> {{ row.tour.name }} · {{ t(`grossanlass.dispo.tourStatus.${row.tour.status}`) }}
            </span>
            <span v-else-if="row.status !== 'returned'" class="ga-tile__badge ga-tile__badge--warn">{{ t('grossanlass.material.tripsNoTour') }}</span>
            <span v-if="requestOf?.(row)" class="ga-tile__badge" :class="requestOf(row)!.status === 'open' ? 'ga-tile__badge--warn' : 'ga-tile__badge--ok'">
              {{ t(`grossanlass.dispo.requests.status.${requestOf(row)!.status}`) }}
            </span>
          </div>
          <div class="ga-tile__actions" @click.stop @keydown.enter.stop>
            <router-link
              v-if="canOpenDispatch"
              class="ga-tile__link"
              :to="{ name: 'GrossanlassLogistikDisposition', params: { departmentId }, query: row.tour ? { tour: row.tour.id } : { need: row.id } }"
              :data-testid="`trip-tile-dispatch-${row.id}`"
            >
              <v-icon icon="mdi-truck-fast-outline" size="14" /> {{ t('grossanlass.material.tripsOpenDispatch') }}
            </router-link>
            <EButton v-if="row.status !== 'returned'" variant="secondary" size="small" :data-testid="`trip-tile-change-${row.id}`" @click="emit('request-change', row)">
              {{ t('grossanlass.material.changeRequest.button') }}
            </EButton>
          </div>
          <footer v-if="!readOnly && row.status !== 'issued'" class="ga-tile__actions" @click.stop @keydown.enter.stop>
            <EButton variant="secondary" size="small" :disabled="busyId === row.id" @click="emit('toggle-packed', row)">
              {{ row.packed ? t('grossanlass.material.tripsUnpack') : t('grossanlass.material.tripsPack') }}
            </EButton>
            <EButton v-if="row.tripReleased" variant="primary" size="small" :disabled="busyId === row.id || !canStart(row)" @click="emit('issue', row)">
              {{ t('grossanlass.material.tripsIssue') }}
            </EButton>
          </footer>
        </article>
      </div>
    </section>

    <section v-if="wishes.length" data-testid="wish-tiles">
      <h3 class="ga-fahr-tiles__title">
        {{ t('grossanlass.material.tilesWishes') }} <span>{{ wishes.length }}</span>
      </h3>
      <p class="ga-fahr-tiles__hint">{{ t('grossanlass.material.tilesWishesHint') }}</p>
      <div class="ga-fahr-tiles__grid">
        <button v-for="need in wishes" :key="need.id" type="button" class="ga-tile ga-tile--wish" @click="emit('open-vehicle', need.id)">
          <span class="ga-tile__head">
            <span class="ga-tile__kind"><v-icon icon="mdi-truck-outline" size="16" />{{ t('grossanlass.material.tilesWishChip') }}</span>
            <v-chip size="x-small" variant="tonal" :color="need.committed ? 'success' : 'warning'">
              {{ need.committed ? t('grossanlass.fahrzeuge.covered') : t('grossanlass.fahrzeuge.openVehicle') }}
            </v-chip>
          </span>
          <span class="ga-tile__origin">{{ need.origin }}</span>
          <span class="ga-tile__name">{{ need.vehicle_label || need.category || t('grossanlass.fahrzeuge.openVehicle') }}</span>
          <span v-if="need.category && need.vehicle_label" class="ga-tile__meta">{{ need.category }}</span>
          <span v-if="need.task_label" class="ga-tile__meta">{{ need.task_label }}</span>
          <span v-if="need.when" class="ga-tile__meta"><v-icon icon="mdi-clock-outline" size="14" />{{ need.when }}</span>
        </button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import type { GaPreviewEinsatz } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { formatHelperWhenLabel, helperOrgLabel } from '@/views/grossanlass/grossanlassHelperAssignment'

export type GaFahrauftragWishTile = {
  id: string
  vehicle_label: string
  task_label: string
  category: string
  when: string
  committed: boolean
  /** Ressort › Bereich › Bauprojekt */
  origin: string
}

const props = defineProps<{
  trips: GaPreviewEinsatz[]
  wishes: GaFahrauftragWishTile[]
  busyId?: string | null
  readOnly?: boolean
  canStartTrip?: (row: GaPreviewEinsatz) => boolean
  departmentId?: string
  /** Disposition einsehbar (Link zur zentralen Logistik-Disposition). */
  canOpenDispatch?: boolean
  requestOf?: (row: GaPreviewEinsatz) => { status: 'open' | 'accepted' | 'adjusted' | 'rejected' } | undefined
  chauffeurOf: (row: GaPreviewEinsatz) => string
  vehicleOf: (row: GaPreviewEinsatz) => string
  destinationOf: (row: GaPreviewEinsatz) => string
}>()

const emit = defineEmits<{
  open: [row: GaPreviewEinsatz]
  'open-vehicle': [id: string]
  'toggle-packed': [row: GaPreviewEinsatz]
  'request-change': [row: GaPreviewEinsatz]
  issue: [row: GaPreviewEinsatz]
}>()

const { t, locale } = useI18n()

const STATUS_COLOR: Record<string, string> = {
  planned: 'warning',
  pending_approval: 'info',
  issued: 'primary',
  returned: 'success',
}

function originOf(row: GaPreviewEinsatz): string {
  return helperOrgLabel(row)
}

function canStart(row: GaPreviewEinsatz): boolean {
  if (!row.destinationPlaceId) return false
  return props.canStartTrip ? props.canStartTrip(row) : true
}
</script>

<style scoped>
.ga-fahr-tiles {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.ga-fahr-tiles__title {
  margin: 0 0 10px;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.ga-fahr-tiles__title span {
  padding: 1px 8px;
  border-radius: 999px;
  background: #e2e8f0;
  font-size: 0.72rem;
}
.ga-fahr-tiles__hint {
  margin: -4px 0 10px;
  font-size: 0.85rem;
  color: #64748b;
}
.ga-fahr-tiles__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 12px;
}
.ga-tile {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left-width: 4px;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.ga-tile:hover,
.ga-tile:focus-visible {
  border-color: #94a3b8;
  outline: none;
}
.ga-tile--planned { border-left-color: #f59e0b; }
.ga-tile--pending_approval { border-left-color: #0ea5e9; }
.ga-tile--issued { border-left-color: #059669; }
.ga-tile--wish {
  border-style: dashed;
  border-left-style: dashed;
  border-left-color: #94a3b8;
  background: #f8fafc;
}
.ga-tile__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.ga-tile__kind {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.ga-tile__origin {
  margin: 0;
  font-size: 0.8rem;
  color: #64748b;
}
.ga-tile__name {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}
.ga-tile__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
  font-size: 0.84rem;
  color: #334155;
}
.ga-tile__warn { color: #b45309; }
.ga-tile__flags,
.ga-tile__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 8px;
}
.ga-tile__badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: #e2e8f0;
  color: #334155;
}
.ga-tile__badge--ok { background: #ccfbf1; color: #0f766e; }
.ga-tile__badge--warn { background: #fee2e2; color: #b91c1c; }
@media (max-width: 720px) {
  .ga-fahr-tiles__grid { grid-template-columns: 1fr; }
}
.ga-tile__link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #059669;
  text-decoration: none;
}
.ga-tile__link:hover {
  text-decoration: underline;
}
</style>
