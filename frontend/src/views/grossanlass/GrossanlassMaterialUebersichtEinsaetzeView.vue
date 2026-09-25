<template>
  <div class="ga-preview-page">
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

    <v-expansion-panels
      v-if="unscheduledJobs.length"
      v-model="unscheduledOpen"
      class="e-accordions unscheduled"
    >
      <v-expansion-panel value="open">
        <v-expansion-panel-title>
          {{ t('grossanlass.planung.unscheduledTitle', { n: unscheduledJobs.length }) }}
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <p class="unscheduled__hint">{{ t('grossanlass.planung.unscheduledHint') }}</p>
          <ul class="unscheduled__list">
            <li v-for="job in unscheduledJobs" :key="job.id">
              <button type="button" class="unscheduled__row" @click="openAuftrag(job)">
                <strong>{{ job.name }}</strong>
                <span v-if="parentName(job)" class="unscheduled__meta">{{ parentName(job) }}</span>
              </button>
              <EButton variant="secondary" size="x-small" @click="openAuftrag(job)">
                {{ t('grossanlass.planung.openAuftrag') }}
              </EButton>
            </li>
          </ul>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <v-tabs v-model="calendarMode" class="materials-view-tabs einsatz-view-tabs" color="primary">
      <v-tab value="gantt">{{ t('grossanlass.planung.calTabGantt') }}</v-tab>
      <v-tab value="calendar">{{ t('grossanlass.planung.calTabCalendar') }}</v-tab>
    </v-tabs>

    <GrossanlassEinsatzProgrammCalendar
      v-if="calendarMode === 'calendar'"
      :department-id="String(route.params.departmentId || '')"
      :groups="groups"
      :trips="calendarTrips"
      @open="openCalendarBlock"
    />

    <ELoadingState v-else-if="uebersicht.loading.value" variant="inline" :message="t('common.loading')" />
    <GrossanlassEinsatzPreviewPanel
      v-else-if="resources.length || displayRows.length || groups.length"
      show-create
      :rows="displayRows"
      :resources="resources"
      :groups="groups"
      :focus-iso="calendarFocusIso"
      :focus-object-id="calendarFocusObjectId"
      :reload-key="belegungReload"
      @create="createOpen = true"
      @open-project="openProjectFromBelegung"
    />
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
      :groups="groups"
      :preset-wish-id="presetWishId"
      :preset-group-id="presetGroupId"
      :preset-place-id="presetPlaceId"
      :default-scope="bookDefaultScope"
      @confirm="onConfirm"
      @confirm-many="onConfirmMany"
      @order="onOrder"
      @place-created="uebersicht.addPlace"
    />

    <EDialog
      v-model="showProject"
      :max-width="1400"
      :title="projectTitle"
      :retain-focus="false"
      highlight-outside
    >
      <GrossanlassBauprojektPanel
        v-if="projectGroup"
        :department-id="String(route.params.departmentId || '')"
        :group-id="projectGroup.id"
        @meta-saved="onProjectMetaSaved"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="showProject = false">
          {{ t('settings.groups.close') }}
        </EButton>
      </template>
    </EDialog>

    <GrossanlassHelperAssignmentDetailDialog
      v-model="tripDetailOpen"
      :assignment="tripAssignment"
      :cards="tripCards"
      :can-toggle-packed="false"
    />

    <EDialog
      v-model="createOpen"
      :title="t('grossanlass.planung.createEntryTitle')"
      max-width="420"
    >
      <div class="create-choices">
        <EButton variant="secondary" @click="createKind('einsatz')">
          {{ t('grossanlass.planung.createEntryEinsatz') }}
        </EButton>
        <EButton variant="secondary" @click="createKind('bau')">
          {{ t('grossanlass.planung.createEntryBau') }}
        </EButton>
        <EButton variant="secondary" @click="createKind('transport')">
          {{ t('grossanlass.planung.createEntryTransport') }}
        </EButton>
      </div>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { EButton, EDialog } from '@/components/form/base'
