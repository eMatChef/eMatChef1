<template>
  <div class="ga-auftrag-overview">
    <p class="ga-auftrag-overview__intro">{{ intro }}</p>

    <ELoadingState
      v-if="uebersicht.loading.value"
      variant="inline"
      :message="t('common.loading')"
    />

    <template v-else-if="kind === 'fahrauftrag' && previewRows.length">
      <GrossanlassFahrauftragList
        :rows="previewRows"
        :busy-id="busyId"
        :can-start-trip="canStartTrip"
        :read-only="!canOperateTrips"
        clickable
        @toggle-packed="onTogglePacked"
        @release="onReleaseTrip"
        @issue="onIssueTrip"
        @open="openFromPreview"
      />
    </template>

    <ul v-else-if="kind === 'bauauftrag' && assignments.length" class="ga-auftrag-overview__list">
      <li v-for="assignment in assignments" :key="assignment.id">
        <GrossanlassHelperAssignmentRow
          :assignment="assignment"
          @open="openAssignment"
        />
      </li>
    </ul>

    <EEmptyState
      v-else
      :icon="emptyIcon"
      :title="emptyTitle"
      :description="emptyText"
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
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { gaCanOperateAusgabe } from '@/utils/grossanlassAccess'
import { gaLageRowsOfKind, type GaLageTaskKind } from '@/utils/grossanlassLage'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import GrossanlassFahrauftragList from '@/views/grossanlass/GrossanlassFahrauftragList.vue'
import GrossanlassHelperAssignmentDetailDialog from '@/views/grossanlass/GrossanlassHelperAssignmentDetailDialog.vue'
import GrossanlassHelperAssignmentRow from '@/views/grossanlass/GrossanlassHelperAssignmentRow.vue'
import type { GaPreviewEinsatz } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import {
  groupsToOrgGroups,
  toHelperAssignment,
  type GaHelperAssignment,
} from '@/views/grossanlass/grossanlassHelperAssignment'

const route = useRoute()
const { t, locale } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const uebersicht = useGaUebersicht()

const groups = ref<GrossanlassGroup[]>([])
const busyId = ref<string | null>(null)
const detailOpen = ref(false)
const selected = ref<GaHelperAssignment | null>(null)

const kind = computed<GaLageTaskKind>(() =>
  route.meta.lageKind === 'fahrauftrag' ? 'fahrauftrag' : 'bauauftrag',
)
const orgGroups = computed(() => groupsToOrgGroups(groups.value))
const places = computed(() => uebersicht.data.value?.places ?? [])
const cards = computed(() => uebersicht.data.value?.cards ?? [])
const canOperateTrips = computed(() => gaCanOperateAusgabe(authStore.currentDepartmentRole))

const rows = computed(() =>
  gaLageRowsOfKind(
    uebersicht.data.value?.einsaetze ?? [],
    kind.value,
    groups.value,
    places.value,
  ),
)

const assignments = computed(() =>
  rows.value.map((row) => toHelperAssignment(row, locale.value, kind.value, orgGroups.value)),
)

const previewRows = computed(() => assignments.value)

const intro = computed(() =>
  kind.value === 'fahrauftrag'
    ? t('grossanlass.materialUebersicht.fahrauftraegeIntro')
    : t('grossanlass.materialUebersicht.bauauftraegeIntro'),
)
const emptyTitle = computed(() =>
  kind.value === 'fahrauftrag'
    ? t('grossanlass.materialUebersicht.emptyFahrauftraegeTitle')
    : t('grossanlass.materialUebersicht.emptyBauauftraegeTitle'),
)
const emptyText = computed(() =>
  kind.value === 'fahrauftrag'
    ? t('grossanlass.materialUebersicht.emptyFahrauftraegeText')
    : t('grossanlass.materialUebersicht.emptyBauauftraegeText'),
)
const emptyIcon = computed(() =>
  kind.value === 'fahrauftrag' ? 'mdi-truck-delivery-outline' : 'mdi-hammer-wrench',
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

function openAssignment(assignment: GaHelperAssignment) {
  selected.value = assignment
  detailOpen.value = true
}

function openFromPreview(row: GaPreviewEinsatz) {
  const match = assignments.value.find((item) => item.id === row.id)
  if (match) openAssignment(match)
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
  toast.success(t('grossanlass.materialUebersicht.tripsReleasedToast'))
}

async function onIssueTrip(row: GaPreviewEinsatz) {
  await withBusy(row.id, () => uebersicht.issue(row.id, row.chauffeurUserId || undefined))
  toast.success(t('grossanlass.materialUebersicht.tripsIssuedToast'))
}

onMounted(() => {
  const dept = String(route.params.departmentId || '')
  if (!dept) return
  void getGrossanlassGroups(dept).then((list) => { groups.value = list }).catch(() => { groups.value = [] })
})
</script>

<style scoped>
.ga-auftrag-overview {
  padding: 4px 0 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ga-auftrag-overview__intro {
  margin: 0;
  color: var(--color-text-muted, #6b7280);
  font-size: 0.9rem;
}

.ga-auftrag-overview__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
</style>
