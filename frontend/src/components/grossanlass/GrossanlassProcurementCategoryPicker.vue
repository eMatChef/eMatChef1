<template>
  <div class="category-picker">
    <EAutocomplete
      v-model="categoryId"
      v-model:search="search"
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
          @mousedown.prevent="createCategory"
        >
          {{ t('grossanlass.beschaffung.bedarf.categoryCreateNamed', { name: searchTrimmed }) }}
        </button>
        <span v-else class="create-option create-option--hint">
          {{ t('grossanlass.beschaffung.bedarf.categoryEmptyHint') }}
        </span>
      </template>
    </EAutocomplete>
    <EButton
      v-if="canCreate"
      class="mt-2"
      variant="secondary"
      size="small"
      :disabled="creating"
      :loading="creating"
      @click="createCategory"
    >
      {{ t('grossanlass.beschaffung.bedarf.categoryCreateNamed', { name: searchTrimmed }) }}
    </EButton>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EAutocomplete, EButton } from '@/components/form/base'
import GrossanlassCategoryDropdownItem from '@/components/grossanlass/GrossanlassCategoryDropdownItem.vue'
import {
  createGrossanlassProcurementCategory,
  type GrossanlassProcurementCategory,
} from '@/api/grossanlassProcurement'

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

const searchTrimmed = computed(() => search.value.trim())

function bySort(a: GrossanlassProcurementCategory, b: GrossanlassProcurementCategory) {
  if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order
  return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
}

const treeItems = computed(() => {
  const parents = props.categories.filter((c) => !c.parent_id).slice().sort(bySort)
  const items: Array<{ title: string; value: string; name: string; depth: number }> = []
  for (const parent of parents) {
    items.push({
      title: parent.name,
      value: parent.id,
      name: parent.name,
      depth: 0,
    })
    const children = props.categories.filter((c) => c.parent_id === parent.id).slice().sort(bySort)
    for (const child of children) {
      items.push({
        title: `${parent.name} / ${child.name}`,
        value: child.id,
        name: child.name,
        depth: 1,
      })
    }
  }
  return items
})

const canCreate = computed(() => {
  const name = searchTrimmed.value
  if (!name) return false
  return !treeItems.value.some((item) => item.name.toLowerCase() === name.toLowerCase())
})

async function createCategory() {
  const name = searchTrimmed.value
  if (!name || creating.value) return

  creating.value = true
  try {
    const created = await createGrossanlassProcurementCategory(props.departmentId, { name })
    emit('created', created)
    categoryId.value = created.id
    search.value = ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCategoryCreate'))
  } finally {
    creating.value = false
  }
}
</script>

<style scoped>
.mt-2 { margin-top: 8px; }
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