import { gaCanApproveEinsatz, gaIsMaterialwart } from '@/utils/grossanlassAccess'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassEinsatzPreviewPanel from '@/views/grossanlass/GrossanlassEinsatzPreviewPanel.vue'
import GrossanlassEinsatzProgrammCalendar from '@/views/grossanlass/GrossanlassEinsatzProgrammCalendar.vue'
import GrossanlassBauprojektPanel from '@/components/grossanlass/GrossanlassBauprojektPanel.vue'
import GrossanlassHelperAssignmentDetailDialog from '@/views/grossanlass/GrossanlassHelperAssignmentDetailDialog.vue'
import { groupsToOrgGroups, toHelperAssignment, type GaHelperAssignment } from '@/views/grossanlass/grossanlassHelperAssignment'
import '@/styles/views/materials-view-tabs.css'
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
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import type { GaUebersichtCreatePayload } from '@/api/grossanlassUebersicht'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
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
const occupancy = computed(() => zusageOccupancyBars(articles.value, tr, locale.value))
const unscheduledOpen = ref<string | undefined>('open')
const createOpen = ref(false)
const calendarMode = ref<'gantt' | 'calendar'>('gantt')
const displayRows = computed(() => [...uebersicht.bookingRows(), ...occupancy.value])
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

const groups = ref<GrossanlassGroup[]>([])
const calendarTrips = computed(() =>
  (uebersicht.data.value?.einsaetze ?? [])
    .filter((row) => row.task_kind === 'fahrauftrag' || row.delivery === 'trip')
    .map((row) => ({
      id: row.id,
      name: row.object_name || row.who || '',
      detail: row.destination_place_name || row.who || '',
      from: row.from,
      to: row.to,
      groupId: row.group_id,
    })),
)
const unscheduledJobs = computed(() =>
  groups.value.filter((group) =>
    group.node_type === 'bauprojekt' && !group.window_start && !group.window_end,
  ),
)

function parentName(job: GrossanlassGroup): string {
  if (!job.parent_id) return ''
  return groups.value.find((group) => group.id === job.parent_id)?.name || ''
}

const showProject = ref(false)
const belegungReload = ref(0)
const projectGroup = ref<GrossanlassGroup | null>(null)
const tripDetailOpen = ref(false)
const tripAssignment = ref<GaHelperAssignment | null>(null)
const tripCards = computed(() => uebersicht.data.value?.cards ?? [])
const projectTitle = computed(() =>
  projectGroup.value
    ? t('grossanlass.planung.ressorts.projectTitle', { name: projectGroup.value.name })
    : t('grossanlass.planung.ressorts.openProject'),
)

function openProjectFromBelegung(id: string) {
  openCalendarBlock({ kind: 'bau', id })
}

function openCalendarBlock(payload: { kind: 'bau' | 'fahrt'; id: string }) {
  if (payload.kind === 'fahrt') {
    const row = (uebersicht.data.value?.einsaetze ?? []).find((item) => item.id === payload.id)
    if (!row) return
    tripAssignment.value = toHelperAssignment(row, locale.value, 'fahrauftrag', groupsToOrgGroups(groups.value))
    tripDetailOpen.value = true
    return
  }
  const group = groups.value.find((item) => item.id === payload.id)
  if (!group) return
  projectGroup.value = group
  showProject.value = true
}

function onProjectMetaSaved(group: { id: string; window_start?: string | null; window_end?: string | null; build_status?: string | null }) {
  groups.value = groups.value.map((row) => (row.id === group.id ? { ...row, ...group } : row))
  if (projectGroup.value?.id === group.id) {
    projectGroup.value = { ...projectGroup.value, ...group }
  }
}

function openAuftrag(job: GrossanlassGroup) {
  const id = String(route.params.departmentId || '')
  if (!id) return
  void router.push(`/${id}/planung/bauauftraege?project=${job.id}`)
}
const mode = ref<GaBookPreviewMode>('einsatz')
const dialogOpen = ref(false)
const draft = ref<GaBookPreviewDraft | null>(null)
const calendarFocusIso = ref<string | null>(null)
const calendarFocusObjectId = ref<string | null>(null)
const bookDefaultScope = computed(() =>
  gaIsMaterialwart(authStore.currentDepartmentRole) ? 'single' : 'project',
)
const presetWishId = computed(() => String(route.query.wish || '') || null)
const presetGroupId = computed(() => String(route.query.group || '') || null)
const presetPlaceId = computed(() => String(route.query.place || '') || null)

