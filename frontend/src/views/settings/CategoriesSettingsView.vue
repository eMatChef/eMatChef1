<template>
  <div class="categories-settings">
    <div class="settings-header">
      <div>
        <h1>{{ t('settings.categories.title') }}</h1>
        <p class="subtitle">{{ t('settings.categories.subtitle') }}</p>
      </div>
      <div class="settings-header-actions">
        <EButton
          variant="secondary"
          data-onboarding="settings-category-templates"
          @click="openTemplatesDialog"
        >
          <v-icon icon="mdi-file-tree-outline" start size="20" />
          {{ t('settings.categories.applyTemplates') }}
        </EButton>
        <EButton variant="primary" data-onboarding="settings-category-new" @click="openCreateModal">
          <v-icon icon="mdi-plus" start size="20" />
          {{ t('settings.categories.newCategory') }}
        </EButton>
      </div>
    </div>

    <div
      v-if="showTemplateSuggestion"
      class="category-templates-suggestion"
      data-onboarding="settings-category-templates-hint"
    >
      <div class="category-templates-suggestion__text">
        <strong>{{ t('settings.categories.templatesSuggestionTitle') }}</strong>
        <p>{{ t('settings.categories.templatesSuggestionBody') }}</p>
      </div>
      <EButton variant="primary" size="small" :loading="isApplyingTemplates" @click="openTemplatesDialog">
        {{ t('settings.categories.applyTemplates') }}
      </EButton>
    </div>

    <!-- Suchleiste -->
    <div class="search-bar">
      <div class="search-box">
        <ESearchField
          v-model="searchQuery"
          :label="t('settings.categories.searchPlaceholder')"
        />
      </div>
      <div class="category-count">
        {{ t('settings.categories.count', { count: filteredCategories.length }) }}
      </div>
    </div>

    <!-- Kategorien-Liste -->
    <div class="categories-list" v-if="!isLoading" data-onboarding="settings-category-list">
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
      <div v-if="filteredCategories.length === 0 && !searchQuery.trim()" class="empty-state">
        <div class="empty-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
          </svg>
        </div>
        <h3>{{ t('settings.categories.emptyTitle') }}</h3>
        <p>{{ t('settings.categories.emptyDescription') }}</p>
        <div class="empty-actions">
          <EButton variant="primary" :loading="isApplyingTemplates" @click="openTemplatesDialog">
            {{ t('settings.categories.applyTemplates') }}
          </EButton>
          <EButton variant="secondary" @click="openCreateModal">
            {{ t('settings.categories.firstCategory') }}
          </EButton>
        </div>
      </div>
      <div v-else-if="filteredCategories.length === 0" class="empty-state">
        <h3>{{ t('settings.categories.emptyTitle') }}</h3>
        <p>{{ t('settings.categories.searchNoResults', { query: searchQuery }) }}</p>
      </div>
    </div>

    <!-- Ladezustand -->
    <ELoadingState v-else variant="list" :message="t('settings.categories.loading')" />

    <!-- Kategorie Modal -->
    <CategoryModal
      v-if="showModal"
      :department-id="departmentId"
      :category="editingCategory"
      :default-parent-id="defaultParentId"
      @close="closeModal"
      @saved="handleCategorySaved"
    />

    <!-- Vorlagen-Dialog -->
    <EDialog
      v-model="showTemplatesDialog"
      :max-width="560"
      :title="t('settings.categories.templatesDialogTitle')"
    >
      <p class="templates-dialog-intro">{{ t('settings.categories.templatesDialogIntro') }}</p>
      <div class="templates-tree">
        <div v-for="item in STANDARD_CATEGORY_TREE" :key="item.name" class="templates-tree__group">
          <label class="templates-tree__main">
            <input v-model="templateSelection.main[item.name]" type="checkbox" />
            <span>{{ item.name }}</span>
          </label>
          <div v-if="item.children?.length" class="templates-tree__children">
            <label
              v-for="child in item.children"
              :key="`${item.name}-${child}`"
              class="templates-tree__child"
            >
              <input v-model="templateSelection.sub[item.name][child]" type="checkbox" />
              <span>{{ child }}</span>
            </label>
          </div>
        </div>
      </div>
      <template #actions>
        <EButton variant="secondary" :disabled="isApplyingTemplates" @click="showTemplatesDialog = false">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          variant="primary"
          :disabled="!hasSelectedTemplates"
          :loading="isApplyingTemplates"
          @click="applySelectedTemplates"
        >
          {{ t('settings.categories.applyTemplatesConfirm') }}
        </EButton>
      </template>
    </EDialog>

    <!-- Lösch-Bestätigung -->
    <EDialog v-model="showDeleteConfirm" :max-width="440" :title="t('settings.categories.deleteConfirmTitle')">
      <p>{{ t('settings.categories.deleteConfirmMessage', { name: deletingCategory?.name }) }}</p>
      <v-alert
        v-if="deletingCategory && deletingCategory.material_count > 0"
        type="warning"
        variant="tonal"
        class="mt-3"
        :text="t('settings.categories.deleteWarning', { count: deletingCategory.material_count })"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="showDeleteConfirm = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="danger" size="small" :disabled="isDeleting" :loading="isDeleting" @click="executeDelete">
          {{ isDeleting ? t('common.deleteInProgress') : t('common.delete') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { getCategories, deleteCategory, type Category } from '@/api/categories'
import CategoryModal from '@/components/CategoryModal.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog, ESearchField } from '@/components/form/base'
import { filterUserSelectableCategories } from '@/utils/repairPartsCategory'
import {
  STANDARD_CATEGORY_TREE,
  createDefaultCategoryTemplateSelection,
  hasAnyCategoryTemplateSelected,
  applyStandardCategoryTemplates,
} from '@/config/standardCategoryTemplates'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const departmentId = computed(() => route.params.departmentId as string)

const categories = ref<Category[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const expandedCategories = ref(new Set<string>())

const showTemplatesDialog = ref(false)
const isApplyingTemplates = ref(false)
const templateSelection = reactive(createDefaultCategoryTemplateSelection())
const templatesDismissed = ref(false)

const userSelectableCount = computed(
  () => filterUserSelectableCategories(categories.value).length
)
const showTemplateSuggestion = computed(
  () => !isLoading.value && userSelectableCount.value === 0 && !templatesDismissed.value
)
const hasSelectedTemplates = computed(() => hasAnyCategoryTemplateSelected(templateSelection))

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

function openTemplatesDialog() {
  Object.assign(templateSelection, createDefaultCategoryTemplateSelection())
  showTemplatesDialog.value = true
}

async function applySelectedTemplates() {
  if (!hasSelectedTemplates.value) {
    toast.error(t('settings.categories.toastSelectCategory'))
    return
  }
  isApplyingTemplates.value = true
  try {
    const { createdCount } = await applyStandardCategoryTemplates(
      departmentId.value,
      categories.value,
      templateSelection
    )
    await loadCategories()
    showTemplatesDialog.value = false
    templatesDismissed.value = true
    toast.success(
      createdCount > 0
        ? t('settings.categories.toastTemplatesNew', { count: createdCount })
        : t('settings.categories.toastTemplatesNoNew')
    )
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.categories.errCreateTemplates'))
  } finally {
    isApplyingTemplates.value = false
  }
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
  gap: 16px;
}

.settings-header-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.category-templates-suggestion {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
  padding: 14px 16px;
  border-radius: 10px;
  border: 1px solid #bbf7d0;
  background: #f0fdf4;
}

.category-templates-suggestion__text {
  flex: 1;
  min-width: 200px;
}

.category-templates-suggestion__text strong {
  display: block;
  font-size: 14px;
  color: #166534;
  margin-bottom: 4px;
}

.category-templates-suggestion__text p {
  margin: 0;
  font-size: 13px;
  line-height: 1.4;
  color: #3f6212;
}

.empty-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
}

.templates-dialog-intro {
  margin: 0 0 14px;
  font-size: 14px;
  color: #475569;
  line-height: 1.45;
}

.templates-tree {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: min(50vh, 420px);
  overflow: auto;
}

.templates-tree__group {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px 10px;
}

.templates-tree__main,
.templates-tree__child {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  cursor: pointer;
}

.templates-tree__main {
  font-weight: 600;
}

.templates-tree__children {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 8px 0 0 22px;
}

.templates-tree__child {
  font-weight: 400;
  color: #475569;
}

.settings-header h1 {
  font-size: 24px;
  font-weight: 600;
  color: var(--color-text, #111827);
  margin: 0 0 4px 0;
}

.settings-header .subtitle {
  font-size: 14px;
  color: var(--color-text-muted, #6b7280);
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
  color: var(--color-text-muted, #6b7280);
}

/* Search input base uses shared ui/page-layout.css */

.category-count {
  font-size: 13px;
  color: var(--color-text-muted, #6b7280);
}

.categories-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.category-group {
  background: var(--color-surface-muted, #f3f4f6);
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  overflow: hidden;
}

.category-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: #fff;
  transition: background 0.2s;
}

.category-item:hover {
  background: var(--color-primary-muted-bg, #ecfdf5);
}

.category-item.main-category {
  cursor: pointer;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
}

.category-item.sub-category {
  padding-left: 48px;
  background: #fff;
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
  color: var(--color-text-muted, #6b7280);
  cursor: pointer;
  transition: transform 0.2s, color 0.15s ease;
}

.expand-btn:hover {
  color: var(--color-primary, #059669);
}

.expand-btn.expanded {
  transform: rotate(90deg);
  color: var(--color-primary, #059669);
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
  background: var(--color-primary-muted-bg, #ecfdf5);
  color: var(--color-primary-dark, #047857);
}

.category-icon.sub {
  background: var(--color-primary-subtle-bg, #d1fae5);
  color: var(--color-primary, #059669);
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
  color: var(--color-text, #111827);
}

.category-meta {
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
}

.child-count {
  color: var(--color-primary, #059669);
  font-weight: 600;
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
  background: #fff;
  border: 1px solid var(--color-border, #d1d5db);
  border-radius: 6px;
  color: var(--color-text-muted, #6b7280);
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn:hover {
  background: var(--color-surface-muted, #f3f4f6);
  color: var(--color-text, #111827);
}

.action-btn.add-child:hover {
  background: var(--color-primary-muted-bg, #ecfdf5);
  color: var(--color-primary-dark, #047857);
  border-color: var(--color-primary-muted-border, #a7f3d0);
}

.action-btn.delete:hover {
  background: var(--color-error-bg, #fee2e2);
  color: var(--color-error, #dc2626);
  border-color: #fecaca;
}

.subcategories {
  border-top: 1px solid var(--color-border, #e5e7eb);
  background: var(--color-primary-muted-bg, #ecfdf5);
}

.subcategory-line {
  width: 20px;
  height: 2px;
  background: var(--color-primary-muted-border, #a7f3d0);
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
  background: var(--color-primary-muted-bg, #ecfdf5);
  border-radius: 50%;
  color: var(--color-primary, #059669);
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
  color: var(--color-text, #111827);
  margin: 0 0 12px 0;
}

.confirm-dialog p {
  font-size: 14px;
  color: var(--color-text-muted, #6b7280);
  margin: 0 0 8px 0;
}

.confirm-dialog .warning {
  background: var(--color-warning-bg, #fef3c7);
  color: var(--color-warning-text, #92400e);
  border: 1px solid var(--color-warning-border, #fcd34d);
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
