<template>
  <div class="ga-fahrauftraege">
    <div class="ga-fahrauftraege__toolbar">
      <p class="ga-fahrauftraege__intro">{{ t('grossanlass.material.fahrauftraegeIntro') }}</p>
      <v-switch
        v-if="isMaterialwart"
        v-model="showAllOrders"
        class="ga-fahrauftraege__mode"
        color="primary"
        density="compact"
        hide-details
        inset
        :label="showAllOrders ? t('grossanlass.planung.bauListAll') : t('grossanlass.planung.bauListMine')"
      />
      <v-btn-toggle
        v-else
        v-model="listMode"
        class="ga-fahrauftraege__mode"
        density="compact"
        color="primary"
        variant="outlined"
        mandatory
      >
        <v-btn value="upcoming" size="small">{{ t('grossanlass.material.fahrauftragFilterUpcoming') }}</v-btn>
        <v-btn value="all" size="small">{{ t('grossanlass.material.fahrauftragFilterAll') }}</v-btn>
      </v-btn-toggle>
      <EButton
        v-if="canAdd"
        variant="primary"
        size="small"
        @click="openCreate"
      >
        {{ t('grossanlass.material.addFahrauftrag') }}
      </EButton>
    </div>

    <v-tabs v-model="subtab" class="materials-view-tabs ga-fahrauftraege__tabs" color="primary">
      <v-tab value="liste">{{ t('grossanlass.planung.transporteSubList') }}</v-tab>
      <v-tab value="kalender">{{ t('grossanlass.planung.transporteSubCalendar') }}</v-tab>
      <v-tab value="wuensche">{{ t('grossanlass.planung.transporteSubWishes') }}</v-tab>
    </v-tabs>

    <GrossanlassEinsatzProgrammCalendar
      v-if="subtab === 'kalender' && !loading"
      only-vehicles
      :department-id="departmentId"
      :groups="visibleGroups"
      :trips="calendarTrips"
      :planned-vehicles="calendarNeeds"
      @open="onCalendarOpen"
    />

    <div v-else-if="subtab === 'wuensche' && !loading" class="ga-fahrauftraege__wishes">
      <p v-if="!wishGroups.length" class="ga-fahrauftraege__wish-empty">
        {{ emptyText }}
      </p>
      <section v-for="group in wishGroups" :key="group.category" class="ga-fahrauftraege__wish-group">
        <h3>{{ group.category }}</h3>
        <ul>
          <li v-for="need in group.rows" :key="need.id">
            <button type="button" class="ga-fahrauftraege__wish" @click="openVehicle(need.id)">
              <strong>{{ need.vehicle_label || t('grossanlass.fahrzeuge.openVehicle') }}</strong>
              <span>{{ need.groupName }}</span>
              <span v-if="need.task_label">{{ need.task_label }}</span>
              <span v-if="need.when">{{ need.when }}</span>
            </button>
          </li>
        </ul>
      </section>
    </div>

    <template v-else>
      <ELoadingState
        v-if="loading"
        variant="inline"
        :message="t('common.loading')"
      />

      <EEmptyState
        v-else-if="!sections.length && !unassignedTrips.length"
        icon="mdi-truck-delivery-outline"
        :title="emptyTitle"
        :description="emptyText"
      >
        <template v-if="canAdd" #actions>
          <EButton @click="openCreate">{{ t('grossanlass.material.addFahrauftrag') }}</EButton>
        </template>
      </EEmptyState>

      <template v-else>
        <section v-if="unassignedTrips.length" class="ga-fahrauftraege__loose">
          <h3>{{ t('grossanlass.material.unassignedFahrauftraege') }}</h3>
          <GrossanlassFahrauftragList
            :rows="unassignedTrips"
            :busy-id="busyId"
            :can-start-trip="canStartTrip"
            :read-only="!canOperateTrips"
            clickable
            :show-title="false"
            @toggle-packed="onTogglePacked"
            @release="onReleaseTrip"
            @issue="onIssueTrip"
            @open="openFromPreview"
          />
        </section>

        <v-expansion-panels v-model="openIds" multiple class="e-accordions ga-fahrauftraege__tree">
          <GrossanlassFahrauftragBranch
            v-for="section in sections"
            :key="section.group.id"
            :section="section"
            :kind-label="kindLabel"
            :busy-id="busyId"
            :can-start-trip="canStartTrip"
            :read-only="!canOperateTrips"
            @toggle-packed="onTogglePacked"
            @release="onReleaseTrip"
            @issue="onIssueTrip"
            @open="openFromPreview"
            @open-vehicle="openVehicle"
          />
        </v-expansion-panels>
      </template>
    </template>

    <GrossanlassEinsatzBookPreviewDialog
      v-model="dialogOpen"
      v-model:draft="draft"
      mode="einsatz"
      preset-delivery="trip"
      lock-delivery
      :wishes="wishes"
      :free-picks="freePicks"
      :rows="bookingRows"
      :resources="resources"
      :chauffeurs="chauffeurs"
      :places="places"
      :groups="visibleGroups"
      :default-scope="bookDefaultScope"
      @confirm="onConfirm"
      @confirm-many="onConfirmMany"
      @order="onOrder"
      @place-created="uebersicht.addPlace"
    />

    <GrossanlassVehicleNeedDialog
      v-model="needOpen"
      :department-id="departmentId"
      :need="selectedNeed"
      :committed="selectedNeedCommitted"
      :can-edit="selectedNeedEditable"
      @saved="onVehicleSaved"
    />

    <GrossanlassHelperAssignmentDetailDialog
      v-model="detailOpen"
      :assignment="selected"
      :cards="cards"
      :busy="busyId === selected?.id"
      :can-toggle-packed="canTogglePackedSelected"
      @toggle-packed="onTogglePackedAssignment"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { gaCanManagePlanung, gaCanOperateAusgabe, gaIsMaterialwart } from '@/utils/grossanlassAccess'
