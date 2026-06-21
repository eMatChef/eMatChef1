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
      v-else-if="myGroups.length === 0"
      variant="default"
      icon="mdi-home-group"
      :title="t('grossanlass.meinRessort.emptyTitle')"
      :description="t('grossanlass.meinRessort.emptyDescription')"
    />

    <div v-else class="mein-ressort-content">
      <div v-for="group in myGroups" :key="group.id" class="ressort-card">
        <div class="ressort-card__head">
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
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const departmentId = computed(() => String(route.params.departmentId || ''))

const groups = ref<GrossanlassGroup[]>([])
const wishes = ref<GrossanlassWishLine[]>([])
const rounds = ref<GrossanlassPlanningRound[]>([])
const isLoading = ref(true)
const error = ref('')

const groupsRef = computed(() => groups.value)
const { isMemberInRessortBranch } = useGrossanlassRessortScope(groupsRef)

const myGroups = computed(() =>
  groups.value.filter((g) => isMemberInRessortBranch(g)).sort((a, b) => a.level - b.level || a.name.localeCompare(b.name)),
)

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
    const [groupList, wishList, roundList] = await Promise.all([
      getGrossanlassGroups(departmentId.value),
      getMyRessortWishes(departmentId.value),
      getGrossanlassPlanningRounds(departmentId.value),
    ])
    groups.value = groupList
    wishes.value = wishList
    rounds.value = roundList
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
</style>
