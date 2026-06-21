<template>
  <EDialog
    v-model="open"
    :max-width="480"
    :title="t('grossanlass.beschaffung.bedarf.editWishTitle')"
  >
    <p v-if="wish" class="wish-context">
      {{ wish.group_name }} · {{ wish.round_name }}
    </p>

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
      :label="t('grossanlass.beschaffung.bedarf.editWishQuantity')"
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
  updateGrossanlassBedarfWish,
  type GrossanlassProcurementPoolWish,
} from '@/api/grossanlassProcurement'
import { EButton, EDialog, ETextField } from '@/components/form/base'

const props = defineProps<{
  departmentId: string
  wish: GrossanlassProcurementPoolWish | null
}>()

const emit = defineEmits<{
  saved: [overview: Awaited<ReturnType<typeof updateGrossanlassBedarfWish>>]
}>()

const open = defineModel<boolean>({ required: true })
const { t } = useI18n()

const form = ref({ label: '', quantity: '', location: '', notes: '' })
const isSubmitting = ref(false)
const errorMessage = ref('')

watch(
  [open, () => props.wish?.id],
  ([visible]) => {
    if (!visible || !props.wish) return
    form.value = {
      label: props.wish.label,
      quantity: String(props.wish.quantity),
      location: props.wish.location,
      notes: props.wish.notes ?? '',
    }
    errorMessage.value = ''
  },
  { immediate: true },
)

async function submit() {
  if (!props.wish) return
  const label = form.value.label.trim()
  const quantity = Number(form.value.quantity)
  if (!label || !Number.isFinite(quantity) || quantity < 1) {
    errorMessage.value = t('grossanlass.beschaffung.bedarf.editWishValidation')
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const overview = await updateGrossanlassBedarfWish(props.departmentId, props.wish.id, {
      label,
      quantity,
      location: form.value.location.trim(),
      notes: form.value.notes.trim() || null,
    })
    open.value = false
    emit('saved', overview)
  } catch (e: any) {
    errorMessage.value = e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorEditWish')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
.wish-context {
  margin: 0 0 12px;
  font-size: 0.78rem;
  color: #64748b;
}
.mt-3 { margin-top: 12px; }
.edit-dialog-error { margin: 12px 0 0; color: #dc2626; font-size: 0.82rem; }
</style>
