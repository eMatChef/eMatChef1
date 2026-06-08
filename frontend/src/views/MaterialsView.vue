<template>
  <div class="materials-view" :class="{ 'materials-view--detail': showDetailView && selectedMaterialId }">
    <!-- Detail View (ersetzt Liste wenn Material ausgewählt) -->
    <MaterialDetailView
      v-if="showDetailView && selectedMaterialId"
      :key="selectedMaterialId"
      :material-id="selectedMaterialId"
      :department-id="currentDepartmentId"
      :initial-batch-id="route.query.batch ? String(route.query.batch) : undefined"
      @close="closeDetailView"
      @updated="handleMaterialUpdated"
      @open-create-for-composition="onOpenCreateForComposition"
    />

    <!-- Liste View -->
    <PageShell v-else class="materials-view materials-view--list">
      <template #title>{{ t('materialsView.title') }}</template>
      <template v-if="!isUserMaterialsBrowseOnly" #subtitle>{{ t('materialsView.description') }}</template>
      <template v-if="canManageMaterials" #actions>
        <EButton variant="primary" @click="openCreateWizard">
          <v-icon icon="mdi-plus" start size="20" />
          {{ t('materialsView.newMaterial') }}
        </EButton>
      </template>

      <template v-if="!isUserMaterialsBrowseOnly" #filters>
        <div class="materials-view-filters-stack">
        <v-tabs
          :model-value="activeTab"
          class="materials-view-tabs"
          color="primary"
          @update:model-value="onMaterialTabChange"
        >
          <v-tab v-for="tab in materialListTabs" :key="tab.id" :value="tab.id">
            <v-icon v-if="tab.icon" :icon="tab.icon" start size="18" />
            {{ tab.label }}
            <v-chip v-if="tab.count != null" size="x-small" variant="tonal" color="primary" class="materials-view-tab-count">
              {{ tab.count }}
            </v-chip>
          </v-tab>
        </v-tabs>

        <EFilterRow v-if="activeTab !== 'storage'" class="materials-view-filters">
          <v-col class="e-filter-row__search">
            <GlobalSearchInput
              mode="inline"
              :department-id="currentDepartmentId"
              default-type="material"
              v-model="searchQuery"
              :placeholder="t('materialsView.searchListPlaceholder')"
            />
          </v-col>
          <v-col v-if="activeTab === 'combos'" cols="auto" class="materials-view-combo-toggle">
            <v-btn-toggle
              v-model="comboFilter"
              density="compact"
              color="primary"
              variant="outlined"
              mandatory
            >
              <v-btn value="all" size="small">{{ t('materialsView.comboFilterBoth') }}</v-btn>
              <v-btn value="physical" size="small">{{ t('materialsView.comboFilterPhysical') }}</v-btn>
              <v-btn value="virtual" size="small">{{ t('materialsView.comboFilterVirtual') }}</v-btn>
            </v-btn-toggle>
          </v-col>
          <v-col v-if="!isUserMaterialsBrowseOnly" cols="auto" class="e-filter-row__select">
            <ESelect
              v-model="selectedCategory"
              :items="categorySelectItems"
              :label="t('materialsView.filterAllCategories')"
              hide-details
            />
          </v-col>
          <v-col v-if="!isUserMaterialsBrowseOnly" cols="auto" class="e-filter-row__select">
            <ESelect
              v-model="selectedCondition"
              :items="conditionSelectItems"
              :label="t('materialsView.filterAllConditions')"
              hide-details
            />
          </v-col>
          <v-col cols="auto" class="e-filter-row__actions">
            <EButton
              variant="text"
              size="small"
              :style="{ visibility: hasActiveFilters ? 'visible' : 'hidden' }"
              :aria-hidden="!hasActiveFilters"
              @click="resetFilters"
            >
              {{ t('materialsView.resetFilters') }}
            </EButton>
          </v-col>
        </EFilterRow>
        </div>
      </template>

      <div class="materials-view-content">
        <!-- Tab: Regale (storage-centric view) -->
        <div v-if="activeTab === 'storage'" class="storage-tab-content">
          <StorageTreeView
            :department-id="currentDepartmentId"
            :open-material-without-batch-query="true"
          />
        </div>

        <template v-else>
          <ELoadingState
            v-if="showFullLoading"
            variant="table"
            :rows="8"
            :message="t('materialsView.loading')"
          />

          <div v-else-if="error && materials.length === 0" class="materials-view-error">
            <v-alert type="error" variant="tonal" :text="error" />
            <EButton variant="secondary" class="mt-3" @click="loadData()">{{ t('common.retry') }}</EButton>
          </div>

          <div v-else class="list-content" :class="{ 'is-soft-loading': isRefreshing }">
            <div v-if="isRefreshing" class="soft-refresh-bar" aria-hidden="true"></div>

            <EEmptyState
              v-if="materials.length === 0"
              variant="create"
              :title="t('materialsView.emptyTitle')"
              :description="t('materialsView.emptyDescription')"
            >
              <template v-if="canManageMaterials" #actions>
                <EButton size="large" @click="openCreateWizard">
                  <v-icon icon="mdi-plus" start size="20" />
                  {{ t('materialsView.emptyCta') }}
                </EButton>
              </template>
            </EEmptyState>

            <EEmptyState
              v-else-if="filteredMaterials.length === 0"
              variant="search"
              :title="t('materialsView.noResultsTitle')"
              :description="t('materialsView.noResultsDescription')"
            >
              <template #actions>
                <EButton variant="secondary" @click="resetFilters">
                  {{ t('materialsView.resetFilters') }}
                </EButton>
              </template>
            </EEmptyState>

            <!-- Materials List (Desktop: v-data-table, Mobile: v-list) -->
            <div v-else class="materials-table-wrapper">
              <EResponsiveDataList>
                <template #table>
                  <MaterialListDataTable
                    :items="filteredMaterials"
                    :categories-by-id="categoriesById"
                    :show-combo-columns="showComboColumns"
                    :show-combo-expand-column="showComboExpandColumn"
                    :show-stock-detail-columns="showStockDetailColumns"
                    :expanded-ids="expandedComboIds"
                    :combo-components-by-id="comboComponentsById"
                    :combo-components-loading="comboComponentsLoading"
                    :assignment-labels="assignmentLabels"
                    @open="openMaterialDetail"
                    @open-component="openMaterialDetailById"
                    @update:expanded-ids="onExpandedComboIdsUpdate"
                  />
                </template>
                <template #mobile>
                  <MaterialListMobile
                    :items="filteredMaterials"
                    :categories-by-id="categoriesById"
                    :show-combo-expand-column="showComboExpandColumn"
                    :show-stock-detail-columns="showStockDetailColumns"
                    :expanded-ids="expandedComboIds"
                    :combo-components-by-id="comboComponentsById"
                    :combo-components-loading="comboComponentsLoading"
                    @open="openMaterialDetail"
                    @open-component="openMaterialDetailById"
                    @toggle-expand="toggleComboExpand"
                  />
                </template>
              </EResponsiveDataList>

              <p class="table-hint">{{ t('materialsView.tableHint') }}</p>
            </div>
          </div>
        </template>
      </div>
    </PageShell>

    <EDialog
      v-model="showPostCreateComboGuideModal"
      :max-width="520"
      :title="t('materialsView.modalPostCreateComboTitle')"
    >
      <p class="text-muted materials-view-composition-intro">
        {{
          t('materialsView.modalPostCreateComboIntro', {
            name: postCreateComboGuideMaterial?.name ?? '',
          })
        }}
      </p>
      <ul class="materials-view-combo-guide-steps">
        <li v-if="postCreateComboGuideMaterial?.material_type === 'physical_combo'">
          {{ t('materialsView.modalPostCreateComboStepFinalize') }}
        </li>
        <li v-else>
          {{ t('materialsView.modalPostCreateComboStepVirtual') }}
        </li>
        <li v-if="postCreateComboGuideHasLinkedContainer">
          {{ t('materialsView.modalPostCreateComboStepContainer') }}
        </li>
      </ul>
      <template #actions>
        <EButton
          v-if="postCreateComboGuideHasLinkedContainer"
          variant="secondary"
          @click="openPostCreateComboStorageTab"
        >
          {{ t('materialsView.btnPostCreateComboOpenStorage') }}
        </EButton>
        <EButton variant="primary" @click="closePostCreateComboGuideModal">
          {{ t('materialsView.btnPostCreateComboGotIt') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showPostCreateCompositionModal"
      :max-width="480"
      :title="t('materialsView.modalPostCreateCompositionTitle')"
    >
      <p class="text-muted materials-view-composition-intro">
        {{
          t('materialsView.modalPostCreateCompositionIntro', {
            combo: postCreateCompositionComboName,
            article: postCreateCompositionContext?.material.name ?? '',
          })
        }}
      </p>
      <ETextField
        v-model.number="postCreateCompositionQty"
        type="number"
        :label="t('materialsView.labelQtyInCombo')"
        :min="1"
        :max="postCreateCompositionMaxQty ?? undefined"
        hide-details="auto"
        @update:model-value="clampPostCreateCompositionQty"
      />
      <p v-if="postCreateCompositionMaxQty !== null && postCreateCompositionMaxQty > 0" class="text-muted text-caption mt-1">
        {{ t('components.materialDetail.hintMaxQty', { n: postCreateCompositionMaxQty }) }}
      </p>
      <p v-else-if="postCreateCompositionMaxQty === 0" class="text-error text-caption mt-1">
        {{ t('components.materialDetail.errAddCompositionNoStock') }}
      </p>
      <p v-if="postCreateCompositionError" class="text-error text-caption mt-2">{{ postCreateCompositionError }}</p>
      <template #actions>
        <EButton variant="secondary" @click="closePostCreateCompositionModal">
          {{ t('materialsView.btnPostCreateCompositionSkip') }}
        </EButton>
        <EButton
          variant="primary"
          :disabled="!canSubmitPostCreateComposition || postCreateCompositionSubmitting"
          :loading="postCreateCompositionSubmitting"
          @click="submitPostCreateComposition"
        >
          {{
            postCreateCompositionSubmitting
              ? t('materialsView.postCreateCompositionSubmitting')
              : t('materialsView.btnPostCreateCompositionAdd')
          }}
        </EButton>
      </template>
    </EDialog>

    <!-- Material Create Wizard -->
    <MaterialCreateWizard
      :key="wizardOpenKey"
      v-model="showCreateWizard"
      :department-id="currentDepartmentId"
      @created="handleMaterialCreated"
    />
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'MaterialsView' })
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import {
  getMaterials,
  getComboComponents,
  addComboComponent,
  type Material,
  type ComboComponent,
} from '@/api/materials'
import { getCategories, type Category } from '@/api/categories'
import MaterialCreateWizard from '@/components/material/MaterialCreateWizard.vue'
import MaterialDetailView from '@/components/material/MaterialDetailView.vue'
import MaterialListDataTable from '@/components/material/MaterialListDataTable.vue'
import MaterialListMobile from '@/components/material/MaterialListMobile.vue'
import PageShell from '@/components/layout/PageShell.vue'
import EFilterRow from '@/components/layout/EFilterRow.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EResponsiveDataList from '@/components/layout/EResponsiveDataList.vue'
import StorageTreeView from '@/components/storage/StorageTreeView.vue'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { useToast } from '@/composables/useToast'
import { useListSearchQueryRoute } from '@/composables/useListSearchQueryRoute'
import { useDepartmentLiveRefresh } from '@/composables/useDepartmentLiveRefresh'
import { useAuthStore } from '@/stores/auth'
import { isDepartmentBasicMemberRole } from '@/composables/useDepartmentMemberRole'
import { isComboMaterial as isComboMaterialType } from '@/utils/comboDisplay'
import '@/styles/material-wizard.css'
import '@/styles/views/materials-view-tabs.css'

