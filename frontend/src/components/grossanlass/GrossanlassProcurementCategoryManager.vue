<template>
  <section class="cat-manager">
    <div class="panel-head">
      <h3>{{ t('grossanlass.beschaffung.bedarf.categoriesTitle') }}</h3>
      <span class="panel-count">{{ categories.length }}</span>
    </div>
    <p class="panel-hint">{{ t('grossanlass.beschaffung.bedarf.categoriesHint') }}</p>

    <div class="cat-create">
      <ETextField
        v-model="newName"
        class="cat-create__name"
        :label="t('grossanlass.beschaffung.bedarf.categoryName')"
        :placeholder="t('grossanlass.beschaffung.bedarf.categoryNamePlaceholder')"
        hide-details
        @keydown.enter.prevent="createCategory"
      />
      <ESelect
        v-model="newParentId"
        class="cat-create__parent"
        :items="parentSelectItems"
        :label="t('grossanlass.beschaffung.bedarf.categoryUnder')"
        hide-details
      />
      <EButton
        variant="primary"
        size="small"
        :disabled="!newNameTrimmed || creating"
        :loading="creating"
        @click="createCategory"
      >
        {{ newParentId
          ? t('grossanlass.beschaffung.bedarf.categoryAddChild')
          : t('grossanlass.beschaffung.bedarf.categoryAdd') }}
      </EButton>
    </div>

    <p v-if="categories.length === 0" class="cat-empty">
      {{ t('grossanlass.beschaffung.bedarf.categoriesEmpty') }}
    </p>

    <ul v-else class="cat-tree">
      <li v-for="parent in parents" :key="parent.id" class="cat-node">
        <div class="cat-node__row">
          <strong>{{ parent.name }}</strong>
          <button
            type="button"
            class="icon-btn icon-btn--danger"
            :title="t('common.delete')"
            @click="removeCategory(parent)"
          >
            <v-icon icon="mdi-delete-outline" size="16" />
          </button>
        </div>
        <ul v-if="childrenOf(parent.id).length" class="cat-tree cat-tree--nested">
          <li v-for="child in childrenOf(parent.id)" :key="child.id" class="cat-node__row">
            <span>{{ child.name }}</span>
            <button
              type="button"
              class="icon-btn icon-btn--danger"
              :title="t('common.delete')"
              @click="removeCategory(child)"
            >
              <v-icon icon="mdi-delete-outline" size="16" />
            </button>
          </li>
        </ul>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import {
  createGrossanlassProcurementCategory,
  deleteGrossanlassProcurementCategory,
  type GrossanlassProcurementCategory,
} from '@/api/grossanlassProcurement'

const props = defineProps<{
  departmentId: string
  categories: GrossanlassProcurementCategory[]
}>()

const emit = defineEmits<{
  created: [category: GrossanlassProcurementCategory]
  deleted: [categoryId: string]
}>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const newName = ref('')
const newParentId = ref('')
const creating = ref(false)

const newNameTrimmed = computed(() => newName.value.trim())

const parents = computed(() => props.categories.filter((c) => !c.parent_id))

const parentSelectItems = computed(() => [
  { title: t('grossanlass.beschaffung.bedarf.categoryUnderNone'), value: '' },
  ...parents.value.map((c) => ({ title: c.name, value: c.id })),
])

function childrenOf(parentId: string): GrossanlassProcurementCategory[] {
  return props.categories.filter((c) => c.parent_id === parentId)
}

async function createCategory() {
  const name = newNameTrimmed.value
  if (!name || creating.value || !props.departmentId) return

  creating.value = true
  try {
    const created = await createGrossanlassProcurementCategory(props.departmentId, {
      name,
      parent_id: newParentId.value || null,
    })
    emit('created', created)
    newName.value = ''
    toast.success(t('grossanlass.beschaffung.bedarf.categoryCreateSuccess'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCategoryCreate'))
  } finally {
    creating.value = false
  }
}

async function removeCategory(category: GrossanlassProcurementCategory) {
  if (!props.departmentId) return
  const hasChildren = childrenOf(category.id).length > 0
  const ok = await confirm.confirm({
    title: t('grossanlass.beschaffung.bedarf.categoryDeleteTitle'),
    message: hasChildren
      ? t('grossanlass.beschaffung.bedarf.categoryDeleteHasChildren', { name: category.name })
      : t('grossanlass.beschaffung.bedarf.categoryDeleteMessage', { name: category.name }),
  })
  if (!ok) return
  try {
    await deleteGrossanlassProcurementCategory(props.departmentId, category.id)
    emit('deleted', category.id)
    if (newParentId.value === category.id) newParentId.value = ''
    toast.success(t('grossanlass.beschaffung.bedarf.categoryDeleteSuccess'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCategoryDelete'))
  }
}
</script>

<style scoped>
.cat-manager {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
  margin-bottom: 20px;
}

.panel-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.panel-head h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.panel-count {
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 2px 8px;
}

.panel-hint {
  margin: 0 0 12px;
  font-size: 0.82rem;
  color: #94a3b8;
}

.cat-create {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
}

.cat-create__name {
  flex: 1 1 180px;
  min-width: 140px;
}

.cat-create__parent {
  flex: 1 1 160px;
  min-width: 140px;
}

.cat-empty {
  margin: 12px 0 0;
  font-size: 0.82rem;
  color: #64748b;
}

.cat-tree {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cat-tree--nested {
  margin: 6px 0 0 16px;
  gap: 4px;
}

.cat-node {
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fafafa;
}

.cat-node__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.icon-btn {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  width: 28px;
  height: 28px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.icon-btn--danger {
  color: #dc2626;
}
</style>
