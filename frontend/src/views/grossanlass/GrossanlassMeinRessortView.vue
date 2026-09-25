<template>
  <PageShell
    class="grossanlass-mein-ressort"
    :title="t('grossanlass.meinRessort.title')"
    :subtitle="pageSubtitle"
  >
    <GrossanlassMeinRessortSkeleton
      v-if="isLoading"
      :helper-view="isHelperHomeView"
      role="status"
      :aria-label="t('grossanlass.meinRessort.loading')"
    />

    <div v-else-if="error" class="mein-ressort-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</EButton>
    </div>

    <div v-else-if="showEmptyState && !isHelperHomeView" class="mein-ressort-empty-only">
      <EEmptyState
        variant="default"
        icon="mdi-home-group"
        :title="t('grossanlass.meinRessort.emptyTitle')"
        :description="emptyDescription"
      >
        <template v-if="canCreateRoot()" #actions>
          <EButton @click="openCreateRoot">{{ t('grossanlass.planung.ressorts.addAction') }}</EButton>
        </template>
      </EEmptyState>
    </div>

    <div v-else class="mein-ressort-content">
      <section v-if="isHelperHomeView" class="helper-scan-panel">
        <MaterialJourneyScanBar
          v-model="scanQuery"
          :loading="scanLoading"
          :session-log="scanSessionLog"
          :pack-target-label="scanPackTargetLabel"
          label-key="grossanlass.meinRessort.scanLabel"
          placeholder-key="grossanlass.meinRessort.scanPlaceholder"
          input-id="ga-helper-scan-input"
          @submit="submitHelperScan"
          @clear="scanQuery = ''"
          @deselect="clearScanActivePack"
        />
        <GrossanlassHelperScanResultPanel
          v-if="scanResult"
          :result="scanResult"
          :arrive-busy="scanArriveBusy"
          @close="clearScanResult"
          @arrive="arriveAtScannedPlace"
          @open-assignment="openAssignmentDetail"
        />
      </section>

      <EEmptyState
        v-if="showEmptyState"
        variant="default"
        icon="mdi-home-group"
        :title="t('grossanlass.meinRessort.emptyTitle')"
        :description="emptyDescription"
      />

      <template v-else>
      <section v-if="showPendingBoard" class="pending-board">
        <h3>{{ t('grossanlass.meinRessort.pendingTitle') }}</h3>
        <ul>
          <li v-for="row in pendingEinsaetze" :key="row.id">
            <strong>{{ row.object_name }}</strong>
            · {{ row.ressort }} · {{ t(`grossanlass.materialUebersicht.status.${row.status}`) }}
          </li>
        </ul>
      </section>

      <template v-if="isHelperHomeView">
        <div
          v-for="group in displayGroupsTree"
          :key="group.id"
          class="ressort-card ressort-card--helper"
        >
          <div class="ressort-card__head" :style="helperHeadStyle(group)">
            <GrossanlassGroupNodeIcon :node-type="group.node_type" />
            <div>
              <h3>{{ group.name }}</h3>
              <span class="kind-badge">{{ helperGroupCaption(group) }}</span>
            </div>
          </div>
        </div>
      </template>

      <section v-else-if="showRessortTree" ref="bereichTreeEl" class="bereich-tree">
        <div v-if="canCreateRoot()" class="bereich-tree__toolbar">
          <EButton variant="primary" size="small" @click="openCreateRoot">
            <v-icon icon="mdi-plus" start size="20" />
            {{ t('grossanlass.planung.ressorts.addAction') }}
          </EButton>
        </div>
        <v-expansion-panels v-model="expandedParentIds" multiple>
          <GrossanlassMeinRessortBranch
            v-for="parent in bereichRoots"
            :key="parent.id"
            :group="parent"
            :allow-self-delete="false"
          />
        </v-expansion-panels>
      </section>

      <section v-if="isHelperHomeView && homeFahrauftraege.length" class="assignments-panel">
        <div class="assignments-panel__head">
          <h3>{{ t('grossanlass.meinRessort.homeFahrauftraegeTitle') }}</h3>
          <EButton variant="secondary" size="small" @click="goToMeineEinsaetze">
            {{ t('grossanlass.meinRessort.homeAllLink') }}
          </EButton>
        </div>
        <ul class="assignments-list">
          <li v-for="row in homeFahrauftraege" :key="row.id">
            <GrossanlassHelperAssignmentRow
              :assignment="toAssignment(row, 'fahrauftrag')"
              @open="openAssignmentDetail"
            />
          </li>
        </ul>
      </section>

      <section v-if="isHelperHomeView && homeEinsaetze.length" class="assignments-panel">
        <div class="assignments-panel__head">
          <h3>{{ t('grossanlass.meinRessort.homeEinsaetzeTitle') }}</h3>
          <EButton v-if="homeFahrauftraege.length === 0" variant="secondary" size="small" @click="goToMeineEinsaetze">
            {{ t('grossanlass.meinRessort.homeAllLink') }}
          </EButton>
        </div>
        <ul class="assignments-list">
          <li v-for="row in homeEinsaetze" :key="row.id">
            <GrossanlassHelperAssignmentRow
              :assignment="toAssignment(row, assignmentKindFor(row))"
              @open="openAssignmentDetail"
            />
          </li>
        </ul>
      </section>

      <div v-if="showKostenPanel" class="kosten-panel">
        <h3>{{ t('grossanlass.beschaffung.kosten.linesTitle') }}</h3>
        <p class="kosten-rahmen">
          {{ t('grossanlass.meinRessort.rahmenSaved') }}:
          {{ ownRahmenAmount == null ? '—' : formatChf(ownRahmenAmount) }}
          · {{ t('grossanlass.meinRessort.nettoIst') }}:
          {{ formatChf(ownNetto) }}
        </p>
        <div v-for="row in costRows" :key="row.id" class="wish-mini-row">
          <span class="wish-label">{{ row.label }} · {{ t(`grossanlass.beschaffung.kosten.kind.${row.cost_kind}`) }}</span>
          <span class="wish-meta">{{ formatChf(row.netto_chf) }} {{ t('grossanlass.beschaffung.kosten.statNetto') }}</span>
        </div>
        <p v-if="costRows.length === 0" class="no-wishes">{{ t('grossanlass.meinRessort.noCostsYet') }}</p>
      </div>

      </template>
    </div>

    <GrossanlassHelperAssignmentDetailDialog
      v-if="isHelperHomeView"
      v-model="assignmentDetailOpen"
      :assignment="selectedAssignment"
      :cards="helperDetailCards"
      :busy="assignmentBusyId === selectedAssignment?.id"
      :can-toggle-packed="canTogglePackedSelected"
      @toggle-packed="onTogglePackedAssignment"
    />

    <EDialog
      v-if="showRessortTree"
      v-model="showCreateProject"
      :title="createModalTitle"
      :max-width="groupDialogMaxWidth"
      scrollable
    >
      <ESelect
        v-if="showEditKindSelect"
        v-model="createForm.kind"
        :items="childKindSelectItems"
        :label="t('grossanlass.planung.ressorts.childKindLabel')"
        hide-details
      />
      <ETextField
        v-model="createForm.name"
        :label="createNameLabel"
        :placeholder="createNamePlaceholder"
        hide-details="auto"
      />
      <ESelect
        v-if="showEditParentSelect"
        v-model="createForm.parent_id"
        :items="editParentSelectItems"
        :label="t('grossanlass.planung.ressorts.parentLabel')"
        hide-details
      />
      <GaBuildMetaFields
        v-if="showEditUsageWindow"
        :department-id="departmentId"
        :autosave="!!editingGroup"
        v-model:start="createForm.window_start"
        v-model:end="createForm.window_end"
        v-model:status="createForm.build_status"
        :window-label="showEditProjectWindow
          ? t('grossanlass.planung.ressorts.windowLabel')
          : t('grossanlass.planung.ressorts.usageWindowLabel')"
        :window-hint="showEditProjectWindow
          ? ''
          : t('grossanlass.planung.ressorts.usageWindowHint')"
        :window-baseline="usageWindowBaseline"
        :status-baseline="buildStatusBaseline"
        hint-class="group-modal-map__hint"
        :save-window="saveUsageWindowAutosave"
        :save-status="saveBuildStatusAutosave"
      />
      <ETextarea
        v-if="showEditDescription"
        v-model="createForm.description"
        :label="t('grossanlass.planung.ressorts.descriptionHeading')"
        :placeholder="t('grossanlass.planung.ressorts.descriptionPlaceholder')"
        rows="4"
        hide-details="auto"
      />
      <ESwitch
        v-if="showEditAreaToggle"
        v-model="createForm.include_on_map"
        :label="t('grossanlass.planung.ressorts.includeOnMap')"
        :hint="t('grossanlass.planung.ressorts.includeOnMapHint')"
        persistent-hint
        hide-details="auto"
      />
      <div v-if="showEditProjectWindow || showEditAreaMap" class="group-modal-map">
        <p v-if="!showEditAreaMap" class="group-modal-map__hint">
          {{ t('grossanlass.planung.ressorts.mapHint') }}
        </p>
        <ActivityVenueOverviewBlock
          v-if="venueAddressId"
          ref="groupMapRef"
          :venue-address-id="venueAddressId"
          :department-id="departmentId"
          :ga-department-id="departmentId"
          ga-map-mode="all"
          inline-place-draft
          :draft-place-name="createForm.name"
          :draft-place-kind="showEditAreaMap ? 'area' : 'bauprojekt'"
          hide-title
          @save-area="saveAreaKeepOpen"
        />
        <p v-else class="group-modal-map__missing">{{ t('grossanlass.planung.ressorts.placeMissing') }}</p>
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeGroupDialog">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!createForm.name.trim() || (!editingGroup && !createAsRoot && !createForm.parent_id) || createSaving"
          :loading="createSaving"
          @click="submitCreateChild"
        >
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-if="showRessortTree"
      v-model="showShareDialog"
      :max-width="560"
      :title="t('grossanlass.planung.ressorts.shareTitle')"
      scrollable
    >
      <p class="muted">{{ t('grossanlass.planung.ressorts.shareHint') }}</p>
      <p v-if="shareGroup" class="share-source">{{ shareGroup.name }}</p>
      <ul v-if="shareGroup?.shared_with?.length" class="share-list">
        <li v-for="row in shareGroup.shared_with" :key="row.id">
          <span>{{ row.target_name }}</span>
          <button type="button" class="action-btn action-btn-danger" @click="removeShare(row.id)">
            {{ t('grossanlass.planung.ressorts.unshare') }}
          </button>
        </li>
      </ul>
      <p class="share-tree-label">{{ t('grossanlass.planung.ressorts.shareTarget') }}</p>
      <div v-if="shareTargetTree.length" class="share-tree">
        <GrossanlassShareTargetTree
          v-model="shareTargetId"
          v-model:creating-parent-id="shareCreateParentId"
          v-model:create-name="shareCreateName"
          :nodes="shareTargetTree"
          :create-saving="shareCreateSaving"
          :can-create-root="canCreateRoot()"
          @create="submitShareCreate"
        />
      </div>
      <p v-else class="muted">{{ t('grossanlass.planung.ressorts.shareEmptyTargets') }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="showShareDialog = false">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!shareTargetId || shareSaving"
          :loading="shareSaving"
          @click="submitShare"
        >
          {{ t('grossanlass.planung.ressorts.shareSubmit') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-if="showRessortTree"
      v-model="showProjectModal"
      :max-width="920"
      :title="projectModalTitle"
      :retain-focus="false"
      highlight-outside
    >
      <GrossanlassBauprojektPanel
        v-if="projectGroup && departmentId"
        :department-id="departmentId"
        :group-id="projectGroup.id"
        @meta-saved="onProjectMetaSaved"
      />
      <template #actions>
        <EButton
          v-if="projectGroup && canShareGroup(projectGroup)"
          variant="secondary"
          size="small"
          @click="openShare(projectGroup)"
        >
          {{ t('grossanlass.planung.ressorts.shareWith') }}
        </EButton>
        <EButton
          v-if="projectGroup && canDeleteGroup(projectGroup)"
          variant="danger"
          size="small"
          :loading="deletingGroupId === projectGroup.id"
          @click="confirmDeleteGroup(projectGroup)"
        >
          {{ t('common.delete') }}
        </EButton>
        <EButton variant="secondary" size="small" @click="showProjectModal = false">
          {{ t('settings.groups.close') }}
        </EButton>
      </template>
    </EDialog>

    <GrossanlassEinsatzBookPreviewDialog
      v-if="showEinsatzTools"
      v-model="submitOpen"
      v-model:draft="submitDraft"
      mode="einsatz"
      :wishes="wishPicks"
      :free-picks="freePicks"
      :chauffeurs="submitChauffeurs"
      :places="submitBoard?.places ?? []"
      :groups="groups"
      :preset-wish-id="presetWishId"
      :preset-group-id="presetGroupId"
      :preset-place-id="presetPlaceId"
      default-scope="project"
      @confirm="onSubmitEinsatz"
      @confirm-many="onSubmitMany"
      @order="onSubmitOrder"
      @place-created="onPlaceCreated"
    />
  </PageShell>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, provide, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassMeinRessortSkeleton from '@/views/grossanlass/GrossanlassMeinRessortSkeleton.vue'
import MaterialJourneyScanBar from '@/components/activities/materialJourney/MaterialJourneyScanBar.vue'
import GrossanlassHelperScanResultPanel from '@/views/grossanlass/GrossanlassHelperScanResultPanel.vue'
import { useGrossanlassHelperScan } from '@/composables/useGrossanlassHelperScan'
import { EButton, EDialog, ESelect, ESwitch, ETextField, ETextarea } from '@/components/form/base'
import ActivityVenueOverviewBlock from '@/components/activities/ActivityVenueOverviewBlock.vue'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { gaCanApproveEinsatz, gaIsHelperHomeView } from '@/utils/grossanlassAccess'
import {
  packBauprojektWindow,
  unpackBauprojektWindow,
} from '@/utils/grossanlassBauprojektWindow'
import type { AutoSaveFieldValue } from '@/components/common/autoSave/types'
import GrossanlassBauprojektPanel from '@/components/grossanlass/GrossanlassBauprojektPanel.vue'
import GaBuildMetaFields from '@/components/grossanlass/GaBuildMetaFields.vue'
import GrossanlassMeinRessortBranch from '@/components/grossanlass/GrossanlassMeinRessortBranch.vue'
import GrossanlassShareTargetTree, {
  type ShareTargetNode,
} from '@/components/grossanlass/GrossanlassShareTargetTree.vue'
import GrossanlassGroupNodeIcon from '@/components/grossanlass/GrossanlassGroupNodeIcon.vue'
import { MEIN_RESSORT_TREE_KEY } from '@/components/grossanlass/meinRessortTree'
import {
  createGrossanlassGroup,
  deleteGrossanlassGroup,
  getGrossanlassGroups,
  updateGrossanlassGroup,
  shareGrossanlassGroup,
  unshareGrossanlassGroup,
  type GrossanlassGroup,
  type GrossanlassGroupKind,
} from '@/api/grossanlassGroups'
import { getMyRessortWishes, type GrossanlassWishLine } from '@/api/grossanlassWishes'
import {
  formatChf,
  listGrossanlassBudgets,
  listGrossanlassCosts,
  listGrossanlassProcurementLines,
  type GrossanlassBudget,
  type GrossanlassCost,
  type GrossanlassProcurementLine,
} from '@/api/grossanlassProcurement'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import {
  flattenGrossanlassGroupsFiltered,
  nestTreeWithLevel,
  type NestedTreeNode,
} from '@/utils/grossanlassGroupHierarchy'
import { isEinsatzBookableWish } from '@/utils/grossanlassBookProjectPicker'
import { grossanlassGroupNodeKindKey } from '@/utils/grossanlassGroupNode'
import GrossanlassEinsatzBookPreviewDialog, {
  type GaBookPreviewDraft,
} from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import {
  createGrossanlassEinsatz,
  getGrossanlassMyEinsaetze,
  getGrossanlassSubmitBoard,
  updateGrossanlassEinsatz,
  type GaMyEinsaetzePayload,
  type GaSubmitBoard,
  type GaUebersichtEinsatz,
} from '@/api/grossanlassUebersicht'
import type { GrossanlassUserCard } from '@/api/grossanlassUserCards'
import GrossanlassHelperAssignmentDetailDialog from '@/views/grossanlass/GrossanlassHelperAssignmentDetailDialog.vue'
import GrossanlassHelperAssignmentRow from '@/views/grossanlass/GrossanlassHelperAssignmentRow.vue'
import {
  toHelperAssignment,
  groupsToOrgGroups,
  type GaHelperAssignment,
  type GaHelperTaskKind,
} from '@/views/grossanlass/grossanlassHelperAssignment'
import { updateGrossanlassPlace, type GaPlace } from '@/api/grossanlassLogistics'
import { getGrossanlassPlanung } from '@/api/grossanlassPlanung'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const departmentId = computed(() => String(route.params.departmentId || ''))

const groups = ref<GrossanlassGroup[]>([])
const wishes = ref<GrossanlassWishLine[]>([])
const costRows = ref<GrossanlassCost[]>([])
const budgets = ref<GrossanlassBudget[]>([])
const directLines = ref<GrossanlassProcurementLine[]>([])
const isLoading = ref(true)
const error = ref('')
const submitOpen = ref(false)
const submitDraft = ref<GaBookPreviewDraft | null>(null)
const submitBoard = ref<GaSubmitBoard | null>(null)
const presetWishId = computed(() => String(route.query.wish || '') || null)
const presetGroupId = computed(() => String(route.query.group || '') || null)
const presetPlaceId = computed(() => String(route.query.place || '') || null)
const myEinsaetze = ref<GaMyEinsaetzePayload | null>(null)
const assignmentDetailOpen = ref(false)
const selectedAssignment = ref<GaHelperAssignment | null>(null)
const assignmentBusyId = ref<string | null>(null)

const groupsRef = computed(() => groups.value)
const helperOrgGroups = computed(() => groupsToOrgGroups(groups.value))
const {
  isInAssignedRessortBranch,
  isLeaderOfGroup,
  isSharedIntoGroup,
  canCreateChild,
  canCreateRoot,
  canEditGroup,
  canDeleteGroup,
  canShareGroup,
  canManageStruktur,
  isBereichsleitung,
} = useGrossanlassRessortScope(groupsRef)

const showCreateProject = ref(false)
const createAsRoot = ref(false)
const editingGroup = ref<GrossanlassGroup | null>(null)
const bereichTreeEl = ref<HTMLElement | null>(null)
const accordionWidth = ref<number | null>(null)
const venueAddressId = ref<string | null>(null)
const groupMapRef = ref<InstanceType<typeof ActivityVenueOverviewBlock> | null>(null)
const showShareDialog = ref(false)
const shareGroup = ref<GrossanlassGroup | null>(null)
const shareTargetId = ref('')
const shareSaving = ref(false)
const shareCreateParentId = ref<string | null>(null)
const shareCreateName = ref('')
const shareCreateSaving = ref(false)
const showProjectModal = ref(false)
const createSaving = ref(false)
const deletingGroupId = ref<string | null>(null)
const expandedParentIds = ref<string[]>([])
const projectGroup = ref<GrossanlassGroup | null>(null)
const createForm = ref({
  name: '',
  parent_id: '' as string,
  kind: 'teilbereich' as GrossanlassGroupKind,
  window_start: '',
  window_end: '',
  build_status: '',
  description: '',
  include_on_map: false,
})

const childKindSelectItems = computed(() => [
  {
    title: t('grossanlass.planung.ressorts.kindUnterressort'),
    value: 'ressort' as GrossanlassGroupKind,
  },
  {
    title: t('grossanlass.planung.ressorts.kindBauprojekt'),
    value: 'teilbereich' as GrossanlassGroupKind,
  },
])

const createModalTitle = computed(() => {
  if (editingGroup.value) {
    return t('grossanlass.meinRessort.editTitle', { name: editingGroup.value.name })
  }
  if (createAsRoot.value) return t('grossanlass.planung.ressorts.modalNewRessort')
  return createForm.value.kind === 'ressort'
    ? t('grossanlass.planung.ressorts.modalNewUnterressort')
    : t('grossanlass.planung.ressorts.modalNewBauprojekt')
})
const createNameLabel = computed(() => {
  if (createAsRoot.value || (editingGroup.value && !editingGroup.value.parent_id && !createForm.value.parent_id)) {
    return t('grossanlass.planung.ressorts.nameLabelRessort')
  }
  return createForm.value.kind === 'ressort'
    ? t('grossanlass.planung.ressorts.nameLabelUnterressort')
    : t('grossanlass.planung.ressorts.nameLabelBauprojekt')
})
const createNamePlaceholder = computed(() => {
  if (createAsRoot.value || (editingGroup.value && !editingGroup.value.parent_id && !createForm.value.parent_id)) {
    return t('grossanlass.planung.ressorts.namePlaceholderRessort')
  }
  return createForm.value.kind === 'ressort'
    ? t('grossanlass.planung.ressorts.namePlaceholderUnterressort')
    : t('grossanlass.planung.ressorts.namePlaceholderBauprojekt')
})

const createParentOptions = computed(() =>
  myGroupsTree.value.filter((group) => group.node_type !== 'bauprojekt' && canCreateChild(group)),
)
const showEditKindSelect = computed(() => {
  if (editingGroup.value) return !!editingGroup.value.parent_id
  return !createAsRoot.value
})
const showEditParentSelect = computed(() => {
  if (editingGroup.value) return canManageStruktur.value
  return !createAsRoot.value
})
const showEditProjectWindow = computed(() => {
  if (createForm.value.kind === 'teilbereich') return true
  return editingGroup.value?.node_type === 'bauprojekt'
})
const showEditBereichWindow = computed(() => {
  if (showEditProjectWindow.value) return false
  if (createAsRoot.value && !createForm.value.parent_id) return false
  if (editingGroup.value && !editingGroup.value.parent_id && !createForm.value.parent_id) return false
  return createForm.value.kind === 'ressort' || editingGroup.value?.node_type === 'unterressort'
})
const showEditUsageWindow = computed(() => showEditProjectWindow.value || showEditBereichWindow.value)
const usageWindowBaseline = ref('|')
const buildStatusBaseline = ref('')
const showEditDescription = computed(() => !!editingGroup.value || showEditProjectWindow.value)
const showEditAreaToggle = computed(() => !showEditProjectWindow.value)
const showEditAreaMap = computed(() => showEditAreaToggle.value && createForm.value.include_on_map)
const groupDialogMaxWidth = computed(() => {
  if (editingGroup.value) {
    return accordionWidth.value ?? 1400
  }
  return showEditProjectWindow.value || showEditAreaMap.value ? 920 : 720
})

function syncAccordionWidth() {
  const width = bereichTreeEl.value?.getBoundingClientRect().width
  accordionWidth.value = width && width >= 480 ? Math.round(width) : null
}
const editParentSelectItems = computed(() => {
  const blocked = editingGroup.value ? collectIdsFrom(editingGroup.value.id) : new Set<string>()
  const items = createParentOptions.value
    .filter((group) => !blocked.has(group.id))
    .map((group) => ({ title: group.name, value: group.id }))
  if (editingGroup.value && canManageStruktur.value) {
    return [{ title: t('grossanlass.planung.ressorts.parentNone'), value: '' }, ...items]
  }
  return items
})
const projectModalTitle = computed(() =>
  projectGroup.value
    ? t('grossanlass.planung.ressorts.projectTitle', { name: projectGroup.value.name })
    : t('grossanlass.planung.ressorts.openProject'),
)

function emptyCreateForm(kind: GrossanlassGroupKind, parentId = ''): typeof createForm.value {
  usageWindowBaseline.value = '|'
  buildStatusBaseline.value = ''
  return {
    name: '',
    parent_id: parentId,
    kind,
    window_start: '',
    window_end: '',
    build_status: '',
    description: '',
    include_on_map: false,
  }
}

function closeGroupDialog() {
  groupMapRef.value?.clearInlineDraft()
  showCreateProject.value = false
  editingGroup.value = null
  createAsRoot.value = false
}

function openCreateRoot() {
  editingGroup.value = null
  createAsRoot.value = true
  createForm.value = emptyCreateForm('ressort')
  showCreateProject.value = true
}

function openCreateChild(kind: GrossanlassGroupKind, parentId?: string) {
  editingGroup.value = null
  createAsRoot.value = false
  createForm.value = emptyCreateForm(kind, parentId || createParentOptions.value[0]?.id || '')
  showCreateProject.value = true
}

function openProject(group: GrossanlassGroup) {
  projectGroup.value = group
  showProjectModal.value = true
}

async function confirmDeleteGroup(group: GrossanlassGroup) {
  if (!departmentId.value || deletingGroupId.value) return
  const isProject = group.node_type === 'bauprojekt'
  const ok = await confirm.confirm({
    title: isProject
      ? t('grossanlass.planung.ressorts.deleteBauprojektTitle')
      : t('grossanlass.planung.ressorts.deleteUnterressortTitle'),
    message: isProject
      ? t('grossanlass.planung.ressorts.deleteBauprojektMessage', { name: group.name })
      : t('grossanlass.planung.ressorts.deleteUnterressortMessage', { name: group.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  deletingGroupId.value = group.id
  try {
    await deleteGrossanlassGroup(departmentId.value, group.id)
    if (projectGroup.value?.id === group.id) {
      showProjectModal.value = false
      projectGroup.value = null
    }
    groups.value = await getGrossanlassGroups(departmentId.value)
    wishes.value = await getMyRessortWishes(departmentId.value)
    await loadDirectLines()
    toast.success(
      isProject
        ? t('grossanlass.meinRessort.bauprojektDeleted')
        : t('grossanlass.meinRessort.unterressortDeleted'),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorDelete'))
  } finally {
    deletingGroupId.value = null
  }
}

async function resumeGroupMapAfterSave() {
  if (!showEditProjectWindow.value && !showEditAreaMap.value) return
  await nextTick()
  await groupMapRef.value?.reloadGa?.()
  await nextTick()
  const placeId = editingGroup.value?.place?.id ?? null
  groupMapRef.value?.finishInlineDraw(placeId)
}

async function persistGroup(closeAfter: boolean) {
  if (!departmentId.value || !createForm.value.name.trim() || createSaving.value) {
    return
  }
  const asRoot = createAsRoot.value || (editingGroup.value ? !createForm.value.parent_id : false)
  if (asRoot && !canCreateRoot() && !editingGroup.value) return
  if (!asRoot && !createForm.value.parent_id && !editingGroup.value) return
  const kind = asRoot ? 'ressort' : createForm.value.kind
  const includeOnMap = showEditAreaToggle.value && createForm.value.include_on_map
  const polygon = includeOnMap ? groupMapRef.value?.getInlineDraftPolygon() ?? [] : null
  createSaving.value = true
  try {
    const payload = {
      name: createForm.value.name.trim(),
      parent_id: asRoot ? null : createForm.value.parent_id,
      kind,
      window_start: showEditUsageWindow.value ? (createForm.value.window_start || null) : null,
      window_end: showEditUsageWindow.value ? (createForm.value.window_end || null) : null,
      build_status: showEditUsageWindow.value ? (createForm.value.build_status || null) : null,
      description: createForm.value.description.trim() || null,
      include_on_map: showEditAreaToggle.value ? includeOnMap : undefined,
      polygon: includeOnMap ? polygon : undefined,
    }
    const wasEditing = !!editingGroup.value
    let saved: GrossanlassGroup
    if (editingGroup.value) {
      saved = await updateGrossanlassGroup(departmentId.value, editingGroup.value.id, {
        ...payload,
        kind: editingGroup.value.parent_id || createForm.value.parent_id ? kind : undefined,
      })
      if (showEditProjectWindow.value) {
        await syncGroupPlaceCoords(saved)
      }
    } else {
      saved = await createGrossanlassGroup(departmentId.value, payload)
    }
    groups.value = await getGrossanlassGroups(departmentId.value)
    const refreshed = groups.value.find((row) => row.id === saved.id) ?? saved
    if (closeAfter) {
      closeGroupDialog()
      if (wasEditing) {
        toast.success(t('grossanlass.meinRessort.ressortUpdated'))
      } else if (kind === 'teilbereich') {
        toast.success(t('grossanlass.meinRessort.bauprojektCreated'))
        openProject(saved)
      } else if (asRoot) {
        toast.success(t('grossanlass.meinRessort.ressortCreated'))
      } else {
        toast.success(t('grossanlass.meinRessort.unterressortCreated'))
      }
      return
    }
    editingGroup.value = refreshed
    createAsRoot.value = !refreshed.parent_id
    toast.success(
      includeOnMap
        ? t('grossanlass.planung.ressorts.areaSaved')
        : t('grossanlass.meinRessort.ressortUpdated'),
    )
    await resumeGroupMapAfterSave()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    createSaving.value = false
  }
}

function patchLocalGroup(saved: GrossanlassGroup) {
  groups.value = groups.value.map((row) => (row.id === saved.id ? { ...row, ...saved } : row))
  if (editingGroup.value?.id === saved.id) {
    editingGroup.value = { ...editingGroup.value, ...saved }
  }
  if (projectGroup.value?.id === saved.id) {
    projectGroup.value = { ...projectGroup.value, ...saved }
  }
}

async function persistUsageMeta() {
  if (!departmentId.value || !editingGroup.value || !showEditUsageWindow.value) return
  const saved = await updateGrossanlassGroup(departmentId.value, editingGroup.value.id, {
    window_start: createForm.value.window_start || null,
    window_end: createForm.value.window_end || null,
    build_status: createForm.value.build_status || null,
  })
  patchLocalGroup(saved)
  usageWindowBaseline.value = packBauprojektWindow(saved.window_start, saved.window_end)
  buildStatusBaseline.value = saved.build_status || ''
}

async function saveUsageWindowAutosave(value: AutoSaveFieldValue) {
  const next = unpackBauprojektWindow(value)
  createForm.value.window_start = next.start
  createForm.value.window_end = next.end
  await persistUsageMeta()
}

async function saveBuildStatusAutosave(value: AutoSaveFieldValue) {
  createForm.value.build_status = value == null ? '' : String(value)
  await persistUsageMeta()
}

function onProjectMetaSaved(group: { id: string; window_start?: string | null; window_end?: string | null; build_status?: string | null }) {
  groups.value = groups.value.map((row) => (row.id === group.id ? { ...row, ...group } : row))
  if (editingGroup.value?.id === group.id) {
    editingGroup.value = { ...editingGroup.value, ...group }
  }
  if (projectGroup.value?.id === group.id) {
    projectGroup.value = { ...projectGroup.value, ...group }
  }
}

function submitCreateChild() {
  return persistGroup(true)
}

function saveAreaKeepOpen() {
  return persistGroup(false)
}

const isHelperHomeView = computed(() =>
  gaIsHelperHomeView(authStore.currentDepartmentRole, isBereichsleitung.value),
)

/** BL: eigener Ast. OK/MW/CMW: derselbe Baum, anlassweit. */
const showRessortTree = computed(
  () => !isHelperHomeView.value && (isBereichsleitung.value || canManageStruktur.value),
)

const showEinsatzTools = computed(
  () => isBereichsleitung.value || canManageStruktur.value,
)

async function reloadMyEinsaetze() {
  if (!departmentId.value || !isHelperHomeView.value) return
  myEinsaetze.value = await getGrossanlassMyEinsaetze(departmentId.value)
}

const {
  scanQuery,
  scanLoading,
  arriveBusy: scanArriveBusy,
  sessionLog: scanSessionLog,
  scanResult,
  scanCards,
  packTargetLabel: scanPackTargetLabel,
  submit: submitHelperScan,
  arriveAtScannedPlace,
  clearActivePackSelection: clearScanActivePack,
  clearScanResult,
} = useGrossanlassHelperScan({
  departmentId: () => departmentId.value,
  locale: () => locale.value,
  orgGroups: () => helperOrgGroups.value,
  onChanged: reloadMyEinsaetze,
})

const helperDetailCards = computed(() => {
  const byId = new Map<string, GrossanlassUserCard>()
  for (const card of myEinsaetze.value?.cards ?? []) {
    byId.set(card.user_id, card)
  }
  for (const card of scanCards.value) {
    if (!byId.has(card.user_id)) byId.set(card.user_id, card)
  }
  return [...byId.values()]
})

const pageSubtitle = computed(() => {
  if (isHelperHomeView.value) return t('grossanlass.meinRessort.subtitleHelper')
  if (canManageStruktur.value && !isBereichsleitung.value) {
    return t('grossanlass.meinRessort.subtitleOk')
  }
  return t('grossanlass.meinRessort.subtitle')
})

const emptyDescription = computed(() => {
  if (isHelperHomeView.value) return t('grossanlass.meinRessort.emptyDescriptionHelper')
  if (canManageStruktur.value) return t('grossanlass.meinRessort.emptyDescriptionOk')
  return t('grossanlass.meinRessort.emptyDescription')
})

const showKostenPanel = computed(
  () => !isHelperHomeView.value && !showRessortTree.value,
)

const homeFahrauftraege = computed(() => myEinsaetze.value?.fahrauftraege ?? [])

const homeEinsaetze = computed(() => [
  ...(myEinsaetze.value?.einsaetze ?? []),
  ...(myEinsaetze.value?.bauauftraege ?? []),
])

const hasHelperAssignments = computed(
  () => homeFahrauftraege.value.length > 0 || homeEinsaetze.value.length > 0,
)

const showEmptyState = computed(() => {
  if (displayGroupsTree.value.length > 0) return false
  if (isHelperHomeView.value && hasHelperAssignments.value) return false
  return true
})

const canTogglePackedSelected = computed(() => {
  const row = selectedAssignment.value
  if (!row || row.taskKind !== 'fahrauftrag') return false
  if (row.operable === false) return false
  if (row.chauffeurUserId !== authStore.userId) return false
  return row.status !== 'issued'
})

function toAssignment(row: GaUebersichtEinsatz, taskKind: GaHelperTaskKind): GaHelperAssignment {
  return toHelperAssignment(row, locale.value, taskKind, helperOrgGroups.value)
}

function assignmentKindFor(row: GaUebersichtEinsatz): GaHelperTaskKind {
  if (row.task_kind === 'bauauftrag') return 'bauauftrag'
  return 'einsatz'
}

function openAssignmentDetail(assignment: GaHelperAssignment) {
  selectedAssignment.value = assignment
  assignmentDetailOpen.value = true
}

async function onTogglePackedAssignment(assignment: GaHelperAssignment) {
  if (!departmentId.value || assignmentBusyId.value) return
  assignmentBusyId.value = assignment.id
  try {
    const result = await updateGrossanlassEinsatz(departmentId.value, assignment.id, {
      packed: !assignment.packed,
    })
    if ('fahrauftraege' in result) {
      myEinsaetze.value = result
      const updated = [...result.fahrauftraege, ...result.einsaetze, ...result.bauauftraege].find(
        (row) => row.id === assignment.id,
      )
      if (updated) {
        selectedAssignment.value = toHelperAssignment(updated, locale.value, assignment.taskKind, helperOrgGroups.value)
      }
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.meineEinsaetze.errorUpdate'))
  } finally {
    assignmentBusyId.value = null
  }
}

const myGroupsTree = computed(() =>
  flattenGrossanlassGroupsFiltered(groups.value, (g) => isInAssignedRessortBranch(g)),
)

const helperMemberGroups = computed(() => {
  const userId = authStore.userId
  if (!userId) return []
  return myGroupsTree.value.filter((group) =>
    group.members?.some((member) => member.user_id === userId),
  )
})

const displayGroupsTree = computed(() =>
  isHelperHomeView.value ? helperMemberGroups.value : myGroupsTree.value,
)

const bereichRoots = computed(() =>
  myGroupsTree.value.filter((group) => {
    if (group.node_type === 'bauprojekt') return false
    if (!group.parent_id) return true
    const parent = myGroupsTree.value.find((row) => row.id === group.parent_id)
    return !parent || parent.node_type === 'bauprojekt'
  }),
)

watch(
  bereichRoots,
  (roots) => {
    const ids = roots.map((parent) => parent.id)
    const kept = expandedParentIds.value.filter((id) => ids.includes(id))
    expandedParentIds.value = kept.length > 0 ? kept : ids
  },
  { immediate: true },
)

function uniqueGroups(rows: GrossanlassGroup[]): GrossanlassGroup[] {
  const seen = new Set<string>()
  return rows.filter((row) => {
    if (seen.has(row.id)) return false
    seen.add(row.id)
    return true
  })
}

function unterressortsOf(parent: GrossanlassGroup): GrossanlassGroup[] {
  return uniqueGroups(myGroupsTree.value.filter((group) => {
    if (group.node_type === 'bauprojekt') return false
    return group.parent_id === parent.id || isSharedIntoGroup(group, parent)
  }))
}

function bauprojekteOf(parent: GrossanlassGroup): GrossanlassGroup[] {
  return uniqueGroups(myGroupsTree.value.filter((group) => {
    if (group.node_type !== 'bauprojekt') return false
    return group.parent_id === parent.id || isSharedIntoGroup(group, parent)
  }))
}

function collectIdsFrom(rootId: string): Set<string> {
  const ids = new Set<string>([rootId])
  const queue = [rootId]
  while (queue.length) {
    const id = queue.shift()!
    for (const child of groups.value) {
      if (child.parent_id === id && !ids.has(child.id)) {
        ids.add(child.id)
        queue.push(child.id)
      }
    }
  }
  return ids
}

function collectAncestorIds(groupId: string): Set<string> {
  const ids = new Set<string>()
  let current = groups.value.find((row) => row.id === groupId)
  const seen = new Set<string>()
  while (current?.parent_id && !seen.has(current.id)) {
    seen.add(current.id)
    ids.add(current.parent_id)
    current = groups.value.find((row) => row.id === current!.parent_id)
  }
  return ids
}

const shareTargetTree = computed((): ShareTargetNode[] => {
  const source = shareGroup.value
  if (!source) return []
  const blocked = new Set([...collectIdsFrom(source.id), ...collectAncestorIds(source.id)])
  const already = new Set((source.shared_with ?? []).map((row) => row.target_group_id))
  const orgs = groups.value.filter((group) => group.node_type !== 'bauprojekt')

  function toNode(group: NestedTreeNode<GrossanlassGroup>): ShareTargetNode | null {
    const children = group.children.map(toNode).filter((node): node is ShareTargetNode => node !== null)
    const selectable = !blocked.has(group.id) && !already.has(group.id)
    const canCreate = canCreateChild(group)
    if (!selectable && children.length === 0 && !canCreate) return null
    return {
      id: group.id,
      name: group.name,
      kind: kindLabel(group),
      nodeType: group.node_type,
      selectable,
      canCreate,
      children,
    }
  }

  return nestTreeWithLevel(orgs).map(toNode).filter((node): node is ShareTargetNode => node !== null)
})

function openEditGroup(group: GrossanlassGroup) {
  editingGroup.value = group
  createAsRoot.value = !group.parent_id
  createForm.value = {
    name: group.name,
    parent_id: group.parent_id || '',
    kind: group.kind,
    window_start: group.window_start || '',
    window_end: group.window_end || '',
    build_status: group.build_status || '',
    description: group.description || '',
    include_on_map: group.include_on_map === true || group.place?.kind === 'area',
  }
  usageWindowBaseline.value = packBauprojektWindow(group.window_start, group.window_end)
  buildStatusBaseline.value = group.build_status || ''
  showCreateProject.value = true
  void syncGroupMapPlacement()
}

async function loadVenueAddress() {
  if (!departmentId.value) {
    venueAddressId.value = null
    return
  }
  try {
    const pack = await getGrossanlassPlanung(departmentId.value)
    venueAddressId.value = pack.config.venue_address_id || null
  } catch {
    venueAddressId.value = null
  }
}

async function syncGroupMapPlacement() {
  if (!showCreateProject.value || !venueAddressId.value) return
  if (!showEditProjectWindow.value && !showEditAreaMap.value) return
  await nextTick()
  await groupMapRef.value?.reloadGa?.()
  await nextTick()
  groupMapRef.value?.refreshMaps?.()
  if (showEditAreaMap.value) {
    if (editingGroup.value?.place?.kind === 'area' && editingGroup.value.place.id) {
      groupMapRef.value?.beginEditPlace(editingGroup.value.place.id)
      return
    }
    groupMapRef.value?.beginInlineDraft(createForm.value.name, 'area')
    return
  }
  if (editingGroup.value?.place?.id) {
    groupMapRef.value?.beginEditPlace(editingGroup.value.place.id)
    return
  }
  if (createForm.value.kind === 'teilbereich') {
    groupMapRef.value?.beginInlineDraft(createForm.value.name, 'bauprojekt')
  }
}

async function syncGroupPlaceCoords(group: GrossanlassGroup | null) {
  if (!departmentId.value || !group?.place?.id) return
  const coords = groupMapRef.value?.getInlineDraftCoords()
  if (coords?.latitude == null || coords?.longitude == null) return
  try {
    await updateGrossanlassPlace(departmentId.value, group.place.id, {
      latitude: coords.latitude,
      longitude: coords.longitude,
    })
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.einstellungen.mapMoveError'))
  }
}

function openShare(group: GrossanlassGroup) {
  shareGroup.value = group
  shareTargetId.value = ''
  shareCreateParentId.value = null
  shareCreateName.value = ''
  showProjectModal.value = false
  showShareDialog.value = true
}

async function submitShareCreate() {
  const parentId = shareCreateParentId.value
  const name = shareCreateName.value.trim()
  const asRoot = parentId === ''
  if (!departmentId.value || parentId === null || !name || shareCreateSaving.value) return
  if (asRoot && !canCreateRoot()) return
  shareCreateSaving.value = true
  try {
    const created = await createGrossanlassGroup(departmentId.value, {
      name,
      parent_id: asRoot ? null : parentId,
      kind: 'ressort',
    })
    groups.value = await getGrossanlassGroups(departmentId.value)
    shareGroup.value = groups.value.find((row) => row.id === shareGroup.value?.id) ?? shareGroup.value
    shareCreateParentId.value = null
    shareCreateName.value = ''
    const blocked = shareGroup.value
      ? new Set([
          ...collectIdsFrom(shareGroup.value.id),
          ...collectAncestorIds(shareGroup.value.id),
        ])
      : new Set<string>()
    if (!blocked.has(created.id)) {
      shareTargetId.value = created.id
    }
    toast.success(
      asRoot
        ? t('grossanlass.planung.ressorts.shareCreateRoot')
        : t('grossanlass.meinRessort.unterressortCreated'),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    shareCreateSaving.value = false
  }
}

function shareCaption(group: GrossanlassGroup): string {
  if ((group.shared_with ?? []).length && canShareGroup(group)) {
    const names = (group.shared_with ?? []).map((row) => row.target_name).filter(Boolean).join(', ')
    if (names) return t('grossanlass.planung.ressorts.shareWithNames', { names })
  }
  const host = groups.value.find((row) => isSharedIntoGroup(group, row) && isInAssignedRessortBranch(row))
  if (host && group.parent_id !== host.id) {
    const owner = parentPathLabel(group)
    return t('grossanlass.planung.ressorts.sharedFrom', { name: owner || group.name })
  }
  return ''
}

async function submitShare() {
  if (!departmentId.value || !shareGroup.value || !shareTargetId.value || shareSaving.value) return
  shareSaving.value = true
  try {
    const created = await shareGrossanlassGroup(departmentId.value, shareGroup.value.id, shareTargetId.value)
    groups.value = await getGrossanlassGroups(departmentId.value)
    shareGroup.value = groups.value.find((row) => row.id === shareGroup.value?.id) ?? shareGroup.value
    shareTargetId.value = ''
    toast.success(t('grossanlass.planung.ressorts.shareSaved', { name: created.target_name || '' }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorShare'))
  } finally {
    shareSaving.value = false
  }
}

async function removeShare(shareId: string) {
  if (!departmentId.value || !shareGroup.value) return
  try {
    await unshareGrossanlassGroup(departmentId.value, shareGroup.value.id, shareId)
    groups.value = await getGrossanlassGroups(departmentId.value)
    shareGroup.value = groups.value.find((row) => row.id === shareGroup.value?.id) ?? null
    toast.success(t('grossanlass.planung.ressorts.shareRemoved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorShare'))
  }
}

const myGroupIds = computed(() => new Set(myGroupsTree.value.map((g) => g.id)))

const ownRahmenAmount = computed(() => {
  const amounts = budgets.value
    .filter((row) => row.payer_group_id && myGroupIds.value.has(row.payer_group_id))
    .map((row) => row.rahmen_chf)
    .filter((value): value is number => value != null)
  if (amounts.length === 0) return null
  return amounts.reduce((sum, value) => sum + value, 0)
})

const ownNetto = computed(() => costRows.value.reduce((sum, row) => sum + (row.netto_chf || 0), 0))

const pendingEinsaetze = computed(() =>
  (submitBoard.value?.einsaetze ?? []).filter((row) => row.status === 'pending_approval'),
)

const showPendingBoard = computed(
  () => gaCanApproveEinsatz(authStore.currentDepartmentRole) && pendingEinsaetze.value.length > 0,
)

const defaultGroupId = computed(() => submitBoard.value?.groups[0]?.id ?? myGroupsTree.value[0]?.id ?? null)

const freePicks = computed(() =>
  (submitBoard.value?.objects ?? []).map((object) => ({
    id: object.id,
    label: object.name,
    objectId: object.id,
    objectName: object.name,
    kind: 'quantity' as const,
    qty: 1,
    stock: object.qty,
    fromIso: new Date().toISOString(),
    toIso: new Date(Date.now() + 86400000).toISOString(),
    fromLabel: object.name,
    toLabel: '',
    ressort: submitBoard.value?.groups[0]?.name ?? '',
    who: '',
    hasConflict: false,
    groupId: defaultGroupId.value,
  })),
)

const wishPicks = computed(() => {
  const objects = submitBoard.value?.objects ?? []
  return wishes.value
    .filter((wish) => isEinsatzBookableWish({ formPurpose: wish.form_purpose }))
    .map((wish) => {
    const object = objects.find((row) => row.name === wish.label)
      || objects.find((row) => wish.label.includes(row.name))
    return {
      id: wish.id,
      label: wish.label,
      objectId: object?.id || '',
      objectName: object?.name || wish.label,
      kind: 'quantity' as const,
      qty: wish.quantity,
      stock: object?.qty ?? wish.quantity,
      fromIso: wish.valid_from || new Date().toISOString(),
      toIso: wish.valid_to || new Date().toISOString(),
      fromLabel: formatGaIsoLabel(wish.valid_from || '', locale.value),
      toLabel: formatGaIsoLabel(wish.valid_to || '', locale.value),
      ressort: wish.group_name || '',
      who: '',
      hasConflict: false,
      groupId: wish.group_id,
      formPurpose: wish.form_purpose,
    }
  })
})

const submitChauffeurs = computed(() =>
  (submitBoard.value?.cards ?? []).map((card) => ({
    value: card.user_id,
    title: card.name,
    subtitle: card.may_drive
      ? t('grossanlass.materialUebersicht.chauffeurMayDrive')
      : t('grossanlass.materialUebersicht.chauffeurNoLicenseShort'),
    mayDrive: card.may_drive,
  })),
)

function wishesForGroup(groupId: string): GrossanlassWishLine[] {
  return wishes.value.filter((w) => w.group_id === groupId)
}

function projectMaterialSummary(groupId: string): string {
  const wishCount = wishesForGroup(groupId).length
  const selfCount = directLines.value.filter((line) => line.group_id === groupId).length
  const einsatzCount = (submitBoard.value?.einsaetze ?? []).filter((row) => row.group_id === groupId).length
  if (wishCount === 0 && selfCount === 0 && einsatzCount === 0) {
    return t('grossanlass.meinRessort.noWishesYet')
  }
  const parts: string[] = []
  if (wishCount > 0) {
    parts.push(t('grossanlass.meinRessort.wishCount', { n: wishCount }))
  }
  if (einsatzCount > 0) {
    parts.push(t('grossanlass.meinRessort.einsatzCount', { n: einsatzCount }))
  }
  if (selfCount > 0) {
    parts.push(t('grossanlass.meinRessort.selfCount', { n: selfCount }))
  }
  return parts.join(' · ')
}

function kindLabel(group: GrossanlassGroup): string {
  return t(grossanlassGroupNodeKindKey(group.node_type))
}

provide(MEIN_RESSORT_TREE_KEY, {
  unterressortsOf,
  bauprojekteOf,
  canCreateChild,
  canEditGroup,
  canDeleteGroup,
  canShareGroup,
  deletingGroupId,
  projectMaterialSummary,
  openCreateChild,
  openProject,
  openShare,
  openEditGroup,
  confirmDeleteGroup,
  kindLabel,
  shareCaption,
})

function helperGroupCaption(group: GrossanlassGroup): string {
  const parentPath = parentPathLabel(group)
  if (parentPath) {
    return t('grossanlass.meinRessort.helperAssignedIn', { path: parentPath })
  }
  return t('grossanlass.meinRessort.helperAssigned')
}

function parentPathLabel(group: GrossanlassGroup): string {
  const parts: string[] = []
  let current: GrossanlassGroup | undefined = group
  const seen = new Set<string>()
  while (current?.parent_id) {
    if (seen.has(current.id)) break
    seen.add(current.id)
    const parent = groups.value.find((row) => row.id === current!.parent_id)
    if (!parent) break
    parts.unshift(parent.name)
    current = parent
  }
  return parts.join(' · ')
}

function helperHeadStyle(group: GrossanlassGroup & { _level?: number }): Record<string, string> {
  if (isHelperHomeView.value) return {}
  const level = group._level ?? group.level ?? 0
  return { paddingLeft: `${level * 24}px` }
}

function goToMeineEinsaetze() {
  void router.push(`/${departmentId.value}/meine-einsaetze`)
}

async function loadDirectLines() {
  if (!departmentId.value || !showEinsatzTools.value) {
    directLines.value = []
    return
  }
  try {
    directLines.value = await listGrossanlassProcurementLines(departmentId.value, { scope: 'direct' })
  } catch {
    directLines.value = []
  }
}

async function openSubmit() {
  if (!departmentId.value) return
  try {
    submitBoard.value = await getGrossanlassSubmitBoard(departmentId.value)
    submitDraft.value = null
    submitOpen.value = true
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.meinRessort.errorLoad'))
  }
}

function onPlaceCreated(place: GaPlace) {
  const board = submitBoard.value
  if (!board) return
  if (board.places.some((row) => row.id === place.id)) return
  submitBoard.value = { ...board, places: [...board.places, place] }
}

async function onSubmitEinsatz(current: GaBookPreviewDraft) {
  if (!departmentId.value) return
  try {
    await createGrossanlassEinsatz(departmentId.value, {
      kind: current.asOrder ? 'order' : 'einsatz',
      commitment_id: current.objectId || undefined,
      wish_line_id: current.fromWish ? current.id : null,
      qty: current.qty,
      from: current.fromIso,
      to: current.toIso,
      who: current.objectName || current.label || current.who,
      chauffeur_user_id: current.chauffeurUserId || null,
      delivery: current.delivery || 'pickup',
      destination_place_id: current.destinationPlaceId || null,
      group_id: current.groupId || defaultGroupId.value,
      pending: Boolean(current.hasConflict),
    })
    submitBoard.value = await getGrossanlassSubmitBoard(departmentId.value)
    toast.success(
      current.asOrder
        ? t('grossanlass.materialUebersicht.orderNoted')
        : t('grossanlass.meinRessort.submitOk'),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.meinRessort.errorLoad'))
  }
}

async function onSubmitMany(drafts: GaBookPreviewDraft[]) {
  if (!departmentId.value) return
  try {
    for (const current of drafts) {
      await createGrossanlassEinsatz(departmentId.value, {
        kind: 'einsatz',
        commitment_id: current.objectId || undefined,
        wish_line_id: current.fromWish ? current.id : null,
        qty: current.qty,
        from: current.fromIso,
        to: current.toIso,
        who: current.objectName || current.label || current.who,
        chauffeur_user_id: current.chauffeurUserId || null,
        delivery: current.delivery || 'pickup',
        destination_place_id: current.destinationPlaceId || null,
        group_id: current.groupId || defaultGroupId.value,
        pending: Boolean(current.hasConflict),
      })
    }
    submitBoard.value = await getGrossanlassSubmitBoard(departmentId.value)
    toast.success(t('grossanlass.materialUebersicht.bookSavedMany', { count: drafts.length }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.meinRessort.errorLoad'))
  }
}

async function onSubmitOrder(current: GaBookPreviewDraft) {
  await onSubmitEinsatz({ ...current, asOrder: true })
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = ''
  try {
    const groupList = await getGrossanlassGroups(departmentId.value)
    groups.value = groupList
    const leaderHome = groupList.some((g) => isLeaderOfGroup(g))
    const helperHome = gaIsHelperHomeView(authStore.currentDepartmentRole, leaderHome)
    const [wishList, costs, budgetList, mine] = await Promise.all([
      helperHome
        ? Promise.resolve([] as GrossanlassWishLine[])
        : getMyRessortWishes(departmentId.value),
      helperHome
        ? Promise.resolve([] as GrossanlassCost[])
        : listGrossanlassCosts(departmentId.value).catch(() => [] as GrossanlassCost[]),
      helperHome
        ? Promise.resolve([] as GrossanlassBudget[])
        : listGrossanlassBudgets(departmentId.value).catch(() => [] as GrossanlassBudget[]),
      helperHome
        ? getGrossanlassMyEinsaetze(departmentId.value)
        : Promise.resolve(null),
    ])
    wishes.value = wishList
    costRows.value = costs
    budgets.value = budgetList
    myEinsaetze.value = mine
    await loadDirectLines()
    if (showEinsatzTools.value) {
      submitBoard.value = await getGrossanlassSubmitBoard(departmentId.value).catch(() => null)
      if (presetWishId.value || presetGroupId.value) {
        submitOpen.value = true
      }
    }
  } catch (e: any) {
    error.value = e.response?.data?.error || t('grossanlass.meinRessort.errorLoad')
  } finally {
    isLoading.value = false
  }
}

watch(
  () => [showCreateProject.value, createForm.value.include_on_map, createForm.value.kind] as const,
  () => {
    if (!showCreateProject.value) {
      editingGroup.value = null
      createAsRoot.value = false
      window.removeEventListener('resize', syncAccordionWidth)
      return
    }
    syncAccordionWidth()
    window.addEventListener('resize', syncAccordionWidth)
    void syncGroupMapPlacement()
  },
)

onMounted(() => {
  void load()
  void loadVenueAddress()
})

onUnmounted(() => {
  window.removeEventListener('resize', syncAccordionWidth)
})
</script>

<style scoped>
.mein-ressort-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ressort-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
}

.ressort-card__head {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 10px;
}

.group-modal-map {
  margin-top: 12px;
  --ev-map-height: 320px;
}
.group-modal-map__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0 0 8px;
}
.group-modal-map__hint,
.group-modal-map__missing {
  margin: 0 0 8px;
  color: #64748b;
  font-size: 0.85rem;
}
.group-modal-map__missing {
  padding: 12px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
}
.bereich-tree__toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 12px;
}
.bereich-tree :deep(.v-expansion-panel) {
  border: 1px solid #e5e7eb;
  border-radius: 10px !important;
  overflow: hidden;
}
.bereich-tree :deep(.v-expansion-panel-title) {
  min-height: 48px;
  padding: 8px 16px;
}
.bereich-tree :deep(.v-expansion-panel-text__wrapper) {
  padding: 0 16px 12px;
}
.bereich-tree__title {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.bereich-tree__title strong {
  font-size: 0.95rem;
}
.bereich-tree__count {
  margin-left: 4px;
  font-size: 0.75rem;
  color: #64748b;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 1px 8px;
}
.bereich-tree__add {
  margin-top: 8px;
}
.bauprojekt-rows {
  list-style: none;
  margin: 0;
  padding: 0;
}
.bauprojekt-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid #eef2f6;
}
.bauprojekt-row:last-child {
  border-bottom: 0;
}
.bauprojekt-row__main {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
  flex: 1;
}
.bauprojekt-row__name {
  border: 0;
  background: transparent;
  padding: 0;
  text-align: left;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  color: inherit;
}
.bauprojekt-row__name:hover {
  text-decoration: underline;
}
.bauprojekt-row__meta {
  font-size: 0.78rem;
  color: #64748b;
}
.bauprojekt-row__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.action-btn {
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 4px;
  color: #64748b;
}
.action-btn:disabled {
  opacity: 0.5;
  cursor: default;
}
.action-btn-danger {
  color: #b91c1c;
}

.ressort-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.ressort-card.is-child {
  background: #fafbfc;
}

.ressort-card--helper {
  background: #fff;
}

.indent-icon {
  color: #94a3b8;
  font-size: 14px;
  flex-shrink: 0;
}

.ressort-card__head h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.kind-badge {
  font-size: 0.78rem;
  color: #6b7280;
}

.wish-mini-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.wish-mini-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  background: #f9fafb;
  border-radius: 6px;
}

.wish-label {
  font-weight: 500;
  font-size: 0.9rem;
}

.wish-meta {
  font-size: 0.78rem;
  color: #6b7280;
}

.no-wishes {
  margin: 0;
  font-size: 0.85rem;
  color: #9ca3af;
}

.assignments-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
}
.assignments-panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}
.assignments-panel__head h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1e293b;
}
.assignments-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.assignments-list li {
  margin: 0;
  padding: 0;
  border: 0;
}

.helper-scan-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
}

.helper-scan-panel :deep(.material-journey-scan-bar) {
  margin: 0;
}

.kosten-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px;
  background: #fff;
}
.kosten-panel h3 {
  margin: 0 0 6px;
  font-size: 0.95rem;
}
.kosten-rahmen {
  margin: 0 0 10px;
  font-size: 0.82rem;
  color: #64748b;
}

.submit-einsatz {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #ecfdf5;
  border-radius: 8px;
}

.submit-einsatz p {
  margin: 0;
  font-size: 0.85rem;
  color: #047857;
}

.pending-board {
  padding: 12px 14px;
  border: 1px solid #fde68a;
  border-radius: 8px;
  background: #fffbeb;
}
.pending-board h3 { margin: 0 0 8px; font-size: 0.95rem; }
.pending-board ul { margin: 0; padding-left: 18px; font-size: 0.88rem; }

.muted { color: #64748b; font-size: 0.88rem; }
.share-tree-label {
  margin: 12px 0 6px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}
.share-tree {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 8px;
  background: #fff;
}
.share-source { font-weight: 600; margin: 8px 0; }
.share-list { list-style: none; padding: 0; margin: 0 0 12px; }
.share-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid #eef2f6;
}
</style>