/** Überlebt Tab-Remounts (router-view :key="route.path") — gleiche Daten, kein Hard-Spinner. */
const materialsListCache = new Map<string, { materials: Material[]; categories: Category[] }>()
/** Überlebt Remount beim Schliessen der Detailansicht (router-view :key="route.path"). */
let lastOpenMaterialDetailId: string | null = null
let skipNextMountedListLoad = false

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const detailTabsStore = useDetailTabsStore()
const router = useRouter()
const toast = useToast()
const currentDepartmentId = computed(() => route.params.departmentId as string)

/** Department-Rolle «User»: eingeschränkte Material-Liste (nur Tab «Alle Artikel», lesen). */
const departmentRole = computed(() => (authStore.currentDepartmentRole || 'u').toLowerCase())
const isUserMaterialsBrowseOnly = computed(() => isDepartmentBasicMemberRole(departmentRole.value))
const canManageMaterials = computed(() => ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value))
const showStockDetailColumns = computed(() => !isUserMaterialsBrowseOnly.value)

type MaterialTab = 'combos' | 'all' | 'virtual_combos' | 'consumables' | 'food' | 'storage'
const materialTabRouteNames: Record<MaterialTab, string> = {
  all: 'MaterialsTabAll',
  combos: 'MaterialsTabCombos',
  virtual_combos: 'MaterialsTabVirtualCombos',
  consumables: 'MaterialsTabConsumables',
  food: 'MaterialsTabFood',
  storage: 'MaterialsTabStorage',
}
const routeNameToMaterialTab: Record<string, MaterialTab> = {
  Materials: 'all',
  MaterialsTabAll: 'all',
  MaterialsTabCombos: 'combos',
  MaterialsTabVirtualCombos: 'virtual_combos',
  MaterialsTabConsumables: 'consumables',
  MaterialsTabFood: 'food',
  MaterialsTabStorage: 'storage',
}
const lastTabStorageKey = computed(() => `materials.lastTab.${currentDepartmentId.value || 'default'}`)

