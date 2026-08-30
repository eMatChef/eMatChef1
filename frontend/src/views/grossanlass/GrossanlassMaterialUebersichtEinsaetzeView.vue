<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.materialUebersicht.einsaetzeIntro') }}</p>

    <section v-if="pendingRows.length && canApproveEinsatz" class="ga-approval-queue">
      <h3>{{ t('grossanlass.materialUebersicht.approvalQueueTitle') }}</h3>
      <ul>
        <li v-for="row in pendingRows" :key="row.id">
          <span>
            <strong>{{ row.objectName }}</strong>
            · {{ row.ressort }} · {{ row.fromLabel }} – {{ row.toLabel }}
          </span>
          <EButton
            variant="primary"
            size="x-small"
            :loading="busyTripId === row.id"
            @click="onApproveEinsatz(row)"
          >
            {{ t('grossanlass.materialUebersicht.approveEinsatz') }}
          </EButton>
        </li>
      </ul>
    </section>

    <ul v-if="orders.length" class="wish-book__orders">
      <li v-for="order in orders" :key="order.id">
        <strong>{{ order.object_name }}</strong>
        <span>{{ t('grossanlass.materialUebersicht.qty', { n: order.qty }) }} · {{ order.ressort }}</span>
        <em>{{ t('grossanlass.materialUebersicht.orderNoted') }}</em>
      </li>
    </ul>

    <p v-if="tripsOnly" class="ga-trips-filter-hint">{{ t('grossanlass.materialUebersicht.tripsFilterHint') }}</p>

    <GrossanlassFahrauftragList
      v-if="tripRows.length"
      :rows="tripRows"
      :busy-id="busyTripId"
      :can-start-trip="canStartTrip"
      @toggle-packed="onTogglePacked"
      @release="onReleaseTrip"
      @issue="onIssueTrip"
    />

    <ELoadingState v-if="uebersicht.loading.value" variant="inline" :message="t('common.loading')" />
    <GrossanlassEinsatzPreviewPanel v-else-if="displayRows.length" :rows="displayRows" />
    <EEmptyState
      v-else
      :title="t('grossanlass.materialUebersicht.emptyEinsaetzeTitle')"
      :description="t('grossanlass.materialUebersicht.emptyEinsaetzeText')"
    />

    <GrossanlassEinsatzBookPreviewDialog
      v-model="dialogOpen"
      v-model:draft="draft"
      :mode="mode"
      :wishes="wishes"
      :free-picks="freePicks"
      :rows="displayRows"
      :resources="resources"
      :chauffeurs="chauffeurs"
      :places="places"
      @confirm="onConfirm"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { EButton } from '@/components/form/base'
import { gaCanApproveEinsatz } from '@/utils/grossanlassAccess'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassEinsatzPreviewPanel from '@/views/grossanlass/GrossanlassEinsatzPreviewPanel.vue'
import GrossanlassFahrauftragList from '@/views/grossanlass/GrossanlassFahrauftragList.vue'
import GrossanlassEinsatzBookPreviewDialog, {
  type GaBookPreviewDraft,
  type GaBookPreviewMode,
} from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import { gaEinsatzComposerKey } from '@/views/grossanlass/gaEinsatzComposer'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { articleToResource, zusageOccupancyBars } from '@/views/grossanlass/grossanlassZusagePreviewData'
import { resourceToPickTemplate } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import type { GaPreviewEinsatz } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { useToast } from '@/composables/useToast'

