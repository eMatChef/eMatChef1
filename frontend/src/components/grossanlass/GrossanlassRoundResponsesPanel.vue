<template>
  <div class="round-responses">
    <div class="responses-toolbar">
      <ETextField
        v-model="searchQuery"
        :label="t('grossanlass.responses.search')"
        hide-details
        density="compact"
        class="responses-search"
        @keyup.enter="applyFilters"
      />
      <ESelect
        v-model="statusFilter"
        :items="statusItems"
        :label="t('grossanlass.responses.filterStatus')"
        hide-details
        density="compact"
        class="responses-filter"
      />
      <ESelect
        v-model="groupFilter"
        :items="groupItems"
        :label="t('grossanlass.responses.filterRessort')"
        hide-details
        density="compact"
        class="responses-filter"
      />
      <EButton variant="secondary" size="small" @click="applyFilters">
        {{ t('grossanlass.responses.filter') }}
      </EButton>
    </div>

    <div class="responses-stats">
      <span>{{ t('grossanlass.responses.statTotal', { count: total }) }}</span>
      <span class="stat-pending">{{ t('grossanlass.responses.statSubmitted', { count: counts.requested }) }}</span>
      <span class="stat-accepted">{{ t('grossanlass.responses.statInProcurement', { count: counts.accepted }) }}</span>
    </div>

    <ELoadingState v-if="isLoading" variant="inline" :message="t('common.loading')" />

    <EEmptyState
      v-else-if="items.length === 0"
      variant="default"
      icon="mdi-clipboard-text-outline"
      :title="t('grossanlass.responses.emptyTitle')"
      :description="t('grossanlass.responses.emptyDescription')"
    />

    <div v-else class="responses-table-wrap">
      <table class="responses-table">
        <thead>
          <tr>
            <th v-if="showActionsColumn">{{ t('grossanlass.responses.colActions') }}</th>
            <th v-for="col in tableColumns" :key="col.id">{{ col.label }}</th>
            <th>{{ t('grossanlass.responses.colStatus') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td v-if="showActionsColumn" class="col-actions">
              <div class="row-actions">
                <button
                  v-if="canModifyItem(item)"
                  type="button"
                  class="icon-btn"
                  :title="t('common.edit')"
                  @click="openEdit(item)"
                >
                  <v-icon icon="mdi-pencil-outline" size="18" />
                </button>
                <button
                  v-if="canModifyItem(item)"
                  type="button"
                  class="icon-btn icon-btn--danger"
                  :title="t('common.delete')"
                  :disabled="deletingId === item.id"
                  @click="deleteItem(item)"
                >
                  <v-icon icon="mdi-delete-outline" size="18" />
                </button>
              </div>
            </td>
            <td v-for="col in tableColumns" :key="col.id">
              <template v-if="col.field?.system_key === 'label'">
                {{ cellValue(item, col.field) }}
                <span v-if="item.wish_kind" class="kind-tag">{{ wishKindLabel(item.wish_kind) }}</span>
              </template>
              <template v-else>
                {{ col.field ? cellValue(item, col.field) : '–' }}
              </template>
            </td>
            <td>
              <span class="status-chip" :class="'status-' + item.status">
                {{ statusLabel(item.status) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EDialog
      v-model="editOpen"
      :title="t('grossanlass.responses.editTitle')"
      max-width="720"
      scrollable
    >
      <GrossanlassWishDynamicForm
        v-if="editOpen && form && editingItem"
        :key="editingItem.id"
        ref="editFormRef"
        :form="form"
        :department-id="departmentId"
        :groups="groups"
        :can-fully-manage="canFullyManage"
        :is-member-in-ressort-branch="isMemberInRessortBranch"
        :is-leader-of-group="isLeaderOfGroup"
        :can-create-child="canCreateChild"
      />
      <template #actions>
        <EButton variant="secondary" @click="editOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :loading="isSavingEdit" @click="saveEdit">
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>

    <div v-if="totalPages > 1" class="responses-pagination">
      <EButton variant="secondary" size="small" :disabled="page <= 1" @click="goPage(page - 1)">
        {{ t('grossanlass.responses.previous') }}
      </EButton>
      <span class="page-info">{{ t('grossanlass.responses.pageInfo', { page, total: totalPages }) }}</span>
      <EButton variant="secondary" size="small" :disabled="page >= totalPages" @click="goPage(page + 1)">
        {{ t('grossanlass.responses.next') }}
      </EButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import GrossanlassWishDynamicForm from '@/components/grossanlass/GrossanlassWishDynamicForm.vue'
import {
  deleteGrossanlassWish,
  getGrossanlassRoundWishes,
  updateGrossanlassWish,
  type GrossanlassWishKind,
  type GrossanlassWishLine,
  type GrossanlassWishListResult,
} from '@/api/grossanlassWishes'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import type { GrossanlassRoundForm, GrossanlassRoundFormField } from '@/api/grossanlassRoundForm'
import type { GrossanlassRoundStatus } from '@/api/grossanlassRounds'
import {
  buildGrossanlassWishTableColumns,
  formatGrossanlassWishCellValue,
} from '@/utils/grossanlassWishDisplay'
import {
  flattenGrossanlassGroupsWithLevel,
  grossanlassGroupIndentTitle,
  isBauprojektGroup,
  ressortPathForBauprojekt,
} from '@/utils/grossanlassGroupHierarchy'

const props = defineProps<{
  departmentId: string
  roundId: string
  roundStatus: GrossanlassRoundStatus
  groups: GrossanlassGroup[]
  form: GrossanlassRoundForm | null
  canFullyManage: boolean
  isMemberInRessortBranch: (g: GrossanlassGroup) => boolean
  isLeaderOfGroup: (g: GrossanlassGroup) => boolean
  canCreateChild: (g: GrossanlassGroup) => boolean
}>()

const emit = defineEmits<{
  changed: []
}>()

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const authStore = useAuthStore()
const { isMaterialwart } = useDepartmentMemberRole()

const items = ref<GrossanlassWishLine[]>([])
const total = ref(0)
const page = ref(1)
const limit = ref(50)
const counts = ref({ requested: 0, accepted: 0 })
const isLoading = ref(false)
const deletingId = ref<string | null>(null)
const editOpen = ref(false)
const editingItem = ref<GrossanlassWishLine | null>(null)
const isSavingEdit = ref(false)
const editFormRef = ref<InstanceType<typeof GrossanlassWishDynamicForm> | null>(null)

const searchQuery = ref('')
const statusFilter = ref<string | null>(null)
const groupFilter = ref<string | null>(null)

const statusItems = computed(() => [
  { title: t('grossanlass.responses.statusAll'), value: null },
  { title: t('grossanlass.responses.statusSubmitted'), value: 'requested' },
  { title: t('grossanlass.responses.statusInProcurement'), value: 'accepted' },
])

const groupItems = computed(() => [
  { title: t('grossanlass.responses.allRessorts'), value: null },
  ...flattenGrossanlassGroupsWithLevel(props.groups).map((g) => ({
    title: isBauprojektGroup(g)
      ? `${g.name} (${ressortPathForBauprojekt(g, props.groups)})`
      : grossanlassGroupIndentTitle(g),
    value: g.id,
  })),
])

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / limit.value)))

const tableColumns = computed(() => buildGrossanlassWishTableColumns(props.form?.fields ?? []))

const roundIsOpen = computed(() => props.roundStatus === 'open')

const showActionsColumn = computed(() => {
  if (!roundIsOpen.value) return false
  if (isMaterialwart.value) return true
  const userId = authStore.userId
  return userId ? items.value.some((item) => item.created_by_user_id === userId) : false
})

function canModifyItem(item: GrossanlassWishLine): boolean {
  if (!roundIsOpen.value) return false
  if (isMaterialwart.value) return true
  return item.created_by_user_id === authStore.userId
}

function cellValue(item: GrossanlassWishLine, field: GrossanlassRoundFormField): string {
  return formatGrossanlassWishCellValue(item, field, { wishKind: wishKindLabel })
}

function wishKindLabel(kind: GrossanlassWishKind): string {
  switch (kind) {
    case 'material':
      return t('grossanlass.wishes.kindMaterial')
    case 'fahrzeug':
      return t('grossanlass.wishes.kindFahrzeug')
    default:
      return t('grossanlass.wishes.kindBeides')
  }
}

function statusLabel(status: string): string {
  if (status === 'accepted') return t('grossanlass.responses.statusInProcurement')
  return t('grossanlass.responses.statusSubmitted')
}

async function load() {
  if (!props.departmentId || !props.roundId) return
  isLoading.value = true
  try {
    const result = await getGrossanlassRoundWishes(props.departmentId, props.roundId, {
      page: page.value,
      limit: limit.value,
      status: statusFilter.value || undefined,
      group_id: groupFilter.value || undefined,
      q: searchQuery.value.trim() || undefined,
    })
    const data = result as GrossanlassWishListResult
    items.value = data.items
    total.value = data.total
    counts.value = data.counts
    page.value = data.page
  } catch {
    items.value = []
    total.value = 0
  } finally {
    isLoading.value = false
  }
}

function applyFilters() {
  page.value = 1
  void load()
}

function goPage(next: number) {
  page.value = next
  void load()
}

const editLoading = ref(false)

async function openEdit(item: GrossanlassWishLine) {
  editingItem.value = item
  editOpen.value = true
}

watch(editFormRef, async (form) => {
  if (!form || !editingItem.value || !editOpen.value || editLoading.value) return
  editLoading.value = true
  try {
    await form.loadFromWish(editingItem.value)
  } finally {
    editLoading.value = false
  }
})

async function saveEdit() {
  if (!editingItem.value || !editFormRef.value) return
  const payload = editFormRef.value.buildPayload()

  if (payload.new_bauprojekt) {
    toast.error(t('grossanlass.responses.errorEditBauprojekt'))
    return
  }
  if (!payload.group_id && !payload.ressort_group_id) {
    toast.error(t('grossanlass.wishes.errorGroup'))
    return
  }

  isSavingEdit.value = true
  try {
    await updateGrossanlassWish(
      props.departmentId,
      props.roundId,
      editingItem.value.id,
      payload,
    )
    toast.success(t('grossanlass.responses.updated'))
    editOpen.value = false
    editingItem.value = null
    emit('changed')
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.responses.errorUpdate'))
  } finally {
    isSavingEdit.value = false
  }
}

async function deleteItem(item: GrossanlassWishLine) {
  const ok = await confirm({
    title: t('grossanlass.responses.deleteTitle'),
    message: t('grossanlass.responses.deleteMessage'),
    variant: 'danger',
  })
  if (!ok) return

  deletingId.value = item.id
  try {
    await deleteGrossanlassWish(props.departmentId, props.roundId, item.id)
    toast.success(t('grossanlass.responses.deleted'))
    emit('changed')
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.responses.errorDelete'))
  } finally {
    deletingId.value = null
  }
}

watch(
  () => [props.departmentId, props.roundId] as const,
  () => {
    page.value = 1
    void load()
  },
)

onMounted(load)

defineExpose({ reload: load })
</script>

<style scoped>
.responses-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: flex-end;
  margin-bottom: 12px;
}

.responses-search {
  flex: 1 1 220px;
  min-width: 180px;
}

.responses-filter {
  flex: 0 1 180px;
  min-width: 160px;
}

.responses-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: 14px;
  font-size: 0.85rem;
  color: #64748b;
}

.stat-pending { color: #b45309; }
.stat-accepted { color: #047857; }

.responses-table-wrap {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}

.responses-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.responses-table th,
.responses-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: top;
}

.responses-table thead th {
  background: #f8fafc;
  font-weight: 600;
  white-space: nowrap;
}

.kind-tag {
  display: inline-block;
  margin-left: 6px;
  font-size: 0.72rem;
  color: #6b7280;
}

.status-chip {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
}

.status-requested {
  background: #fef3c7;
  color: #92400e;
}

.status-accepted {
  background: #d1fae5;
  color: #065f46;
}

.col-actions {
  white-space: nowrap;
}

.row-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
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
  color: #374151;
}

.icon-btn:hover {
  background: #f9fafb;
}

.icon-btn--danger {
  color: #dc2626;
}

.icon-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.responses-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 16px;
}

.page-info {
  font-size: 0.85rem;
  color: #64748b;
}
</style>
