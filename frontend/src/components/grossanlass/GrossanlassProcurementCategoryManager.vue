<template>
  <section class="cat-manager">
    <div class="panel-head">
      <h3>{{ t('grossanlass.beschaffung.bedarf.categoriesTitle') }}</h3>
      <span class="panel-count">{{ categories.length }}</span>
    </div>
    <p class="panel-hint">{{ t('grossanlass.beschaffung.bedarf.categoriesHint') }}</p>
    <p v-if="gmailConnected" class="panel-hint panel-hint--gmail">
      {{ t('grossanlass.beschaffung.bedarf.categoryGmailSyncHint', { email: gmailEmail }) }}
    </p>

    <div class="cat-create">
      <div class="cat-create__name">
        <ETextField
          v-model="newName"
          :label="t('grossanlass.beschaffung.bedarf.categoryName')"
          :placeholder="t('grossanlass.beschaffung.bedarf.categoryNamePlaceholder')"
          hide-details
          @keydown.enter.prevent="createCategory"
        />
      </div>
      <div class="cat-create__parent">
        <ESelect
          v-model="newParentId"
          :items="parentSelectItems"
          :label="t('grossanlass.beschaffung.bedarf.categoryUnder')"
          hide-details
        >
          <template #item="{ props: itemProps, item }">
            <GrossanlassCategoryDropdownItem :item-props="itemProps" :item="item" />
          </template>
        </ESelect>
      </div>
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
      <li v-for="(parent, parentIndex) in parents" :key="parent.id" class="cat-branch">
        <div class="cat-node">
        <div v-if="editingId === parent.id" class="cat-node__row cat-node__row--edit">
          <div class="cat-edit__name">
            <ETextField
              v-model="editName"
              :label="t('grossanlass.beschaffung.bedarf.categoryName')"
              hide-details
              @keydown.enter.prevent="saveEdit"
            />
          </div>
          <div class="cat-edit__parent">
                <ESelect
                  v-model="editParentId"
                  :items="editParentItems(parent)"
                  :label="t('grossanlass.beschaffung.bedarf.categoryUnder')"
                  hide-details
                >
                  <template #item="{ props: itemProps, item }">
                    <GrossanlassCategoryDropdownItem :item-props="itemProps" :item="item" />
                  </template>
                </ESelect>
          </div>
          <div class="cat-node__actions">
            <button type="button" class="icon-btn" :title="t('common.save')" :disabled="!!savingId" @click="saveEdit">
              <v-icon icon="mdi-check" size="16" />
            </button>
            <button type="button" class="icon-btn" :title="t('common.cancel')" :disabled="!!savingId" @click="cancelEdit">
              <v-icon icon="mdi-close" size="16" />
            </button>
          </div>
        </div>
        <div v-else class="cat-node__row">
          <strong class="cat-node__name">{{ parent.name }}</strong>
          <div class="cat-node__actions">
            <button
              type="button"
              class="icon-btn"
              :title="t('grossanlass.beschaffung.bedarf.categoryMoveUp')"
              :disabled="parentIndex === 0 || !!savingId"
              @click="moveCategory(parent, -1)"
            >
              <v-icon icon="mdi-arrow-up" size="16" />
            </button>
            <button
              type="button"
              class="icon-btn"
              :title="t('grossanlass.beschaffung.bedarf.categoryMoveDown')"
              :disabled="parentIndex === parents.length - 1 || !!savingId"
              @click="moveCategory(parent, 1)"
            >
              <v-icon icon="mdi-arrow-down" size="16" />
            </button>
            <button type="button" class="icon-btn" :title="t('common.edit')" @click="startEdit(parent)">
              <v-icon icon="mdi-pencil-outline" size="16" />
            </button>
            <button
              type="button"
              class="icon-btn icon-btn--danger"
              :title="t('common.delete')"
              @click="removeCategory(parent)"
            >
              <v-icon icon="mdi-delete-outline" size="16" />
            </button>
          </div>
        </div>
        </div>
        <ul v-if="childrenOf(parent.id).length" class="cat-tree cat-tree--nested">
          <li
            v-for="(child, childIndex) in childrenOf(parent.id)"
            :key="child.id"
            class="cat-node cat-node--child"
          >
            <div v-if="editingId === child.id" class="cat-node__row cat-node__row--edit">
              <div class="cat-edit__name">
                <ETextField
                  v-model="editName"
                  :label="t('grossanlass.beschaffung.bedarf.categoryName')"
                  hide-details
                  @keydown.enter.prevent="saveEdit"
                />
              </div>
              <div class="cat-edit__parent">
                <ESelect
                  v-model="editParentId"
                  :items="editParentItems(child)"
                  :label="t('grossanlass.beschaffung.bedarf.categoryUnder')"
                  hide-details
                >
                  <template #item="{ props: itemProps, item }">
                    <GrossanlassCategoryDropdownItem :item-props="itemProps" :item="item" />
                  </template>
                </ESelect>
              </div>
              <div class="cat-node__actions">
                <button type="button" class="icon-btn" :title="t('common.save')" :disabled="!!savingId" @click="saveEdit">
                  <v-icon icon="mdi-check" size="16" />
                </button>
                <button type="button" class="icon-btn" :title="t('common.cancel')" :disabled="!!savingId" @click="cancelEdit">
                  <v-icon icon="mdi-close" size="16" />
                </button>
              </div>
            </div>
            <div v-else class="cat-node__row">
              <span class="cat-node__name cat-node__name--child">
                <span class="cat-node__indent" aria-hidden="true">↳</span>
                {{ child.name }}
              </span>
              <div class="cat-node__actions">
                <button
                  type="button"
                  class="icon-btn"
                  :title="t('grossanlass.beschaffung.bedarf.categoryMoveUp')"
                  :disabled="childIndex === 0 || !!savingId"
                  @click="moveCategory(child, -1)"
                >
                  <v-icon icon="mdi-arrow-up" size="16" />
                </button>
                <button
                  type="button"
                  class="icon-btn"
                  :title="t('grossanlass.beschaffung.bedarf.categoryMoveDown')"
                  :disabled="childIndex === childrenOf(parent.id).length - 1 || !!savingId"
                  @click="moveCategory(child, 1)"
                >
                  <v-icon icon="mdi-arrow-down" size="16" />
                </button>
                <button type="button" class="icon-btn" :title="t('common.edit')" @click="startEdit(child)">
                  <v-icon icon="mdi-pencil-outline" size="16" />
                </button>
                <button
                  type="button"
                  class="icon-btn icon-btn--danger"
                  :title="t('common.delete')"
                  @click="removeCategory(child)"
                >
                  <v-icon icon="mdi-delete-outline" size="16" />
                </button>
              </div>
            </div>
          </li>
        </ul>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import GrossanlassCategoryDropdownItem from '@/components/grossanlass/GrossanlassCategoryDropdownItem.vue'