const { t, locale } = useI18n()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const { articles } = useGaCommitmentCatalog()
const uebersicht = useGaUebersicht()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const resources = computed(() => articles.value.map((article) => articleToResource(article)))
const freePicks = computed(() =>
  resources.value
    .map((resource) => {
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
const orders = computed(() => uebersicht.data.value?.orders ?? [])
const occupancy = computed(() => zusageOccupancyBars(articles.value, tr, locale.value))
const tripsOnly = computed(() => String(route.query.delivery || '') === 'trip')
const tripRows = computed(() =>
  uebersicht.bookingRows().filter(
    (row) => row.delivery === 'trip' && row.status !== 'returned',
  ),
)
const displayRows = computed(() => {
  if (tripsOnly.value) return tripRows.value
  return [...uebersicht.bookingRows(), ...occupancy.value]
})
const pendingRows = computed(() =>
  uebersicht.bookingRows().filter((row) => row.status === 'pending_approval'),
)
const canApproveEinsatz = computed(() => gaCanApproveEinsatz(authStore.currentDepartmentRole))
const busyTripId = ref<string | null>(null)

const chauffeurs = computed(() =>
  (uebersicht.data.value?.cards ?? []).map((card) => ({
    value: card.user_id,
    title: card.name,
    subtitle: card.may_drive
      ? t('grossanlass.materialUebersicht.chauffeurMayDrive')
      : t('grossanlass.materialUebersicht.chauffeurNoLicenseShort'),
    mayDrive: card.may_drive,
  })),
)
const places = computed(() => uebersicht.data.value?.places ?? [])

function canStartTrip(row: GaPreviewEinsatz): boolean {
  if (!row.destinationPlaceId || !row.chauffeurUserId) return false
  const card = (uebersicht.data.value?.cards ?? []).find((item) => item.user_id === row.chauffeurUserId)
  return !!card?.may_drive
}

const mode = ref<GaBookPreviewMode>('einsatz')
const dialogOpen = ref(false)
const draft = ref<GaBookPreviewDraft | null>(null)

function openModal(next: GaBookPreviewMode) {
  mode.value = next
  draft.value = null
  dialogOpen.value = true
}

const composer = inject(gaEinsatzComposerKey, null)
onMounted(() => {
  if (composer) composer.open = openModal
})
onBeforeUnmount(() => {
  if (composer) composer.open = () => {}
})

async function onConfirm(current: GaBookPreviewDraft) {
  try {
    await uebersicht.create({
      kind: mode.value === 'order' ? 'order' : 'einsatz',
      commitment_id: current.objectId || undefined,
      wish_line_id: current.fromWish ? current.id : null,
      qty: current.qty,
      from: current.fromIso,
      to: current.toIso,
      who: current.who,
      chauffeur_user_id: current.chauffeurUserId || null,
      delivery: current.delivery || 'pickup',
      destination_place_id: current.destinationPlaceId || null,
      group_id: current.groupId || null,
      pending: current.hasConflict,
      has_conflict: current.hasConflict,
    })
    toast.success(
      current.hasConflict
        ? t('grossanlass.materialUebersicht.mwNoteSent')
        : t('grossanlass.beschaffung.zusagen.createdToast'),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function withTrip(row: GaPreviewEinsatz, fn: () => Promise<void>) {
  busyTripId.value = row.id
  try {
    await fn()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    busyTripId.value = null
  }
}

async function onTogglePacked(row: GaPreviewEinsatz) {
  await withTrip(row, () => uebersicht.updateEinsatz(row.id, { packed: !row.packed }))
}

async function onReleaseTrip(row: GaPreviewEinsatz) {
  await withTrip(row, () => uebersicht.updateEinsatz(row.id, { trip_released: true }))
  toast.success(t('grossanlass.materialUebersicht.tripsReleasedToast'))
}

async function onIssueTrip(row: GaPreviewEinsatz) {
  await withTrip(row, () => uebersicht.issue(row.id, row.chauffeurUserId || undefined))
  toast.success(t('grossanlass.materialUebersicht.tripsIssuedToast'))
}

async function onApproveEinsatz(row: GaPreviewEinsatz) {
  await withTrip(row, () => uebersicht.updateEinsatz(row.id, { status: 'planned' }))
  toast.success(t('grossanlass.materialUebersicht.approveEinsatzToast'))
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: var(--color-text-muted, #6b7280); font-size: 0.9rem; }
.ga-approval-queue {
  margin: 0 0 16px;
  padding: 12px 14px;
  border: 1px solid #fde68a;
  border-radius: 10px;
  background: #fffbeb;
}
.ga-approval-queue h3 {
  margin: 0 0 8px;
  font-size: 0.95rem;
}
.ga-approval-queue ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.ga-approval-queue li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 0.88rem;
}
.wish-book__orders {
  list-style: none;
  margin: 0 0 16px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  display: grid;
  gap: 8px;
}
.wish-book__orders li { display: flex; flex-direction: column; gap: 2px; }
.ga-trips-filter-hint {
  margin: 0 0 12px;
  font-size: 0.85rem;
  color: #0f766e;
}
.wish-book__orders span, .wish-book__orders em { font-size: 0.8rem; color: #64748b; font-style: normal; }
</style>