function readMaterialsCache(deptId: string | undefined) {
  if (!deptId) return null
  return materialsListCache.get(deptId) ?? null
}

function writeMaterialsCache(deptId: string) {
  materialsListCache.set(deptId, {
    materials: materials.value,
    categories: categories.value,
  })
}

function hydrateMaterialsFromCache(deptId: string | undefined): boolean {
  const cached = readMaterialsCache(deptId)
  if (!cached) return false
  materials.value = cached.materials
  categories.value = cached.categories
  return true
}

// State
const materials = ref<Material[]>([])
const categories = ref<Category[]>([])
if (hydrateMaterialsFromCache(currentDepartmentId.value)) {
  /* Sofort aus Cache — Tab-Wechsel ohne Hard-Load */
}
const categoriesById = computed(() =>
  Object.fromEntries(categories.value.map(c => [c.id, c.name]))
)
const isLoading = ref(false)
const isRefreshing = ref(false)
const error = ref<string | null>(null)
const showFullLoading = computed(() => isLoading.value && materials.value.length === 0)

// Tab State
const activeTab = ref<MaterialTab>('all')

const categorySelectItems = computed(() => [
  { title: t('materialsView.filterAllCategories'), value: '' },
  ...categories.value.map((cat) => ({
    title: `${cat.parent_id ? '↳ ' : ''}${cat.name} (${cat.material_count})`,
    value: cat.id,
  })),
])

