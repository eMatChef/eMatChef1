<template>
  <div class="beschaffung-bedarf">
    <p class="bedarf-intro">{{ t('grossanlass.beschaffung.bedarf.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <template v-else>
    <GrossanlassProcurementCategoryManager
      :department-id="departmentId"
      :categories="categories"
      @created="onCategoryCreated"
      @deleted="onCategoryDeleted"
    />

    <div class="bedarf-layout">
      <section class="bedarf-panel bedarf-panel--pool">
        <div class="panel-head">
          <h3>{{ t('grossanlass.beschaffung.bedarf.poolTitle') }}</h3>
          <span class="panel-count">{{ pool.length }}</span>
        </div>
        <p class="panel-hint">{{ t('grossanlass.beschaffung.bedarf.poolHint') }}</p>

        <div v-if="suggestions.length > 0" class="suggestions">
          <h4 class="suggestions-title">{{ t('grossanlass.beschaffung.bedarf.suggestionsTitle') }}</h4>
          <p class="suggestions-hint">{{ t('grossanlass.beschaffung.bedarf.suggestionsHint') }}</p>
          <div
            v-for="suggestion in suggestions"
            :key="suggestion.key"
            class="suggestion-card"
          >
            <div class="suggestion-card__head">
              <strong>{{ suggestion.suggested_label }}</strong>
              <span>{{
                t('grossanlass.beschaffung.bedarf.suggestionMeta', {
                  count: suggestion.wish_count,
                  sum: suggestion.quantity_sum,
                })
              }}</span>
            </div>
            <ul class="suggestion-card__list">
              <li v-for="wish in suggestion.wishes" :key="wish.id">
                {{ wish.quantity }}× {{ wish.label }}
                <span class="suggestion-card__meta">· {{ wish.group_name }}</span>
              </li>
            </ul>
            <EButton variant="secondary" size="small" @click="openBundleFromSuggestion(suggestion)">
              {{ t('grossanlass.beschaffung.bedarf.suggestionReview') }}
            </EButton>
          </div>
        </div>

        <EEmptyState
          v-if="pool.length === 0"
          variant="default"
          icon="mdi-clipboard-check-outline"
          :title="t('grossanlass.beschaffung.bedarf.poolEmptyTitle')"
          :description="t('grossanlass.beschaffung.bedarf.poolEmptyDescription')"
        />

        <div v-else class="pool-actions">
          <p v-if="selectedWishIds.length > 0" class="bundle-preview">
            {{ t('grossanlass.beschaffung.bedarf.bundlePreview', {
              count: selectedWishIds.length,
              sum: selectedQuantitySum,
            }) }}
          </p>
          <EButton
            variant="primary"
            size="small"
            :disabled="selectedWishIds.length === 0 || isSaving"
            :loading="isSaving"
            @click="openBundleFromSelection"
          >
            {{ t('grossanlass.beschaffung.bedarf.bundleAction', { count: selectedWishIds.length }) }}
          </EButton>
          <EButton
            v-if="selectedWishIds.length > 0 && lines.length > 0"
            variant="secondary"
            size="small"
            :disabled="!mergeTargetLineId || isSaving"
            @click="mergeIntoLine"
          >
            {{ t('grossanlass.beschaffung.bedarf.mergeIntoLine') }}
          </EButton>
          <ESelect
            v-if="selectedWishIds.length > 0 && lines.length > 0"
            v-model="mergeTargetLineId"
            :items="lineSelectItems"
            :label="t('grossanlass.beschaffung.bedarf.mergeTarget')"
            hide-details
            density="compact"
            class="merge-select"
          />
        </div>

        <div v-if="pool.length > 0" class="wish-pool-list">
          <div
            v-for="wish in pool"
            :key="wish.id"
            class="pool-row"
            :class="{ 'is-selected': selectedWishIds.includes(wish.id) }"
          >
            <label class="pool-row__select">
              <input
                type="checkbox"
                :value="wish.id"
                :checked="selectedWishIds.includes(wish.id)"
                @change="toggleWish(wish.id)"
              />
              <div class="pool-row__body">
                <div class="pool-row__main">
                  <strong>{{ wish.quantity }}× {{ wish.label }}</strong>
                  <span class="kind-tag">{{ wishKindLabel(wish.wish_kind) }}</span>
                </div>
                <div class="pool-row__meta">
                  {{ wish.group_name }} · {{ wish.location }}
                </div>
                <div class="pool-row__meta">
                  {{ wish.round_name }} · {{ wish.created_by_name }}
                </div>
              </div>
            </label>
            <button
              type="button"
              class="icon-btn"
              :title="t('common.edit')"
              @click="openEditWish(wish)"
            >
              <v-icon icon="mdi-pencil-outline" size="16" />
            </button>
          </div>
        </div>
      </section>

      <section class="bedarf-panel bedarf-panel--lines">
        <div class="panel-head">
          <h3>{{ t('grossanlass.beschaffung.bedarf.linesTitle') }}</h3>
          <span class="panel-count">{{ lines.length }}</span>
        </div>
        <p class="panel-hint">{{ t('grossanlass.beschaffung.bedarf.linesHint') }}</p>
        <ESelect
          v-if="lines.length > 0"
          v-model="categoryFilter"
          class="category-filter"
          :items="categoryFilterItems"
          :label="t('grossanlass.beschaffung.bedarf.categoryFilter')"
          hide-details
          density="compact"
        />

        <EEmptyState
          v-if="lines.length === 0"
          variant="default"
          icon="mdi-package-variant-closed"
          :title="t('grossanlass.beschaffung.bedarf.linesEmptyTitle')"
          :description="t('grossanlass.beschaffung.bedarf.linesEmptyDescription')"
        />
        <p v-else-if="groupedLines.length === 0" class="panel-hint">
          {{ t('grossanlass.beschaffung.bedarf.categoryFilterEmpty') }}
        </p>

        <div v-else class="lines-list">
          <section
            v-for="group in groupedLines"
            :key="group.parentId ?? 'uncategorized'"
            class="line-group"
          >
            <h4 class="line-group__title">{{ group.parentName }}</h4>
            <div
              v-for="sub in group.subgroups"
              :key="sub.categoryId ?? 'parent'"
              class="line-subgroup"
            >
              <h5 v-if="sub.categoryName" class="line-subgroup__title">{{ sub.categoryName }}</h5>
              <div v-for="line in sub.lines" :key="line.id" class="line-card">
            <div class="line-card__head">
              <div>
                <strong>{{ line.quantity }}× {{ line.label }}</strong>
                <span class="status-chip">{{ statusLabel(line.status) }}</span>
                <span v-if="line.merge_frozen" class="status-chip status-chip--frozen">
                  {{ t('grossanlass.beschaffung.bedarf.frozenBadge') }}
                </span>
              </div>
              <div class="line-card__actions">
                <button
                  v-if="line.status === 'bedarf'"
                  type="button"
                  class="icon-btn"
                  :title="t('common.edit')"
                  @click="openEditLine(line)"
                >
                  <v-icon icon="mdi-pencil-outline" size="18" />
                </button>
                <button
                  v-if="line.status === 'bedarf'"
                  type="button"
                  class="icon-btn icon-btn--danger"
                  :title="t('common.delete')"
                  @click="removeLine(line)"
                >
                  <v-icon icon="mdi-delete-outline" size="18" />
                </button>
              </div>
            </div>
            <div class="line-card__meta">
              {{ line.group_name }} · {{ line.location }}
              <span v-if="categoryPath(line)" class="category-chip">{{ categoryPath(line) }}</span>
            </div>
            <div class="line-card__total">
              <span class="line-card__total-label">{{ t('grossanlass.beschaffung.bedarf.totalQuantity') }}</span>
              <strong class="line-card__total-value">{{ line.quantity }}×</strong>
              <span
                v-if="line.quantity_asked != null"
                class="quantity-adjusted-hint"
              >
                {{ t('grossanlass.beschaffung.bedarf.askedVsCurrent', {
                  asked: line.quantity_asked,
                  current: line.quantity_current,
                  delta: line.quantity_delta ?? 0,
                }) }}
              </span>
              <span
                v-else-if="line.source_quantity_sum != null && line.quantity !== line.source_quantity_sum"
                class="quantity-adjusted-hint"
              >
                {{ t('grossanlass.beschaffung.bedarf.quantityAdjusted', { sum: line.source_quantity_sum }) }}
              </span>
            </div>
            <div v-if="line.source_wishes?.length" class="line-card__sources">
              <button
                type="button"
                class="sources-toggle"
                @click="toggleLineSources(line.id)"
              >
                <v-icon
                  :icon="expandedLineIds.includes(line.id) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                  size="16"
                />
                {{
                  t('grossanlass.beschaffung.bedarf.sourceWishesToggle', {
                    count: line.source_wishes.length,
                    sum: line.source_quantity_sum ?? line.quantity,
                  })
                }}
              </button>
              <p v-if="expandedLineIds.includes(line.id)" class="sources-hint">
                {{ t('grossanlass.beschaffung.bedarf.sourceWishesHint') }}
              </p>
              <ul v-if="expandedLineIds.includes(line.id)" class="sources-list">
                <li v-for="source in line.source_wishes" :key="source.id" class="source-row">
                  <div>
                    <div class="source-row__main">
                      <strong>{{ source.quantity }}× {{ source.label }}</strong>
                    </div>
                    <div class="source-row__meta">{{ source.group_name }} · {{ source.location }}</div>
                    <div class="source-row__meta">{{ source.round_name }} · {{ source.created_by_name }}</div>
                  </div>
                  <div class="source-row__actions">
                    <button
                      v-if="line.status === 'bedarf'"
                      type="button"
                      class="icon-btn"
                      :title="t('common.edit')"
                      @click="openEditWish(source)"
                    >
                      <v-icon icon="mdi-pencil-outline" size="16" />
                    </button>
                    <button
                      v-if="line.status === 'bedarf' && line.wish_count > 1"
                      type="button"
                      class="split-btn"
                      @click="splitWish(line, source.id)"
                    >
                      {{ t('grossanlass.beschaffung.bedarf.splitWish') }}
                    </button>
                  </div>
                </li>
              </ul>
            </div>
          </div>
            </div>
          </section>
        </div>
      </section>
    </div>
    </template>

    <GrossanlassProcurementLineEditDialog
      v-if="editLine"
      v-model="editDialogOpen"
      :department-id="departmentId"
      :line="editLine"
      :categories="categories"
      @saved="onLineEdited"
      @category-created="onCategoryCreated"
    />

    <GrossanlassProcurementWishEditDialog
      v-if="editWish"
      v-model="editWishDialogOpen"
      :department-id="departmentId"
      :wish="editWish"
      @saved="onWishEdited"
    />

    <GrossanlassProcurementBundleDialog
      v-if="bundleWishes.length > 0"
      v-model="bundleDialogOpen"
      :department-id="departmentId"
      :wishes="bundleWishes"
      :suggested-label="bundleSuggestedLabel"
      :categories="categories"
      @saved="onBundleSaved"
      @category-created="onCategoryCreated"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassProcurementLineEditDialog from '@/components/grossanlass/GrossanlassProcurementLineEditDialog.vue'
import GrossanlassProcurementWishEditDialog from '@/components/grossanlass/GrossanlassProcurementWishEditDialog.vue'
import GrossanlassProcurementBundleDialog from '@/components/grossanlass/GrossanlassProcurementBundleDialog.vue'
import GrossanlassProcurementCategoryManager from '@/components/grossanlass/GrossanlassProcurementCategoryManager.vue'
import { EButton, ESelect } from '@/components/form/base'
import {
  addWishesToGrossanlassProcurementLine,
  deleteGrossanlassProcurementLine,
  getGrossanlassBedarfOverview,
  removeWishFromGrossanlassProcurementLine,
  type GrossanlassBedarfOverview,
  type GrossanlassProcurementBundleSuggestion,
  type GrossanlassProcurementCategory,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementPoolWish,
} from '@/api/grossanlassProcurement'
import { procurementStatusLabel } from '@/utils/grossanlassProcurementStatus'
import type { GrossanlassWishKind } from '@/api/grossanlassWishes'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const departmentId = computed(() => String(route.params.departmentId || ''))

const pool = ref<GrossanlassProcurementPoolWish[]>([])
const lines = ref<GrossanlassProcurementLine[]>([])
const categories = ref<GrossanlassProcurementCategory[]>([])
const suggestions = ref<GrossanlassProcurementBundleSuggestion[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const selectedWishIds = ref<string[]>([])
const mergeTargetLineId = ref<string | null>(null)
const expandedLineIds = ref<string[]>([])
const editDialogOpen = ref(false)
const editLine = ref<GrossanlassProcurementLine | null>(null)
const editWishDialogOpen = ref(false)
const editWish = ref<GrossanlassProcurementPoolWish | null>(null)
const bundleDialogOpen = ref(false)
const bundleWishes = ref<GrossanlassProcurementPoolWish[]>([])
const bundleSuggestedLabel = ref('')
const categoryFilter = ref('all')
const UNCATEGORIZED_FILTER = '__uncategorized'

const selectedQuantitySum = computed(() =>
  pool.value
    .filter((w) => selectedWishIds.value.includes(w.id))
    .reduce((sum, w) => sum + w.quantity, 0),
)

const lineSelectItems = computed(() =>
  lines.value
    .filter((l) => l.status === 'bedarf' && !l.merge_frozen)
    .map((l) => ({
      title: `${l.quantity}× ${l.label} (${t('grossanlass.beschaffung.bedarf.wishCount', { count: l.wish_count })})`,
      value: l.id,
    })),
)

const categoryFilterItems = computed(() => {
  const items: Array<{ title: string; value: string }> = [
    { title: t('grossanlass.beschaffung.bedarf.categoryFilterAll'), value: 'all' },
    { title: t('grossanlass.beschaffung.bedarf.categoryUncategorized'), value: UNCATEGORIZED_FILTER },
  ]
  for (const parent of categories.value.filter((c) => !c.parent_id)) {
    items.push({ title: parent.name, value: parent.id })
    for (const child of categories.value.filter((c) => c.parent_id === parent.id)) {
      items.push({ title: `${parent.name} / ${child.name}`, value: child.id })
    }
  }
  return items
})

const groupedLines = computed(() => {
  const filter = categoryFilter.value
  let visible = lines.value
  if (filter === UNCATEGORIZED_FILTER) {
    visible = lines.value.filter((l) => !l.category_id)
  } else if (filter !== 'all') {
    const childIds = new Set(
      categories.value.filter((c) => c.parent_id === filter).map((c) => c.id),
    )
    visible = lines.value.filter(
      (l) => l.category_id === filter || (l.category_id != null && childIds.has(l.category_id)),
    )
  }

  const groups: Array<{
    parentId: string | null
    parentName: string
    subgroups: Array<{ categoryId: string | null; categoryName: string | null; lines: GrossanlassProcurementLine[] }>
  }> = []

  for (const parent of categories.value.filter((c) => !c.parent_id)) {
    const children = categories.value.filter((c) => c.parent_id === parent.id)
    const parentLines = visible.filter((l) => l.category_id === parent.id)
    const subgroups = [
      ...(parentLines.length
        ? [{ categoryId: parent.id, categoryName: null, lines: parentLines }]
        : []),
      ...children
        .map((child) => ({
          categoryId: child.id,
          categoryName: child.name,
          lines: visible.filter((l) => l.category_id === child.id),
        }))
        .filter((s) => s.lines.length > 0),
    ]
    if (subgroups.length) {
      groups.push({ parentId: parent.id, parentName: parent.name, subgroups })
    }
  }

  const uncategorized = visible.filter((l) => !l.category_id)
  if (uncategorized.length) {
    groups.push({
      parentId: null,
      parentName: t('grossanlass.beschaffung.bedarf.categoryUncategorized'),
      subgroups: [{ categoryId: null, categoryName: null, lines: uncategorized }],
    })
  }

  return groups
})

function toggleLineSources(lineId: string) {
  if (expandedLineIds.value.includes(lineId)) {
    expandedLineIds.value = expandedLineIds.value.filter((id) => id !== lineId)
  } else {
    expandedLineIds.value = [...expandedLineIds.value, lineId]
  }
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
  return procurementStatusLabel(status, t)
}

function toggleWish(id: string) {
  if (selectedWishIds.value.includes(id)) {
    selectedWishIds.value = selectedWishIds.value.filter((x) => x !== id)
  } else {
    selectedWishIds.value = [...selectedWishIds.value, id]
  }
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  try {
    const data = await getGrossanlassBedarfOverview(departmentId.value)
    applyBedarfOverview(data)
    selectedWishIds.value = []
    mergeTargetLineId.value = lineSelectItems.value[0]?.value ?? null
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorLoad'))
  } finally {
    isLoading.value = false
  }
}

function applyBedarfOverview(data: GrossanlassBedarfOverview) {
  pool.value = data.pool
  lines.value = data.lines
  categories.value = data.categories ?? []
  suggestions.value = data.suggestions ?? []
}

function categoryPath(line: GrossanlassProcurementLine): string {
  if (!line.category_name) return ''
  if (line.category_parent_name) return `${line.category_parent_name} / ${line.category_name}`
  return line.category_name
}

function onCategoryCreated(category: GrossanlassProcurementCategory) {
  if (categories.value.some((c) => c.id === category.id)) return
  categories.value = [...categories.value, category]
}

function onCategoryDeleted(categoryId: string) {
  const removed = new Set(
    categories.value
      .filter((c) => c.id === categoryId || c.parent_id === categoryId)
      .map((c) => c.id),
  )
  categories.value = categories.value.filter((c) => !removed.has(c.id))
  lines.value = lines.value.map((line) =>
    line.category_id && removed.has(line.category_id)
      ? {
          ...line,
          category_id: null,
          category_name: null,
          category_parent_id: null,
          category_parent_name: null,
        }
      : line,
  )
}

function openBundleFromSelection() {
  bundleWishes.value = pool.value.filter((w) => selectedWishIds.value.includes(w.id))
  bundleSuggestedLabel.value = bundleWishes.value[0]?.label ?? ''
  bundleDialogOpen.value = true
}

function openBundleFromSuggestion(suggestion: GrossanlassProcurementBundleSuggestion) {
  bundleWishes.value = suggestion.wishes?.length
    ? suggestion.wishes
    : pool.value.filter((w) => suggestion.wish_ids.includes(w.id))
  bundleSuggestedLabel.value = suggestion.suggested_label
  bundleDialogOpen.value = true
}

async function onBundleSaved() {
  toast.success(t('grossanlass.beschaffung.bedarf.bundleSuccess'))
  await load()
}

async function mergeIntoLine() {
  if (!departmentId.value || !mergeTargetLineId.value || selectedWishIds.value.length === 0) return
  isSaving.value = true
  try {
    await addWishesToGrossanlassProcurementLine(departmentId.value, mergeTargetLineId.value, {
      wish_line_ids: selectedWishIds.value,
    })
    toast.success(t('grossanlass.beschaffung.bedarf.mergeSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorMerge'))
  } finally {
    isSaving.value = false
  }
}

async function splitWish(line: GrossanlassProcurementLine, wishLineId: string) {
  if (!departmentId.value) return
  try {
    await removeWishFromGrossanlassProcurementLine(departmentId.value, line.id, wishLineId)
    toast.success(t('grossanlass.beschaffung.bedarf.splitSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorSplit'))
  }
}

function openEditLine(line: GrossanlassProcurementLine) {
  editLine.value = line
  editDialogOpen.value = true
}

function openEditWish(wish: GrossanlassProcurementPoolWish) {
  editWish.value = wish
  editWishDialogOpen.value = true
}

async function onLineEdited() {
  toast.success(t('grossanlass.beschaffung.bedarf.editSuccess'))
  await load()
}

async function onWishEdited(overview: GrossanlassBedarfOverview) {
  applyBedarfOverview(overview)
  toast.success(t('grossanlass.beschaffung.bedarf.editWishSuccess'))
}

async function removeLine(line: GrossanlassProcurementLine) {
  if (!departmentId.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.beschaffung.bedarf.deleteTitle'),
    message: t('grossanlass.beschaffung.bedarf.deleteMessage', { label: line.label }),
  })
  if (!ok) return
  try {
    await deleteGrossanlassProcurementLine(departmentId.value, line.id)
    toast.success(t('grossanlass.beschaffung.bedarf.deleteSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorDelete'))
  }
}

onMounted(load)
</script>

<style scoped>
.beschaffung-bedarf {
  padding: 8px 0 24px;
}

.bedarf-intro {
  margin: 0 0 16px;
  color: #64748b;
  font-size: 0.9rem;
}

.bedarf-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  align-items: start;
}

@media (max-width: 960px) {
  .bedarf-layout {
    grid-template-columns: 1fr;
  }
}

.bedarf-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
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

.pool-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
  margin-bottom: 12px;
}

.merge-select {
  flex: 1 1 200px;
  min-width: 160px;
}

.category-filter {
  margin-bottom: 12px;
}

.suggestions {
  margin-bottom: 16px;
  padding: 10px 12px;
  border: 1px dashed #bfdbfe;
  border-radius: 8px;
  background: #f8fbff;
}

.suggestions-title {
  margin: 0 0 4px;
  font-size: 0.85rem;
  font-weight: 600;
}

.suggestions-hint {
  margin: 0 0 10px;
  font-size: 0.78rem;
  color: #64748b;
}

.suggestion-card {
  border: 1px solid #dbeafe;
  border-radius: 8px;
  padding: 10px 12px;
  background: #fff;
  margin-bottom: 8px;
}

.suggestion-card:last-child {
  margin-bottom: 0;
}

.suggestion-card__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 6px;
  margin-bottom: 6px;
  font-size: 0.82rem;
}

