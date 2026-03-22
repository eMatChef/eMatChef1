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
    />

    <!-- Liste View -->
    <div v-else class="list-view">
      <!-- Header -->
      <header class="page-header">
        <div class="header-content">
          <div>
            <h1>Materialien</h1>
            <p class="description">Verwalten Sie Ihr gesamtes Material-Inventar</p>
          </div>
          <button @click="openCreateWizard" class="btn-primary">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>Neues Material</span>
          </button>
        </div>
      </header>

      <!-- Tab Navigation -->
      <div class="material-tabs">
        <button 
          class="material-tab" 
          :class="{ active: activeTab === 'combos' }" 
          @click="selectTab('combos')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 21L12 3l9 18H3z"/>
          </svg>
          Kombos
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
          Alle Artikel
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
          Virtuelle Kobis
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
          Verbrauchsmaterial
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
          Esswaren
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
          Regale
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
            placeholder="Material suchen (material:, aktivität:, reparatur:)"
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
              Beide
            </button>
            <button 
              class="filter-chip" 
              :class="{ active: comboFilter === 'physical' }" 
              @click="comboFilter = 'physical'"
            >
              Physisch
            </button>
            <button 
              class="filter-chip" 
              :class="{ active: comboFilter === 'virtual' }" 
              @click="comboFilter = 'virtual'"
            >
              Virtuell
            </button>
          </div>
          <select v-model="selectedCategory" class="filter-select">
            <option value="">Alle Kategorien</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.parent_id ? '↳ ' : '' }}{{ cat.name }} ({{ cat.material_count }})
            </option>
          </select>
          
          <select v-model="selectedCondition" class="filter-select">
            <option value="">Alle Zustände</option>
            <option value="ok">OK</option>
            <option value="defect">Defekt</option>
            <option value="repair">Reparatur</option>
            <option value="lost">Verloren</option>
          </select>
          
          <button
            @click="resetFilters"
            class="reset-btn"
            :style="{ visibility: hasActiveFilters ? 'visible' : 'hidden' }"
            :aria-hidden="!hasActiveFilters"
          >
            Filter zurücksetzen
          </button>
        </div>
      </div>

      <!-- Content Area (zentriert Empty State vertikal) -->
      <div class="content-area">
        <!-- Tab: Regale (storage-centric view) -->
        <div v-if="activeTab === 'storage'" class="storage-tab-content">
          <StorageTreeView :department-id="currentDepartmentId" />
        </div>

        <!-- Loading State -->
        <div v-else-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>Materialien werden geladen...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p class="error-message">{{ error }}</p>
        <button @click="loadData" class="retry-btn">Erneut versuchen</button>
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
        <h2>Noch keine Materialien</h2>
        <p>Erfassen Sie Ihr erstes Material, um Ihr Inventar zu starten.</p>
        <button @click="openCreateWizard" class="btn-primary btn-large">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
          Erstes Material erfassen
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
        <h2>Keine Treffer</h2>
        <p>Keine Materialien entsprechen Ihren Filterkriterien.</p>
        <button @click="resetFilters" class="btn-secondary">Filter zurücksetzen</button>
      </div>

      <!-- Materials Table -->
      <div v-else class="materials-table-wrapper">
          <table class="materials-table">
            <thead>
              <tr>
                <th v-if="showComboColumns" class="col-expand"></th>
                <th class="col-code">Code</th>
                <th class="col-name">Name</th>
                <th v-if="showComboColumns" class="col-type">Typ</th>
                <th class="col-category">Kategorie</th>
                <th class="col-stock">Total</th>
                <th v-if="showComboColumns" class="col-stock-sm">Kombo</th>
                <th class="col-stock-sm">Draussen</th>
                <th class="col-stock-sm">Reparatur</th>
                <th class="col-stock-sm">Verfügbar</th>
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
                  <!-- Expand Button (nur bei Kombos) -->
                  <td v-if="showComboColumns" class="col-expand">
                    <button 
                      class="expand-btn"
                      :class="{ expanded: expandedCombos.has(material.id) }"
                      @click.stop="toggleComboExpand(material.id)"
                    >
                      <svg class="table-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                      </svg>
                    </button>
                  </td>
                  <td class="col-code">
                    <div class="code-cell">
                      <span v-if="material.barcode_tag" class="code-badge">{{ material.barcode_tag }}</span>
                      <PublicQrTag :url="material.public_url" :code="material.public_code" :size="56" />
                    </div>
                  </td>
                  <td class="col-name">
                    <div class="name-cell">
                      <div class="material-icon" :class="getMaterialIconClass(material)">
                        <svg v-if="material.is_tent" class="table-icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M3 21L12 3l9 18H3z"/>
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
                        </span>
                        <span v-if="material.manufacturer" class="material-manufacturer">{{ material.manufacturer }}</span>
                        <span v-if="material.open_loss_reports > 0" class="loss-reported-badge">
                          Verlust gemeldet ({{ material.open_loss_qty }} Stk.)
                        </span>
                      </div>
                    </div>
                  </td>
                  <td v-if="showComboColumns" class="col-type">
                    <span class="combo-type-badge" :class="material.material_type">
                      {{ material.material_type === 'physical_combo' ? 'Physisch' : 'Virtuell' }}
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
                  <td class="col-stock-sm">
                    <span v-if="material.issued_out > 0" class="stock-badge issued">{{ material.issued_out }}</span>
                    <span v-else class="stock-zero">–</span>
                  </td>
                  <td class="col-stock-sm">
                    <span v-if="material.repair_stock > 0" class="stock-badge repair">{{ material.repair_stock }}</span>
                    <span v-else class="stock-zero">–</span>
                  </td>
                  <td class="col-stock-sm">
                    <span class="stock-badge available" :class="{ low: material.available < 3 && material.total_stock > 0, empty: material.available <= 0 && material.total_stock > 0 }">
                      {{ material.available }}
                    </span>
                  </td>
                  <td class="col-actions">
                    <button class="action-btn" @click.stop="openMaterialDetail(material)" title="Details öffnen">
                      <svg class="table-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                      </svg>
                    </button>
                  </td>
                </tr>

                <!-- Expanded Combo Components -->
                <tr 
                  v-if="showComboColumns && expandedCombos.has(material.id)"
                  class="combo-components-row"
                >
                  <td :colspan="showComboColumns ? 11 : 8">
                    <div class="combo-components-container">
                      <div v-if="comboComponentsLoading.has(material.id)" class="combo-loading">
                        <div class="spinner-sm"></div>
                        Komponenten werden geladen...
                      </div>
                      <div v-else-if="(comboComponentsCache.get(material.id) || []).length === 0" class="combo-empty">
                        Keine Komponenten zugewiesen
                      </div>
                      <table v-else class="combo-sub-table">
                        <thead>
                          <tr>
                            <th>Komponente</th>
                            <th>Seriennummer</th>
                            <th>Menge</th>
                            <th>Zuordnung</th>
                            <th>Status</th>
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
                              <span class="assignment-badge" :class="comp.assignment_mode">
                                {{ assignmentLabels[comp.assignment_mode] || comp.assignment_mode }}
                              </span>
                            </td>
                            <td>
                              <span v-if="comp.is_awaiting" class="status-dot awaiting" title="Wartet auf Zuweisung"></span>
                              <span v-else-if="comp.is_assigned" class="status-dot assigned" title="Zugewiesen"></span>
                              <span v-else class="status-dot linked" title="Verknüpft"></span>
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
          
          <p class="table-hint">Doppelklick auf eine Zeile öffnet die Detailansicht</p>
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
import { ref, computed, reactive, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getMaterials, getComboComponents, type Material, type ComboComponent } from '@/api/materials'
import { getCategories, type Category } from '@/api/categories'
import MaterialCreateWizard from '@/components/material/MaterialCreateWizard.vue'
import MaterialDetailView from '@/components/material/MaterialDetailView.vue'
import StorageTreeView from '@/components/storage/StorageTreeView.vue'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import { useDetailTabsStore } from '@/stores/detailTabs'
import '@/styles/material-wizard.css'