const conditionSelectItems = computed(() => [
  { title: t('materialsView.filterAllConditions'), value: '' },
  { title: t('materialsView.conditionOk'), value: 'ok' },
  { title: t('materialsView.conditionDefect'), value: 'defect' },
  { title: t('materialsView.conditionRepair'), value: 'repair' },
  { title: t('materialsView.conditionLost'), value: 'lost' },
])

function onMaterialTabChange(tab: unknown) {
  if (typeof tab === 'string' && tab in materialTabRouteNames) {
    selectTab(tab as MaterialTab)
  }
}

// Filter State
const searchQuery = ref('')
const selectedCategory = ref('')
const selectedCondition = ref('')
const comboFilter = ref<'all' | 'physical' | 'virtual'>('physical')

// Wizard State
const showCreateWizard = ref(false)
const wizardOpenNonce = ref(0)
const wizardOpenKey = computed(() => `${currentDepartmentId.value}-${wizardOpenNonce.value}`)
const materialJustCreated = ref(false)
/** Nach Wizard: neuen Artikel als Komponente dieser Kombi verknüpfen */
const pendingCompositionParentId = ref<string | null>(null)

const showPostCreateComboGuideModal = ref(false)
const postCreateComboGuideMaterial = ref<Material | null>(null)

const postCreateComboGuideHasLinkedContainer = computed(
  () =>
    postCreateComboGuideMaterial.value?.material_type === 'physical_combo' &&
    !!(
      postCreateComboGuideMaterial.value.linked_container_batch_id ||
      postCreateComboGuideMaterial.value.linked_container_batch?.id
    ),
)

function closePostCreateComboGuideModal() {
  showPostCreateComboGuideModal.value = false
  postCreateComboGuideMaterial.value = null
}

function openPostCreateComboStorageTab() {
  const m = postCreateComboGuideMaterial.value
  if (!m?.id || !currentDepartmentId.value) {
    closePostCreateComboGuideModal()
    return
  }
  closePostCreateComboGuideModal()
  router.push({
    path: `/${currentDepartmentId.value}/materials/${m.id}`,
    query: { tab: 'stored-in' },
  })
}

const showPostCreateCompositionModal = ref(false)
const postCreateCompositionContext = ref<{
  parentId: string
  material: Material
  existing: ComboComponent[]
  defaultMode: 'fixed' | 'assigned' | 'on_issue' | 'bulk'
} | null>(null)
const postCreateCompositionQty = ref(1)
const postCreateCompositionSubmitting = ref(false)
const postCreateCompositionError = ref('')

const postCreateCompositionComboName = computed(() => {
  const pid = postCreateCompositionContext.value?.parentId
  if (!pid) return ''
  return materials.value.find((m) => m.id === pid)?.name || pid
})

const postCreateCompositionMaxQty = computed((): number | null => {
  const m = postCreateCompositionContext.value?.material
  if (!m) return null
  const n = m.total_stock
  if (typeof n !== 'number' || !Number.isFinite(n)) return null
  return Math.max(0, Math.floor(n))
})

