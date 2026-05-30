<template>
  <div class="category-autocomplete-field">
    <div ref="wrapperRef" class="autocomplete-wrapper">
      <input
        v-model="categorySearch"
        type="text"
        class="form-input"
        :disabled="disabled"
        :placeholder="placeholder || t('components.categoryAutocomplete.placeholderDefault')"
        autocomplete="off"
        @input="onSearchInput"
        @focus="onInputFocus"
        @blur="hideDropdownDelayed"
      />
      <button
        v-if="allowCreate"
        type="button"
        class="add-inline-btn"
        :disabled="disabled"
        :title="t('components.categoryAutocomplete.addCategoryTitle')"
        @click="openAddCategoryModal"
      >
        +
      </button>
    </div>
    <p v-if="selectedCategory" class="selected-address category-autocomplete-selected">
      ✓ {{ getCategoryPath(selectedCategory) }}
      <button
        type="button"
        class="clear-selection"
        :title="t('components.categoryAutocomplete.removeCategoryTitle')"
        @click="clearCategory"
      >
        ×
      </button>
    </p>

    <Teleport to="body">
      <div
        v-if="showDropdown"
        class="autocomplete-dropdown category-dropdown category-autocomplete-dropdown-portal"
        :style="dropdownFixedStyle"
        role="listbox"
      >
        <template v-if="categories.length === 0">
          <div class="autocomplete-item autocomplete-empty">
            <span class="item-name">{{ t('components.categoryAutocomplete.emptyHint') }}</span>
          </div>
        </template>
        <template v-else>
          <div
            v-for="cat in filteredCategories"
            :key="cat.id"
            class="autocomplete-item"
            :class="{ 'is-child': !!cat.parent_id }"
            @mousedown.prevent="selectCategory(cat)"
          >
            <span class="item-name">
              <span v-if="cat.parent_id" class="cat-indent">└ </span>{{ cat.name }}
            </span>
            <span class="item-count">{{ t('components.categoryAutocomplete.articleCount', { n: cat.material_count }) }}</span>
          </div>
          <div
            v-if="filteredCategories.length === 0 && categorySearch.trim().length > 0 && categorySearch.trim().length < 2"
            class="autocomplete-item autocomplete-empty"
          >
            <span class="item-name">{{ t('components.categoryAutocomplete.typeMoreHint') }}</span>
          </div>
          <div
            v-if="allowCreate && filteredCategories.length === 0 && categorySearch.trim().length >= 2"
            class="autocomplete-item create-new"
            @mousedown.prevent="openAddCategoryModal"
          >
            <span class="item-name">+ {{ t('components.categoryAutocomplete.createNamed', { name: categorySearch }) }}</span>
          </div>
        </template>
      </div>
    </Teleport>

    <CategoryModal
      v-if="showCategoryModal && departmentId"
      :department-id="departmentId"
      :default-name="categoryModalDefaultName"
      @close="showCategoryModal = false"
      @saved="onCategoryModalSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onUnmounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import CategoryModal from '@/components/CategoryModal.vue'
import type { Category } from '@/api/categories'
import '@/styles/material-wizard.css'

const props = withDefaults(
  defineProps<{
    modelValue: string
    categories: Category[]
    departmentId: string
    disabled?: boolean
    allowCreate?: boolean
    placeholder?: string
  }>(),
  {
    disabled: false,
    allowCreate: true,
    placeholder: undefined,
  }
)

const { t } = useI18n()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'reload-categories': [saved?: Category]
  focus: []
  blur: []
  change: []
}>()

const wrapperRef = ref<HTMLElement | null>(null)
const categorySearch = ref('')
const selectedCategory = ref<Category | null>(null)
const filteredCategories = ref<Category[]>([])
const showDropdown = ref(false)
const showCategoryModal = ref(false)
const categoryModalDefaultName = ref('')

const dropdownFixedStyle = ref<Record<string, string>>({
  position: 'fixed',
  top: '0px',
  left: '0px',
  width: '240px',
  zIndex: '99999',
})

let positionListenersBound = false
let positionHandler: (() => void) | null = null

function sortCategoriesHierarchical(all: Category[]): Category[] {
  const mains = all
    .filter((c) => !c.parent_id)
    .sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name, 'de-CH'))
  const out: Category[] = []
  for (const m of mains) {
    out.push(m)
    const children = all
      .filter((c) => c.parent_id === m.id)
      .sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name, 'de-CH'))
    out.push(...children)
  }
  return out
}

