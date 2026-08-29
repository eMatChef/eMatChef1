<template>
  <EDialog
    v-model="open"
    :max-width="720"
    scrollable
    :title="t('grossanlass.beschaffung.bedarf.editWishTitle')"
  >
    <p v-if="wish" class="wish-context">
      {{ wish.group_name }} · {{ wish.round_name }}
    </p>

    <ELoadingState v-if="isLoading" variant="inline" :message="t('common.loading')" />

    <p v-else-if="loadError" class="edit-dialog-error">{{ loadError }}</p>

    <GrossanlassWishDynamicForm
      v-else-if="roundForm"
      :key="wish?.id"
      ref="formRef"
      :form="roundForm"
      :department-id="departmentId"
      :groups="groups"
      :can-fully-manage="canFullyManage"
      :is-member-in-ressort-branch="isMemberInRessortBranch"
      :is-leader-of-group="isLeaderOfGroup"
      :can-create-child="canCreateChild"
    />

    <p v-if="errorMessage" class="edit-dialog-error">{{ errorMessage }}</p>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="isLoading || !roundForm"
        :loading="isSubmitting"
        @click="submit"
      >
        {{ t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import {
  updateGrossanlassBedarfWish,
  type GrossanlassProcurementPoolWish,
} from '@/api/grossanlassProcurement'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import {
  getGrossanlassRoundForm,
  orderFormFieldsForRound,
  type GrossanlassRoundForm,
} from '@/api/grossanlassRoundForm'
import type { GrossanlassWishLine } from '@/api/grossanlassWishes'
import GrossanlassWishDynamicForm from '@/components/grossanlass/GrossanlassWishDynamicForm.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog } from '@/components/form/base'

const props = defineProps<{
  departmentId: string
  wish: GrossanlassProcurementPoolWish | null
}>()

const emit = defineEmits<{
  saved: [overview: Awaited<ReturnType<typeof updateGrossanlassBedarfWish>>]
}>()

const open = defineModel<boolean>({ required: true })
const { t } = useI18n()
const toast = useToast()

const formRef = ref<InstanceType<typeof GrossanlassWishDynamicForm> | null>(null)
const roundForm = ref<GrossanlassRoundForm | null>(null)
const groups = ref<GrossanlassGroup[]>([])
const groupsRef = computed(() => groups.value)
const { canFullyManage, isMemberInRessortBranch, isLeaderOfGroup, canCreateChild } =
  useGrossanlassRessortScope(groupsRef)

const isLoading = ref(false)
const isSubmitting = ref(false)
const hydrating = ref(false)
const loadError = ref('')
const errorMessage = ref('')

function toWishLine(wish: GrossanlassProcurementPoolWish): GrossanlassWishLine {
  return {
    id: wish.id,
    round_id: wish.round_id,
    group_id: wish.group_id,
    group_name: wish.group_name,
    wish_kind: wish.wish_kind,
    label: wish.label,
    quantity: wish.quantity,
    location: wish.location,
    valid_from: wish.valid_from,
    valid_to: wish.valid_to,
    timeframe_notes: wish.timeframe_notes ?? null,
    notes: wish.notes ?? null,
    status: (wish.status as GrossanlassWishLine['status']) || 'accepted',
    last_stage: wish.last_stage ?? undefined,
    created_by_user_id: wish.created_by_user_id || '',
    created_by_name: wish.created_by_name,
    created_at: wish.created_at,
    updated_at: wish.updated_at || wish.created_at,
    custom_values: wish.custom_values,
  }
}

async function loadForm() {
  if (!open.value || !props.wish || !props.departmentId) return
  isLoading.value = true
  loadError.value = ''
  errorMessage.value = ''
  roundForm.value = null
  try {
    const [form, groupList] = await Promise.all([
      getGrossanlassRoundForm(props.departmentId, props.wish.round_id),
      getGrossanlassGroups(props.departmentId),
    ])
    groups.value = groupList
    roundForm.value = { ...form, fields: orderFormFieldsForRound(form.fields) }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorEditWish')
  } finally {
    isLoading.value = false
  }
}

watch(
  [open, () => props.wish?.id],
  ([visible]) => {
    if (!visible) {
      roundForm.value = null
      return
    }
    void loadForm()
  },
  { immediate: true },
)

watch(formRef, async (form) => {
  if (!form || !props.wish || !open.value || hydrating.value) return
  hydrating.value = true
  try {
    await form.loadFromWish(toWishLine(props.wish))
  } finally {
    hydrating.value = false
  }
})

async function submit() {
  if (!props.wish || !formRef.value) return
  const payload = formRef.value.buildPayload()

  if (payload.new_bauprojekt) {
    toast.error(t('grossanlass.responses.errorEditBauprojekt'))
    return
  }
  if (!payload.group_id && !payload.ressort_group_id) {
    errorMessage.value = t('grossanlass.wishes.errorGroup')
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const overview = await updateGrossanlassBedarfWish(props.departmentId, props.wish.id, payload)
    open.value = false
    emit('saved', overview)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    errorMessage.value = err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorEditWish')
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
.edit-dialog-error { margin: 12px 0 0; color: #dc2626; font-size: 0.82rem; }
</style>