import { nestTreeWithLevel, type NestedTreeNode } from '@/utils/grossanlassGroupHierarchy'
import { grossanlassGroupNodeKindKey } from '@/utils/grossanlassGroupNode'
import { listGrossanlassVehicleNeeds, type GaBauprojektVehicleNeed } from '@/api/grossanlassBauprojekt'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import type { GaUebersichtCreatePayload } from '@/api/grossanlassUebersicht'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { gaFahrauftragComposerKey } from '@/views/grossanlass/gaFahrauftragComposer'
import GrossanlassEinsatzBookPreviewDialog, {
  type GaBookPreviewDraft,
} from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import GrossanlassFahrauftragBranch, {
  type GaFahrauftragSection,
  type GaFahrauftragVehicleRow,
} from '@/views/grossanlass/GrossanlassFahrauftragBranch.vue'
import GrossanlassFahrauftragList from '@/views/grossanlass/GrossanlassFahrauftragList.vue'
import GrossanlassEinsatzProgrammCalendar from '@/views/grossanlass/GrossanlassEinsatzProgrammCalendar.vue'
import GrossanlassHelperAssignmentDetailDialog from '@/views/grossanlass/GrossanlassHelperAssignmentDetailDialog.vue'
import GrossanlassVehicleNeedDialog from '@/components/grossanlass/GrossanlassVehicleNeedDialog.vue'
import '@/styles/views/materials-view-tabs.css'
import {
  articleToResource,
  formatGaIsoLabel,
} from '@/views/grossanlass/grossanlassZusagePreviewData'
import { resourceToPickTemplate, type GaPreviewEinsatz } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import {
  groupsToOrgGroups,
  toHelperAssignment,
  type GaHelperAssignment,
} from '@/views/grossanlass/grossanlassHelperAssignment'

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const { articles, commitments } = useGaCommitmentCatalog()
const uebersicht = useGaUebersicht()

