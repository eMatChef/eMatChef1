<template>
  <EDialog
    v-model="open"
    :max-width="480"
    :title="t('grossanlass.beschaffung.bedarf.editTitle')"
    :retain-focus="false"
  >
    <ETextField
      v-model="form.label"
      :label="t('grossanlass.beschaffung.bedarf.editLabel')"
      hide-details="auto"
    />
    <ETextField
      v-model="form.quantity"
      class="mt-3"
      type="number"
      min="1"
      step="1"
      :label="t('grossanlass.beschaffung.bedarf.editQuantity')"
      hide-details="auto"
    />
    <ETextField
      v-model="form.location"
      class="mt-3"
      :label="t('grossanlass.beschaffung.bedarf.editLocation')"
      hide-details="auto"
    />
    <ETextField
      v-model="form.notes"
      class="mt-3"
      :label="t('grossanlass.beschaffung.bedarf.editNotes')"
      hide-details="auto"
    />
    <GrossanlassProcurementCategoryPicker
      v-model="form.categoryId"
      class="mt-3"
      :department-id="departmentId"
      :categories="categories"
      @created="emit('category-created', $event)"
    />

    <p v-if="errorMessage" class="edit-dialog-error">{{ errorMessage }}</p>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton variant="primary" size="small" :loading="isSubmitting" @click="submit">
        {{ t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  updateGrossanlassProcurementLine,
  type GrossanlassProcurementCategory,
  type GrossanlassProcurementLine,
} from '@/api/grossanlassProcurement'
import GrossanlassProcurementCategoryPicker from '@/components/grossanlass/GrossanlassProcurementCategoryPicker.vue'
import { EButton, EDialog, ETextField } from '@/components/form/base'

const props = defineProps<{
  departmentId: string
  line: GrossanlassProcurementLine | null
  categories: GrossanlassProcurementCategory[]
}>()

const emit = defineEmits<{
  saved: []
  'category-created': [category: GrossanlassProcurementCategory]
}>()

const open = defineModel<boolean>({ required: true })
const { t } = useI18n()

const form = ref({ label: '', quantity: '', location: '', notes: '', categoryId: null as string | null })
const isSubmitting = ref(false)
const errorMessage = ref('')

watch(
  [open, () => props.line?.id],
  ([visible]) => {
    if (!visible || !props.line) return
    form.value = {
      label: props.line.label,
      quantity: String(props.line.quantity),
      location: props.line.location,
      notes: props.line.notes ?? '',
      categoryId: props.line.category_id,
    }
    errorMessage.value = ''
  },
  { immediate: true },
)

async function submit() {
  if (!props.line) return
  const label = form.value.label.trim()
  const quantity = Number(form.value.quantity)
  if (!label || !Number.isFinite(quantity) || quantity < 1) {
    errorMessage.value = t('grossanlass.beschaffung.bedarf.editValidation')
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  try {
    await updateGrossanlassProcurementLine(props.departmentId, props.line.id, {
      label,
      quantity,
      location: form.value.location.trim(),
      notes: form.value.notes.trim() || null,
      category_id: form.value.categoryId,
    })
    open.value = false
    emit('saved')
  } catch (e: any) {
    errorMessage.value = e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorEdit')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
.mt-3 { margin-top: 12px; }
.edit-dialog-error { margin: 12px 0 0; color: #dc2626; font-size: 0.82rem; }
</style>