function searchCategories() {
  const query = categorySearch.value.toLowerCase().trim()
  if (!query) {
    filteredCategories.value = sortCategoriesHierarchical(props.categories)
    return
  }
  filteredCategories.value = props.categories
    .filter((c) => c.name.toLowerCase().includes(query))
    .sort((a, b) => a.name.localeCompare(b.name, 'de-CH'))
}

function getCategoryPath(cat: Category): string {
  if (!cat.parent_id) return cat.name
  const parent = props.categories.find((c) => c.id === cat.parent_id)
  return parent ? `${parent.name} › ${cat.name}` : cat.name
}

function syncFromModel() {
  const id = props.modelValue
  if (!id) {
    selectedCategory.value = null
    categorySearch.value = ''
    return
  }
  const cat = props.categories.find((c) => c.id === id) ?? null
  selectedCategory.value = cat
  categorySearch.value = cat?.name ?? ''
}

watch(
  () => [props.modelValue, props.categories] as const,
  () => {
    syncFromModel()
  },
  { immediate: true, deep: true }
)

function updateDropdownPosition(retry = 0) {
  const wrap = wrapperRef.value
  if (!wrap) return
  const input = wrap.querySelector('input')
  const r = (input ?? wrap).getBoundingClientRect()
  if ((r.width < 2 || r.height < 2) && retry < 30) {
    requestAnimationFrame(() => updateDropdownPosition(retry + 1))
    return
  }
  const w = Math.max(Math.round(r.width), 240)
  dropdownFixedStyle.value = {
    position: 'fixed',
    top: `${Math.round(r.bottom + 4)}px`,
    left: `${Math.round(r.left)}px`,
    width: `${w}px`,
    zIndex: '99999',
  }
}

function bindPositionListeners() {
  if (positionListenersBound) return
  positionListenersBound = true
  positionHandler = () => updateDropdownPosition(0)
  window.addEventListener('resize', positionHandler)
  window.addEventListener('scroll', positionHandler, true)
}

function unbindPositionListeners() {
  if (!positionListenersBound || !positionHandler) return
  positionListenersBound = false
  window.removeEventListener('resize', positionHandler)
  window.removeEventListener('scroll', positionHandler, true)
  positionHandler = null
}

function onInputFocus() {
  emit('focus')
  showDropdown.value = true
  searchCategories()
  void nextTick(() => {
    updateDropdownPosition(0)
    requestAnimationFrame(() => updateDropdownPosition(0))
    bindPositionListeners()
  })
}

function onSearchInput() {
  searchCategories()
  void nextTick(() => updateDropdownPosition(0))
}

function hideDropdownDelayed() {
  setTimeout(() => {
    showDropdown.value = false
    unbindPositionListeners()
    emit('blur')
  }, 200)
}

function selectCategory(cat: Category) {
  selectedCategory.value = cat
  categorySearch.value = cat.name
  emit('update:modelValue', cat.id)
  emit('change')
  showDropdown.value = false
  unbindPositionListeners()
}

function clearCategory() {
  selectedCategory.value = null
  categorySearch.value = ''
  emit('update:modelValue', '')
  emit('change')
}

function openAddCategoryModal() {
  categoryModalDefaultName.value = categorySearch.value.trim()
  showCategoryModal.value = true
}

function onCategoryModalSaved(cat: Category) {
  showCategoryModal.value = false
  categoryModalDefaultName.value = ''
  emit('reload-categories', cat)
  emit('change')
}

watch(showDropdown, (open) => {
  if (!open) unbindPositionListeners()
})

onUnmounted(() => {
  unbindPositionListeners()
})
</script>

<style scoped>
.category-autocomplete-field {
  width: 100%;
}

.category-autocomplete-selected {
  margin-top: 6px;
}

/* Teleport-Dropdown: gleiche Fixes wie Wizard-Portal */
:global(.category-autocomplete-dropdown-portal) {
  position: fixed !important;
  right: auto !important;
  margin-top: 0 !important;
  min-width: 240px;
  min-height: 44px;
  pointer-events: auto;
  background: #fff;
  max-height: 280px;
  overflow-y: auto;
}
</style>