const groups = ref<GrossanlassGroup[]>([])
const vehicleNeeds = ref<Array<GaBauprojektVehicleNeed & { group_name: string }>>([])
const listMode = ref<'upcoming' | 'all'>('upcoming')
/** MW/CMW: Standard «Alle Aufträge» (Überblick), umschaltbar auf «Meine Aufträge» (eigenes Ressort / eigene Fahrten). */
const scopeMode = ref<'mine' | 'all'>('all')
const showAllOrders = computed({
  get: () => scopeMode.value === 'all',
  set: (value: boolean) => {
    scopeMode.value = value ? 'all' : 'mine'
  },
})
const subtab = ref<'liste' | 'kalender' | 'wuensche'>('liste')
const groupsLoading = ref(true)
const openIds = ref<string[]>([])
const dialogOpen = ref(false)
const draft = ref<GaBookPreviewDraft | null>(null)
const busyId = ref<string | null>(null)
const detailOpen = ref(false)
const selected = ref<GaHelperAssignment | null>(null)
const needOpen = ref(false)
const selectedNeedId = ref<string | null>(null)

const {
  canManageStruktur,
  isInAssignedRessortBranch,
  isInOwnAssignedBranch,
} = useGrossanlassRessortScope(groups)

const departmentId = computed(() => String(route.params.departmentId || ''))
const visibleGroups = computed(() =>
  groups.value.filter((group) => isInAssignedRessortBranch(group)),
)
const isMaterialwart = computed(() => gaCanManagePlanung(authStore.currentDepartmentRole))
const mineOnly = computed(() => isMaterialwart.value && scopeMode.value === 'mine')
const scopeGroups = computed(() =>
  mineOnly.value ? groups.value.filter((group) => isInOwnAssignedBranch(group)) : visibleGroups.value,
)
const visibleIds = computed(() => new Set(scopeGroups.value.map((group) => group.id)))
const orgGroups = computed(() => groupsToOrgGroups(groups.value))
const cards = computed(() => uebersicht.data.value?.cards ?? [])
const places = computed(() => uebersicht.data.value?.places ?? [])
const canOperateTrips = computed(() => gaCanOperateAusgabe(authStore.currentDepartmentRole))
const canAdd = computed(() => visibleGroups.value.length > 0 || canManageStruktur.value)
const loading = computed(() => groupsLoading.value || uebersicht.loading.value)
const bookDefaultScope = computed(() =>
  gaIsMaterialwart(authStore.currentDepartmentRole) ? 'single' : 'project',
)
const selectedNeed = computed(() =>
  vehicleNeeds.value.find((need) => need.id === selectedNeedId.value) ?? null,
)
const selectedNeedCommitted = computed(() =>
  selectedNeed.value ? needCommitted(selectedNeed.value) : false,
)
const selectedNeedEditable = computed(() => {
  const need = selectedNeed.value
  if (!need) return false
  const group = groups.value.find((item) => item.id === need.group_id)
  if (!group) return canManageStruktur.value
  return canManageStruktur.value || isInAssignedRessortBranch(group)
})

function openVehicle(id: string) {
  selectedNeedId.value = id
  needOpen.value = true
}

function onVehicleSaved(row: GaBauprojektVehicleNeed) {
  vehicleNeeds.value = vehicleNeeds.value.map((item) =>
    item.id === row.id ? { ...item, ...row, group_name: item.group_name } : item,
  )
}

function kindLabel(group: GrossanlassGroup): string {
  return t(grossanlassGroupNodeKindKey(group.node_type))
}

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const resources = computed(() => articles.value.map((article) => articleToResource(article)))
const freePicks = computed(() =>
  resources.value.map((resource) => {
    const template = resourceToPickTemplate(resource, tr)
    const article = articles.value.find((item) => item.id === resource.id)
    return {
      ...template,
      id: `pick-${resource.id}`,
      objectId: resource.id,
      fromIso: article?.presentFromIso || article?.handoverFromIso || template.fromIso,
      toIso: article?.presentToIso || article?.returnToIso || template.toIso,
      stock: resource.stock,
      qty: resource.kind === 'quantity' ? Math.min(2, resource.stock) : 1,
    }
  }),
)
const wishes = computed(() => uebersicht.wishTemplates.value)
const bookingRows = computed(() => uebersicht.bookingRows())

const tripRows = computed(() =>
  bookingRows.value.filter((row) => row.delivery === 'trip' && row.status !== 'returned'),
)