const canSubmitPostCreateComposition = computed(() => {
  const q = postCreateCompositionQty.value ?? 0
  if (q < 1) return false
  const cap = postCreateCompositionMaxQty.value
  if (cap === 0) return false
  if (cap !== null && q > cap) return false
  return true
})

function clampPostCreateCompositionQty() {
  const cap = postCreateCompositionMaxQty.value
  if (cap === null) return
  if ((postCreateCompositionQty.value ?? 0) > cap) postCreateCompositionQty.value = Math.max(0, cap)
  if (cap > 0 && (postCreateCompositionQty.value ?? 0) < 1) postCreateCompositionQty.value = 1
}

function closePostCreateCompositionModal() {
  showPostCreateCompositionModal.value = false
  postCreateCompositionContext.value = null
  postCreateCompositionQty.value = 1
  postCreateCompositionError.value = ''
}

async function submitPostCreateComposition() {
  const ctx = postCreateCompositionContext.value
  if (!ctx || !canSubmitPostCreateComposition.value) return
  postCreateCompositionSubmitting.value = true
  postCreateCompositionError.value = ''
  try {
    await addComboComponent(ctx.parentId, {
      component_material_id: ctx.material.id,
      qty: Math.max(1, postCreateCompositionQty.value || 1),
      assignment_mode: ctx.defaultMode,
      sort_order: ctx.existing.length,
    })
    toast.success(t('materialsView.toastAddedToComposition'))
    closePostCreateCompositionModal()
    await loadData({ silent: true })
  } catch (err: unknown) {
    const ax = err as { response?: { data?: { error?: string } } }
    postCreateCompositionError.value = ax.response?.data?.error || t('materialsView.errLinkComponent')
  } finally {
    postCreateCompositionSubmitting.value = false
  }
}

// Combo Expand State
const expandedComboIds = ref<string[]>([])
const comboComponentsCache = ref<Map<string, ComboComponent[]>>(new Map())
const comboComponentsLoading = ref<Set<string>>(new Set())

const comboComponentsById = computed(() =>
  Object.fromEntries(comboComponentsCache.value) as Record<string, ComboComponent[]>
)

// Detail View State (gesteuert über Route-Parameter)
const selectedMaterialId = computed(() => route.params.materialId as string | undefined || null)
const showDetailView = computed(() => !!selectedMaterialId.value)

const assignmentLabels = computed((): Record<string, string> => ({
  fixed: t('components.materialDetail.assignmentFixed'),
  assigned: t('components.materialDetail.assignmentAssigned'),
  on_issue: t('components.materialDetail.assignmentOnIssue'),
  bulk: t('components.materialDetail.assignmentBulk'),
}))

// Tab Counts
const comboCount = computed(() => 
  materials.value.filter(m => m.material_type === 'physical_combo' || m.material_type === 'virtual_combo').length
)
const virtualComboCount = computed(() => 
  materials.value.filter(m => m.material_type === 'virtual_combo').length
)
const allCount = computed(() => 
  materials.value.filter(m => m.material_type !== 'virtual_combo').length
)
const consumableCount = computed(() => 
  materials.value.filter(m => m.is_consumable).length
)
const foodCount = computed(() => 
  materials.value.filter(m => m.is_food).length
)

const materialListTabs = computed(() => [
  { id: 'combos' as MaterialTab, label: t('materialsView.tabCombos'), icon: 'mdi-triangle-outline', count: comboCount.value },
  { id: 'all' as MaterialTab, label: t('materialsView.tabAll'), icon: 'mdi-package-variant', count: allCount.value },
  {
    id: 'virtual_combos' as MaterialTab,
    label: t('materialsView.tabVirtualCombos'),
    icon: 'mdi-triangle-outline',
    count: virtualComboCount.value,
  },
  {
    id: 'consumables' as MaterialTab,
    label: t('materialsView.tabConsumables'),
    icon: 'mdi-minus-circle-outline',
    count: consumableCount.value,
  },
  { id: 'food' as MaterialTab, label: t('materialsView.tabFood'), icon: 'mdi-coffee-outline', count: foodCount.value },
  { id: 'storage' as MaterialTab, label: t('materialsView.tabStorage'), icon: 'mdi-warehouse' },
])

// Combo-Spalten: Typ + Kombo-Bestand (Tabs „Kombos“ / „Virtuelle Kobis“)
const showComboColumns = computed(() =>
  activeTab.value === 'combos' || activeTab.value === 'virtual_combos'
)

/** Pfeil zum Aufklappen der Komponenten auch unter „Alle Artikel“. */
const showComboExpandColumn = computed(() =>
  activeTab.value === 'all' || activeTab.value === 'combos' || activeTab.value === 'virtual_combos'
)

