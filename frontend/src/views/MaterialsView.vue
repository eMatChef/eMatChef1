<template>
  <div class="materials-view">
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
    <div v-else class="list-view">
      <!-- Header -->
      <header class="page-header">
        <div class="header-content">
          <div>
            <h1>{{ t('materialsView.title') }}</h1>
            <p v-if="!isUserMaterialsBrowseOnly" class="description">{{ t('materialsView.description') }}</p>
          </div>
          <button v-if="canManageMaterials" @click="openCreateWizard" class="btn-primary">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>{{ t('materialsView.newMaterial') }}</span>
          </button>
        </div>
      </header>

      <!-- Tab Navigation (User: nur «Alle Artikel» — Tabs ausgeblendet) -->
      <div v-if="!isUserMaterialsBrowseOnly" class="material-tabs">
        <button 
          class="material-tab" 
          :class="{ active: activeTab === 'combos' }" 
          @click="selectTab('combos')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 21L12 3l9 18H3z"/>
          </svg>
          {{ t('materialsView.tabCombos') }}
          <span class="tab-count">{{ comboCount }}</span>
        </button>
        <button 
          class="material-tab" 
          :class="{ active: activeTab === 'all' }" 
          @click="selectTab('all')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
          </svg>
          {{ t('materialsView.tabAll') }}
          <span class="tab-count">{{ allCount }}</span>
        </button>
        <button 
          class="material-tab" 
          :class="{ active: activeTab === 'virtual_combos' }" 
          @click="selectTab('virtual_combos')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 21L12 3l9 18H3z"/>
          </svg>
          {{ t('materialsView.tabVirtualCombos') }}
          <span class="tab-count">{{ virtualComboCount }}</span>
        </button>
        <button 
          class="material-tab" 
          :class="{ active: activeTab === 'consumables' }" 
          @click="selectTab('consumables')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><path d="M8 12h8"/>
          </svg>
          {{ t('materialsView.tabConsumables') }}
          <span class="tab-count">{{ consumableCount }}</span>
        </button>
        <button 
          class="material-tab" 
          :class="{ active: activeTab === 'food' }" 
          @click="selectTab('food')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8zM6 1v3M10 1v3M14 1v3"/>
          </svg>
          {{ t('materialsView.tabFood') }}
          <span class="tab-count">{{ foodCount }}</span>
        </button>
        <button 
          class="material-tab" 
          :class="{ active: activeTab === 'storage' }" 
          @click="selectTab('storage')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          {{ t('materialsView.tabStorage') }}
        </button>
      </div>

      <!-- Search & Filter Bar (nicht bei Regale-Tab) -->
      <div v-if="activeTab !== 'storage'" class="filter-bar">
        <div class="search-box">
          <GlobalSearchInput
            mode="inline"
            :department-id="currentDepartmentId"
            default-type="material"
            v-model="searchQuery"
            :placeholder="t('materialsView.searchListPlaceholder')"
          />
        </div>
        
        <div class="filter-group">
          <!-- Kombos-Tab: Filter Physisch/Virtuell/Beide -->
          <div v-if="activeTab === 'combos'" class="combo-type-filter">
            <button 
              class="filter-chip" 
              :class="{ active: comboFilter === 'all' }" 
              @click="comboFilter = 'all'"
            >
              {{ t('materialsView.comboFilterBoth') }}
            </button>
            <button 
              class="filter-chip" 
              :class="{ active: comboFilter === 'physical' }" 
              @click="comboFilter = 'physical'"
            >
              {{ t('materialsView.comboFilterPhysical') }}
            </button>
            <button 
              class="filter-chip" 
              :class="{ active: comboFilter === 'virtual' }" 
              @click="comboFilter = 'virtual'"
            >
              {{ t('materialsView.comboFilterVirtual') }}
            </button>
          </div>
          <select v-if="!isUserMaterialsBrowseOnly" v-model="selectedCategory" class="filter-select">
            <option value="">{{ t('materialsView.filterAllCategories') }}</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.parent_id ? '↳ ' : '' }}{{ cat.name }} ({{ cat.material_count }})
            </option>
          </select>
          
          <select v-if="!isUserMaterialsBrowseOnly" v-model="selectedCondition" class="filter-select">
            <option value="">{{ t('materialsView.filterAllConditions') }}</option>
            <option value="ok">{{ t('materialsView.conditionOk') }}</option>
            <option value="defect">{{ t('materialsView.conditionDefect') }}</option>
            <option value="repair">{{ t('materialsView.conditionRepair') }}</option>
            <option value="lost">{{ t('materialsView.conditionLost') }}</option>
          </select>
          
          <button
            @click="resetFilters"
            class="reset-btn"
            :style="{ visibility: hasActiveFilters ? 'visible' : 'hidden' }"
            :aria-hidden="!hasActiveFilters"
          >
            {{ t('materialsView.resetFilters') }}
          </button>
        </div>
      </div>

      <!-- Content Area (zentriert Empty State vertikal) -->
      <div class="content-area">
        <!-- Tab: Regale (storage-centric view) -->
        <div v-if="activeTab === 'storage'" class="storage-tab-content">
          <StorageTreeView
            :department-id="currentDepartmentId"
            :open-material-without-batch-query="true"
          />
        </div>

        <!-- Loading State -->
        <div v-else-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>{{ t('materialsView.loading') }}</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p class="error-message">{{ error }}</p>
        <button @click="loadData" class="retry-btn">{{ t('common.retry') }}</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="materials.length === 0" class="empty-state">
        <div class="empty-illustration">
          <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
            <rect x="20" y="30" width="80" height="60" rx="4" stroke="#d1d5db" stroke-width="2" stroke-dasharray="4 4"/>
            <rect x="35" y="45" width="50" height="8" rx="2" fill="#e5e7eb"/>
            <rect x="35" y="60" width="35" height="6" rx="2" fill="#e5e7eb"/>
            <rect x="35" y="72" width="25" height="6" rx="2" fill="#e5e7eb"/>
            <circle cx="90" cy="85" r="20" fill="#10b981" fill-opacity="0.15"/>
            <path d="M90 75V95M80 85H100" stroke="#10b981" stroke-width="3" stroke-linecap="round"/>
          </svg>
        </div>
        <h2>{{ t('materialsView.emptyTitle') }}</h2>
        <p>{{ t('materialsView.emptyDescription') }}</p>
        <button v-if="canManageMaterials" @click="openCreateWizard" class="btn-primary btn-large">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
          {{ t('materialsView.emptyCta') }}
        </button>
      </div>

      <!-- No Results State -->
      <div v-else-if="filteredMaterials.length === 0" class="empty-state">
        <div class="empty-illustration">
          <svg width="100" height="100" viewBox="0 0 100 100" fill="none">
            <circle cx="45" cy="45" r="25" stroke="#d1d5db" stroke-width="3"/>
            <line x1="63" y1="63" x2="80" y2="80" stroke="#d1d5db" stroke-width="3" stroke-linecap="round"/>
            <line x1="35" y1="45" x2="55" y2="45" stroke="#e5e7eb" stroke-width="3" stroke-linecap="round"/>
          </svg>
        </div>
        <h2>{{ t('materialsView.noResultsTitle') }}</h2>
        <p>{{ t('materialsView.noResultsDescription') }}</p>
        <button @click="resetFilters" class="btn-secondary">{{ t('materialsView.resetFilters') }}</button>
      </div>

      <!-- Materials Table -->
      <div v-else class="materials-table-wrapper">
          <table class="materials-table">
            <thead>
              <tr>
                <th v-if="showComboExpandColumn" class="col-expand"></th>
                <th class="col-name">{{ t('common.name') }}</th>
                <th v-if="showComboColumns" class="col-type">{{ t('materialsView.colType') }}</th>
                <th class="col-category">{{ t('materialsView.colCategory') }}</th>
                <th class="col-stock">{{ t('materialsView.colTotal') }}</th>
                <th v-if="showComboColumns" class="col-stock-sm">{{ t('materialsView.colCombo') }}</th>
                <th v-if="showStockDetailColumns" class="col-stock-sm">{{ t('materialsView.colIssuedOut') }}</th>
                <th v-if="showStockDetailColumns" class="col-stock-sm">{{ t('materialsView.colRepair') }}</th>
                <th v-if="showStockDetailColumns" class="col-stock-sm">{{ t('materialsView.colAvailable') }}</th>
                <th class="col-actions"></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="material in filteredMaterials" :key="material.id">
                <!-- Material Row -->
                <tr 
                  class="material-row"
                  @dblclick="openMaterialDetail(material)"
                >
                  <!-- Aufklappen: Komponenten (nur Kombos) -->
                  <td v-if="showComboExpandColumn" class="col-expand">
                    <button
                      v-if="isComboMaterial(material)"
                      type="button"
                      class="expand-btn"
                      :class="{ expanded: expandedCombos.has(material.id) }"
                      :aria-expanded="expandedCombos.has(material.id)"
                      :title="t('materialsView.expandComboTitle')"
                      @click.stop="toggleComboExpand(material.id)"
                    >
                      <svg class="table-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                      </svg>
                    </button>
                  </td>
                  <td class="col-name">
                    <div class="name-cell">
                      <div class="material-icon" :class="getMaterialIconClass(material)">
                        <svg v-if="material.is_container" class="table-icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                          <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                          <line x1="12" y1="22.08" x2="12" y2="12"/>
                        </svg>
                        <svg v-else-if="material.is_food" class="table-icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8zM6 1v3M10 1v3M14 1v3"/>
                        </svg>
                        <svg v-else-if="material.is_consumable" class="table-icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10"/><path d="M8 12h8"/>
                        </svg>
                        <svg v-else class="table-icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                      </div>
                      <div class="name-info">
                        <span class="material-name">
                          {{ material.name }}
                          <span v-if="material.is_js_material" class="source-badge">J&amp;S</span>
                          <span v-if="isComboDraft(material)" class="combo-draft-badge">{{ t('materialsView.comboDraftBadge') }}</span>
                        </span>
                        <span v-if="material.manufacturer" class="material-manufacturer">{{ material.manufacturer }}</span>
                        <span v-if="material.open_loss_reports > 0" class="loss-reported-badge">
                          {{ t('materialsView.lossReported', { qty: material.open_loss_qty }) }}
                        </span>
                      </div>
                    </div>
                  </td>
                  <td v-if="showComboColumns" class="col-type">
                    <span class="combo-type-badge" :class="material.material_type">
                      <span class="combo-type-badge-emoji" aria-hidden="true">{{ comboBadgeEmoji({ materialType: material.material_type }) }}</span>
                      {{
                        material.material_type === 'physical_combo'
                          ? t('components.materialDetail.typePhysicalShort')
                          : t('components.materialDetail.typeVirtualShort')
                      }}
                    </span>
                  </td>
                  <td class="col-category">
                    <span v-if="material.category" class="category-tag">{{ material.category.name }}</span>
                    <span v-else class="no-category">-</span>
                  </td>
                  <td class="col-stock">
                    <span class="stock-value" :class="getStockClass(material.total_stock)">
                      {{ material.total_stock }}
                    </span>
                    <span v-if="material.pack_size && material.pack_unit" class="pack-info">
                      {{ Math.floor(material.total_stock / material.pack_size) }} {{ material.pack_unit }}
                    </span>
                  </td>
                  <td v-if="showComboColumns" class="col-stock-sm">
                    <span v-if="material.combo_allocated > 0" class="stock-badge combo">{{ material.combo_allocated }}</span>
                    <span v-else class="stock-zero">–</span>
                  </td>
                  <td v-if="showStockDetailColumns" class="col-stock-sm">
                    <span v-if="material.issued_out > 0" class="stock-badge issued">{{ material.issued_out }}</span>
                    <span v-else class="stock-zero">–</span>
                  </td>
                  <td v-if="showStockDetailColumns" class="col-stock-sm">
                    <span v-if="material.repair_stock > 0" class="stock-badge repair">{{ material.repair_stock }}</span>
                    <span v-else class="stock-zero">–</span>
                  </td>
                  <td v-if="showStockDetailColumns" class="col-stock-sm">
                    <span class="stock-badge available" :class="{ low: material.available < 3 && material.total_stock > 0, empty: material.available <= 0 && material.total_stock > 0 }">
                      {{ material.available }}
                    </span>
                  </td>
                  <td class="col-actions">
                    <button class="action-btn" @click.stop="openMaterialDetail(material)" :title="t('materialsView.titleOpenDetails')">
                      <svg class="table-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                      </svg>
                    </button>
                  </td>
                </tr>

                <!-- Expanded Combo Components -->
                <tr 
                  v-if="showComboExpandColumn && isComboMaterial(material) && expandedCombos.has(material.id)"
                  class="combo-components-row"
                >
                  <td :colspan="materialTableColspan">
                    <div class="combo-components-container">
                      <div v-if="comboComponentsLoading.has(material.id)" class="combo-loading">
                        <div class="spinner-sm"></div>
                        {{ t('materialsView.comboComponentsLoading') }}
                      </div>
                      <div v-else-if="(comboComponentsCache.get(material.id) || []).length === 0" class="combo-empty">
                        {{ t('materialsView.comboComponentsEmpty') }}
                      </div>
                      <table v-else class="combo-sub-table">
                        <thead>
                          <tr>
                            <th>{{ t('materialsView.subColComponent') }}</th>
                            <th>{{ t('common.serialNumber') }}</th>
                            <th>{{ t('materialsView.subColQty') }}</th>
                            <th>{{ t('materialsView.subColAssignment') }}</th>
                            <th>{{ t('common.status') }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="comp in comboComponentsCache.get(material.id)" :key="comp.id">
                            <td class="comp-name">
                              <span 
                                class="comp-link" 
                                @click="openMaterialDetailById(comp.component_material.id)"
                              >
                                {{ comp.component_material.name }}
                              </span>
                            </td>
                            <td>
                              <span v-if="comp.component_batch?.serial_number" class="serial-code">
                                {{ comp.component_batch.serial_number }}
                              </span>
                              <span v-else class="no-serial">–</span>
                            </td>
                            <td>{{ comp.qty }}</td>
                            <td>
                              <span class="assignment-badge" :class="comp.assignment_mode === 'fixed' ? 'fix' : comp.assignment_mode">
                                {{ assignmentLabels[comp.assignment_mode] || comp.assignment_mode }}
                              </span>
                            </td>
                            <td>
                              <span v-if="comp.is_awaiting" class="status-dot awaiting" :title="t('materialsView.statusAwaitingTitle')"></span>
                              <span v-else-if="comp.is_assigned" class="status-dot assigned" :title="t('materialsView.statusAssignedTitle')"></span>
                              <span v-else class="status-dot linked" :title="t('materialsView.statusLinkedTitle')"></span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
          
          <p class="table-hint">{{ t('materialsView.tableHint') }}</p>
        </div>
      </div>
    </div>

    <div v-if="showPostCreateCompositionModal && postCreateCompositionContext" class="modal-overlay">
      <div class="modal-dialog">
        <h3>{{ t('materialsView.modalPostCreateCompositionTitle') }}</h3>
        <p class="text-muted">
          {{
            t('materialsView.modalPostCreateCompositionIntro', {
              combo: postCreateCompositionComboName,
              article: postCreateCompositionContext.material.name,
            })
          }}
        </p>
        <div class="form-group">
          <label>{{ t('materialsView.labelQtyInCombo') }}</label>
          <input
            v-model.number="postCreateCompositionQty"
            type="number"
            min="1"
            :max="postCreateCompositionMaxQty ?? undefined"
            class="form-input"
            @input="clampPostCreateCompositionQty"
            @blur="clampPostCreateCompositionQty"
          />
          <p v-if="postCreateCompositionMaxQty !== null && postCreateCompositionMaxQty > 0" class="batch-field-hint">
            {{ t('components.materialDetail.hintMaxQty', { n: postCreateCompositionMaxQty }) }}
          </p>
          <p v-else-if="postCreateCompositionMaxQty === 0" class="error-text">
            {{ t('components.materialDetail.errAddCompositionNoStock') }}
          </p>
        </div>
        <p v-if="postCreateCompositionError" class="error-text">{{ postCreateCompositionError }}</p>
        <div class="modal-actions">
          <button type="button" class="btn-secondary btn-sm" @click="closePostCreateCompositionModal">
            {{ t('materialsView.btnPostCreateCompositionSkip') }}
          </button>
          <button
            type="button"
            class="btn-primary btn-sm"
            :disabled="!canSubmitPostCreateComposition || postCreateCompositionSubmitting"
            @click="submitPostCreateComposition"
          >
            {{
              postCreateCompositionSubmitting
                ? t('materialsView.postCreateCompositionSubmitting')
                : t('materialsView.btnPostCreateCompositionAdd')
            }}
          </button>
        </div>
      </div>
    </div>

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
import StorageTreeView from '@/components/storage/StorageTreeView.vue'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { useToast } from '@/composables/useToast'
import { useListSearchQueryRoute } from '@/composables/useListSearchQueryRoute'
import { useAuthStore } from '@/stores/auth'
import { isDepartmentBasicMemberRole } from '@/composables/useDepartmentMemberRole'
import { isComboMaterial as isComboMaterialType, comboBadgeEmoji } from '@/utils/comboDisplay'
import '@/styles/material-wizard.css'

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

// State
const materials = ref<Material[]>([])
const categories = ref<Category[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)

// Tab State
const activeTab = ref<MaterialTab>('all')

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
    await loadData()
  } catch (err: unknown) {
    const ax = err as { response?: { data?: { error?: string } } }
    postCreateCompositionError.value = ax.response?.data?.error || t('materialsView.errLinkComponent')
  } finally {
    postCreateCompositionSubmitting.value = false
  }
}

