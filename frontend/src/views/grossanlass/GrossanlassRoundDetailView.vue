<template>
  <PageShell
    class="grossanlass-round-detail"
    :title="round?.name || t('grossanlass.planung.rounds.pageTitle')"
    :subtitle="roundSubtitle"
  >
    <div v-if="isLoading" class="round-detail-loading">
      <ELoadingState variant="list" :message="t('common.loading')" />
    </div>
    <div v-else-if="error" class="round-detail-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</EButton>
    </div>
    <template v-else-if="round">
      <div class="round-detail-toolbar">
        <EButton variant="secondary" size="small" @click="goBack">
          <v-icon icon="mdi-arrow-left" start size="18" />
          {{ t('grossanlass.planung.rounds.backToList') }}
        </EButton>
        <span class="status-badge" :class="'status-' + round.status">{{ statusLabel(round.status) }}</span>
      </div>

      <v-tabs v-model="activeTab" class="round-detail-tabs" color="primary">
        <v-tab v-if="round.status === 'open'" value="input">{{ t('grossanlass.roundDetail.tabInput') }}</v-tab>
        <v-tab value="responses">
          {{ t('grossanlass.roundDetail.tabResponses') }}
          <span v-if="pendingCount > 0" class="tab-badge">{{ pendingCount }}</span>
        </v-tab>
      </v-tabs>

      <v-tabs-window v-model="activeTab" class="round-detail-window">
        <v-tabs-window-item v-if="round.status === 'open'" value="input">
          <div class="tab-panel">
            <h3 class="section-title">{{ t('grossanlass.wishes.formTitle') }}</h3>
            <p class="section-hint">{{ formHint }}</p>

            <div v-if="isFeinRound" class="refine-panel">
              <p class="refine-title">{{ t('grossanlass.wishes.refineTitle') }}</p>
              <p class="refine-hint">{{ t('grossanlass.wishes.refineHint') }}</p>
              <ul v-if="refineCandidates.length" class="refine-list">
                <li v-for="wish in refineCandidates" :key="wish.id">
                  <span>{{ wish.quantity }}× {{ wish.label }} · {{ wish.group_name }}</span>
                  <EButton size="small" variant="secondary" @click="startRefine(wish)">
                    {{ t('grossanlass.wishes.refineAction') }}
                  </EButton>
                </li>
              </ul>
              <p v-else class="section-hint">{{ t('grossanlass.wishes.refineEmpty') }}</p>
              <EButton
                v-if="refineWishId"
                size="small"
                variant="secondary"
                class="mt-2"
                @click="clearRefine"
              >
                {{ t('grossanlass.wishes.refineNew') }}
              </EButton>
            </div>

            <GrossanlassWishDynamicForm
              v-if="roundForm"
              ref="wishFormRef"
              :form="roundForm"
              :department-id="departmentId"
              :groups="groups"
              :can-fully-manage="canFullyManage"
              :is-member-in-ressort-branch="isMemberInRessortBranch"
              :is-leader-of-group="isLeaderOfGroup"
              :can-create-child="canCreateChild"
            />

            <EButton variant="primary" :loading="isSaving" class="mt-4" @click="submitWish">
              {{ refineWishId ? t('grossanlass.wishes.refineSave') : t('grossanlass.wishes.submit') }}
            </EButton>
          </div>
        </v-tabs-window-item>

        <v-tabs-window-item value="responses">
          <div class="tab-panel">
            <GrossanlassRoundResponsesPanel
              ref="responsesRef"
              :department-id="departmentId"
              :round-id="roundId"
              :round-status="round.status"
              :groups="groups"
              :form="roundForm"
              :can-fully-manage="canFullyManage"
              :is-member-in-ressort-branch="isMemberInRessortBranch"
              :is-leader-of-group="isLeaderOfGroup"
              :can-create-child="canCreateChild"
              @changed="onResponseChanged"
            />
          </div>
        </v-tabs-window-item>
      </v-tabs-window>
    </template>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import PageShell from '@/components/layout/PageShell.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton } from '@/components/form/base'
import GrossanlassWishDynamicForm from '@/components/grossanlass/GrossanlassWishDynamicForm.vue'
import GrossanlassRoundResponsesPanel from '@/components/grossanlass/GrossanlassRoundResponsesPanel.vue'
import {
  getGrossanlassPlanningRounds,
  type GrossanlassPlanningRound,
  type GrossanlassRoundStatus,
} from '@/api/grossanlassRounds'
import {
  createGrossanlassWish,
  getGrossanlassRefineCandidates,
  getGrossanlassRoundWishes,
  type GrossanlassWishLine,
  type GrossanlassWishListResult,
} from '@/api/grossanlassWishes'
import { getGrossanlassRoundForm, orderFormFieldsForRound, type GrossanlassRoundForm } from '@/api/grossanlassRoundForm'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => String(route.params.departmentId || ''))
const roundId = computed(() => String(route.params.roundId || ''))

