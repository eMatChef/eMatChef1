<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="480"
    :title="isEditing ? t('settings.categories.modal.editTitle') : t('settings.categories.modal.newTitle')"
    scrollable
    persistent
  >
    <form id="category-modal-form" @submit.prevent="handleSubmit">
      <ETextField
        ref="nameInputRef"
        v-model="formData.name"
        :label="t('common.name')"
        :placeholder="t('settings.categories.modal.namePlaceholder')"
        :rules="[requiredNameRule]"
        hide-details="auto"
        class="mb-3"
      />

      <ESelect
        v-model="formData.parent_id"
        :items="parentSelectItems"
        :label="t('settings.categories.modal.parentLabel')"
        hide-details="auto"
        class="mb-1"
      />
      <p class="form-hint">{{ t('settings.categories.modal.parentHint') }}</p>

      <ETextarea
        v-model="formData.description"
        :label="t('common.description')"
        :placeholder="t('settings.categories.modal.descriptionPlaceholder')"
        rows="2"
        hide-details="auto"
        class="mt-3"
      />

      <v-alert v-if="error" type="error" variant="tonal" class="mt-3" :text="error" />
    </form>

    <template #actions>
      <EButton variant="secondary" size="small" @click="close">{{ t('common.cancel') }}</EButton>
      <EButton
        variant="primary"
        size="small"
        type="submit"
        form="category-modal-form"
        :disabled="isLoading || !formData.name.trim()"
        :loading="isLoading"
      >
        {{ isLoading ? t('common.saving') : (isEditing ? t('common.save') : t('common.create')) }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { getCategories, createCategory, updateCategory, type Category, type CreateCategoryRequest, type UpdateCategoryRequest } from '@/api/categories'
import { EButton, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'

interface Props {
  departmentId: string
  category?: Category | null
  defaultName?: string
  defaultParentId?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  category: null,
  defaultName: '',
  defaultParentId: null,
})

const emit = defineEmits<{
  close: []
  saved: [category: Category]
}>()

const { t } = useI18n()
const toast = useToast()
const nameInputRef = ref<{ focus?: () => void } | null>(null)
const dialogOpen = ref(true)
const isLoading = ref(false)
const error = ref('')
const allCategories = ref<Category[]>([])

const formData = ref({
  name: props.defaultName || props.category?.name || '',
  description: props.category?.description || '',
  parent_id: props.defaultParentId || props.category?.parent_id || null,
})

const isEditing = computed(() => !!props.category)

const requiredNameRule = (v: string) => !!String(v || '').trim() || t('settings.categories.modal.nameRequired')

watch(dialogOpen, (open) => {
  if (!open) emit('close')
})

const availableParents = computed(() => {
  if (!isEditing.value) {
    return allCategories.value.filter((c) => !c.parent_id)
  }

  const excludeIds = new Set<string>([props.category!.id])

  const findChildren = (parentId: string) => {
    allCategories.value
      .filter((c) => c.parent_id === parentId)
      .forEach((c) => {
        excludeIds.add(c.id)
        findChildren(c.id)
      })
  }
  findChildren(props.category!.id)

  return allCategories.value.filter((c) => !excludeIds.has(c.id) && !c.parent_id)
})

const parentSelectItems = computed(() => [
  { title: t('settings.categories.modal.parentMain'), value: null },
  ...availableParents.value.map((cat) => ({
    title: cat.parent_id ? `  └ ${cat.name}` : cat.name,
    value: cat.id,
  })),
])

function close() {
  dialogOpen.value = false
}

async function loadCategories() {
  try {
    allCategories.value = await getCategories(props.departmentId)
  } catch (err) {
    console.error('Fehler beim Laden der Kategorien:', err)
  }
}

async function handleSubmit() {
  if (!formData.value.name.trim()) {
    error.value = t('settings.categories.modal.nameRequired')
    return
  }

  isLoading.value = true
  error.value = ''

  try {
    let savedCategory: Category

    if (isEditing.value && props.category) {
      const updateData: UpdateCategoryRequest = {
        name: formData.value.name.trim(),
        description: formData.value.description?.trim() || null,
        parent_id: formData.value.parent_id,
      }
      savedCategory = await updateCategory(props.category.id, updateData)
    } else {
      const createData: CreateCategoryRequest = {
        department_id: props.departmentId,
        name: formData.value.name.trim(),
        description: formData.value.description?.trim() || null,
        parent_id: formData.value.parent_id,
      }
      savedCategory = await createCategory(createData)
    }

    emit('saved', savedCategory)
  } catch (err: unknown) {
    const msg =
      (err as { response?: { data?: { error?: string } } })?.response?.data?.error ||
      t('settings.categories.modal.saveError')
    error.value = msg
    toast.error(msg)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await loadCategories()
  await nextTick()
  nameInputRef.value?.focus?.()
})
</script>

<style scoped>
.form-hint {
  font-size: 12px;
  color: #6b7280;
  margin: 4px 0 0;
}
</style>
