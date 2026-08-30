<template>
  <div class="beschaffung-bedarf">
    <p class="bedarf-intro">{{ t('grossanlass.beschaffung.bedarf.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <template v-else>
    <v-expansion-panels v-model="openCategorySection" multiple class="e-accordions bedarf-cat-accordion">
      <v-expansion-panel value="categories">
        <v-expansion-panel-title>
          <span class="panel-head">
            <span class="panel-head__label">
              {{ t('grossanlass.beschaffung.bedarf.categoriesTitle') }}
              <span class="panel-head__count">{{ categories.length }}</span>
            </span>
            <span
              class="panel-head__settings"
              role="link"
              tabindex="0"
              @click.stop="goCategorySettings"
              @keydown.enter.stop.prevent="goCategorySettings"
            >
              {{ t('grossanlass.beschaffung.bedarf.categoriesOpenSettings') }}
            </span>
          </span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <GrossanlassProcurementCategoryManager
            hide-heading
            :department-id="departmentId"
            :categories="categories"
            @created="onCategoryCreated"
            @updated="onCategoryUpdated"
            @deleted="onCategoryDeleted"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <div class="source-tabs" role="tablist">
      <button
        type="button"
        class="source-tabs__btn"
        :class="{ 'is-active': sourceTab === 'material' }"
        @click="sourceTab = 'material'"
      >
        {{ t('grossanlass.beschaffung.bedarf.sourceMaterial') }}
        <span class="source-tabs__count">{{ pool.length }}</span>
      </button>
      <button
        type="button"
        class="source-tabs__btn"
        :class="{ 'is-active': sourceTab === 'company' }"
        @click="sourceTab = 'company'"
      >
        {{ t('grossanlass.beschaffung.bedarf.sourceCompany') }}
        <span class="source-tabs__count">{{ companyTips.length }}</span>
      </button>
      <button
        type="button"
        class="source-tabs__btn"
        :class="{ 'is-active': sourceTab === 'free' }"
        @click="sourceTab = 'free'"
      >
        {{ t('grossanlass.beschaffung.bedarf.sourceFree') }}
        <span class="source-tabs__count">{{ freeIdeas.length }}</span>
      </button>
    </div>

    <div class="bedarf-layout">
      <section class="bedarf-panel bedarf-panel--pool">
        <div class="panel-head">
          <h3>{{ poolPanelTitle }}</h3>
          <span class="panel-count">{{ sourceTab === 'material' ? filteredPool.length : filteredCollector.length }}</span>
        </div>
        <p class="panel-hint">{{ poolPanelHint }}</p>

        <div v-if="sourceTab === 'material'" class="pool-filters">
          <ESelect
            v-model="poolRoundId"
            :items="poolRoundItems"
            :label="t('grossanlass.beschaffung.bedarf.filterRound')"
            hide-details
            density="compact"
          />
          <ESelect
            v-model="poolGroupId"
            :items="poolGroupItems"
            :label="t('grossanlass.beschaffung.bedarf.filterGroup')"
            hide-details
            density="compact"
          />
          <ESelect
            v-model="poolKind"
            :items="poolKindItems"
            :label="t('grossanlass.beschaffung.bedarf.filterKind')"
            hide-details
            density="compact"
          />
          <ESelect
            v-model="poolStage"
            :items="poolStageItems"
            :label="t('grossanlass.beschaffung.bedarf.filterStage')"
            hide-details
            density="compact"
          />
        </div>

        <div v-if="sourceTab === 'material' && visibleSuggestions.length > 0" class="suggestions">
          <h4 class="suggestions-title">{{ t('grossanlass.beschaffung.bedarf.suggestionsTitle') }}</h4>
          <p class="suggestions-hint">{{ t('grossanlass.beschaffung.bedarf.suggestionsHint') }}</p>
          <div
            v-for="suggestion in visibleSuggestions"
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
          v-if="sourceTab === 'material' && filteredPool.length === 0"
          variant="default"
          icon="mdi-clipboard-check-outline"
          :title="pool.length === 0
            ? t('grossanlass.beschaffung.bedarf.poolEmptyTitle')
            : t('grossanlass.beschaffung.bedarf.poolFilterEmptyTitle')"
          :description="pool.length === 0
            ? t('grossanlass.beschaffung.bedarf.poolEmptyDescription')
            : t('grossanlass.beschaffung.bedarf.poolFilterEmptyDescription')"
        />

        <div v-else-if="sourceTab === 'material'" class="pool-actions">
          <p v-if="visibleSelectedIds.length > 0" class="bundle-preview">
            {{ t('grossanlass.beschaffung.bedarf.bundlePreview', {
              count: visibleSelectedIds.length,
              sum: selectedQuantitySum,
            }) }}
          </p>
          <EButton
            variant="primary"
            size="small"
            :disabled="visibleSelectedIds.length === 0 || isSaving"
            :loading="isSaving"
            @click="openBundleFromSelection"
          >
            {{ t('grossanlass.beschaffung.bedarf.bundleAction', { count: visibleSelectedIds.length }) }}
          </EButton>
          <EButton
            v-if="visibleSelectedIds.length > 0 && lineSelectItems.length > 0"
            variant="secondary"
            size="small"
            :disabled="isSaving"
            @click="openMergeDialog"
          >
            {{ t('grossanlass.beschaffung.bedarf.mergeIntoLine') }}
          </EButton>
        </div>

        <div v-if="sourceTab === 'material' && filteredPool.length > 0" class="wish-pool-list">
          <div
            v-for="wish in filteredPool"
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
                  <span class="kind-tag kind-tag--stage">{{ stageLabel(wish.last_stage) }}</span>
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

        <div v-if="sourceTab !== 'material'" class="pool-filters">
          <ESelect
            v-model="collectorRoundId"
            :items="collectorRoundItems"
            :label="t('grossanlass.beschaffung.bedarf.filterRound')"
            hide-details
            density="compact"
          />
          <ESelect
            v-model="collectorGroupId"
            :items="collectorGroupItems"
            :label="t('grossanlass.beschaffung.bedarf.filterGroup')"
            hide-details
            density="compact"
          />
        </div>

        <EEmptyState
          v-if="sourceTab !== 'material' && filteredCollector.length === 0"
          variant="default"
          icon="mdi-clipboard-text-outline"
          :title="sourceTab === 'company'
            ? t('grossanlass.beschaffung.bedarf.companyEmptyTitle')
            : t('grossanlass.beschaffung.bedarf.freeEmptyTitle')"
          :description="sourceTab === 'company'
            ? t('grossanlass.beschaffung.bedarf.companyEmptyDescription')
            : t('grossanlass.beschaffung.bedarf.freeEmptyDescription')"
        />

        <div v-else-if="sourceTab !== 'material'" class="wish-pool-list">
          <div v-for="item in filteredCollector" :key="item.id" class="pool-row pool-row--collector">
            <div class="pool-row__body">
              <div class="pool-row__main">
                <strong>{{ item.label || t('grossanlass.beschaffung.bedarf.sourceFree') }}</strong>
              </div>
              <div class="pool-row__meta">
                {{ item.group_name }}
                <template v-if="item.location"> · {{ item.location }}</template>
              </div>
              <div class="pool-row__meta">
                {{ item.round_name }} · {{ item.created_by_name }}
              </div>
              <ul v-if="item.answers.length > 0" class="collector-answers">
                <li v-for="(answer, idx) in item.answers" :key="idx">
                  <span>{{ answer.label }}</span>
                  {{ answer.value }}
                </li>
              </ul>
              <div class="collector-actions">
                <EButton
                  v-if="sourceTab === 'company' || sourceTab === 'free'"
                  variant="primary"
                  size="small"
                  :disabled="isSaving"
                  @click="assignToInquiry(item)"
                >
                  {{ sourceTab === 'company'
                    ? t('grossanlass.beschaffung.bedarf.companyAssign')
                    : t('grossanlass.beschaffung.bedarf.freeToCompany') }}
                </EButton>
                <EButton
                  v-if="sourceTab === 'free'"
                  variant="secondary"
                  size="small"
                  :disabled="isSaving"
                  @click="openMaterialAssign(item)"
                >
                  {{ t('grossanlass.beschaffung.bedarf.freeToMaterial') }}
                </EButton>
                <EButton
                  variant="text"
                  size="small"
                  :disabled="isSaving"
                  @click="discardItem(item)"
                >
                  {{ t('grossanlass.beschaffung.bedarf.discard') }}
                </EButton>
              </div>
            </div>
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
        >
          <template #item="{ props: itemProps, item }">
            <GrossanlassCategoryDropdownItem :item-props="itemProps" :item="item" />
          </template>
        </ESelect>

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
      v-model="bundleDialogOpen"
      :department-id="departmentId"
      :wishes="bundleWishes"
      :suggested-label="bundleSuggestedLabel"
      :categories="categories"
      @saved="onBundleSaved"
      @category-created="onCategoryCreated"
    />

    <EDialog
      v-model="mergeDialogOpen"
      :title="t('grossanlass.beschaffung.bedarf.mergeIntoLine')"
      max-width="480"
    >
      <p class="panel-hint">{{ t('grossanlass.beschaffung.bedarf.mergeReviewHint') }}</p>
      <ESelect
        v-model="mergeTargetLineId"
        :items="lineSelectItems"
        :label="t('grossanlass.beschaffung.bedarf.mergeTarget')"
        hide-details
        density="compact"
        class="assign-field"
      />
      <GrossanlassProcurementCategoryPicker
        v-model="mergeCategoryId"
        class="assign-field"
        required
        :department-id="departmentId"
        :categories="categories"
        @created="onCategoryCreated"
      />
      <p class="panel-hint">{{ t('grossanlass.beschaffung.bedarf.categoryRequiredHint') }}</p>
      <template #actions>
        <EButton variant="secondary" @click="mergeDialogOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          :disabled="!mergeTargetLineId || !mergeCategoryId || isSaving"
          :loading="isSaving"
          @click="confirmMergeIntoLine"
        >
          {{ t('grossanlass.beschaffung.bedarf.mergeConfirm') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="materialAssignOpen"
      :title="t('grossanlass.beschaffung.bedarf.assignMaterialTitle')"
      max-width="480"
    >
      <p class="panel-hint">{{ t('grossanlass.beschaffung.bedarf.assignMaterialHint') }}</p>
      <p v-if="materialRounds.length === 0" class="panel-hint">
        {{ t('grossanlass.beschaffung.bedarf.assignMaterialNoRound') }}
      </p>
      <ESelect
        v-else
        v-model="materialAssignRoundId"
        :items="materialRoundItems"
        :label="t('grossanlass.beschaffung.bedarf.assignMaterialRound')"
        hide-details
        density="compact"
      />
      <ETextField
        v-model="materialAssignLabel"
        :label="t('grossanlass.beschaffung.bedarf.editLabel')"
        hide-details
        density="compact"
        class="assign-field"
      />
      <ETextField
        v-model="materialAssignQuantity"
        type="number"
        :label="t('grossanlass.beschaffung.bedarf.editWishQuantity')"
        hide-details
        density="compact"
        class="assign-field"
      />
      <template #actions>
        <EButton variant="secondary" @click="materialAssignOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          :disabled="materialRounds.length === 0 || !materialAssignRoundId || isSaving"
          :loading="isSaving"
          @click="confirmMaterialAssign"
        >
          {{ t('grossanlass.beschaffung.bedarf.freeToMaterial') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassProcurementLineEditDialog from '@/components/grossanlass/GrossanlassProcurementLineEditDialog.vue'
import GrossanlassProcurementWishEditDialog from '@/components/grossanlass/GrossanlassProcurementWishEditDialog.vue'
import GrossanlassProcurementBundleDialog from '@/components/grossanlass/GrossanlassProcurementBundleDialog.vue'
import GrossanlassProcurementCategoryManager from '@/components/grossanlass/GrossanlassProcurementCategoryManager.vue'
import GrossanlassCategoryDropdownItem from '@/components/grossanlass/GrossanlassCategoryDropdownItem.vue'
import GrossanlassProcurementCategoryPicker from '@/components/grossanlass/GrossanlassProcurementCategoryPicker.vue'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import {
  addWishesToGrossanlassProcurementLine,
  assignGrossanlassCollectorToInquiry,
  assignGrossanlassCollectorToMaterial,
  deleteGrossanlassProcurementLine,
  discardGrossanlassCollectorItem,
  getGrossanlassBedarfOverview,
  removeWishFromGrossanlassProcurementLine,
  type GrossanlassBedarfOverview,
  type GrossanlassCollectorItem,
  type GrossanlassCollectorRoundOption,
  type GrossanlassProcurementBundleSuggestion,
  type GrossanlassProcurementCategory,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementPoolWish,
} from '@/api/grossanlassProcurement'
import { procurementStatusLabel } from '@/utils/grossanlassProcurementStatus'
import {
  childrenOfProcurementCategory,
  descendantIdsOfProcurementCategory,
  pathLabelOfProcurementCategory,
  procurementCategoryTreeItems,
} from '@/utils/grossanlassProcurementCategoryTree'
import type { GrossanlassWishKind } from '@/api/grossanlassWishes'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const departmentId = computed(() => String(route.params.departmentId || ''))

function goCategorySettings() {
  void router.push(`/${departmentId.value}/einstellungen/kategorien`)
}

const pool = ref<GrossanlassProcurementPoolWish[]>([])
const companyTips = ref<GrossanlassCollectorItem[]>([])
const freeIdeas = ref<GrossanlassCollectorItem[]>([])
const materialRounds = ref<GrossanlassCollectorRoundOption[]>([])
const sourceTab = ref<'material' | 'company' | 'free'>('material')
const openCategorySection = ref<string[]>([])
const collectorRoundId = ref('all')
const collectorGroupId = ref('all')
const materialAssignOpen = ref(false)
const materialAssignItem = ref<GrossanlassCollectorItem | null>(null)
const materialAssignRoundId = ref<string | null>(null)
const materialAssignLabel = ref('')
const materialAssignQuantity = ref('1')
const lines = ref<GrossanlassProcurementLine[]>([])
const categories = ref<GrossanlassProcurementCategory[]>([])
const suggestions = ref<GrossanlassProcurementBundleSuggestion[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const selectedWishIds = ref<string[]>([])
const mergeTargetLineId = ref<string | null>(null)
const mergeDialogOpen = ref(false)
const mergeCategoryId = ref<string | null>(null)
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
const poolRoundId = ref('all')
const poolGroupId = ref('all')
const poolKind = ref('all')
const poolStage = ref('all')

const FILTER_ALL = 'all'

const collectorItems = computed(() =>
  sourceTab.value === 'company' ? companyTips.value : sourceTab.value === 'free' ? freeIdeas.value : [],
)

const filteredCollector = computed(() =>
  collectorItems.value.filter((item) => {
    if (collectorRoundId.value !== FILTER_ALL && item.round_id !== collectorRoundId.value) return false
    if (collectorGroupId.value !== FILTER_ALL && item.group_id !== collectorGroupId.value) return false
    return true
  }),
)

const poolPanelTitle = computed(() => {
  if (sourceTab.value === 'company') return t('grossanlass.beschaffung.bedarf.sourceCompany')
  if (sourceTab.value === 'free') return t('grossanlass.beschaffung.bedarf.sourceFree')
  return t('grossanlass.beschaffung.bedarf.poolTitle')
})

const poolPanelHint = computed(() => {
  if (sourceTab.value === 'company') return t('grossanlass.beschaffung.bedarf.companyHint')
  if (sourceTab.value === 'free') return t('grossanlass.beschaffung.bedarf.freeHint')
  return t('grossanlass.beschaffung.bedarf.poolHint')
})

const collectorRoundItems = computed(() => {
  const seen = new Set<string>()
  const items: Array<{ title: string; value: string }> = [
    { title: t('grossanlass.beschaffung.bedarf.filterRoundAll'), value: FILTER_ALL },
  ]
  for (const item of collectorItems.value) {
    if (seen.has(item.round_id)) continue
    seen.add(item.round_id)
    items.push({ title: item.round_name, value: item.round_id })
  }
  return items
})

const collectorGroupItems = computed(() => {
  const seen = new Set<string>()
  const items: Array<{ title: string; value: string }> = [
    { title: t('grossanlass.beschaffung.bedarf.filterGroupAll'), value: FILTER_ALL },
  ]
  for (const item of collectorItems.value) {
    if (seen.has(item.group_id)) continue
    seen.add(item.group_id)
    items.push({ title: item.group_name, value: item.group_id })
  }
  return items
})

const materialRoundItems = computed(() =>
  materialRounds.value.map((round) => ({ title: round.name, value: round.id })),
)

const filteredPool = computed(() =>
  pool.value.filter((wish) => {
    if (poolRoundId.value !== FILTER_ALL && wish.round_id !== poolRoundId.value) return false
    if (poolGroupId.value !== FILTER_ALL && wish.group_id !== poolGroupId.value) return false
    if (poolKind.value !== FILTER_ALL && wish.wish_kind !== poolKind.value) return false
    if (poolStage.value !== FILTER_ALL && (wish.last_stage || 'grob') !== poolStage.value) return false
    return true
  }),
)

const filteredPoolIds = computed(() => new Set(filteredPool.value.map((wish) => wish.id)))

const visibleSuggestions = computed(() =>
  suggestions.value
    .map((suggestion) => {
      const wishes = suggestion.wishes.filter((wish) => filteredPoolIds.value.has(wish.id))
      return {
        ...suggestion,
        wishes,
        wish_count: wishes.length,
        quantity_sum: wishes.reduce((sum, wish) => sum + wish.quantity, 0),
      }
    })
    .filter((suggestion) => suggestion.wishes.length >= 2),
)

const poolRoundItems = computed(() => {
  const seen = new Set<string>()
  const items: Array<{ title: string; value: string }> = [
    { title: t('grossanlass.beschaffung.bedarf.filterRoundAll'), value: FILTER_ALL },
  ]
  for (const wish of pool.value) {
    if (seen.has(wish.round_id)) continue
    seen.add(wish.round_id)
    items.push({ title: wish.round_name, value: wish.round_id })
  }
  return items
})

const poolGroupItems = computed(() => {
  const seen = new Set<string>()
  const items: Array<{ title: string; value: string }> = [
    { title: t('grossanlass.beschaffung.bedarf.filterGroupAll'), value: FILTER_ALL },
  ]
  for (const wish of pool.value) {
    if (seen.has(wish.group_id)) continue
    seen.add(wish.group_id)
    items.push({ title: wish.group_name, value: wish.group_id })
  }
  return items
})

const poolKindItems = computed(() => [
  { title: t('grossanlass.beschaffung.bedarf.filterKindAll'), value: FILTER_ALL },
  { title: t('grossanlass.wishes.kindMaterial'), value: 'material' },
  { title: t('grossanlass.wishes.kindFahrzeug'), value: 'fahrzeug' },
  { title: t('grossanlass.wishes.kindBeides'), value: 'beides' },
])

const poolStageItems = computed(() => [
  { title: t('grossanlass.beschaffung.bedarf.filterStageAll'), value: FILTER_ALL },
  { title: t('grossanlass.planung.wishForms.stageGrob'), value: 'grob' },
  { title: t('grossanlass.planung.wishForms.stageFein'), value: 'fein' },
])

const selectedQuantitySum = computed(() =>
  filteredPool.value
    .filter((w) => selectedWishIds.value.includes(w.id))
    .reduce((sum, w) => sum + w.quantity, 0),
)

const visibleSelectedIds = computed(() =>
  selectedWishIds.value.filter((id) => filteredPoolIds.value.has(id)),
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
  const items: Array<{ title: string; value: string; name: string; depth: number }> = [
    {
      title: t('grossanlass.beschaffung.bedarf.categoryFilterAll'),
      value: 'all',
      name: t('grossanlass.beschaffung.bedarf.categoryFilterAll'),
      depth: 0,
    },
    {
      title: t('grossanlass.beschaffung.bedarf.categoryUncategorized'),
      value: UNCATEGORIZED_FILTER,
      name: t('grossanlass.beschaffung.bedarf.categoryUncategorized'),
      depth: 0,
    },
    ...procurementCategoryTreeItems(categories.value),
  ]
  return items
})

const groupedLines = computed(() => {
  const filter = categoryFilter.value
  let visible = lines.value
  if (filter === UNCATEGORIZED_FILTER) {
    visible = lines.value.filter((l) => !l.category_id)
  } else if (filter !== 'all') {
    const ids = descendantIdsOfProcurementCategory(categories.value, filter)
    visible = lines.value.filter((l) => l.category_id != null && ids.has(l.category_id))
  }

  const groups: Array<{
    parentId: string | null
    parentName: string
    subgroups: Array<{ categoryId: string | null; categoryName: string | null; lines: GrossanlassProcurementLine[] }>
  }> = []

  for (const parent of childrenOfProcurementCategory(categories.value, null)) {
    const parentLines = visible.filter((l) => l.category_id === parent.id)
    const descendantRows: Array<{
      categoryId: string | null
      categoryName: string | null
      lines: GrossanlassProcurementLine[]
    }> = []
    const walk = (parentId: string) => {
      for (const child of childrenOfProcurementCategory(categories.value, parentId)) {
        const linesForChild = visible.filter((l) => l.category_id === child.id)
        if (linesForChild.length) {
          const full = pathLabelOfProcurementCategory(categories.value, child.id)
          const prefix = `${parent.name} / `
          descendantRows.push({
            categoryId: child.id,
            categoryName: full.startsWith(prefix) ? full.slice(prefix.length) : child.name,
            lines: linesForChild,
          })
        }
        walk(child.id)
      }
    }
    walk(parent.id)
    const subgroups = [
      ...(parentLines.length
        ? [{ categoryId: parent.id, categoryName: null, lines: parentLines }]
        : []),
      ...descendantRows,
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

function stageLabel(stage: string | null | undefined): string {
  return stage === 'fein'
    ? t('grossanlass.planung.wishForms.stageFein')
    : t('grossanlass.planung.wishForms.stageGrob')
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
  companyTips.value = data.company_tips ?? []
  freeIdeas.value = data.free_ideas ?? []
  materialRounds.value = data.material_rounds ?? []
}

function categoryPath(line: GrossanlassProcurementLine): string {
  if (!line.category_id) return line.category_name || ''
  return pathLabelOfProcurementCategory(categories.value, line.category_id) || line.category_name || ''
}

function onCategoryCreated(category: GrossanlassProcurementCategory) {
  if (categories.value.some((c) => c.id === category.id)) return
  categories.value = [...categories.value, category]
}

function onCategoryUpdated(category: GrossanlassProcurementCategory) {
  categories.value = categories.value.map((row) => (row.id === category.id ? category : row))
  lines.value = lines.value.map((line) => {
    if (line.category_id === category.id) {
      return {
        ...line,
        category_name: category.name,
        category_parent_id: category.parent_id,
        category_parent_name: category.parent_name,
      }
    }
    if (line.category_parent_id === category.id) {
      return { ...line, category_parent_name: category.name }
    }
    return line
  })
}

function onCategoryDeleted(categoryId: string, reassignTo?: GrossanlassProcurementCategory) {
  const removed = descendantIdsOfProcurementCategory(categories.value, categoryId)
  categories.value = categories.value.filter((c) => !removed.has(c.id))
  lines.value = lines.value.map((line) => {
    if (!line.category_id || !removed.has(line.category_id)) return line
    if (reassignTo) {
      return {
        ...line,
        category_id: reassignTo.id,
        category_name: reassignTo.name,
        category_parent_id: reassignTo.parent_id,
        category_parent_name: reassignTo.parent_name,
      }
    }
    return {
      ...line,
      category_id: null,
      category_name: null,
      category_parent_id: null,
      category_parent_name: null,
    }
  })
}

function openBundleFromSelection() {
  bundleWishes.value = filteredPool.value.filter((w) => selectedWishIds.value.includes(w.id))
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

function openMergeDialog() {
  if (visibleSelectedIds.value.length === 0 || lineSelectItems.value.length === 0) return
  if (!mergeTargetLineId.value || !lineSelectItems.value.some((row) => row.value === mergeTargetLineId.value)) {
    mergeTargetLineId.value = lineSelectItems.value[0]?.value ?? null
  }
  const line = lines.value.find((row) => row.id === mergeTargetLineId.value)
  mergeCategoryId.value = line?.category_id ?? null
  mergeDialogOpen.value = true
}

watch(mergeTargetLineId, (id) => {
  if (!mergeDialogOpen.value) return
  const line = lines.value.find((row) => row.id === id)
  mergeCategoryId.value = line?.category_id ?? mergeCategoryId.value
})

async function confirmMergeIntoLine() {
  if (!departmentId.value || !mergeTargetLineId.value || !mergeCategoryId.value || visibleSelectedIds.value.length === 0) {
    return
  }
  isSaving.value = true
  try {
    await addWishesToGrossanlassProcurementLine(departmentId.value, mergeTargetLineId.value, {
      wish_line_ids: visibleSelectedIds.value,
      category_id: mergeCategoryId.value,
    })
    mergeDialogOpen.value = false
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

async function assignToInquiry(item: GrossanlassCollectorItem) {
  if (!departmentId.value) return
  isSaving.value = true
  try {
    const data = await assignGrossanlassCollectorToInquiry(departmentId.value, item.id, {
      name: item.label,
      email: item.email,
      place: item.location,
      category_ids: item.suggested_categories,
    })
    applyBedarfOverview(data)
    toast.success(t('grossanlass.beschaffung.bedarf.assignInquirySuccess'))
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCollector'))
  } finally {
    isSaving.value = false
  }
}

function openMaterialAssign(item: GrossanlassCollectorItem) {
  materialAssignItem.value = item
  materialAssignLabel.value = item.label
  materialAssignQuantity.value = String(item.quantity || 1)
  materialAssignRoundId.value = materialRounds.value[0]?.id ?? null
  materialAssignOpen.value = true
}

async function confirmMaterialAssign() {
  if (!departmentId.value || !materialAssignItem.value || !materialAssignRoundId.value) return
  isSaving.value = true
  try {
    const qty = Number.parseInt(materialAssignQuantity.value, 10)
    const data = await assignGrossanlassCollectorToMaterial(departmentId.value, materialAssignItem.value.id, {
      target_round_id: materialAssignRoundId.value,
      label: materialAssignLabel.value,
      quantity: Number.isFinite(qty) && qty > 0 ? qty : 1,
      location: materialAssignItem.value.location,
    })
    applyBedarfOverview(data)
    materialAssignOpen.value = false
    sourceTab.value = 'material'
    toast.success(t('grossanlass.beschaffung.bedarf.assignMaterialSuccess'))
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCollector'))
  } finally {
    isSaving.value = false
  }
}

async function discardItem(item: GrossanlassCollectorItem) {
  const ok = await confirm.confirm({
    title: t('grossanlass.beschaffung.bedarf.discardConfirmTitle'),
    message: t('grossanlass.beschaffung.bedarf.discardConfirmMessage', { label: item.label }),
  })
  if (!ok || !departmentId.value) return
  isSaving.value = true
  try {
    const data = await discardGrossanlassCollectorItem(departmentId.value, item.id)
    applyBedarfOverview(data)
    toast.success(t('grossanlass.beschaffung.bedarf.discardSuccess'))
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorCollector'))
  } finally {
    isSaving.value = false
  }
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

.bedarf-cat-accordion {
  margin-bottom: 16px;
}

.bedarf-cat-accordion :deep(.panel-head__settings) {
  margin-left: auto;
  font-size: 0.78rem;
  font-weight: 600;
  color: #0f766e;
  text-decoration: none;
  cursor: pointer;
  position: relative;
  z-index: 1;
}

.bedarf-cat-accordion :deep(.panel-head__settings:hover) {
  text-decoration: underline;
}

.source-tabs {
  display: flex;
  gap: 6px;
  margin: 0 0 16px;
  flex-wrap: wrap;
}

.source-tabs__btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 999px;
  padding: 6px 12px;
  font-size: 0.85rem;
  cursor: pointer;
}

.source-tabs__btn.is-active {
  border-color: #93c5fd;
  background: #eff6ff;
  font-weight: 600;
}

.source-tabs__count {
  min-width: 1.4em;
  text-align: center;
  font-size: 0.75rem;
  color: #64748b;
}

.collector-answers {
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
  font-size: 0.8rem;
  color: #334155;
}

.collector-answers li {
  display: grid;
  grid-template-columns: minmax(5rem, 8rem) 1fr;
  gap: 8px;
  margin-top: 2px;
}

.collector-answers span {
  color: #64748b;
}

.collector-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}

.assign-field {
  margin-top: 12px;
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

.pool-filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 8px;
  margin-bottom: 12px;
}

.kind-tag--stage {
  color: #1d4ed8;
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
