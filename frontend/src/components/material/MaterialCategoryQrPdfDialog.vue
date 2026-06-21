<template>
  <EDialog
    v-model="open"
    :max-width="620"
    :title="t('tasksPrint.exportMaterialQrPdfTitle')"
  >
    <p class="material-qr-pdf-intro">{{ t('tasksPrint.materialQrPdfIntro') }}</p>

    <div v-if="isLoading" class="material-qr-pdf-loading">
      <div class="spinner"></div>
      <p>{{ t('common.loading') }}</p>
    </div>

    <template v-else-if="hasContent">
      <div class="material-qr-pdf-toolbar">
        <button type="button" class="btn-link" @click="selectAll">{{ t('settings.storage.qrPdfSelectAll') }}</button>
        <span class="material-qr-pdf-sep">·</span>
        <button type="button" class="btn-link" @click="selectNone">{{ t('settings.storage.qrPdfSelectNone') }}</button>
        <span class="material-qr-pdf-count">{{ t('settings.storage.qrPdfSelectedCount', { count: selectedCount }) }}</span>
      </div>

      <div class="material-qr-pdf-tree">
        <section v-if="uncategorizedMaterials.length > 0" class="material-qr-pdf-group">
          <div class="material-qr-pdf-accordion-header">
            <button
              type="button"
              class="material-qr-pdf-accordion-toggle"
              :aria-expanded="isSectionExpanded('uncategorized')"
              @click="toggleSection('uncategorized')"
            >
              <span class="material-qr-pdf-accordion-chevron" aria-hidden="true">
                {{ isSectionExpanded('uncategorized') ? '▾' : '▸' }}
              </span>
            </button>
            <span class="material-qr-pdf-section-title">
              {{ t('tasksPrint.materialQrPdfUncategorized') }}
              <span
                v-if="materialsSelectionTotal(uncategorizedMaterials) > 0"
                class="material-qr-pdf-selection-badge"
                :class="selectionBadgeClass(materialsSelectedCount(uncategorizedMaterials), materialsSelectionTotal(uncategorizedMaterials))"
              >
                {{ materialsSelectedCount(uncategorizedMaterials) }}/{{ materialsSelectionTotal(uncategorizedMaterials) }}
              </span>
            </span>
          </div>
          <div v-show="isSectionExpanded('uncategorized')" class="material-qr-pdf-accordion-body">
            <MaterialQrPdfMaterialBlock
              v-for="material in uncategorizedMaterials"
              :key="material.id"
              :material="material"
              :expanded="isSectionExpanded(`mat:${material.id}`)"
              :selected-count="materialSelectedCount(material)"
              :is-batch-checked="isBatchChecked"
              @toggle-expanded="toggleSection(`mat:${material.id}`)"
              @toggle-batch="toggleBatch"
            />
          </div>
        </section>

        <section
          v-for="mainCategory in mainCategoryGroups"
          :key="mainCategory.id"
          class="material-qr-pdf-group"
        >
          <div class="material-qr-pdf-accordion-header">
            <button
              type="button"
              class="material-qr-pdf-accordion-toggle"
              :aria-expanded="isSectionExpanded(`main:${mainCategory.id}`)"
              @click="toggleSection(`main:${mainCategory.id}`)"
            >
              <span class="material-qr-pdf-accordion-chevron" aria-hidden="true">
                {{ isSectionExpanded(`main:${mainCategory.id}`) ? '▾' : '▸' }}
              </span>
            </button>
            <span class="material-qr-pdf-section-title">
              <span class="material-qr-pdf-type">{{ t('tasksPrint.materialQrPdfTypeCategory') }}</span>
              {{ mainCategory.name }}
              <span
                v-if="mainCategorySelectionTotal(mainCategory) > 0"
                class="material-qr-pdf-selection-badge"
                :class="selectionBadgeClass(mainCategorySelectedCount(mainCategory), mainCategorySelectionTotal(mainCategory))"
              >
                {{ mainCategorySelectedCount(mainCategory) }}/{{ mainCategorySelectionTotal(mainCategory) }}
              </span>
            </span>
          </div>

          <div v-show="isSectionExpanded(`main:${mainCategory.id}`)" class="material-qr-pdf-accordion-body">
            <div
              v-for="subCategory in mainCategory.subcategories"
              :key="subCategory.id"
              class="material-qr-pdf-subgroup"
            >
              <div class="material-qr-pdf-accordion-header material-qr-pdf-accordion-header--nested">
                <button
                  type="button"
                  class="material-qr-pdf-accordion-toggle"
                  :aria-expanded="isSectionExpanded(`sub:${subCategory.id}`)"
                  @click="toggleSection(`sub:${subCategory.id}`)"
                >
                  <span class="material-qr-pdf-accordion-chevron" aria-hidden="true">
                    {{ isSectionExpanded(`sub:${subCategory.id}`) ? '▾' : '▸' }}
                  </span>
                </button>
                <span class="material-qr-pdf-section-title">
                  <span class="material-qr-pdf-type">{{ t('tasksPrint.materialQrPdfTypeSubcategory') }}</span>
                  {{ subCategory.name }}
                  <span
                    v-if="materialsSelectionTotal(subCategory.materials) > 0"
                    class="material-qr-pdf-selection-badge"
                    :class="selectionBadgeClass(materialsSelectedCount(subCategory.materials), materialsSelectionTotal(subCategory.materials))"
                  >
                    {{ materialsSelectedCount(subCategory.materials) }}/{{ materialsSelectionTotal(subCategory.materials) }}
                  </span>
                </span>
              </div>
              <div v-show="isSectionExpanded(`sub:${subCategory.id}`)" class="material-qr-pdf-accordion-body material-qr-pdf-accordion-body--nested">
                <MaterialQrPdfMaterialBlock
                  v-for="material in subCategory.materials"
                  :key="material.id"
                  :material="material"
                  :expanded="isSectionExpanded(`mat:${material.id}`)"
                  :selected-count="materialSelectedCount(material)"
                  :is-batch-checked="isBatchChecked"
                  @toggle-expanded="toggleSection(`mat:${material.id}`)"
                  @toggle-batch="toggleBatch"
                />
              </div>
            </div>

            <MaterialQrPdfMaterialBlock
              v-for="material in mainCategory.directMaterials"
              :key="material.id"
              :material="material"
              :expanded="isSectionExpanded(`mat:${material.id}`)"
              :selected-count="materialSelectedCount(material)"
              :is-batch-checked="isBatchChecked"
              @toggle-expanded="toggleSection(`mat:${material.id}`)"
              @toggle-batch="toggleBatch"
            />
          </div>
        </section>
      </div>
    </template>

    <p v-else-if="!isLoading" class="material-qr-pdf-empty">{{ t('tasksPrint.materialQrPdfNoMaterials') }}</p>

    <template #actions>
      <EButton variant="secondary" size="small" :disabled="isExporting" @click="close">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="selectedCount === 0 || isExporting || isLoading"
        :loading="isExporting"
        @click="exportPdf"
      >
        {{ isExporting ? t('settings.storage.qrPdfExporting') : t('settings.storage.qrPdfDownload') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog } from '@/components/form/base'
import MaterialQrPdfMaterialBlock from '@/components/material/MaterialQrPdfMaterialBlock.vue'
import {
  downloadMaterialQrPdf,
  getMaterialQrTree,
  type MaterialQrTreeMaterial,
} from '@/api/tasks'
import { useToast } from '@/composables/useToast'

interface CategoryGroup {
  id: string
  name: string
  subcategories: Array<{
    id: string
    name: string
    materials: MaterialQrTreeMaterial[]
  }>
  directMaterials: MaterialQrTreeMaterial[]
}

const props = defineProps<{
  modelValue: boolean
  departmentId: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const { t } = useI18n()
const toast = useToast()
const isLoading = ref(false)
const isExporting = ref(false)
const selectedBatchIds = ref<Set<string>>(new Set())
const expandedSections = ref<Set<string>>(new Set())
const treeMaterials = ref<MaterialQrTreeMaterial[]>([])
const mainCategoryGroups = ref<CategoryGroup[]>([])
const uncategorizedMaterials = ref<MaterialQrTreeMaterial[]>([])

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const hasContent = computed(() =>
  uncategorizedMaterials.value.length > 0 || mainCategoryGroups.value.length > 0,
)

const selectedCount = computed(() => selectedBatchIds.value.size)

function batchKey(batchId: string): string {
  return `batch|${batchId}`
}

function allBatchIds(): string[] {
  const ids: string[] = []
  for (const material of treeMaterials.value) {
    for (const batch of material.batches) {
      ids.push(batch.id)
    }
  }
  return ids
}

function buildDefaultSelection(): Set<string> {
  return new Set(allBatchIds().map(batchKey))
}

function collapseAllSections() {
  expandedSections.value = new Set()
}

function materialSelectedCount(material: MaterialQrTreeMaterial): number {
  return material.batches.filter((batch) => isBatchChecked(batch.id)).length
}

function materialsSelectedCount(materials: MaterialQrTreeMaterial[]): number {
  return materials.reduce((sum, material) => sum + materialSelectedCount(material), 0)
}

function materialsSelectionTotal(materials: MaterialQrTreeMaterial[]): number {
  return materials.reduce((sum, material) => sum + material.batches.length, 0)
}

function mainCategorySelectedCount(main: CategoryGroup): number {
  let count = materialsSelectedCount(main.directMaterials)
  for (const sub of main.subcategories) {
    count += materialsSelectedCount(sub.materials)
  }
  return count
}

function mainCategorySelectionTotal(main: CategoryGroup): number {
  let total = materialsSelectionTotal(main.directMaterials)
  for (const sub of main.subcategories) {
    total += materialsSelectionTotal(sub.materials)
  }
  return total
}

function selectionBadgeClass(selected: number, total: number): Record<string, boolean> {
  return {
    'material-qr-pdf-selection-badge--partial': selected > 0 && selected < total,
    'material-qr-pdf-selection-badge--full': selected > 0 && selected === total,
  }
}

function buildCategoryGroups(
  categories: Array<{ id: string; name: string; parent_id: string | null; sort_order: number }>,
  materials: MaterialQrTreeMaterial[],
): { mainGroups: CategoryGroup[]; uncategorized: MaterialQrTreeMaterial[] } {
  const materialsByCategory = new Map<string, MaterialQrTreeMaterial[]>()
  const uncategorized: MaterialQrTreeMaterial[] = []

  for (const material of materials) {
    if (!material.category_id) {
      uncategorized.push(material)
      continue
    }
    const list = materialsByCategory.get(material.category_id) || []
    list.push(material)
    materialsByCategory.set(material.category_id, list)
  }

  const mainCategories = categories
    .filter((category) => !category.parent_id)
    .sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name))

  const subcategoriesByParent = new Map<string, typeof categories>()
  for (const category of categories) {
    if (!category.parent_id) continue
    const list = subcategoriesByParent.get(category.parent_id) || []
    list.push(category)
    subcategoriesByParent.set(category.parent_id, list)
  }

  const mainGroups: CategoryGroup[] = []
  for (const main of mainCategories) {
    const subcategories = (subcategoriesByParent.get(main.id) || [])
      .sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name))
      .map((sub) => ({
        id: sub.id,
        name: sub.name,
        materials: (materialsByCategory.get(sub.id) || []).sort((a, b) => a.name.localeCompare(b.name)),
      }))
      .filter((sub) => sub.materials.length > 0)

    const directMaterials = (materialsByCategory.get(main.id) || [])
      .sort((a, b) => a.name.localeCompare(b.name))

    if (subcategories.length === 0 && directMaterials.length === 0) {
      continue
    }

    mainGroups.push({
      id: main.id,
      name: main.name,
      subcategories,
      directMaterials,
    })
  }

  return {
    mainGroups,
    uncategorized: uncategorized.sort((a, b) => a.name.localeCompare(b.name)),
  }
}