import { getGrossanlassGmailStatus } from '@/api/grossanlassGmail'
import {
  createGrossanlassProcurementCategory,
  deleteGrossanlassProcurementCategory,
  updateGrossanlassProcurementCategory,
  type GrossanlassProcurementCategory,
} from '@/api/grossanlassProcurement'

const props = defineProps<{
  departmentId: string
  categories: GrossanlassProcurementCategory[]
}>()

const emit = defineEmits<{
  created: [category: GrossanlassProcurementCategory]
  updated: [category: GrossanlassProcurementCategory]
  deleted: [categoryId: string]
}>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const newName = ref('')
const newParentId = ref('')
const creating = ref(false)
const editingId = ref<string | null>(null)
const editName = ref('')
const editParentId = ref('')
const savingId = ref<string | null>(null)
const gmailConnected = ref(false)
const gmailEmail = ref('')

const newNameTrimmed = computed(() => newName.value.trim())

function bySort(a: GrossanlassProcurementCategory, b: GrossanlassProcurementCategory) {
  if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order
  return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
}

const parents = computed(() =>
  props.categories.filter((c) => !c.parent_id).slice().sort(bySort),
)

type CategorySelectItem = {
  title: string
  value: string
  name: string
  depth: number
  props?: { disabled: boolean }
}

function noneSelectItem(): CategorySelectItem {
  const title = t('grossanlass.beschaffung.bedarf.categoryUnderNone')
  return { title, value: '', name: title, depth: 0 }
}

function childrenOf(parentId: string): GrossanlassProcurementCategory[] {
  return props.categories.filter((c) => c.parent_id === parentId).slice().sort(bySort)
}

