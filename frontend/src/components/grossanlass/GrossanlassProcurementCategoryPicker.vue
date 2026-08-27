<template>
  <div class="category-picker">
    <EAutocomplete
      v-model="parentId"
      v-model:search="parentSearch"
      :items="parentItems"
      item-title="title"
      item-value="value"
      :label="t('grossanlass.beschaffung.bedarf.categoryParent')"
      :placeholder="t('grossanlass.beschaffung.bedarf.categoryParentPlaceholder')"
      :no-filter="false"
      :disabled="disabled || creating"
      :loading="creating && creatingLevel === 'parent'"
      clearable
      hide-details
    >
      <template #no-data>
        <button
          v-if="parentSearchTrimmed"
          type="button"
          class="create-option"
          :disabled="creating"
          @mousedown.prevent="createCategory('parent')"
        >
          {{ t('grossanlass.beschaffung.bedarf.categoryCreateNamed', { name: parentSearchTrimmed }) }}
        </button>
        <span v-else class="create-option create-option--hint">
          {{ t('grossanlass.beschaffung.bedarf.categoryEmptyHint') }}
        </span>
      </template>
    </EAutocomplete>
    <EButton
      v-if="canCreateParent"
      class="mt-2"
      variant="secondary"
      size="small"
      :disabled="creating"
      :loading="creating && creatingLevel === 'parent'"
      @click="createCategory('parent')"
    >
      {{ t('grossanlass.beschaffung.bedarf.categoryCreateNamed', { name: parentSearchTrimmed }) }}
    </EButton>

    <EAutocomplete
      v-if="parentId"
      v-model="childId"
      v-model:search="childSearch"
      class="mt-3"
      :items="childItems"
      item-title="title"
      item-value="value"
      :label="t('grossanlass.beschaffung.bedarf.categoryChild')"
      :placeholder="t('grossanlass.beschaffung.bedarf.categoryChildPlaceholder')"
      :no-filter="false"
      :disabled="disabled || creating"
      :loading="creating && creatingLevel === 'child'"
      clearable
      hide-details
    >
      <template #no-data>
        <button
          v-if="childSearchTrimmed"
          type="button"
          class="create-option"
          :disabled="creating"
          @mousedown.prevent="createCategory('child')"
        >
          {{ t('grossanlass.beschaffung.bedarf.categoryCreateNamed', { name: childSearchTrimmed }) }}
        </button>
        <span v-else class="create-option create-option--hint">
          {{ t('grossanlass.beschaffung.bedarf.categoryChildEmptyHint') }}
        </span>
      </template>
    </EAutocomplete>
    <EButton
      v-if="parentId && canCreateChild"
      class="mt-2"
      variant="secondary"
      size="small"
      :disabled="creating"
      :loading="creating && creatingLevel === 'child'"
      @click="createCategory('child')"
    >
      {{ t('grossanlass.beschaffung.bedarf.categoryCreateNamed', { name: childSearchTrimmed }) }}
    </EButton>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EAutocomplete, EButton } from '@/components/form/base'
import {
  createGrossanlassProcurementCategory,
  type GrossanlassProcurementCategory,
} from '@/api/grossanlassProcurement'

const props = defineProps<{
  departmentId: string
  categories: GrossanlassProcurementCategory[]
  disabled?: boolean
}>()

const emit = defineEmits<{
  created: [category: GrossanlassProcurementCategory]
}>()

const categoryId = defineModel<string | null>({ default: null })
const { t } = useI18n()
const toast = useToast()

const parentId = ref<string | null>(null)
const childId = ref<string | null>(null)
const parentSearch = ref('')
const childSearch = ref('')
const creating = ref(false)
const creatingLevel = ref<'parent' | 'child' | null>(null)
const syncing = ref(false)

const parentSearchTrimmed = computed(() => parentSearch.value.trim())
const childSearchTrimmed = computed(() => childSearch.value.trim())

const parentItems = computed(() =>
  props.categories
    .filter((c) => !c.parent_id)
    .map((c) => ({ title: c.name, value: c.id })),
)

const childItems = computed(() =>
  props.categories
    .filter((c) => c.parent_id === parentId.value)
    .map((c) => ({ title: c.name, value: c.id })),
)

const canCreateParent = computed(() => {
  const name = parentSearchTrimmed.value
  if (!name) return false
  return !parentItems.value.some((item) => item.title.toLowerCase() === name.toLowerCase())
})

const canCreateChild = computed(() => {
  const name = childSearchTrimmed.value
  if (!name) return false
  return !childItems.value.some((item) => item.title.toLowerCase() === name.toLowerCase())
})

function applyFromModel(id: string | null) {
  syncing.value = true
  if (!id) {
    parentId.value = null
    childId.value = null
    void nextTick(() => {
      syncing.value = false
    })
    return
  }
  const selected = props.categories.find((c) => c.id === id)
  if (!selected) {
    parentId.value = id
    childId.value = null
    void nextTick(() => {
      syncing.value = false
    })
    return
  }
  if (selected.parent_id) {
    parentId.value = selected.parent_id
    childId.value = selected.id
  } else {
    parentId.value = selected.id
    childId.value = null
  }
  void nextTick(() => {
    syncing.value = false
  })
}

watch(
  [categoryId, () => props.categories],
  () => applyFromModel(categoryId.value ?? null),
  { immediate: true },
)

watch([parentId, childId], () => {
  if (syncing.value) return
  const next = childId.value ?? parentId.value ?? null
  if (next !== (categoryId.value ?? null)) {
    categoryId.value = next
  }
})

watch(parentId, (id, previous) => {
  if (syncing.value || id === previous) return
  childId.value = null
  childSearch.value = ''
})

async function createCategory(level: 'parent' | 'child') {
  const name = level === 'parent' ? parentSearchTrimmed.value : childSearchTrimmed.value
  if (!name || creating.value) return
  if (level === 'child' && !parentId.value) return

  creating.value = true
  creatingLevel.value = level
  try {
    const created = await createGrossanlassProcurementCategory(props.departmentId, {
      name,
      parent_id: level === 'child' ? parentId.value : null,
    })
    emit('created', created)
    syncing.value = true
    if (level === 'parent') {
      parentId.value = created.id
      childId.value = null
      parentSearch.value = ''
    } else {
      childId.value = created.id
      childSearch.value = ''
    }
    categoryId.value = created.id
    await nextTick()
    syncing.value = false
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCategoryCreate'))
  } finally {
    creating.value = false
    creatingLevel.value = null
  }
}
</script>

<style scoped>
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
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