const route = useRoute()
const detailTabsStore = useDetailTabsStore()
const router = useRouter()
const currentDepartmentId = computed(() => route.params.departmentId as string)
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

// Combo Expand State
const expandedCombos = ref<Set<string>>(new Set())
const comboComponentsCache = ref<Map<string, ComboComponent[]>>(new Map())
const comboComponentsLoading = ref<Set<string>>(new Set())

// Detail View State (gesteuert über Route-Parameter)
const selectedMaterialId = computed(() => route.params.materialId as string | undefined || null)
const showDetailView = computed(() => !!selectedMaterialId.value)

// Assignment Mode Labels
const assignmentLabels: Record<string, string> = {
  fixed: 'Fest verbaut',
  assigned: 'Zugewiesen',
  on_issue: 'Bei Ausgabe',
  bulk: 'Mengenware'
}

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

// Combo-Spalten anzeigen (Kombos + Virtuelle Kobis)
const showComboColumns = computed(() => 
  activeTab.value === 'combos' || activeTab.value === 'virtual_combos'
)

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
  const baseFilters = searchQuery.value || selectedCategory.value || selectedCondition.value
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
    error.value = err.response?.data?.error || 'Fehler beim Laden der Materialien'
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
  if (material.is_tent) return 'tent'
  if (material.is_food) return 'food'
  if (material.is_consumable) return 'consumable'
  return ''
}