async function loadTree() {
  if (!props.departmentId) {
    treeMaterials.value = []
    mainCategoryGroups.value = []
    uncategorizedMaterials.value = []
    return
  }

  isLoading.value = true
  try {
    const tree = await getMaterialQrTree(props.departmentId)
    treeMaterials.value = tree.materials || []
    const grouped = buildCategoryGroups(tree.categories || [], treeMaterials.value)
    mainCategoryGroups.value = grouped.mainGroups
    uncategorizedMaterials.value = grouped.uncategorized
    selectedBatchIds.value = buildDefaultSelection()
    collapseAllSections()
  } catch (err: any) {
    treeMaterials.value = []
    mainCategoryGroups.value = []
    uncategorizedMaterials.value = []
    toast.error(err?.message || err?.response?.data?.error || t('tasksPrint.errors.loadMaterialQrTree'))
  } finally {
    isLoading.value = false
  }
}

watch(
  () => props.modelValue,
  (visible) => {
    if (visible) void loadTree()
  },
)

function isSectionExpanded(key: string): boolean {
  return expandedSections.value.has(key)
}

function toggleSection(key: string) {
  const next = new Set(expandedSections.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  expandedSections.value = next
}

function isBatchChecked(batchId: string): boolean {
  return selectedBatchIds.value.has(batchKey(batchId))
}

function toggleBatch(batchId: string, checked: boolean) {
  const key = batchKey(batchId)
  const next = new Set(selectedBatchIds.value)
  if (checked) next.add(key)
  else next.delete(key)
  selectedBatchIds.value = next
}

function selectAll() {
  selectedBatchIds.value = buildDefaultSelection()
}

function selectNone() {
  selectedBatchIds.value = new Set()
}

function close() {
  open.value = false
}

async function exportPdf() {
  if (!props.departmentId || selectedCount.value === 0 || isExporting.value) return
  isExporting.value = true
  try {
    const batchIds = Array.from(selectedBatchIds.value).map((key) => key.replace(/^batch\|/, ''))
    const blob = await downloadMaterialQrPdf(props.departmentId, batchIds)
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `material-qr-codes-${props.departmentId}.pdf`
    anchor.click()
    URL.revokeObjectURL(url)
    toast.success(t('tasksPrint.exportMaterialQrPdfSuccess'))
    close()
  } catch (err: any) {
    toast.error(err?.message || err?.response?.data?.error || t('tasksPrint.errors.exportMaterialQrPdf'))
  } finally {
    isExporting.value = false
  }
}
</script>

<style scoped>
.material-qr-pdf-intro {
  margin: 0 0 12px;
  font-size: 14px;
  color: #6b7280;
}

.material-qr-pdf-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 0;
  color: #6b7280;
  font-size: 14px;
}

