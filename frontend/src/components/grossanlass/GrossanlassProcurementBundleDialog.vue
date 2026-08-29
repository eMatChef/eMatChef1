<template>
  <EDialog
    v-model="open"
    :max-width="560"
    :title="t('grossanlass.beschaffung.bedarf.bundleReviewTitle')"
    :retain-focus="false"
    scrollable
  >
    <p class="review-hint">{{ t('grossanlass.beschaffung.bedarf.bundleReviewHint') }}</p>

    <ETextField
      v-model="label"
      :label="t('grossanlass.beschaffung.bedarf.editLabel')"
      hide-details="auto"
    />

    <GrossanlassProcurementCategoryPicker
      v-model="categoryId"
      class="mt-3"
      required
      :department-id="departmentId"
      :categories="categories"
      @created="emit('category-created', $event)"
    />
    <p class="review-hint review-hint--tight">{{ t('grossanlass.beschaffung.bedarf.categoryRequiredHint') }}</p>

    <div class="cost-grid">
      <ESelect
        v-model="costKind"
        :items="kindItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.beschaffung.kosten.colKind')"
        hide-details
      />
      <ESelect
        v-model="payerGroupId"
        :items="payerItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.beschaffung.kosten.colPayer')"
        hide-details
      />
    </div>
    <p class="review-hint review-hint--tight">{{ t('grossanlass.beschaffung.bedarf.costHint') }}</p>

    <p class="review-sum">
      {{ t('grossanlass.beschaffung.bedarf.bundlePreview', {
        count: selectedIds.length,
        sum: selectedQuantitySum,
      }) }}
    </p>

    <div class="review-list">
      <label
        v-for="wish in wishes"
        :key="wish.id"
        class="review-row"
        :class="{ 'is-selected': selectedIds.includes(wish.id) }"
      >
        <input
          type="checkbox"
          :checked="selectedIds.includes(wish.id)"
          @change="toggleWish(wish.id)"
        />
        <div class="review-row__body">
          <div class="review-row__main">
            <strong>{{ wish.quantity }}× {{ wish.label }}</strong>
          </div>
          <div class="review-row__meta">{{ wish.group_name }} · {{ wish.location }}</div>
          <div class="review-row__meta">{{ wish.round_name }} · {{ wish.created_by_name }}</div>
        </div>
      </label>
    </div>

    <p v-if="errorMessage" class="review-error">{{ errorMessage }}</p>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="selectedIds.length === 0 || !categoryId"
        :loading="isSubmitting"
        @click="submit"
      >
        {{ t('grossanlass.beschaffung.bedarf.bundleConfirm', { count: selectedIds.length }) }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  createGrossanlassProcurementLine,
  type GrossanlassCostKind,
  type GrossanlassProcurementCategory,
  type GrossanlassProcurementPoolWish,
} from '@/api/grossanlassProcurement'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { getGrossanlassPlanung } from '@/api/grossanlassPlanung'
import GrossanlassProcurementCategoryPicker from '@/components/grossanlass/GrossanlassProcurementCategoryPicker.vue'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import { grossanlassPayerSelectItems } from '@/utils/grossanlassCostPayer'

const props = defineProps<{
  departmentId: string
  wishes: GrossanlassProcurementPoolWish[]
  suggestedLabel?: string
  categories: GrossanlassProcurementCategory[]
}>()

const emit = defineEmits<{
  saved: []
  'category-created': [category: GrossanlassProcurementCategory]
}>()

const open = defineModel<boolean>({ required: true })
const { t } = useI18n()

const label = ref('')
const categoryId = ref<string | null>(null)
const costKind = ref<GrossanlassCostKind>('loan')
const payerGroupId = ref<string | null>(null)
const groups = ref<GrossanlassGroup[]>([])
const logisticsGroupId = ref<string | null>(null)
const selectedIds = ref<string[]>([])
const isSubmitting = ref(false)
const errorMessage = ref('')

const kindItems = computed(() =>
  (['purchase', 'rental', 'loan', 'buy_resale'] as GrossanlassCostKind[]).map((value) => ({
    title: t(`grossanlass.beschaffung.kosten.kind.${value}`),
    value,
  })),
)
const payerItems = computed(() => {
  const fromApi = groups.value
  const known = new Set(fromApi.map((item) => item.id))
  const extra: Array<{ id: string; name: string; parent_id: string | null }> = []
  for (const wish of props.wishes) {
    if (!wish.group_id || known.has(wish.group_id)) continue
    known.add(wish.group_id)
    extra.push({ id: wish.group_id, name: wish.group_name, parent_id: null })
  }
  return grossanlassPayerSelectItems([...fromApi, ...extra], logisticsGroupId.value, {
    central: t('grossanlass.beschaffung.kosten.payerCentral'),
    potSuffix: t('grossanlass.beschaffung.kosten.payerPotSuffix'),
  })
})

const selectedQuantitySum = computed(() =>
  props.wishes
    .filter((w) => selectedIds.value.includes(w.id))
    .reduce((sum, w) => sum + w.quantity, 0),
)

watch(
  [open, () => props.wishes.map((w) => w.id).join(',')],
  async ([visible]) => {
    if (!visible) return
    selectedIds.value = props.wishes.map((w) => w.id)
    label.value = (props.suggestedLabel ?? props.wishes[0]?.label ?? '').trim()
    categoryId.value = null
    costKind.value = 'loan'
    errorMessage.value = ''
    if (groups.value.length === 0 && props.departmentId) {
      try {
        const [groupList, planung] = await Promise.all([
          getGrossanlassGroups(props.departmentId),
          getGrossanlassPlanung(props.departmentId),
        ])
        groups.value = groupList
        logisticsGroupId.value = planung.config.logistics_group_id || null
      } catch {
        groups.value = []
      }
    }
    payerGroupId.value = props.wishes[0]?.group_id ?? null
  },
  { immediate: true },
)

function toggleWish(id: string) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((x) => x !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

async function submit() {
  const trimmed = label.value.trim()
  if (!trimmed || selectedIds.value.length === 0 || !categoryId.value) {
    errorMessage.value = t('grossanlass.beschaffung.bedarf.bundleReviewValidation')
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  try {
    await createGrossanlassProcurementLine(props.departmentId, {
      wish_line_ids: selectedIds.value,
      label: trimmed,
      category_id: categoryId.value,
      cost_kind: costKind.value,
      payer_group_id: payerGroupId.value,
    })
    open.value = false
    emit('saved')
  } catch (e: any) {
    errorMessage.value = e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorBundle')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
.mt-3 { margin-top: 12px; }
.review-hint {
  margin: 0 0 12px;
  font-size: 0.85rem;
  color: #64748b;
}
.review-hint--tight { margin: 6px 0 0; }
.cost-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 12px;
}
@media (max-width: 640px) {
  .cost-grid { grid-template-columns: 1fr; }
}
.review-sum {
  margin: 14px 0 8px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #1d4ed8;
}
.review-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 280px;
  overflow-y: auto;
}
.review-row {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
}
.review-row.is-selected {
  border-color: #93c5fd;
  background: #eff6ff;
}
.review-row__body { flex: 1; min-width: 0; }
.review-row__meta {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 2px;
}
.review-error { margin: 12px 0 0; color: #dc2626; font-size: 0.82rem; }
</style>