watch(
  [() => uebersicht.loading.value, presetWishId, presetGroupId, () => String(route.query.book || '')],
  ([loading]) => {
    if (loading || dialogOpen.value) return
    const book = String(route.query.book || '') === '1'
    if (!presetWishId.value && !presetGroupId.value && !book) return
    mode.value = 'einsatz'
    dialogOpen.value = true
    if (book) {
      const { book: _removed, ...rest } = route.query
      void router.replace({ query: rest })
    }
  },
)

function revealEinsatz(fromIso?: string, objectId?: string) {
  if (fromIso) calendarFocusIso.value = fromIso
  if (objectId) calendarFocusObjectId.value = objectId
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
    delivery: current.delivery || 'pickup',
    destination_place_id: current.destinationPlaceId || null,
    group_id: current.groupId || null,
    pending: current.hasConflict,
    has_conflict: current.hasConflict,
  }
}

function openModal(next: GaBookPreviewMode) {
  mode.value = next
  draft.value = null
  dialogOpen.value = true
}

function createKind(kind: 'einsatz' | 'bau' | 'transport') {
  createOpen.value = false
  const id = String(route.params.departmentId || '')
  if (kind === 'einsatz') {
    openModal('einsatz')
    return
  }
  if (!id) return
  void router.push(`/${id}/planung/${kind === 'bau' ? 'bauauftraege' : 'transporte'}?create=1`)
}

const composer = inject(gaEinsatzComposerKey, null)
async function softReloadBelegung() {
  const dept = String(route.params.departmentId || '')
  await uebersicht.load({ silent: true }).catch(() => {})
  if (!dept) return
  const rows = await getGrossanlassGroups(dept).catch(() => null)
  if (rows) groups.value = rows
  belegungReload.value += 1
}

let belegungTimer = 0
onMounted(() => {
  if (composer) composer.open = openModal
  const dept = String(route.params.departmentId || '')
  if (dept) {
    void getGrossanlassGroups(dept).then((rows) => { groups.value = rows }).catch(() => { groups.value = [] })
  }
  belegungTimer = window.setInterval(() => { void softReloadBelegung() }, 15000)
})
onBeforeUnmount(() => {
  if (composer) composer.open = () => {}
  if (belegungTimer) window.clearInterval(belegungTimer)
})

watch(showProject, (open) => {
  if (!open) void softReloadBelegung()
})

async function onConfirm(current: GaBookPreviewDraft) {
  const kind = current.asOrder || mode.value === 'order' ? 'order' : 'einsatz'
  try {
    await uebersicht.create(payloadFromDraft(current, kind))
    revealEinsatz(current.fromIso, current.objectId)
    toast.success(
      kind === 'order'
        ? t('grossanlass.materialUebersicht.orderNoted')
        : current.hasConflict
          ? t('grossanlass.materialUebersicht.mwNoteSent')
          : t('grossanlass.beschaffung.zusagen.createdToast'),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function onConfirmMany(drafts: GaBookPreviewDraft[]) {
  try {
    await uebersicht.createMany(drafts.map((row) => payloadFromDraft(row, 'einsatz')))
    revealEinsatz(drafts[0]?.fromIso, drafts[0]?.objectId)
    const noted = drafts.some((row) => row.hasConflict)
    toast.success(
      noted
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
.create-choices {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.unscheduled { margin: 0 0 16px; }
.unscheduled__hint { margin: 0 0 10px; color: #64748b; font-size: 0.88rem; }
.unscheduled__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.unscheduled__list li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.unscheduled__row {
  flex: 1 1 auto;
  min-width: 0;
  padding: 0;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
}
.unscheduled__meta { display: block; color: #64748b; font-size: 0.8rem; }
</style>