.material-qr-pdf-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 14px;
}

.material-qr-pdf-sep {
  color: #d1d5db;
}

.material-qr-pdf-count {
  margin-left: auto;
  color: #6b7280;
  font-size: 13px;
}

.material-qr-pdf-tree {
  max-height: 420px;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 10px;
}

.material-qr-pdf-group + .material-qr-pdf-group {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #f3f4f6;
}

.material-qr-pdf-subgroup + .material-qr-pdf-subgroup,
.material-qr-pdf-subgroup + :deep(.material-qr-pdf-material-block) {
  margin-top: 4px;
}

.material-qr-pdf-accordion-header {
  display: flex;
  align-items: flex-start;
  gap: 2px;
}

.material-qr-pdf-accordion-header--nested {
  margin-left: 8px;
}

.material-qr-pdf-accordion-toggle {
  flex-shrink: 0;
  width: 24px;
  height: 28px;
  margin-top: 1px;
  padding: 0;
  border: none;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
  line-height: 1;
}

.material-qr-pdf-accordion-toggle:hover {
  color: #374151;
}

.material-qr-pdf-accordion-chevron {
  font-size: 12px;
}

.material-qr-pdf-section-title {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  font-weight: 600;
  line-height: 1.35;
  padding-top: 4px;
}

.material-qr-pdf-selection-badge {
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.4;
  color: #6b7280;
  background: #f3f4f6;
}

.material-qr-pdf-selection-badge--partial {
  color: #047857;
  background: #d1fae5;
}

.material-qr-pdf-selection-badge--full {
  color: #065f46;
  background: #a7f3d0;
}

.material-qr-pdf-type {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #9ca3af;
}

.material-qr-pdf-accordion-body {
  margin: 2px 0 4px 24px;
}

.material-qr-pdf-accordion-body--nested {
  margin-left: 16px;
}

.material-qr-pdf-empty {
  margin: 8px 0 0;
  font-size: 13px;
  color: #9ca3af;
}
</style>
