<template>
  <div class="wish-readonly">
    <p v-if="wish" class="wish-readonly__context">
      {{ wish.round_name }} · {{ wish.created_by_name }}
    </p>

    <ELoadingState v-if="isLoading" variant="inline" :message="t('common.loading')" />
    <p v-else-if="loadError" class="wish-readonly__error">{{ loadError }}</p>

    <div v-else-if="roundForm" class="wish-readonly__form" aria-readonly="true">
      <GrossanlassWishDynamicForm
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
      <GrossanlassWishEnoughOnHandField
        v-model="enough"
        :commitments="commitments"
        class="wish-readonly__enough"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import { type GrossanlassProcurementPoolWish } from '@/api/grossanlassProcurement'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import {
  getGrossanlassRoundForm,
  orderFormFieldsForRound,
  type GrossanlassRoundForm,
} from '@/api/grossanlassRoundForm'
import { getGrossanlassCommitments, type GrossanlassCommitment } from '@/api/grossanlassCommitments'
import type { GrossanlassWishLine } from '@/api/grossanlassWishes'
import GrossanlassWishDynamicForm from '@/components/grossanlass/GrossanlassWishDynamicForm.vue'
import GrossanlassWishEnoughOnHandField from '@/components/grossanlass/GrossanlassWishEnoughOnHandField.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  emptyEnoughOnHand,
  enoughOnHandFromApi,
  type EnoughOnHandValue,
} from '@/utils/grossanlassEnoughOnHand'

const props = defineProps<{
  departmentId: string
  wish: GrossanlassProcurementPoolWish
}>()

const { t } = useI18n()
const formRef = ref<InstanceType<typeof GrossanlassWishDynamicForm> | null>(null)
const roundForm = ref<GrossanlassRoundForm | null>(null)
const groups = ref<GrossanlassGroup[]>([])
const groupsRef = computed(() => groups.value)
const { canFullyManage, isMemberInRessortBranch, isLeaderOfGroup, canCreateChild } =
  useGrossanlassRessortScope(groupsRef)

const isLoading = ref(false)
const hydrating = ref(false)
const loadError = ref('')
const enough = ref<EnoughOnHandValue>(emptyEnoughOnHand())
const commitments = ref<GrossanlassCommitment[]>([])

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
    enough_on_hand: wish.enough_on_hand,
    enough_on_hand_source: wish.enough_on_hand_source,
    enough_on_hand_detail: wish.enough_on_hand_detail,
    enough_on_hand_ref_id: wish.enough_on_hand_ref_id,
    created_by_user_id: wish.created_by_user_id || '',
    created_by_name: wish.created_by_name,
    created_at: wish.created_at,
    updated_at: wish.updated_at || wish.created_at,
    custom_values: wish.custom_values,
  }
}

async function loadForm() {
  isLoading.value = true
  loadError.value = ''
  roundForm.value = null
  enough.value = enoughOnHandFromApi(props.wish)
  try {
    const [form, groupList, zusageList] = await Promise.all([
      getGrossanlassRoundForm(props.departmentId, props.wish.round_id),
      getGrossanlassGroups(props.departmentId),
      getGrossanlassCommitments(props.departmentId).catch(() => [] as GrossanlassCommitment[]),
    ])
    groups.value = groupList
    commitments.value = zusageList
    roundForm.value = { ...form, fields: orderFormFieldsForRound(form.fields) }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorEditWish')
  } finally {
    isLoading.value = false
  }
}

watch(
  () => props.wish.id,
  () => {
    void loadForm()
  },
  { immediate: true },
)

watch(formRef, async (form) => {
  if (!form || hydrating.value) return
  hydrating.value = true
  try {
    await form.loadFromWish(toWishLine(props.wish))
  } finally {
    hydrating.value = false
  }
})
</script>

<style scoped>
.wish-readonly__context {
  margin: 0 0 12px;
  font-size: 0.78rem;
  color: #64748b;
}
.wish-readonly__error {
  margin: 0;
  color: #dc2626;
  font-size: 0.82rem;
}
.wish-readonly__form {
  pointer-events: none;
}
.wish-readonly__enough {
  margin-top: 8px;
}
</style>