async function toggleComboExpand(materialId: string) {
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
      console.error('Fehler beim Laden der Komponenten:', err)
      comboComponentsCache.value.set(materialId, [])
      comboComponentsCache.value = new Map(comboComponentsCache.value)
    } finally {
      comboComponentsLoading.value.delete(materialId)
      comboComponentsLoading.value = new Set(comboComponentsLoading.value)
    }
  }
}

function resetFilters() {
  searchQuery.value = ''
  selectedCategory.value = ''
  selectedCondition.value = ''
  comboFilter.value = 'physical'
}

function selectTab(tab: MaterialTab) {
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
  const raw = localStorage.getItem(lastTabStorageKey.value)
  if (raw && raw in materialTabRouteNames) {
    return raw as MaterialTab
  }
  return 'all'
}

function openCreateWizard() {
  wizardOpenNonce.value += 1
  showCreateWizard.value = true
}

async function handleMaterialCreated(_material: Material) {
  materialJustCreated.value = true
  await loadData()
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
      label: m?.name || `Material ${matId}`,
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

watch(
  () => route.query.q,
  (q) => {
    if (route.path.includes('/materials')) {
      searchQuery.value = (q as string) ?? ''
    }
  },
  { immediate: true }
)

// Query ?new=1: Wizard direkt öffnen (z.B. vom Dashboard)
watch(
  () => route.query.new,
  (val) => {
    if (val === '1' && !showCreateWizard.value) {
      openCreateWizard()
      const q = { ...route.query }
      delete q.new
      router.replace({ path: route.path, query: q })
    }
  },
  { immediate: true }
)

// from=dashboard: Bei Schliessen ohne Speichern zurück zum Dashboard
watch(showCreateWizard, (isOpen) => {
  if (!isOpen && route.query.from === 'dashboard' && currentDepartmentId.value && !materialJustCreated.value) {
    router.replace(`/${currentDepartmentId.value}/dashboard`)
  }
  if (!isOpen) materialJustCreated.value = false
})

// Lifecycle
onMounted(() => {
  loadData()
})
</script>

<style scoped src="@/styles/materials-view.css"></style>
