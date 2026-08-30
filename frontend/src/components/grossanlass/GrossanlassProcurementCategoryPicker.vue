<template>
  <div class="category-picker">
    <div class="category-picker__row">
      <EAutocomplete
        v-model="categoryId"
        v-model:search="search"
        class="category-picker__field"
        :items="treeItems"
        item-title="title"
        item-value="value"
        :label="label || t('grossanlass.beschaffung.bedarf.categoryParent')"
        :placeholder="t('grossanlass.beschaffung.bedarf.categoryParentPlaceholder')"
        :no-filter="false"
        :disabled="disabled || creating"
        :loading="creating"
        :clearable="!required"
        hide-details
      >
        <template #item="{ props: itemProps, item }">
          <GrossanlassCategoryDropdownItem :item-props="itemProps" :item="item" />
        </template>
        <template #no-data>
          <button
            v-if="searchTrimmed"
            type="button"
            class="create-option"
            :disabled="creating"
            @mousedown.prevent="createFromSearch"
          >
            {{ t('grossanlass.beschaffung.bedarf.categoryCreateNamed', { name: searchTrimmed }) }}
          </button>
          <span v-else class="create-option create-option--hint">
            {{ t('grossanlass.beschaffung.bedarf.categoryEmptyHint') }}
          </span>
        </template>
      </EAutocomplete>
      <button
        type="button"
        class="category-picker__plus"
        :class="{ 'is-open': showCreate }"
        :title="t('grossanlass.beschaffung.bedarf.categoryAdd')"
        :aria-label="t('grossanlass.beschaffung.bedarf.categoryAdd')"
        :aria-expanded="showCreate"
        :disabled="disabled || creating"
        @click="toggleCreate"
      >
        <v-icon :icon="showCreate ? 'mdi-close' : 'mdi-plus'" size="20" />
      </button>
    </div>

    <div v-if="showCreate" class="category-picker__create">
      <ETextField
        v-model="newName"
        :label="t('grossanlass.beschaffung.bedarf.categoryName')"
        :placeholder="t('grossanlass.beschaffung.bedarf.categoryNamePlaceholder')"
        hide-details
        :disabled="creating"
        @keydown.enter.prevent="createFromPanel"
      />
      <ESelect
        v-model="newParentId"
        :items="parentSelectItems"
        :label="t('grossanlass.beschaffung.bedarf.categoryUnder')"
        hide-details
        :disabled="creating"
      >
        <template #item="{ props: itemProps, item }">
          <GrossanlassCategoryDropdownItem :item-props="itemProps" :item="item" />
        </template>
      </ESelect>
      <EButton
        variant="primary"
        size="small"
        :disabled="!newNameTrimmed || creating"
        :loading="creating"
        @click="createFromPanel"
      >
        {{ newParentId
          ? t('grossanlass.beschaffung.bedarf.categoryAddChild')
          : t('grossanlass.beschaffung.bedarf.categoryAdd') }}
      </EButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EAutocomplete, EButton, ESelect, ETextField } from '@/components/form/base'
import GrossanlassCategoryDropdownItem from '@/components/grossanlass/GrossanlassCategoryDropdownItem.vue'
import {
  createGrossanlassProcurementCategory,
  type GrossanlassProcurementCategory,
} from '@/api/grossanlassProcurement'
import { procurementCategoryTreeItems } from '@/utils/grossanlassProcurementCategoryTree'

const props = defineProps<{
  departmentId: string
  categories: GrossanlassProcurementCategory[]
  disabled?: boolean
  required?: boolean
  label?: string
}>()

const emit = defineEmits<{
  created: [category: GrossanlassProcurementCategory]
}>()

const categoryId = defineModel<string | null>({ default: null })
const { t } = useI18n()
const toast = useToast()

const search = ref('')
const creating = ref(false)
const showCreate = ref(false)
const newName = ref('')
const newParentId = ref('')

const searchTrimmed = computed(() => search.value.trim())
const newNameTrimmed = computed(() => newName.value.trim())

const treeItems = computed(() => procurementCategoryTreeItems(props.categories))

const parentSelectItems = computed(() => {
  const noneTitle = t('grossanlass.beschaffung.bedarf.categoryUnderNone')
  return [
    { title: noneTitle, value: '', name: noneTitle, depth: 0 },
    ...treeItems.value,
  ]
})

function toggleCreate() {
  showCreate.value = !showCreate.value
  if (!showCreate.value) return
  if (!newName.value && searchTrimmed.value) {
    const exists = treeItems.value.some(
      (item) => item.name.toLowerCase() === searchTrimmed.value.toLowerCase(),
    )
    if (!exists) newName.value = searchTrimmed.value
  }
}

async function createCategory(name: string, parentId: string | null) {
  if (!name || creating.value || !props.departmentId) return

  creating.value = true
  try {
    const created = await createGrossanlassProcurementCategory(props.departmentId, {
      name,
      parent_id: parentId,
    })
    emit('created', created)
    categoryId.value = created.id
    search.value = ''
    newName.value = ''
    newParentId.value = ''
    showCreate.value = false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCategoryCreate'))
  } finally {
    creating.value = false
  }
}

function createFromSearch() {
  void createCategory(searchTrimmed.value, null)
}

function createFromPanel() {
  void createCategory(newNameTrimmed.value, newParentId.value || null)
}
</script>

<style scoped>
.category-picker__row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

.category-picker__field {
  flex: 1;
  min-width: 0;
}

.category-picker__plus {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  margin-bottom: 0;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  color: var(--color-primary-dark, #166534);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.category-picker__plus:hover:not(:disabled) {
  background: var(--color-primary-muted-bg, #ecfdf3);
  border-color: #a7f3d0;
}

.category-picker__plus.is-open {
  background: #f1f5f9;
  color: #334155;
}

.category-picker__plus:disabled {
  opacity: 0.5;
  cursor: default;
}

.category-picker__create {
  display: grid;
  gap: 8px;
  margin-top: 10px;
  padding: 10px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
}

.create-option {
  display: block;
  width: 100%;
  text-align: left;
  border: none;
  background: transparent;
  padding: 10px 14px;
  font-size: 0.85rem;
  color: #1d4ed8;
  cursor: pointer;
}
.create-option:hover:not(:disabled) {
  background: #eff6ff;
}
.create-option:disabled {
  opacity: 0.6;
  cursor: default;
}
.create-option--hint {
  color: #94a3b8;
  cursor: default;
}
</style>