const visibleTrips = computed(() =>
  tripRows.value.filter((row) => {
    if (!row.groupId) {
      return mineOnly.value ? row.chauffeurUserId === authStore.userId : canManageStruktur.value
    }
    if (mineOnly.value && row.chauffeurUserId === authStore.userId) return true
    return visibleIds.value.has(row.groupId)
  }),
)

function windowStillAhead(startsAt: string | null | undefined, durationMinutes: number | null | undefined): boolean {
  if (!startsAt) return false
  const start = new Date(startsAt)
  if (Number.isNaN(start.getTime())) return false
  const end = new Date(start.getTime())
  const minutes = durationMinutes ?? 0
  if (minutes > 0) end.setMinutes(end.getMinutes() + minutes)
  return end.getTime() >= Date.now()
}

function tripStillAhead(row: GaPreviewEinsatz): boolean {
  const iso = row.toIso || row.fromIso
  if (!iso) return row.status !== 'issued'
  const end = new Date(iso)
  if (Number.isNaN(end.getTime())) return true
  return end.getTime() >= Date.now()
}

const scopedNeeds = computed(() =>
  mineOnly.value ? vehicleNeeds.value.filter((need) => visibleIds.value.has(need.group_id)) : vehicleNeeds.value,
)

const shownNeeds = computed(() =>
  listMode.value === 'all'
    ? scopedNeeds.value
    : scopedNeeds.value.filter((need) => windowStillAhead(need.starts_at, need.duration_minutes)),
)

const shownTrips = computed(() =>
  listMode.value === 'all' ? visibleTrips.value : visibleTrips.value.filter(tripStillAhead),
)

function tripsOf(groupId: string): GaPreviewEinsatz[] {
  return shownTrips.value.filter((row) => row.groupId === groupId)
}

function needCommitted(need: GaBauprojektVehicleNeed): boolean {
  const label = need.vehicle_label.trim().toLowerCase()
  return commitments.value.some((row) => {
    if (row.family !== 'vehicle') return false
    if (need.procurement_line_id && row.item_details?.from_line_id === need.procurement_line_id) return true
    return label !== '' && row.name.trim().toLowerCase() === label
  })
}

