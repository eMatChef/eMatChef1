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
      :department-id="departmentId"
      :categories="categories"
      @created="emit('category-created', $event)"
    />

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
        :disabled="selectedIds.length === 0"
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
  type GrossanlassProcurementCategory,
  type GrossanlassProcurementPoolWish,
} from '@/api/grossanlassProcurement'
import GrossanlassProcurementCategoryPicker from '@/components/grossanlass/GrossanlassProcurementCategoryPicker.vue'
import { EButton, EDialog, ETextField } from '@/components/form/base'

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
const selectedIds = ref<string[]>([])
const isSubmitting = ref(false)
const errorMessage = ref('')

const selectedQuantitySum = computed(() =>
  props.wishes
    .filter((w) => selectedIds.value.includes(w.id))
    .reduce((sum, w) => sum + w.quantity, 0),
)

watch(
  [open, () => props.wishes.map((w) => w.id).join(',')],
  ([visible]) => {
    if (!visible) return
    selectedIds.value = props.wishes.map((w) => w.id)
    label.value = (props.suggestedLabel ?? props.wishes[0]?.label ?? '').trim()
    categoryId.value = null
    errorMessage.value = ''
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
  if (!trimmed || selectedIds.value.length === 0) {
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