function isComboMaterial(material: Material): boolean {
  return isComboMaterialType(material)
}

// Computed
const filteredMaterials = computed(() => {
  // 1. Tab-Filter
  let result = [...materials.value]
  
  if (activeTab.value === 'combos') {
    result = result.filter(m => m.material_type === 'physical_combo' || m.material_type === 'virtual_combo')
    // Kombos-Filter: Physisch / Virtuell / Beide
    if (comboFilter.value === 'physical') {
      result = result.filter(m => m.material_type === 'physical_combo')
    } else if (comboFilter.value === 'virtual') {
      result = result.filter(m => m.material_type === 'virtual_combo')
    }
  } else if (activeTab.value === 'all') {
    result = result.filter(m => m.material_type !== 'virtual_combo')
  } else if (activeTab.value === 'virtual_combos') {
    result = result.filter(m => m.material_type === 'virtual_combo')
  } else if (activeTab.value === 'consumables') {
    result = result.filter(m => m.is_consumable)
  } else if (activeTab.value === 'food') {
    result = result.filter(m => m.is_food)
  }
  
  // 2. Suche
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(m => 
      m.name.toLowerCase().includes(query) ||
      (m.description && m.description.toLowerCase().includes(query)) ||
      (m.manufacturer && m.manufacturer.toLowerCase().includes(query))
    )
  }
  
  // 3. Kategorie
  if (selectedCategory.value) {
    result = result.filter(m => m.category?.id === selectedCategory.value)
  }
  
  // 4. Zustand
  if (selectedCondition.value) {
    result = result.filter(m => m.condition === selectedCondition.value)
  }
  
  return result
})

const hasActiveFilters = computed(() => {
  const baseFilters = searchQuery.value
    || (!isUserMaterialsBrowseOnly.value && selectedCategory.value)
    || (!isUserMaterialsBrowseOnly.value && selectedCondition.value)
  const comboTypeFilter = activeTab.value === 'combos' && comboFilter.value !== 'all'
  return baseFilters || comboTypeFilter
})

// Methods
async function loadData(opts?: { silent?: boolean }) {
  const deptId = currentDepartmentId.value
  if (!deptId) return

  const hasCachedList = materials.value.length > 0
  const silent = opts?.silent ?? hasCachedList

  if (silent) {
    isRefreshing.value = true
  } else {
    isLoading.value = true
    error.value = null
  }

  try {
    const [materialsData, categoriesData] = await Promise.all([
      getMaterials(deptId),
      getCategories(deptId)
    ])

    materials.value = materialsData
    categories.value = categoriesData
    writeMaterialsCache(deptId)
  } catch (err: any) {
    if (!silent) {
      error.value = err.response?.data?.error || t('materialsView.errLoadList')
    }
  } finally {
    isLoading.value = false
    isRefreshing.value = false
  }
}

/** Hintergrund-Aktualisierung (andere User, Tab-Remount) ohne Hard-Spinner. */
useDepartmentLiveRefresh({
  departmentId: currentDepartmentId,
  reload: loadData,
  enabled: () => !showDetailView.value && activeTab.value !== 'storage',
  isBusy: () =>
    showCreateWizard.value
    || showPostCreateCompositionModal.value
    || showPostCreateComboGuideModal.value
    || (isLoading.value && materials.value.length === 0),
})

async function ensureComboComponentsLoaded(materialId: string) {
  if (comboComponentsCache.value.has(materialId)) return

  comboComponentsLoading.value.add(materialId)
  comboComponentsLoading.value = new Set(comboComponentsLoading.value)
  try {
    const components = await getComboComponents(materialId)
    comboComponentsCache.value.set(materialId, components)
    comboComponentsCache.value = new Map(comboComponentsCache.value)
  } catch (err) {
    console.error(t('materialsView.logErrorLoadComponents'), err)
    comboComponentsCache.value.set(materialId, [])
    comboComponentsCache.value = new Map(comboComponentsCache.value)
  } finally {
    comboComponentsLoading.value.delete(materialId)
    comboComponentsLoading.value = new Set(comboComponentsLoading.value)
  }
}

function onExpandedComboIdsUpdate(ids: string[]) {
  const previouslyExpanded = new Set(expandedComboIds.value)
  expandedComboIds.value = ids
  for (const id of ids) {
    if (!previouslyExpanded.has(id)) {
      void ensureComboComponentsLoaded(id)
    }
  }
}

async function toggleComboExpand(materialId: string) {
  const row = materials.value.find((m) => m.id === materialId)
  if (!row || !isComboMaterial(row)) return

  if (expandedComboIds.value.includes(materialId)) {
    expandedComboIds.value = expandedComboIds.value.filter((id) => id !== materialId)
    return
  }

  expandedComboIds.value = [...expandedComboIds.value, materialId]
  await ensureComboComponentsLoaded(materialId)
}

