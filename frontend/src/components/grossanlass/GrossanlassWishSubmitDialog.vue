<template>
  <EDialog
    v-model="open"
    :max-width="720"
    :title="dialogTitle"
    scrollable
    :retain-focus="false"
  >
    <ELoadingState
      v-if="isLoading"
      variant="inline"
      :message="t('common.loading')"
    />
    <v-alert v-else-if="loadError" type="error" variant="tonal" :text="loadError" />

    <template v-else-if="round && roundForm">
      <p class="dialog-hint">{{ isFeinRound ? t('grossanlass.wishes.feinFormHint') : t('grossanlass.wishes.formHint') }}</p>
      <div v-if="isFeinRound" class="refine-panel">
        <ul v-if="refineCandidates.length" class="refine-list">
          <li v-for="wish in refineCandidates" :key="wish.id">
            <span>{{ wish.quantity }}× {{ wish.label }}</span>
            <EButton size="small" variant="secondary" @click="startRefine(wish)">
              {{ t('grossanlass.wishes.refineAction') }}
            </EButton>
          </li>
        </ul>
      </div>
      <GrossanlassWishDynamicForm
        ref="wishFormRef"
        :form="roundForm"
        :department-id="departmentId"
        :groups="groups"
        :can-fully-manage="canFullyManage"
        :is-member-in-ressort-branch="isMemberInRessortBranch"
        :is-leader-of-group="isLeaderOfGroup"
        :can-create-child="canCreateChild"
      />
    </template>

    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton
        v-if="round?.status === 'open' && roundForm"
        variant="primary"
        :loading="isSaving"
        @click="submitWish"
      >
        {{ refineWishId ? t('grossanlass.wishes.refineSave') : t('grossanlass.wishes.submit') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog } from '@/components/form/base'
import GrossanlassWishDynamicForm from '@/components/grossanlass/GrossanlassWishDynamicForm.vue'
import {
  getGrossanlassPlanningRounds,
  type GrossanlassPlanningRound,
} from '@/api/grossanlassRounds'
import { createGrossanlassWish, getGrossanlassRefineCandidates, type GrossanlassWishLine } from '@/api/grossanlassWishes'
import { getGrossanlassRoundForm, orderFormFieldsForRound, type GrossanlassRoundForm } from '@/api/grossanlassRoundForm'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'

const props = defineProps<{
  departmentId: string
  roundId: string | null
}>()

const emit = defineEmits<{
  submitted: []
}>()

const open = defineModel<boolean>({ default: false })
const { t } = useI18n()
const toast = useToast()

const round = ref<GrossanlassPlanningRound | null>(null)
const roundForm = ref<GrossanlassRoundForm | null>(null)
const groups = ref<GrossanlassGroup[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const loadError = ref('')
const wishFormRef = ref<InstanceType<typeof GrossanlassWishDynamicForm> | null>(null)
const refineCandidates = ref<GrossanlassWishLine[]>([])
const refineWishId = ref<string | null>(null)

const groupsRef = computed(() => groups.value)
const { canFullyManage, isMemberInRessortBranch, isLeaderOfGroup, canCreateChild } =
  useGrossanlassRessortScope(groupsRef)

const isFeinRound = computed(
  () => round.value?.form_purpose === 'material_wish' && round.value.material_stage === 'fein',
)

const dialogTitle = computed(() =>
  round.value?.name
    ? t('grossanlass.dashboard.wishDialogTitle', { name: round.value.name })
    : t('grossanlass.wishes.formTitle'),
)

async function loadRoundData() {
  const deptId = props.departmentId
  const roundId = props.roundId
  if (!deptId || !roundId || !open.value) return

  isLoading.value = true
  loadError.value = ''
  refineCandidates.value = []
  refineWishId.value = null
  round.value = null
  roundForm.value = null

  try {
    const [rounds, groupList, form] = await Promise.all([
      getGrossanlassPlanningRounds(deptId),
      getGrossanlassGroups(deptId),
      getGrossanlassRoundForm(deptId, roundId),
    ])
    groups.value = groupList
    round.value = rounds.find((r) => r.id === roundId) || null
    roundForm.value = { ...form, fields: orderFormFieldsForRound(form.fields) }

    if (!round.value) {
      loadError.value = t('grossanlass.planung.rounds.errorLoad')
      return
    }
    if (round.value.status !== 'open') {
      loadError.value = t('grossanlass.dashboard.wishDialogClosed')
    } else if (round.value.form_purpose === 'material_wish' && round.value.material_stage === 'fein') {
      refineCandidates.value = await getGrossanlassRefineCandidates(deptId, roundId)
    }
  } catch (e: any) {
    loadError.value = e.response?.data?.error || t('grossanlass.planung.rounds.errorLoad')
  } finally {
    isLoading.value = false
  }
}

async function submitWish() {
  const deptId = props.departmentId
  const roundId = props.roundId
  if (!deptId || !roundId || !wishFormRef.value || round.value?.status !== 'open') return

  const payload = wishFormRef.value.buildPayload()

  if (payload.new_bauprojekt) {
    if (!payload.new_bauprojekt.parent_id || !payload.new_bauprojekt.name) {
      toast.error(t('grossanlass.wishes.errorBauprojekt'))
      return
    }
  } else if (!payload.group_id && !payload.ressort_group_id) {
    toast.error(t('grossanlass.wishes.errorGroup'))
    return
  }

  const hasPeriod = roundForm.value?.fields.some((f) => f.system_key === 'period' && f.enabled && f.required)
  if (hasPeriod && (!payload.valid_from || !payload.valid_to)) {
    toast.error(t('grossanlass.wishes.errorRequired'))
    return
  }

  isSaving.value = true
  try {
    await createGrossanlassWish(deptId, roundId, {
      ...payload,
      refine_wish_id: refineWishId.value || undefined,
    })
    toast.success(refineWishId.value ? t('grossanlass.wishes.refined') : t('grossanlass.wishes.created'))
    wishFormRef.value.resetAfterSubmit()
    refineWishId.value = null
    if (payload.new_bauprojekt) {
      groups.value = await getGrossanlassGroups(deptId)
    }
    emit('submitted')
    open.value = false
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.wishes.errorSave'))
  } finally {
    isSaving.value = false
  }
}

function startRefine(wish: GrossanlassWishLine) {
  refineWishId.value = wish.id
  wishFormRef.value?.loadFromWish(wish)
}

watch(
  () => [open.value, props.roundId, props.departmentId] as const,
  ([isOpen]) => {
    if (isOpen) {
      void loadRoundData()
    }
  },
)
</script>

<style scoped>
.dialog-hint {
  margin: 0 0 16px;
  font-size: 0.9rem;
  color: var(--color-text-muted, #6b7280);
  line-height: 1.5;
}
.refine-panel { margin: 0 0 12px; }
.refine-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.refine-list li { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 0.88rem; }
</style>
