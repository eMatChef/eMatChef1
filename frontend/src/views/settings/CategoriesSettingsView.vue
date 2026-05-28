<template>
  <div class="categories-settings">
    <div class="settings-header">
      <div>
        <h1>{{ t('settings.categories.title') }}</h1>
        <p class="subtitle">{{ t('settings.categories.subtitle') }}</p>
      </div>
      <button class="btn-primary" @click="openCreateModal">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"/>
          <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        {{ t('settings.categories.newCategory') }}
      </button>
    </div>

    <!-- Suchleiste -->
    <div class="search-bar">
      <div class="search-input-wrapper">
        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/>
          <path d="m21 21-4.35-4.35"/>
        </svg>
        <input 
          v-model="searchQuery" 
          type="text" 
          :placeholder="t('settings.categories.searchPlaceholder')"
          class="search-input"
        />
      </div>
      <div class="category-count">
        {{ t('settings.categories.count', { count: filteredCategories.length }) }}
      </div>
    </div>

    <!-- Kategorien-Liste -->
    <div class="categories-list" v-if="!isLoading">
      <!-- Hauptkategorien -->
      <div 
        v-for="mainCat in mainCategories" 
        :key="mainCat.id"
        class="category-group"
      >
        <div class="category-item main-category" @click="toggleExpand(mainCat.id)">
          <div class="category-left">
            <button class="expand-btn" :class="{ expanded: expandedCategories.has(mainCat.id) }">
              <svg v-if="getChildren(mainCat.id).length > 0" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
              </svg>
            </button>
            <div class="category-icon main">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
              </svg>
            </div>
            <div class="category-info">
              <span class="category-name">{{ mainCat.name }}</span>
              <span class="category-meta">
                {{ t('settings.categories.articlesCount', { count: mainCat.material_count }) }}
                <span v-if="getChildren(mainCat.id).length > 0" class="child-count">
                  · {{ t('settings.categories.subcategoriesCount', { count: getChildren(mainCat.id).length }) }}
                </span>
              </span>
            </div>
          </div>
          <div class="category-actions">
            <button class="action-btn" @click.stop="openEditModal(mainCat)" :title="t('common.edit')">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
            </button>
            <button class="action-btn add-child" @click.stop="openCreateChildModal(mainCat)" :title="t('settings.categories.addSubcategory')">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <button class="action-btn delete" @click.stop="confirmDelete(mainCat)" :title="t('common.delete')">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Unterkategorien -->
        <transition name="expand">
          <div v-if="expandedCategories.has(mainCat.id)" class="subcategories">
            <div 
              v-for="subCat in getChildren(mainCat.id)" 
              :key="subCat.id"
              class="category-item sub-category"
            >
              <div class="category-left">
                <div class="subcategory-line"></div>
                <div class="category-icon sub">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                  </svg>
                </div>
                <div class="category-info">
                  <span class="category-name">{{ subCat.name }}</span>
                  <span class="category-meta">{{ t('settings.categories.articlesCount', { count: subCat.material_count }) }}</span>
                </div>
              </div>
              <div class="category-actions">
                <button class="action-btn" @click.stop="openEditModal(subCat)" :title="t('common.edit')">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
                <button class="action-btn delete" @click.stop="confirmDelete(subCat)" :title="t('common.delete')">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Leerer Zustand -->
      <div v-if="filteredCategories.length === 0" class="empty-state">
        <div class="empty-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
          </svg>
        </div>
        <h3>{{ t('settings.categories.emptyTitle') }}</h3>
        <p>{{ t('settings.categories.emptyDescription') }}</p>
        <button class="btn-primary" @click="openCreateModal">
          {{ t('settings.categories.firstCategory') }}
        </button>
      </div>
    </div>

    <!-- Ladezustand -->
    <div v-else class="loading-state">
      <div class="spinner"></div>
      <p>{{ t('settings.categories.loading') }}</p>
    </div>

    <!-- Kategorie Modal -->
    <CategoryModal
      v-if="showModal"
      :department-id="departmentId"
      :category="editingCategory"
      :default-parent-id="defaultParentId"
      @close="closeModal"
      @saved="handleCategorySaved"
    />

    <!-- Lösch-Bestätigung -->
    <div v-if="showDeleteConfirm" class="modal-overlay">
      <div class="confirm-dialog">
        <h3>{{ t('settings.categories.deleteConfirmTitle') }}</h3>
        <p>
          {{ t('settings.categories.deleteConfirmMessage', { name: deletingCategory?.name }) }}
        </p>
        <p v-if="deletingCategory && deletingCategory.material_count > 0" class="warning">
          ⚠️ {{ t('settings.categories.deleteWarning', { count: deletingCategory.material_count }) }}
        </p>
        <div class="confirm-actions">
          <button class="btn-secondary" @click="showDeleteConfirm = false">{{ t('common.cancel') }}</button>
          <button class="btn-danger" @click="executeDelete" :disabled="isDeleting">
            {{ isDeleting ? t('common.deleteInProgress') : t('common.delete') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { getCategories, deleteCategory, type Category } from '@/api/categories'
import CategoryModal from '@/components/CategoryModal.vue'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const departmentId = computed(() => route.params.departmentId as string)

const categories = ref<Category[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const expandedCategories = ref(new Set<string>())

// Modal State
const showModal = ref(false)
const editingCategory = ref<Category | null>(null)
const defaultParentId = ref<string | null>(null)

// Delete State
const showDeleteConfirm = ref(false)
const deletingCategory = ref<Category | null>(null)
const isDeleting = ref(false)

// Gefilterte Kategorien
const filteredCategories = computed(() => {
  if (!searchQuery.value.trim()) return categories.value
  const query = searchQuery.value.toLowerCase()
  return categories.value.filter(c => c.name.toLowerCase().includes(query))
})

// Hauptkategorien (ohne Parent)
const mainCategories = computed(() => {
  return filteredCategories.value.filter(c => !c.parent_id)
})

// Unterkategorien für eine Hauptkategorie
function getChildren(parentId: string): Category[] {
  return filteredCategories.value.filter(c => c.parent_id === parentId)
}

// Expand/Collapse
function toggleExpand(categoryId: string) {
  if (expandedCategories.value.has(categoryId)) {
    expandedCategories.value.delete(categoryId)
  } else {
    expandedCategories.value.add(categoryId)
  }
}

// Modal Funktionen
function openCreateModal() {
  editingCategory.value = null
  defaultParentId.value = null
  showModal.value = true
}

function openCreateChildModal(parent: Category) {
  editingCategory.value = null
  defaultParentId.value = parent.id
  showModal.value = true
}

function openEditModal(category: Category) {
  editingCategory.value = category
  defaultParentId.value = null
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingCategory.value = null
  defaultParentId.value = null
}

async function handleCategorySaved(category: Category) {
  closeModal()
  await loadCategories()
  
  // Expandiere die Eltern-Kategorie wenn eine Unterkategorie erstellt wurde
  if (category.parent_id) {
    expandedCategories.value.add(category.parent_id)
  }
}

// Löschen
function confirmDelete(category: Category) {
  deletingCategory.value = category
  showDeleteConfirm.value = true
}

async function executeDelete() {
  if (!deletingCategory.value) return
  
  isDeleting.value = true
  try {
    await deleteCategory(deletingCategory.value.id)
    await loadCategories()
    showDeleteConfirm.value = false
    deletingCategory.value = null
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.categories.deleteError'))
  } finally {
    isDeleting.value = false
  }
}

// Daten laden
async function loadCategories() {
  isLoading.value = true
  try {
    categories.value = await getCategories(departmentId.value)
    
    // Alle Hauptkategorien standardmäßig expandieren
    mainCategories.value.forEach(cat => {
      if (getChildren(cat.id).length > 0) {
        expandedCategories.value.add(cat.id)
      }
    })
  } catch (err) {
    console.error(t('settings.categories.loadError'), err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadCategories()
})
</script>

<style scoped>
.categories-settings {
  min-height: 500px;
}

.settings-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.settings-header h1 {
  font-size: 24px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.settings-header .subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

/* Buttons use shared ui/buttons.css */

.search-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.search-input-wrapper {
  position: relative;
  flex: 1;
  max-width: 400px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
}

/* Search input base uses shared ui/page-layout.css */

.category-count {
  font-size: 13px;
  color: #6b7280;
}

.categories-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.category-group {
  background: #f9fafb;
  border-radius: 10px;
  overflow: hidden;
}

.category-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: white;
  transition: background 0.2s;
}

.category-item:hover {
  background: #f9fafb;
}

.category-item.main-category {
  cursor: pointer;
  border-bottom: 1px solid #f3f4f6;
}

.category-item.sub-category {
  padding-left: 48px;
  background: #fafafa;
}

.category-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.expand-btn {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  transition: transform 0.2s;
}

.expand-btn.expanded {
  transform: rotate(90deg);
}

.category-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
}

.category-icon.main {
  background: #ede9fe;
  color: #7c3aed;
}

.category-icon.sub {
  background: #f3f4f6;
  color: #6b7280;
  width: 32px;
  height: 32px;
}

.category-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.category-name {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
}

.category-meta {
  font-size: 12px;
  color: #9ca3af;
}

.child-count {
  color: #8b5cf6;
}

.category-actions {
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.2s;
}

.category-item:hover .category-actions {
  opacity: 1;
}

.action-btn {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #f3f4f6;
  color: #374151;
}

.action-btn.add-child:hover {
  background: #ede9fe;
  color: #7c3aed;
  border-color: #c4b5fd;
}

.action-btn.delete:hover {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fecaca;
}

.subcategories {
  border-top: 1px solid #f3f4f6;
}

.subcategory-line {
  width: 20px;
  height: 2px;
  background: #e5e7eb;
  margin-left: -8px;
}

/* Expand Transition */
.expand-enter-active,
.expand-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
}

.expand-enter-to,
.expand-leave-from {
  opacity: 1;
  max-height: 500px;
}

/* Empty state base uses shared ui/states.css */

.empty-icon {
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 50%;
  color: #9ca3af;
  margin-bottom: 16px;
}

/* Empty-state title/text typography uses shared ui/states.css */

/* Loading state base uses shared ui/states.css */

/* Modal overlay base uses shared ui/modals.css */

.confirm-dialog {
  background: white;
  border-radius: 12px;
  padding: 24px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.confirm-dialog h3 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px 0;
}

.confirm-dialog p {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 8px 0;
}

.confirm-dialog .warning {
  background: #fef3cd;
  color: #856404;
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 13px;
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}

/* Secondary/danger buttons use shared ui/buttons.css */
</style>