const { clearSearchFromRoute, stripQueryFromDetailRoute } = useListSearchQueryRoute({
  searchQuery,
  route,
  router,
  pathIncludes: '/materials',
  isListView: () => !selectedMaterialId.value,
  isSearchActive: () => activeTab.value !== 'storage',
})

function resetFilters() {
  clearSearchFromRoute()
  selectedCategory.value = ''
  selectedCondition.value = ''
  comboFilter.value = 'physical'
}

function selectTab(tab: MaterialTab) {
  if (isUserMaterialsBrowseOnly.value && tab !== 'all') return
  activeTab.value = tab
  if (selectedMaterialId.value) return
  const routeName = materialTabRouteNames[tab]
  if (route.name !== routeName) {
    router.push({
      name: routeName,
      params: { departmentId: currentDepartmentId.value },
      query: route.query,
    })
  }
}

function readLastMaterialTab(): MaterialTab {
  if (isUserMaterialsBrowseOnly.value) return 'all'
  const raw = localStorage.getItem(lastTabStorageKey.value)
  if (raw && raw in materialTabRouteNames) {
    return raw as MaterialTab
  }
  return 'all'
}

function enforceUserMaterialsListRoute(): void {
  if (!isUserMaterialsBrowseOnly.value) return
  activeTab.value = 'all'
  if (selectedMaterialId.value) return
  const name = String(route.name || '')
  if (name !== 'MaterialsTabAll' && name !== 'Materials') return
  if (name === 'MaterialsTabAll') return
  router.replace({
    name: 'MaterialsTabAll',
    params: { departmentId: currentDepartmentId.value },
    query: route.query,
  })
}

function openCreateWizard(opts?: { linkAsComboComponentTo?: string }) {
  wizardOpenNonce.value += 1
  pendingCompositionParentId.value = opts?.linkAsComboComponentTo ?? null
  showCreateWizard.value = true
}

function onOpenCreateForComposition(payload: { parentMaterialId: string }) {
  openCreateWizard({ linkAsComboComponentTo: payload.parentMaterialId })
}

async function handleMaterialCreated(material: Material) {
  materialJustCreated.value = true
  const linkParent = pendingCompositionParentId.value
  pendingCompositionParentId.value = null

  await loadData({ silent: true })

  if (linkParent && material?.id) {
    try {
      const existing = await getComboComponents(linkParent)
      const parentRow = materials.value.find((m) => m.id === linkParent)
      const defaultMode =
        parentRow?.material_type === 'virtual_combo' ? 'on_issue' : 'bulk'
      const mergedMaterial =
        materials.value.find((m) => m.id === material.id) || material
      postCreateCompositionContext.value = {
        parentId: linkParent,
        material: mergedMaterial,
        existing,
        defaultMode,
      }
      postCreateCompositionQty.value = 1
      postCreateCompositionError.value = ''
      showPostCreateCompositionModal.value = true
      void nextTick(() => clampPostCreateCompositionQty())
    } catch (err: unknown) {
      const ax = err as { response?: { data?: { error?: string } } }
      console.error(err)
      toast.error(ax.response?.data?.error || t('materialsView.errLinkComponent'))
    }
    if (route.query.from === 'dashboard') {
      const q = { ...route.query }
      delete q.from
      router.replace({ path: route.path, query: q })
    }
    return
  }

  const mergedMaterial = materials.value.find((m) => m.id === material?.id) || material
  const isCombo =
    mergedMaterial?.material_type === 'physical_combo' ||
    mergedMaterial?.material_type === 'virtual_combo'

  if (isCombo && material?.id && currentDepartmentId.value) {
    postCreateComboGuideMaterial.value = mergedMaterial
    showPostCreateComboGuideModal.value = true
    router.push({
      path: `/${currentDepartmentId.value}/materials/${material.id}`,
      query: { tab: 'composition' },
    })
  } else if (material?.id && currentDepartmentId.value) {
    router.push(`/${currentDepartmentId.value}/materials/${material.id}`)
  }

  if (route.query.from === 'dashboard') {
    const q = { ...route.query }
    delete q.from
    router.replace({ path: route.path, query: q })
  }
}

function openMaterialDetail(material: Material) {
  router.push(`/${currentDepartmentId.value}/materials/${material.id}`)
}

function openMaterialDetailById(materialId: string) {
  router.push(`/${currentDepartmentId.value}/materials/${materialId}`)
}

