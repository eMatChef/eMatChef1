<template>
  <section class="cat-manager">
    <div v-if="!hideHeading" class="panel-head">
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
      <li
        v-for="row in visibleRows"
        :key="row.kind === 'add' ? `add-${row.parentId}` : row.category.id"
        class="cat-node"
        :class="{
          'cat-node--child': row.depth > 0,
          'cat-node--add': row.kind === 'add',
        }"
        :style="{ marginLeft: `${row.depth * 1.15}rem` }"
      >
        <template v-if="row.kind === 'add'">
          <div class="cat-node__row cat-node__row--edit">
            <div class="cat-edit__name">
              <ETextField
                v-model="childName"
                class="cat-add-child"
                :label="t('grossanlass.beschaffung.bedarf.categoryName')"
                :placeholder="t('grossanlass.beschaffung.bedarf.categoryNamePlaceholder')"
                hide-details
                @keydown.enter.prevent="confirmAddChild"
              />
            </div>
            <div class="cat-node__actions">
              <button
                type="button"
                class="icon-btn"
                :title="t('grossanlass.beschaffung.bedarf.categoryAddChild')"
                :disabled="!childNameTrimmed || creating"
                @click="confirmAddChild"
              >
                <v-icon icon="mdi-check" size="16" />
              </button>
              <button
                type="button"
                class="icon-btn"
                :title="t('common.cancel')"
                :disabled="creating"
                @click="cancelAddChild"
              >
                <v-icon icon="mdi-close" size="16" />
              </button>
            </div>
          </div>
        </template>
        <template v-else-if="row.kind === 'node' && editingId === row.category.id">
          <div class="cat-node__row cat-node__row--edit">
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
                :items="editParentItems(row.category)"
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
        </template>
        <div v-else-if="row.kind === 'node'" class="cat-node__row">
          <button
            v-if="row.hasChildren"
            type="button"
            class="icon-btn icon-btn--twist"
            :title="isCollapsed(row.category.id)
              ? t('grossanlass.beschaffung.bedarf.categoryExpand')
              : t('grossanlass.beschaffung.bedarf.categoryCollapse')"
            :aria-expanded="!isCollapsed(row.category.id)"
            @click="toggleCollapsed(row.category.id)"
          >
            <v-icon :icon="isCollapsed(row.category.id) ? 'mdi-chevron-right' : 'mdi-chevron-down'" size="18" />
          </button>
          <span v-else class="cat-twist-spacer" aria-hidden="true" />
          <strong
            class="cat-node__name"
            :class="{ 'cat-node__name--child': row.depth > 0, 'is-toggle': row.hasChildren }"
            @click="row.hasChildren ? toggleCollapsed(row.category.id) : undefined"
          >
            {{ row.category.name }}
            <span v-if="isLocked(row.category)" class="cat-node__lock">{{ t('grossanlass.beschaffung.bedarf.categoryLockedBadge') }}</span>
            <span v-if="row.hasChildren && isCollapsed(row.category.id)" class="cat-node__count">{{ row.childCount }}</span>
          </strong>
          <div class="cat-node__actions">
            <button
              type="button"
              class="icon-btn"
              :title="t('grossanlass.beschaffung.bedarf.categoryMoveUp')"
              :disabled="isLocked(row.category) || row.siblingIndex === 0 || !!savingId"
              @click="moveCategory(row.category, -1)"
            >
              <v-icon icon="mdi-arrow-up" size="16" />
            </button>
            <button
              type="button"
              class="icon-btn"
              :title="t('grossanlass.beschaffung.bedarf.categoryMoveDown')"
              :disabled="isLocked(row.category) || row.siblingIndex === row.siblingCount - 1 || !!savingId"
              @click="moveCategory(row.category, 1)"
            >
              <v-icon icon="mdi-arrow-down" size="16" />
            </button>
            <button
              type="button"
              class="icon-btn icon-btn--add"
              :title="t('grossanlass.beschaffung.bedarf.categoryAddUnder', { name: row.category.name })"
              :disabled="!!savingId || creating || addingUnderId === row.category.id"
              @click="startAddChild(row.category)"
            >
              <v-icon icon="mdi-plus" size="16" />
            </button>
            <button
              v-if="!isLocked(row.category)"
              type="button"
              class="icon-btn"
              :title="t('common.edit')"
              @click="startEdit(row.category)"
            >
              <v-icon icon="mdi-pencil-outline" size="16" />
            </button>
            <button
              v-if="!isLocked(row.category)"
              type="button"
              class="icon-btn icon-btn--danger"
              :title="t('common.delete')"
              @click="removeCategory(row.category)"
            >
              <v-icon icon="mdi-delete-outline" size="16" />
            </button>
          </div>
        </div>
      </li>
    </ul>
  </section>

  <EDialog
    v-model="reassignOpen"
    :max-width="560"
    :title="t('grossanlass.beschaffung.bedarf.categoryDeleteBlockedTitle')"
    persistent
    scrollable
    :retain-focus="false"
  >
    <p class="reassign-hint">
      {{ t('grossanlass.beschaffung.bedarf.categoryDeleteBlockedHint', {
        name: deletingCategory?.name || '',
      }) }}
    </p>
    <ESelect
      v-model="reassignTargetId"
      :items="reassignTargetItems"
      :label="t('grossanlass.beschaffung.bedarf.categoryDeleteReassign')"
      hide-details
    >
      <template #item="{ props: itemProps, item }">
        <GrossanlassCategoryDropdownItem :item-props="itemProps" :item="item" />
      </template>
    </ESelect>
    <p v-if="reassignTargetItems.length === 0" class="reassign-empty">
      {{ t('grossanlass.beschaffung.bedarf.categoryDeleteNeedOther') }}
    </p>
    <div v-if="usageLines.length" class="reassign-block">
      <h4>{{ t('grossanlass.beschaffung.bedarf.categoryDeleteLinesTitle', { count: usageLines.length }) }}</h4>
      <ul class="reassign-list">
        <li v-for="line in usageLines" :key="line.id">
          <strong>{{ line.quantity }}× {{ line.label }}</strong>
          <span>{{ line.group_name }}{{ line.category_name ? ` · ${line.category_name}` : '' }}</span>
        </li>
      </ul>
    </div>
    <div v-if="usageInquiries.length" class="reassign-block">
      <h4>{{ t('grossanlass.beschaffung.bedarf.categoryDeleteInquiriesTitle', { count: usageInquiries.length }) }}</h4>
      <ul class="reassign-list">
        <li v-for="row in usageInquiries" :key="row.id">{{ row.name }}</li>
      </ul>
      <p class="reassign-mail-hint">{{ t('grossanlass.beschaffung.bedarf.categoryDeleteMailHint') }}</p>
    </div>
    <template #actions>
      <EButton variant="secondary" size="small" :disabled="reassigning" @click="closeReassign">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="!reassignTargetId || reassignTargetItems.length === 0"
        :loading="reassigning"
        @click="confirmReassignAndDelete"
      >
        {{ t('grossanlass.beschaffung.bedarf.categoryDeleteReassignAction') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import GrossanlassCategoryDropdownItem from '@/components/grossanlass/GrossanlassCategoryDropdownItem.vue'
import { getGrossanlassGmailStatus } from '@/api/grossanlassGmail'
import {
  createGrossanlassProcurementCategory,
  deleteGrossanlassProcurementCategory,
  getGrossanlassProcurementCategoryUsage,
  updateGrossanlassProcurementCategory,
  type GrossanlassCategoryUsageInquiry,
  type GrossanlassCategoryUsageLine,
  type GrossanlassProcurementCategory,
} from '@/api/grossanlassProcurement'
import {
  childrenOfProcurementCategory,
  descendantIdsOfProcurementCategory,
  procurementCategoryTreeItems,
} from '@/utils/grossanlassProcurementCategoryTree'

const props = defineProps<{
  departmentId: string
  categories: GrossanlassProcurementCategory[]
  hideHeading?: boolean
}>()

const emit = defineEmits<{
  created: [category: GrossanlassProcurementCategory]
  updated: [category: GrossanlassProcurementCategory]
  deleted: [categoryId: string, reassignTo?: GrossanlassProcurementCategory]
}>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const newName = ref('')
const newParentId = ref('')
const creating = ref(false)
const addingUnderId = ref<string | null>(null)
const childName = ref('')
const collapsedIds = ref<string[]>([])
const editingId = ref<string | null>(null)
const editName = ref('')
const editParentId = ref('')
const savingId = ref<string | null>(null)
const gmailConnected = ref(false)
const gmailEmail = ref('')
const reassignOpen = ref(false)
const reassigning = ref(false)
const deletingCategory = ref<GrossanlassProcurementCategory | null>(null)
const reassignTargetId = ref('')
const usageLines = ref<GrossanlassCategoryUsageLine[]>([])
const usageInquiries = ref<GrossanlassCategoryUsageInquiry[]>([])

const newNameTrimmed = computed(() => newName.value.trim())
const childNameTrimmed = computed(() => childName.value.trim())

function isLocked(category: GrossanlassProcurementCategory): boolean {
  return !!category.system_key
}

function childrenOf(parentId: string | null): GrossanlassProcurementCategory[] {
  return childrenOfProcurementCategory(props.categories, parentId)
}

function isCollapsed(id: string): boolean {
  return collapsedIds.value.includes(id)
}

function toggleCollapsed(id: string) {
  if (isCollapsed(id)) {
    collapsedIds.value = collapsedIds.value.filter((row) => row !== id)
    return
  }
  collapsedIds.value = [...collapsedIds.value, id]
}

function expandAncestors(id: string) {
  const open = new Set(collapsedIds.value)
  let current = props.categories.find((c) => c.id === id)
  const seen = new Set<string>()
  while (current && !seen.has(current.id)) {
    seen.add(current.id)
    open.delete(current.id)
    current = current.parent_id
      ? props.categories.find((c) => c.id === current!.parent_id)
      : undefined
  }
  collapsedIds.value = [...open]
}

type VisibleRow =
  | {
      kind: 'node'
      category: GrossanlassProcurementCategory
      depth: number
      siblingIndex: number
      siblingCount: number
      hasChildren: boolean
      childCount: number
    }
  | { kind: 'add'; parentId: string; depth: number }

const visibleRows = computed((): VisibleRow[] => {
  const rows: VisibleRow[] = []
  const walk = (parentId: string | null, depth: number) => {
    const siblings = childrenOf(parentId)
    siblings.forEach((category, siblingIndex) => {
      const childCount = childrenOf(category.id).length
      const addingHere = addingUnderId.value === category.id
      const hasChildren = childCount > 0 || addingHere
      rows.push({
        kind: 'node',
        category,
        depth,
        siblingIndex,
        siblingCount: siblings.length,
        hasChildren,
        childCount,
      })
      if (hasChildren && !isCollapsed(category.id)) {
        walk(category.id, depth + 1)
        if (addingHere) {
          rows.push({ kind: 'add', parentId: category.id, depth: depth + 1 })
        }
      }
    })
  }
  walk(null, 0)
  return rows
})

const reassignRemovedIds = computed(() => {
  const id = deletingCategory.value?.id
  if (!id) return new Set<string>()
  return descendantIdsOfProcurementCategory(props.categories, id)
})

const reassignTargetItems = computed(() =>
  procurementCategoryTreeItems(props.categories, reassignRemovedIds.value),
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

function parentTreeItems(excludeIds?: Set<string>): CategorySelectItem[] {
  return [noneSelectItem(), ...procurementCategoryTreeItems(props.categories, excludeIds)]
}

const parentSelectItems = computed(() => parentTreeItems())

function editParentItems(category: GrossanlassProcurementCategory) {
  return parentTreeItems(descendantIdsOfProcurementCategory(props.categories, category.id))
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
  const parentId = newParentId.value || null
  await submitCreate(newNameTrimmed.value, parentId, () => {
    newName.value = ''
    if (parentId) expandAncestors(parentId)
  })
}

async function startAddChild(parent: GrossanlassProcurementCategory) {
  if (savingId.value || creating.value) return
  cancelEdit()
  expandAncestors(parent.id)
  addingUnderId.value = parent.id
  childName.value = ''
  await nextTick()
  const input = document.querySelector('.cat-add-child input') as HTMLInputElement | null
  input?.focus()
}

function cancelAddChild() {
  if (creating.value) return
  addingUnderId.value = null
  childName.value = ''
}

async function confirmAddChild() {
  const parentId = addingUnderId.value
  if (!parentId) return
  await submitCreate(childNameTrimmed.value, parentId, () => {
    addingUnderId.value = null
    childName.value = ''
  })
}

async function submitCreate(name: string, parentId: string | null, onSuccess: () => void) {
  if (!name || creating.value || !props.departmentId) return

  creating.value = true
  try {
    const created = await createGrossanlassProcurementCategory(props.departmentId, {
      name,
      parent_id: parentId,
    })
    emit('created', created)
    onSuccess()
    toast.success(t('grossanlass.beschaffung.bedarf.categoryCreateSuccess'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCategoryCreate'))
  } finally {
    creating.value = false
  }
}

function startEdit(category: GrossanlassProcurementCategory) {
  if (isLocked(category)) return
  cancelAddChild()
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
  const siblings = childrenOf(category.parent_id)
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
  if (!props.departmentId || isLocked(category)) return
  try {
    const usage = await getGrossanlassProcurementCategoryUsage(props.departmentId, category.id)
    if (usage.lines.length > 0 || usage.inquiries.length > 0) {
      deletingCategory.value = category
      usageLines.value = usage.lines
      usageInquiries.value = usage.inquiries
      reassignTargetId.value = reassignTargetItems.value[0]?.value || ''
      reassignOpen.value = true
      return
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCategoryDelete'))
    return
  }
  const hasChildren = childrenOf(category.id).length > 0
  const ok = await confirm.confirm({
    title: t('grossanlass.beschaffung.bedarf.categoryDeleteTitle'),
    message: hasChildren
      ? t('grossanlass.beschaffung.bedarf.categoryDeleteHasChildren', { name: category.name })
      : t('grossanlass.beschaffung.bedarf.categoryDeleteMessage', { name: category.name }),
  })
  if (!ok) return
  await executeDelete(category.id)
}

function closeReassign() {
  if (reassigning.value) return
  reassignOpen.value = false
  deletingCategory.value = null
  usageLines.value = []
  usageInquiries.value = []
  reassignTargetId.value = ''
}

async function confirmReassignAndDelete() {
  const category = deletingCategory.value
  const targetId = reassignTargetId.value
  if (!category || !targetId || !props.departmentId) return
  reassigning.value = true
  try {
    await executeDelete(category.id, targetId)
    reassignOpen.value = false
    deletingCategory.value = null
    usageLines.value = []
    usageInquiries.value = []
    reassignTargetId.value = ''
  } finally {
    reassigning.value = false
  }
}

async function executeDelete(categoryId: string, reassignTo?: string) {
  if (!props.departmentId) return
  try {
    await deleteGrossanlassProcurementCategory(
      props.departmentId,
      categoryId,
      reassignTo ? { reassign_to: reassignTo } : undefined,
    )
    const target = reassignTo
      ? props.categories.find((c) => c.id === reassignTo)
      : undefined
    emit('deleted', categoryId, target)
    if (newParentId.value === categoryId) newParentId.value = ''
    if (addingUnderId.value === categoryId) cancelAddChild()
    if (editingId.value === categoryId) cancelEdit()
    toast.success(
      reassignTo
        ? t('grossanlass.beschaffung.bedarf.categoryDeleteReassignSuccess')
        : t('grossanlass.beschaffung.bedarf.categoryDeleteSuccess'),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string; code?: string } } }
    if (err.response?.data?.code === 'category_in_use') {
      toast.error(t('grossanlass.beschaffung.bedarf.categoryDeleteBlockedHint', {
        name: deletingCategory.value?.name || '',
      }))
      return
    }
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
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.cat-node__name.is-toggle {
  cursor: pointer;
}

.cat-node__lock {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #0f766e;
  background: #ccfbf1;
  border-radius: 999px;
  padding: 1px 8px;
}

.cat-node__count {
  font-size: 0.72rem;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 1px 7px;
}

.cat-node__name--child {
  color: #334155;
}

.cat-node__actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.cat-twist-spacer {
  width: 28px;
  flex-shrink: 0;
}

.icon-btn--twist {
  border-color: transparent;
  background: transparent;
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

.icon-btn--add {
  color: #0f766e;
}

.icon-btn--danger {
  color: #dc2626;
}

.cat-node--add {
  border-style: dashed;
  background: #f0fdfa;
}

.reassign-hint {
  margin: 0 0 12px;
  font-size: 0.88rem;
  color: #334155;
}

.reassign-empty {
  margin: 8px 0 0;
  font-size: 0.82rem;
  color: #b45309;
}

.reassign-block {
  margin-top: 14px;
}

.reassign-block h4 {
  margin: 0 0 6px;
  font-size: 0.82rem;
  font-weight: 600;
}

.reassign-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 6px;
  max-height: 180px;
  overflow: auto;
}

.reassign-list li {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f8fafc;
  font-size: 0.85rem;
}

.reassign-list span {
  color: #64748b;
  font-size: 0.78rem;
}

.reassign-mail-hint {
  margin: 8px 0 0;
  font-size: 0.8rem;
  color: #64748b;
}
</style>