const round = ref<GrossanlassPlanningRound | null>(null)
const roundForm = ref<GrossanlassRoundForm | null>(null)
const groups = ref<GrossanlassGroup[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref('')
const pendingCount = ref(0)
const activeTab = ref('input')
const wishFormRef = ref<InstanceType<typeof GrossanlassWishDynamicForm> | null>(null)
const responsesRef = ref<InstanceType<typeof GrossanlassRoundResponsesPanel> | null>(null)
const refineCandidates = ref<GrossanlassWishLine[]>([])
const refineWishId = ref<string | null>(null)

const groupsRef = computed(() => groups.value)
const { canFullyManage, isMemberInRessortBranch, isLeaderOfGroup, canCreateChild } = useGrossanlassRessortScope(groupsRef)

const isFeinRound = computed(
  () => round.value?.form_purpose === 'material_wish' && round.value.material_stage === 'fein',
)

const formHint = computed(() => {
  if (isFeinRound.value) return t('grossanlass.wishes.feinFormHint')
  return t('grossanlass.wishes.formHint')
})

const roundSubtitle = computed(() => {
  if (!round.value) return ''
  if (round.value.status === 'open') return t('grossanlass.wishes.roundOpenSubtitle')
  return t('grossanlass.wishes.roundClosedSubtitle')
})

function statusLabel(status: GrossanlassRoundStatus): string {
  switch (status) {
    case 'open':
      return t('grossanlass.planung.rounds.statusOpen')
    case 'closed':
      return t('grossanlass.planung.rounds.statusClosed')
    default:
      return t('grossanlass.planung.rounds.statusScheduled')
  }
}

function goBack() {
  void router.push(`/${departmentId.value}/planung`)
}

function setDefaultTab() {
  const queryTab = String(route.query.tab || '')
  if (queryTab === 'responses') {
    activeTab.value = 'responses'
    return
  }
  if (queryTab === 'input' && round.value?.status === 'open') {
    activeTab.value = 'input'
    return
  }
  if (round.value?.status === 'open') {
    activeTab.value = 'input'
  } else {
    activeTab.value = 'responses'
  }
}

async function loadPendingCount() {
  if (!departmentId.value || !roundId.value) return
  try {
    const result = await getGrossanlassRoundWishes(departmentId.value, roundId.value, {
      page: 1,
      limit: 1,
      status: 'requested',
    })
    pendingCount.value = (result as GrossanlassWishListResult).counts.requested
  } catch {
    pendingCount.value = 0
  }
}

async function loadForm() {
  if (!departmentId.value || !roundId.value) return
  try {
    const form = await getGrossanlassRoundForm(departmentId.value, roundId.value)
    roundForm.value = { ...form, fields: orderFormFieldsForRound(form.fields) }
  } catch {
    roundForm.value = null
  }
}

async function load() {
  if (!departmentId.value || !roundId.value) return
  isLoading.value = true
  error.value = ''
  try {
    const [rounds, groupList] = await Promise.all([
      getGrossanlassPlanningRounds(departmentId.value),
      getGrossanlassGroups(departmentId.value),
      loadForm(),
    ])
    groups.value = groupList
    round.value = rounds.find((r) => r.id === roundId.value) || null
    if (!round.value) {
      error.value = t('grossanlass.planung.rounds.errorLoad')
      return
    }
    setDefaultTab()
    await loadPendingCount()
    await loadRefineCandidates()
  } catch (e: any) {
    error.value = e.response?.data?.error || t('grossanlass.planung.rounds.errorLoad')
  } finally {
    isLoading.value = false
  }
}

async function submitWish() {
  if (!departmentId.value || !roundId.value || !wishFormRef.value) return
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
    await createGrossanlassWish(departmentId.value, roundId.value, {
      ...payload,
      refine_wish_id: refineWishId.value || undefined,
    })
    toast.success(refineWishId.value ? t('grossanlass.wishes.refined') : t('grossanlass.wishes.created'))
    wishFormRef.value.resetAfterSubmit()
    refineWishId.value = null
    if (payload.new_bauprojekt) {
      groups.value = await getGrossanlassGroups(departmentId.value)
    }
    await loadPendingCount()
    await loadRefineCandidates()
    responsesRef.value?.reload()
    activeTab.value = 'responses'
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.wishes.errorSave'))
  } finally {
    isSaving.value = false
  }
}

async function loadRefineCandidates() {
  refineCandidates.value = []
  if (!departmentId.value || !roundId.value || !isFeinRound.value) return
  try {
    refineCandidates.value = await getGrossanlassRefineCandidates(departmentId.value, roundId.value)
  } catch {
    refineCandidates.value = []
  }
}

function startRefine(wish: GrossanlassWishLine) {
  refineWishId.value = wish.id
  wishFormRef.value?.loadFromWish(wish)
}

function clearRefine() {
  refineWishId.value = null
  wishFormRef.value?.resetAfterSubmit()
}

function onResponseChanged() {
  void loadPendingCount()
}

onMounted(load)
</script>

<style scoped>
.round-detail-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.status-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-open { background: #d1fae5; color: #065f46; }
.status-closed { background: #f3f4f6; color: #4b5563; }
.status-scheduled { background: #e0e7ff; color: #3730a3; }

.round-detail-tabs {
  margin-bottom: 0;
}

.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  margin-left: 6px;
  padding: 0 5px;
  border-radius: 999px;
  background: #f59e0b;
  color: #fff;
  font-size: 0.68rem;
  font-weight: 700;
}

.round-detail-window {
  margin-top: 0;
}

.tab-panel {
  padding: 20px 0 8px;
}

.section-title {
  margin: 0 0 8px;
  font-size: 1.05rem;
  font-weight: 600;
}

.section-hint {
  margin: 0 0 16px;
  color: #6b7280;
  font-size: 0.9rem;
}

.refine-panel {
  margin: 0 0 16px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f8fafc;
}
.refine-title { margin: 0 0 4px; font-weight: 600; font-size: 0.92rem; }
.refine-hint { margin: 0 0 8px; color: #64748b; font-size: 0.85rem; }
.refine-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.refine-list li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  font-size: 0.88rem;
}

.mt-4 {
  margin-top: 16px;
}
</style>