function closeDetailView() {
  // Tab bleibt im Header (keep-alive); nur × im Header schliesst den Chip
  const nextQuery = { ...route.query }
  delete nextQuery.batch
  router.push({
    name: materialTabRouteNames[activeTab.value],
    params: { departmentId: currentDepartmentId.value },
    query: nextQuery,
  })
}

async function handleMaterialUpdated(material: Material) {
  const index = materials.value.findIndex(m => m.id === material.id)
  if (index !== -1) {
    materials.value[index] = material
    if (currentDepartmentId.value) {
      writeMaterialsCache(currentDepartmentId.value)
    }
  }
}

// Tab-Registrierung für Detail-Ansicht (Header-Chip bleibt bei «Zurück zur Liste»)
watch(
  [selectedMaterialId, currentDepartmentId],
  ([matId, deptId]) => {
    if (!matId || !deptId) return
    const m = materials.value.find((x) => x.id === matId)
    detailTabsStore.addOrUpdateTab({
      id: matId,
      type: 'material',
      label: m?.name || t('materialsView.fallbackTabLabel', { id: matId }),
      departmentId: deptId,
      path: `/${deptId}/materials/${matId}`,
    })
  },
  { immediate: true }
)

// Watchers
watch(currentDepartmentId, (deptId) => {
  if (selectedMaterialId.value) {
    router.replace({
      name: materialTabRouteNames[activeTab.value],
      params: { departmentId: currentDepartmentId.value },
    })
  }
  if (!deptId) return
  if (hydrateMaterialsFromCache(deptId)) {
    void loadData({ silent: true })
  } else {
    materials.value = []
    categories.value = []
    void loadData()
  }
})

watch(
  () => route.name,
  (name) => {
    if (!name) return
    if (isUserMaterialsBrowseOnly.value) {
      const tab = routeNameToMaterialTab[String(name)]
      if (tab && tab !== 'all' && !selectedMaterialId.value) {
        router.replace({
          name: 'MaterialsTabAll',
          params: { departmentId: currentDepartmentId.value },
          query: route.query,
        })
        return
      }
      activeTab.value = 'all'
      enforceUserMaterialsListRoute()
      return
    }
    const tab = routeNameToMaterialTab[String(name)]
    if (tab) {
      activeTab.value = tab
      localStorage.setItem(lastTabStorageKey.value, tab)
      return
    }
    if (String(name) === 'Materials' && !selectedMaterialId.value) {
      const lastTab = readLastMaterialTab()
      if (lastTab !== 'all') {
        router.replace({
          name: materialTabRouteNames[lastTab],
          params: { departmentId: currentDepartmentId.value },
          query: route.query,
        })
      }
    }
  },
  { immediate: true }
)

watch(selectedMaterialId, (id, prevId) => {
  if (id) {
    lastOpenMaterialDetailId = id
    stripQueryFromDetailRoute()
    return
  }
  const closedDetailId = prevId ?? lastOpenMaterialDetailId
  if (!closedDetailId) return
  lastOpenMaterialDetailId = null
  skipNextMountedListLoad = true
  void loadData({ silent: true })
}, { immediate: true })

// Query ?new=1: Wizard direkt öffnen (z.B. vom Dashboard)
watch(
  () => route.query.new,
  (val) => {
    if (val === '1') {
      const q = { ...route.query }
      delete q.new
      router.replace({ path: route.path, query: q })
      if (canManageMaterials.value && !showCreateWizard.value) {
        openCreateWizard()
      }
    }
  },
  { immediate: true }
)

// from=dashboard: Bei Schliessen ohne Speichern zurück zum Dashboard
watch(showCreateWizard, (isOpen) => {
  if (!isOpen && route.query.from === 'dashboard' && currentDepartmentId.value && !materialJustCreated.value) {
    router.replace(`/${currentDepartmentId.value}`)
  }
  if (!isOpen) materialJustCreated.value = false
})

// Lifecycle
onMounted(() => {
  if (skipNextMountedListLoad) {
    skipNextMountedListLoad = false
    return
  }
  if (selectedMaterialId.value) return
  void loadData()
})
</script>

<style scoped src="@/styles/materials-view.css"></style>
<style scoped>
.materials-view--detail {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
}

.materials-view--detail :deep(.material-detail-view) {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.materials-view-composition-intro {
  margin: 0 0 12px;
  line-height: 1.45;
}

.materials-view-combo-guide-steps {
  margin: 0 0 8px;
  padding-left: 1.25rem;
  line-height: 1.5;
}

.materials-view-combo-guide-steps li + li {
  margin-top: 8px;
}

.materials-view-error {
  padding: 24px;
  text-align: center;
}
</style>
