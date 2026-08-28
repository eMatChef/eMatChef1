<template>
  <PageShell
    class="grossanlass-mein-ressort"
    :title="t('grossanlass.meinRessort.title')"
    :subtitle="t('grossanlass.meinRessort.subtitle')"
  >
    <ELoadingState v-if="isLoading" variant="list" :message="t('grossanlass.meinRessort.loading')" />

    <div v-else-if="error" class="mein-ressort-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</EButton>
    </div>

    <EEmptyState
      v-else-if="myGroupsTree.length === 0"
      variant="default"
      icon="mdi-home-group"
      :title="t('grossanlass.meinRessort.emptyTitle')"
      :description="t('grossanlass.meinRessort.emptyDescription')"
    />

    <div v-else class="mein-ressort-content">
      <div
        v-for="group in myGroupsTree"
        :key="group.id"
        class="ressort-card"
        :class="{ 'is-child': group._level > 0 }"
      >
        <div class="ressort-card__head" :style="{ paddingLeft: group._level * 24 + 'px' }">
          <span v-if="group._level > 0" class="indent-icon">↳</span>
          <v-icon :icon="nodeIcon(group.node_type)" size="20" />
          <div>
            <h3>{{ group.name }}</h3>
            <span class="kind-badge">{{ kindLabel(group) }}</span>
          </div>
        </div>

        <div v-if="wishesForGroup(group.id).length > 0" class="wish-mini-list">
          <div v-for="wish in wishesForGroup(group.id)" :key="wish.id" class="wish-mini-row">
            <span class="wish-label">{{ wish.quantity }}× {{ wish.label }}</span>
            <span class="wish-meta">{{ wish.location }} · {{ wish.group_name }}</span>
          </div>
        </div>
        <p v-else class="no-wishes">{{ t('grossanlass.meinRessort.noWishesYet') }}</p>
      </div>

      <div class="kosten-panel">
        <h3>{{ t('grossanlass.beschaffung.kosten.linesTitle') }}</h3>
        <p class="kosten-rahmen">
          {{ t('grossanlass.meinRessort.rahmenSaved') }}:
          {{ ownRahmenAmount == null ? '—' : formatChf(ownRahmenAmount) }}
          · {{ t('grossanlass.meinRessort.nettoIst') }}:
          {{ formatChf(ownNetto) }}
        </p>
        <div v-for="row in costRows" :key="row.id" class="wish-mini-row">
          <span class="wish-label">{{ row.label }} · {{ t(`grossanlass.beschaffung.kosten.kind.${row.cost_kind}`) }}</span>
          <span class="wish-meta">{{ formatChf(row.netto_chf) }} {{ t('grossanlass.beschaffung.kosten.statNetto') }}</span>
        </div>
        <p v-if="costRows.length === 0" class="no-wishes">{{ t('grossanlass.meinRessort.noCostsYet') }}</p>
      </div>

      <div v-if="openRounds.length > 0" class="open-rounds-hint">
        <p>{{ t('grossanlass.meinRessort.openRoundsHint') }}</p>
        <EButton variant="primary" size="small" @click="goToPlanung">{{ t('sidebar.planung') }}</EButton>
      </div>
    </div>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton } from '@/components/form/base'
import { getGrossanlassGroups, type GrossanlassGroup, type GrossanlassNodeType } from '@/api/grossanlassGroups'
import { getMyRessortWishes, type GrossanlassWishLine } from '@/api/grossanlassWishes'
import { getGrossanlassPlanningRounds, type GrossanlassPlanningRound } from '@/api/grossanlassRounds'
import { formatChf, listGrossanlassBudgets, listGrossanlassCosts, type GrossanlassBudget, type GrossanlassCost } from '@/api/grossanlassProcurement'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import {
  flattenGrossanlassGroupsFiltered,
} from '@/utils/grossanlassGroupHierarchy'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const departmentId = computed(() => String(route.params.departmentId || ''))