.suggestion-card__list {
  margin: 0 0 10px;
  padding-left: 18px;
  font-size: 0.8rem;
  color: #334155;
}

.suggestion-card__meta {
  color: #94a3b8;
}

.line-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.line-group__title {
  margin: 4px 0 0;
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.line-subgroup {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.line-subgroup__title {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
}

.category-chip {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 600;
  background: #f1f5f9;
  color: #475569;
}

.wish-pool-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 520px;
  overflow-y: auto;
}

.pool-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.pool-row__select {
  display: flex;
  gap: 10px;
  flex: 1;
  min-width: 0;
  cursor: pointer;
}

.pool-row.is-selected {
  border-color: #93c5fd;
  background: #eff6ff;
}

.pool-row__body {
  flex: 1;
  min-width: 0;
}

.pool-row__main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.pool-row__meta {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 2px;
}

.kind-tag {
  font-size: 0.72rem;
  color: #6b7280;
}

.lines-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.line-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 12px;
}

.line-card__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}

.line-card__actions {
  display: flex;
  gap: 6px;
}

.line-card__meta {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 4px;
}

.status-chip {
  display: inline-block;
  margin-left: 8px;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
  background: #e0e7ff;
  color: #3730a3;
}
.status-chip--frozen {
  background: #ffedd5;
  color: #9a3412;
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

.bundle-preview {
  flex: 1 1 100%;
  margin: 0 0 4px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #1d4ed8;
}

.line-card__total {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #e5e7eb;
}

.line-card__total-label {
  font-size: 0.78rem;
  color: #64748b;
}

.line-card__total-value {
  font-size: 1.05rem;
  color: #0f172a;
}

.quantity-adjusted-hint {
  font-size: 0.72rem;
  color: #b45309;
}

.line-card__sources {
  margin-top: 10px;
}

.sources-toggle {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: none;
  background: #f8fafc;
  color: #334155;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 6px 10px;
  border-radius: 6px;
  cursor: pointer;
}

.sources-toggle:hover {
  background: #f1f5f9;
}

.sources-hint {
  margin: 8px 0 6px;
  font-size: 0.75rem;
  color: #94a3b8;
}

.sources-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.source-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fafafa;
}

.split-btn {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  font-size: 0.72rem;
  padding: 4px 8px;
  cursor: pointer;
  white-space: nowrap;
}

.split-btn:hover {
  background: #f8fafc;
}

.source-row__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.source-row__main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.source-row__meta {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 2px;
}
</style>