// Combo Expand State
const expandedCombos = ref<Set<string>>(new Set())
const comboComponentsCache = ref<Map<string, ComboComponent[]>>(new Map())
const comboComponentsLoading = ref<Set<string>>(new Set())

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

// Combo-Spalten: Typ + Kombo-Bestand (Tabs „Kombos“ / „Virtuelle Kobis“)
const showComboColumns = computed(() =>
  activeTab.value === 'combos' || activeTab.value === 'virtual_combos'
)

/** Pfeil zum Aufklappen der Komponenten auch unter „Alle Artikel“. */
const showComboExpandColumn = computed(() =>
  activeTab.value === 'all' || activeTab.value === 'combos' || activeTab.value === 'virtual_combos'
)

const materialTableColspan = computed(() => {
  if (isUserMaterialsBrowseOnly.value) {
    let cols = 3 // name, category, total
    if (showComboExpandColumn.value) cols += 1
    cols += 1 // actions
    return cols
  }
  if (showComboColumns.value) return 10
  if (showComboExpandColumn.value) return 8
  return 7
})

function isComboMaterial(material: Material): boolean {
  return isComboMaterialType(material)
}

function isComboDraft(material: Material): boolean {
  return isComboMaterial(material) && material.combo_status === 'draft'
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
async function loadData() {
  if (!currentDepartmentId.value) return
  
  isLoading.value = true
  error.value = null
  
  try {
    const [materialsData, categoriesData] = await Promise.all([
      getMaterials(currentDepartmentId.value),
      getCategories(currentDepartmentId.value)
    ])
    
    materials.value = materialsData
    categories.value = categoriesData
  } catch (err: any) {
    error.value = err.response?.data?.error || t('materialsView.errLoadList')
  } finally {
    isLoading.value = false
  }
}

function getStockClass(stock: number): string {
  if (stock === 0) return 'empty'
  if (stock < 5) return 'low'
  return 'ok'
}

function getMaterialIconClass(material: Material): string {
  if (material.is_container) return 'container'
  if (material.is_food) return 'food'
  if (material.is_consumable) return 'consumable'
  return ''
}

async function toggleComboExpand(materialId: string) {
  const row = materials.value.find((m) => m.id === materialId)
  if (!row || !isComboMaterial(row)) return

  if (expandedCombos.value.has(materialId)) {
    expandedCombos.value.delete(materialId)
    // Trigger reactivity
    expandedCombos.value = new Set(expandedCombos.value)
    return
  }

  expandedCombos.value.add(materialId)
  expandedCombos.value = new Set(expandedCombos.value)

  // Lazy-load components if not cached
  if (!comboComponentsCache.value.has(materialId)) {
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

  await loadData()

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
  // Tab bleibt offen, Änderungen bleiben erhalten (keep-alive)
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
  }
}

// Tab-Registrierung für Detail-Ansicht (Tab bleibt offen bei Zurück zur Liste)
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
watch(currentDepartmentId, () => {
  if (selectedMaterialId.value) {
    router.replace({
      name: materialTabRouteNames[activeTab.value],
      params: { departmentId: currentDepartmentId.value },
    })
  }
  loadData()
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

watch(selectedMaterialId, (id) => {
  if (id) stripQueryFromDetailRoute()
})

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
  loadData()
})
</script>

<style scoped src="@/styles/materials-view.css"></style>