const groups = ref<GrossanlassGroup[]>([])
const wishes = ref<GrossanlassWishLine[]>([])
const rounds = ref<GrossanlassPlanningRound[]>([])
const costRows = ref<GrossanlassCost[]>([])
const budgets = ref<GrossanlassBudget[]>([])
const isLoading = ref(true)
const error = ref('')

const groupsRef = computed(() => groups.value)
const { isInAssignedRessortBranch } = useGrossanlassRessortScope(groupsRef)

const myGroupsTree = computed(() =>
  flattenGrossanlassGroupsFiltered(groups.value, (g) => isInAssignedRessortBranch(g)),
)

const myGroupIds = computed(() => new Set(myGroupsTree.value.map((g) => g.id)))

const ownRahmenAmount = computed(() => {
  const amounts = budgets.value
    .filter((row) => row.payer_group_id && myGroupIds.value.has(row.payer_group_id))
    .map((row) => row.rahmen_chf)
    .filter((value): value is number => value != null)
  if (amounts.length === 0) return null
  return amounts.reduce((sum, value) => sum + value, 0)
})

const ownNetto = computed(() => costRows.value.reduce((sum, row) => sum + (row.netto_chf || 0), 0))

const openRounds = computed(() => rounds.value.filter((r) => r.status === 'open'))

function wishesForGroup(groupId: string): GrossanlassWishLine[] {
  return wishes.value.filter((w) => w.group_id === groupId)
}

function nodeIcon(nodeType: GrossanlassNodeType): string {
  if (nodeType === 'bauprojekt') return 'mdi-hammer-wrench'
  if (nodeType === 'unterressort') return 'mdi-file-tree'
  return 'mdi-sitemap'
}

function kindLabel(group: GrossanlassGroup): string {
  if (group.node_type === 'bauprojekt') return t('grossanlass.planung.ressorts.kindBauprojekt')
  if (group.node_type === 'unterressort') return t('grossanlass.planung.ressorts.kindUnterressort')
  return t('grossanlass.planung.ressorts.kindRessort')
}

function goToPlanung() {
  void router.push(`/${departmentId.value}/planung`)
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = ''
  try {
    const [groupList, wishList, roundList, costs, budgetList] = await Promise.all([
      getGrossanlassGroups(departmentId.value),
      getMyRessortWishes(departmentId.value),
      getGrossanlassPlanningRounds(departmentId.value),
      listGrossanlassCosts(departmentId.value).catch(() => [] as GrossanlassCost[]),
      listGrossanlassBudgets(departmentId.value).catch(() => [] as GrossanlassBudget[]),
    ])
    groups.value = groupList
    wishes.value = wishList
    rounds.value = roundList
    costRows.value = costs
    budgets.value = budgetList
  } catch (e: any) {
    error.value = e.response?.data?.error || t('grossanlass.meinRessort.errorLoad')
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.mein-ressort-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ressort-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
}

.ressort-card__head {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 10px;
}

.ressort-card.is-child {
  background: #fafbfc;
}

.indent-icon {
  color: #94a3b8;
  font-size: 14px;
  flex-shrink: 0;
}

.ressort-card__head h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.kind-badge {
  font-size: 0.78rem;
  color: #6b7280;
}

.wish-mini-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.wish-mini-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  background: #f9fafb;
  border-radius: 6px;
}

.wish-label {
  font-weight: 500;
  font-size: 0.9rem;
}

.wish-meta {
  font-size: 0.78rem;
  color: #6b7280;
}

.no-wishes {
  margin: 0;
  font-size: 0.85rem;
  color: #9ca3af;
}

.open-rounds-hint {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #eff6ff;
  border-radius: 8px;
  font-size: 0.9rem;
}
.kosten-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px;
  background: #fff;
}
.kosten-panel h3 {
  margin: 0 0 6px;
  font-size: 0.95rem;
}
.kosten-rahmen {
  margin: 0 0 10px;
  font-size: 0.82rem;
  color: #64748b;
}
</style>