function needWhen(need: GaBauprojektVehicleNeed): string {
  if (!need.starts_at) return ''
  const start = formatGaIsoLabel(need.starts_at, locale.value)
  const minutes = need.duration_minutes ?? 0
  if (minutes <= 0) return start
  const end = new Date(need.starts_at)
  if (Number.isNaN(end.getTime())) return start
  end.setMinutes(end.getMinutes() + minutes)
  const pad = (value: number) => String(value).padStart(2, '0')
  const endIso = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}T${pad(end.getHours())}:${pad(end.getMinutes())}:00`
  return `${start} – ${formatGaIsoLabel(endIso, locale.value)}`
}

function vehiclesOf(groupId: string): GaFahrauftragVehicleRow[] {
  return shownNeeds.value
    .filter((need) => need.group_id === groupId)
    .map((need) => ({
      id: need.id,
      vehicle_label: need.vehicle_label,
      task_label: need.task_label,
      category: need.category_label || '',
      when: needWhen(need),
      committed: needCommitted(need),
    }))
}

function toSection(node: NestedTreeNode<GrossanlassGroup>): GaFahrauftragSection {
  return {
    group: node,
    trips: tripsOf(node.id),
    vehicles: vehiclesOf(node.id),
    children: node.children.map(toSection),
  }
}

function pruneSection(section: GaFahrauftragSection): GaFahrauftragSection | null {
  const children = section.children
    .map(pruneSection)
    .filter((child): child is GaFahrauftragSection => child !== null)
  if (!section.trips.length && !section.vehicles.length && !children.length) return null
  return { ...section, children }
}

const sections = computed(() =>
  nestTreeWithLevel(scopeGroups.value)
    .map(toSection)
    .map(pruneSection)
    .filter((section): section is GaFahrauftragSection => section !== null),
)

const unassignedTrips = computed(() =>
  shownTrips.value.filter((row) => !row.groupId),
)

const calendarTrips = computed(() =>
  shownTrips.value.map((row) => ({
    id: row.id,
    name: row.objectName || row.who || '',
    detail: row.who || '',
    from: row.fromIso,
    to: row.toIso || row.fromIso,
    groupId: row.groupId || null,
    vehicle: false,
  })),
)

const calendarNeeds = computed(() =>
  shownNeeds.value.map((need) => ({
    id: need.id,
    group_id: need.group_id,
    vehicle_label: need.vehicle_label,
    task_label: need.task_label,
    category_label: need.category_label,
    starts_at: need.starts_at,
    duration_minutes: need.duration_minutes,
  })),
)

const wishGroups = computed(() => {
  const buckets = new Map<string, GaFahrauftragVehicleRow[]>()
  for (const need of shownNeeds.value) {
    const category = need.category_label?.trim() || t('grossanlass.planung.transporteNoCategory')
    const groupName = groups.value.find((group) => group.id === need.group_id)?.name || need.group_name
    const row: GaFahrauftragVehicleRow & { groupName: string } = {
      id: need.id,
      vehicle_label: need.vehicle_label,
      task_label: need.task_label,
      category,
      when: needWhen(need),
      committed: needCommitted(need),
      groupName,
    }
    const list = buckets.get(category) ?? []
    list.push(row)
    buckets.set(category, list)
  }
  return [...buckets.entries()].map(([category, rows]) => ({ category, rows }))
})

function onCalendarOpen(payload: { kind: 'bau' | 'fahrt'; id: string }) {
  if (payload.kind !== 'fahrt') return
  const row = shownTrips.value.find((item) => item.id === payload.id)
  if (row) openFromPreview(row)
}

const hasAnything = computed(() => visibleTrips.value.length > 0 || vehicleNeeds.value.length > 0)
const emptyTitle = computed(() =>
  listMode.value === 'upcoming' && hasAnything.value
    ? t('grossanlass.material.emptyUpcomingFahrauftraegeTitle')
    : t('grossanlass.material.emptyFahrauftraegeTitle'),
)
const emptyText = computed(() =>
  listMode.value === 'upcoming' && hasAnything.value
    ? t('grossanlass.material.emptyUpcomingFahrauftraegeText')
    : t('grossanlass.material.emptyFahrauftraegeText'),
)

const chauffeurs = computed(() =>
  cards.value.map((card) => ({
    value: card.user_id,
    title: card.name,
    subtitle: card.may_drive
      ? t('grossanlass.material.chauffeurMayDrive')
      : t('grossanlass.material.chauffeurNoLicenseShort'),
    mayDrive: card.may_drive,
  })),
)

const canTogglePackedSelected = computed(() => {
  if (!canOperateTrips.value) return false
  const row = selected.value
  if (!row || row.taskKind !== 'fahrauftrag') return false
  return row.status !== 'issued'
})

function canStartTrip(row: GaPreviewEinsatz): boolean {
  if (!row.destinationPlaceId || !row.chauffeurUserId) return false
  const card = cards.value.find((item) => item.user_id === row.chauffeurUserId)
  return !!card?.may_drive
}

function openCreate() {
  draft.value = null
  dialogOpen.value = true
}

function openAssignment(assignment: GaHelperAssignment) {
  selected.value = assignment
  detailOpen.value = true
}

function openFromPreview(row: GaPreviewEinsatz) {
  const api = (uebersicht.data.value?.einsaetze ?? []).find((item) => item.id === row.id)
  if (!api) return
  openAssignment(toHelperAssignment(api, locale.value, 'fahrauftrag', orgGroups.value))
}

function payloadFromDraft(
  current: GaBookPreviewDraft,
  kind: 'einsatz' | 'order',
): GaUebersichtCreatePayload {
  return {
    kind,
    commitment_id: current.objectId || undefined,
    wish_line_id: current.fromWish ? current.id : null,
    qty: current.qty,
    from: current.fromIso,
    to: current.toIso,
    who: current.objectName || current.label || current.who,
    chauffeur_user_id: current.chauffeurUserId || null,
    delivery: 'trip',
    destination_place_id: current.destinationPlaceId || null,
    group_id: current.groupId || null,
    pending: current.hasConflict,
    has_conflict: current.hasConflict,
  }
}

async function withBusy(id: string, fn: () => Promise<void>) {
  busyId.value = id
  try {
    await fn()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    busyId.value = null
  }
}

async function onTogglePacked(row: GaPreviewEinsatz) {
  await withBusy(row.id, () => uebersicht.updateEinsatz(row.id, { packed: !row.packed }))
}

async function onTogglePackedAssignment(assignment: GaHelperAssignment) {
  await onTogglePacked(assignment)
}

async function onReleaseTrip(row: GaPreviewEinsatz) {
  await withBusy(row.id, () => uebersicht.updateEinsatz(row.id, { trip_released: true }))
  toast.success(t('grossanlass.material.tripsReleasedToast'))
}

async function onIssueTrip(row: GaPreviewEinsatz) {
  await withBusy(row.id, () => uebersicht.issue(row.id, row.chauffeurUserId || undefined))
  toast.success(t('grossanlass.material.tripsIssuedToast'))
}

async function onConfirm(current: GaBookPreviewDraft) {
  const kind = current.asOrder ? 'order' : 'einsatz'
  try {
    await uebersicht.create(payloadFromDraft(current, kind))
    toast.success(
      kind === 'order'
        ? t('grossanlass.material.orderNoted')
        : current.hasConflict
          ? t('grossanlass.material.mwNoteSent')
          : t('grossanlass.material.fahrauftragCreated'),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function onConfirmMany(drafts: GaBookPreviewDraft[]) {
  try {
    await uebersicht.createMany(drafts.map((row) => payloadFromDraft(row, 'einsatz')))
    toast.success(
      drafts.some((row) => row.hasConflict)
        ? t('grossanlass.material.mwNoteSent')
        : t('grossanlass.material.bookSavedMany', { count: drafts.length }),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function onOrder(current: GaBookPreviewDraft) {
  try {
    await uebersicht.create(payloadFromDraft(current, 'order'))
    toast.success(t('grossanlass.material.orderNoted'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

const composer = inject(gaFahrauftragComposerKey, null)
watch(canAdd, (value) => {
  if (composer) composer.canAdd = value
}, { immediate: true })

onMounted(() => {
  if (composer) composer.open = openCreate
  if (String(route.query.create || '') === '1') {
    const { create: _removed, ...rest } = route.query
    void router.replace({ query: rest })
    openCreate()
  }
  const dept = departmentId.value
  if (!dept) {
    groupsLoading.value = false
    return
  }
  void Promise.all([
    getGrossanlassGroups(dept),
    listGrossanlassVehicleNeeds(dept).catch(() => []),
  ])
    .then(([rows, needs]) => {
      groups.value = rows
      vehicleNeeds.value = needs
      openIds.value = sections.value.map((section) => section.group.id)
    })
    .catch(() => { groups.value = [] })
    .finally(() => { groupsLoading.value = false })
})

onBeforeUnmount(() => {
  if (!composer) return
  composer.open = () => {}
  composer.canAdd = false
})
</script>

<style scoped>
.ga-fahrauftraege {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 4px 0 24px;
}
.ga-fahrauftraege__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.ga-fahrauftraege__intro {
  margin: 0;
  flex: 1 1 240px;
  color: var(--color-text-muted, #6b7280);
  font-size: 0.9rem;
}
.ga-fahrauftraege__mode {
  flex: 0 0 auto;
  height: fit-content;
}
.ga-fahrauftraege__tabs {
  margin-top: -4px;
}
.ga-fahrauftraege__wishes {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.ga-fahrauftraege__wish-group h3 {
  margin: 0 0 8px;
  font-size: 0.95rem;
}
.ga-fahrauftraege__wish-group ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ga-fahrauftraege__wish-group li {
  padding: 0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
}
.ga-fahrauftraege__wish {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  width: 100%;
  padding: 10px 12px;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
}
.ga-fahrauftraege__wish-group span,
.ga-fahrauftraege__wish-empty {
  color: #64748b;
  font-size: 0.85rem;
}
.ga-fahrauftraege__tree {
  border-radius: 10px;
}
.ga-fahrauftraege__loose h3 {
  margin: 0 0 8px;
  font-size: 0.95rem;
}
</style>