function parentTreeItems(excludeId?: string): CategorySelectItem[] {
  const items: CategorySelectItem[] = [noneSelectItem()]
  for (const parent of parents.value) {
    if (parent.id === excludeId) continue
    items.push({ title: parent.name, value: parent.id, name: parent.name, depth: 0 })
    for (const child of childrenOf(parent.id)) {
      if (child.id === excludeId) continue
      items.push({
        title: child.name,
        value: child.id,
        name: child.name,
        depth: 1,
        props: { disabled: true },
      })
    }
  }
  return items
}

const parentSelectItems = computed(() => parentTreeItems())

function editParentItems(category: GrossanlassProcurementCategory) {
  if (childrenOf(category.id).length > 0) {
    return [noneSelectItem()]
  }
  return parentTreeItems(category.id)
}

onMounted(async () => {
  if (!props.departmentId) return
  try {
    const status = await getGrossanlassGmailStatus(props.departmentId)
    gmailConnected.value = status.connected
    gmailEmail.value = status.email || ''
  } catch {
    gmailConnected.value = false
  }
})

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

function startEdit(category: GrossanlassProcurementCategory) {
  editingId.value = category.id
  editName.value = category.name
  editParentId.value = category.parent_id || ''
}

function cancelEdit() {
  editingId.value = null
  editName.value = ''
  editParentId.value = ''
}

async function saveEdit() {
  const id = editingId.value
  if (!id || !props.departmentId || savingId.value) return
  const name = editName.value.trim()
  if (!name) return
  savingId.value = id
  try {
    const updated = await updateGrossanlassProcurementCategory(props.departmentId, id, {
      name,
      parent_id: editParentId.value || null,
    })
    emit('updated', updated)
    cancelEdit()
    toast.success(t('grossanlass.beschaffung.bedarf.categoryUpdateSuccess'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCategoryUpdate'))
  } finally {
    savingId.value = null
  }
}

async function moveCategory(category: GrossanlassProcurementCategory, direction: number) {
  if (!props.departmentId || savingId.value) return
  const siblings = category.parent_id ? childrenOf(category.parent_id) : parents.value
  const index = siblings.findIndex((c) => c.id === category.id)
  const swapIndex = index + direction
  if (index < 0 || swapIndex < 0 || swapIndex >= siblings.length) return
  const reordered = [...siblings]
  const [moved] = reordered.splice(index, 1)
  reordered.splice(swapIndex, 0, moved)
  savingId.value = category.id
  try {
    const updated: GrossanlassProcurementCategory[] = []
    for (let i = 0; i < reordered.length; i++) {
      const nextOrder = (i + 1) * 10
      if (reordered[i].sort_order === nextOrder) continue
      updated.push(
        await updateGrossanlassProcurementCategory(props.departmentId, reordered[i].id, {
          sort_order: nextOrder,
        }),
      )
    }
    for (const row of updated) emit('updated', row)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCategoryUpdate'))
  } finally {
    savingId.value = null
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
    if (editingId.value === category.id) cancelEdit()
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

.panel-hint--gmail {
  margin-top: -8px;
  color: #64748b;
}

.cat-create {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
}

.cat-create__name {
  flex: 2 1 280px;
  min-width: 240px;
}

.cat-create__parent {
  flex: 1 1 220px;
  min-width: 180px;
}

.cat-create__name :deep(.e-form-field),
.cat-create__parent :deep(.e-form-field),
.cat-edit__name :deep(.e-form-field),
.cat-edit__parent :deep(.e-form-field) {
  width: 100%;
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

.cat-branch {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cat-tree.cat-tree--nested {
  margin: 0 0 0 5.5rem;
  padding-left: 0.75rem;
  border-left: 3px solid #0d9488;
  gap: 6px;
}

.cat-node {
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fafafa;
}

.cat-node--child {
  background: #fff;
}

.cat-node__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.cat-node__row--edit {
  flex-wrap: wrap;
  align-items: flex-end;
}

.cat-node__name {
  min-width: 0;
  flex: 1;
}

.cat-node__name--child {
  color: #334155;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.cat-node__indent {
  color: #94a3b8;
  font-weight: 600;
}

.cat-node__actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.cat-edit__name {
  flex: 2 1 220px;
  min-width: 180px;
}

.cat-edit__parent {
  flex: 1 1 180px;
  min-width: 140px;
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

.icon-btn:disabled {
  opacity: 0.4;
  cursor: default;
}

.icon-btn--danger {
  color: #dc2626;
}
</style>
