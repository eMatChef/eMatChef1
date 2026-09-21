<template>
  <div class="ga-fahrauftraege">
    <div class="ga-fahrauftraege__toolbar">
      <p class="ga-fahrauftraege__intro">{{ t('grossanlass.materialUebersicht.fahrauftraegeIntro') }}</p>
      <EButton
        v-if="canAdd"
        variant="primary"
        size="small"
        @click="openCreate"
      >
        {{ t('grossanlass.materialUebersicht.addFahrauftrag') }}
      </EButton>
    </div>

    <ELoadingState
      v-if="loading"
      variant="inline"
      :message="t('common.loading')"
    />

    <EEmptyState
      v-else-if="!sections.length && !unassignedTrips.length"
      icon="mdi-truck-delivery-outline"
      :title="t('grossanlass.materialUebersicht.emptyFahrauftraegeTitle')"
      :description="t('grossanlass.materialUebersicht.emptyFahrauftraegeText')"
    >
      <template v-if="canAdd" #actions>
        <EButton @click="openCreate">{{ t('grossanlass.materialUebersicht.addFahrauftrag') }}</EButton>
      </template>
    </EEmptyState>

    <template v-else>
      <section v-if="unassignedTrips.length" class="ga-fahrauftraege__loose">
        <h3>{{ t('grossanlass.materialUebersicht.unassignedFahrauftraege') }}</h3>
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
        />
      </v-expansion-panels>
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
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { gaCanOperateAusgabe, gaIsMaterialwart } from '@/utils/grossanlassAccess'
import { nestTreeWithLevel, type NestedTreeNode } from '@/utils/grossanlassGroupHierarchy'
import { grossanlassGroupNodeKindKey } from '@/utils/grossanlassGroupNode'
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
} from '@/views/grossanlass/GrossanlassFahrauftragBranch.vue'
import GrossanlassFahrauftragList from '@/views/grossanlass/GrossanlassFahrauftragList.vue'
import GrossanlassHelperAssignmentDetailDialog from '@/views/grossanlass/GrossanlassHelperAssignmentDetailDialog.vue'
import {
  articleToResource,
} from '@/views/grossanlass/grossanlassZusagePreviewData'
import { resourceToPickTemplate, type GaPreviewEinsatz } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import {
  groupsToOrgGroups,
  toHelperAssignment,
  type GaHelperAssignment,
} from '@/views/grossanlass/grossanlassHelperAssignment'

const route = useRoute()
const { t, locale } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const { articles } = useGaCommitmentCatalog()
const uebersicht = useGaUebersicht()

const groups = ref<GrossanlassGroup[]>([])
const groupsLoading = ref(true)
const openIds = ref<string[]>([])
const dialogOpen = ref(false)
const draft = ref<GaBookPreviewDraft | null>(null)
const busyId = ref<string | null>(null)
const detailOpen = ref(false)
const selected = ref<GaHelperAssignment | null>(null)

const {
  canManageStruktur,
  isInAssignedRessortBranch,
} = useGrossanlassRessortScope(groups)

const departmentId = computed(() => String(route.params.departmentId || ''))
const visibleGroups = computed(() =>
  groups.value.filter((group) => isInAssignedRessortBranch(group)),
)
const visibleIds = computed(() => new Set(visibleGroups.value.map((group) => group.id)))
const orgGroups = computed(() => groupsToOrgGroups(groups.value))
const cards = computed(() => uebersicht.data.value?.cards ?? [])
const places = computed(() => uebersicht.data.value?.places ?? [])
const canOperateTrips = computed(() => gaCanOperateAusgabe(authStore.currentDepartmentRole))
const canAdd = computed(() => visibleGroups.value.length > 0 || canManageStruktur.value)
const loading = computed(() => groupsLoading.value || uebersicht.loading.value)
const bookDefaultScope = computed(() =>
  gaIsMaterialwart(authStore.currentDepartmentRole) ? 'single' : 'project',
)

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
    if (!row.groupId) return canManageStruktur.value
    return visibleIds.value.has(row.groupId)
  }),
)

function tripsOf(groupId: string): GaPreviewEinsatz[] {
  return visibleTrips.value.filter((row) => row.groupId === groupId)
}

function toSection(node: NestedTreeNode<GrossanlassGroup>): GaFahrauftragSection {
  return {
    group: node,
    trips: tripsOf(node.id),
    children: node.children.map(toSection),
  }
}

const sections = computed(() =>
  nestTreeWithLevel(visibleGroups.value).map(toSection),
)

const unassignedTrips = computed(() =>
  visibleTrips.value.filter((row) => !row.groupId),
)

const chauffeurs = computed(() =>
  cards.value.map((card) => ({
    value: card.user_id,
    title: card.name,
    subtitle: card.may_drive
      ? t('grossanlass.materialUebersicht.chauffeurMayDrive')
      : t('grossanlass.materialUebersicht.chauffeurNoLicenseShort'),
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
  toast.success(t('grossanlass.materialUebersicht.tripsReleasedToast'))
}

async function onIssueTrip(row: GaPreviewEinsatz) {
  await withBusy(row.id, () => uebersicht.issue(row.id, row.chauffeurUserId || undefined))
  toast.success(t('grossanlass.materialUebersicht.tripsIssuedToast'))
}

async function onConfirm(current: GaBookPreviewDraft) {
  const kind = current.asOrder ? 'order' : 'einsatz'
  try {
    await uebersicht.create(payloadFromDraft(current, kind))
    toast.success(
      kind === 'order'
        ? t('grossanlass.materialUebersicht.orderNoted')
        : current.hasConflict
          ? t('grossanlass.materialUebersicht.mwNoteSent')
          : t('grossanlass.materialUebersicht.fahrauftragCreated'),
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
        ? t('grossanlass.materialUebersicht.mwNoteSent')
        : t('grossanlass.materialUebersicht.bookSavedMany', { count: drafts.length }),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function onOrder(current: GaBookPreviewDraft) {
  try {
    await uebersicht.create(payloadFromDraft(current, 'order'))
    toast.success(t('grossanlass.materialUebersicht.orderNoted'))
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
  const dept = departmentId.value
  if (!dept) {
    groupsLoading.value = false
    return
  }
  void getGrossanlassGroups(dept)
    .then((rows) => {
      groups.value = rows
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
.ga-fahrauftraege__tree {
  border-radius: 10px;
}
.ga-fahrauftraege__loose h3 {
  margin: 0 0 8px;
  font-size: 0.95rem;
}
</style>
