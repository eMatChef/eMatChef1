<template>
  <div class="ga-dashboard">
    <ELoadingState
      v-if="isLoading"
      variant="page"
      :message="t('grossanlass.dashboard.loading')"
    />

    <v-alert v-else-if="error" type="error" variant="tonal" :text="error" class="mb-4" />

    <template v-else>
      <!-- Kennzahlen -->
      <div class="ga-dashboard__stats">
        <div class="stat-card">
          <span class="stat-card__value">{{ ressortRootCount }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statRessorts') }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__value">{{ openRounds.length }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statOpenRounds') }}</span>
        </div>
        <div v-if="canManageProcurement && procurementOverview" class="stat-card">
          <span class="stat-card__value">{{ formatChf(procurementOverview.totals.soll_chf) }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statBudgetSoll') }}</span>
        </div>
        <div v-if="canManageProcurement && procurementOverview" class="stat-card">
          <span class="stat-card__value">{{ procurementOverview.totals.ordered_not_received_count }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statAwaitingDelivery') }}</span>
        </div>
      </div>

      <!-- Offene Runden — Wunsch einreichen -->
      <section class="ga-dashboard__section">
        <div class="section-header">
          <h2 class="section-title">{{ t('grossanlass.dashboard.openRoundsTitle') }}</h2>
          <router-link :to="planungLink" class="section-link">{{ t('grossanlass.dashboard.allRounds') }}</router-link>
        </div>

        <EEmptyState
          v-if="openRounds.length === 0"
          variant="default"
          icon="mdi-calendar-clock"
          :title="t('grossanlass.dashboard.noOpenRoundsTitle')"
          :description="t('grossanlass.dashboard.noOpenRoundsDescription')"
        >
          <template v-if="canManageRounds" #actions>
            <router-link :to="planungLink">
              <EButton>{{ t('grossanlass.planung.rounds.addAction') }}</EButton>
            </router-link>
          </template>
        </EEmptyState>

        <div v-else class="round-cards">
          <article v-for="round in openRounds" :key="round.id" class="round-card">
            <div class="round-card__main">
              <h3 class="round-card__name">{{ round.name }}</h3>
              <p class="round-card__meta">
                <span class="status-badge status-open">{{ t('grossanlass.planung.rounds.statusOpen') }}</span>
                <span class="round-card__window">{{ formatWindow(round) }}</span>
              </p>
            </div>
            <div class="round-card__actions">
              <EButton variant="primary" size="small" @click="openWishDialog(round.id)">
                <v-icon icon="mdi-form-select" start size="18" />
                {{ t('grossanlass.dashboard.submitWish') }}
              </EButton>
              <EButton
                variant="secondary"
                size="small"
                @click="router.push(roundDetailLink(round.id, 'input'))"
              >
                {{ t('grossanlass.dashboard.openRoundPage') }}
              </EButton>
            </div>
          </article>
        </div>
      </section>

      <!-- Geplant / kürzlich geschlossen -->
      <section v-if="otherRounds.length > 0" class="ga-dashboard__section">
        <h2 class="section-title">{{ t('grossanlass.dashboard.otherRoundsTitle') }}</h2>
        <ul class="round-list">
          <li v-for="round in otherRounds.slice(0, 5)" :key="round.id" class="round-list__item">
            <router-link :to="roundDetailLink(round.id)" class="round-list__link">
              <span class="round-list__name">{{ round.name }}</span>
              <span class="status-badge" :class="'status-' + round.status">
                {{ statusLabel(round.status) }}
              </span>
            </router-link>
          </li>
        </ul>
      </section>

      <!-- Schnellzugriff -->
      <section class="ga-dashboard__section">
        <h2 class="section-title">{{ t('grossanlass.dashboard.quickLinksTitle') }}</h2>
        <div class="quick-links">
          <router-link v-if="canManageRounds" :to="ressortsLink" class="quick-link-card">
            <v-icon icon="mdi-sitemap" size="22" />
            <span>{{ t('grossanlass.dashboard.linkRessorts') }}</span>
          </router-link>
          <router-link v-else :to="meinRessortLink" class="quick-link-card">
            <v-icon icon="mdi-home-group" size="22" />
            <span>{{ t('sidebar.meinRessort') }}</span>
          </router-link>
          <router-link :to="planungLink" class="quick-link-card">
            <v-icon icon="mdi-calendar-clock" size="22" />
            <span>{{ t('sidebar.planung') }}</span>
          </router-link>
          <router-link
            v-if="canManageProcurement"
            :to="beschaffungLink"
            class="quick-link-card"
          >
            <v-icon icon="mdi-cart-outline" size="22" />
            <span>{{ t('sidebar.beschaffung') }}</span>
          </router-link>
        </div>
      </section>
    </template>

    <GrossanlassWishSubmitDialog
      v-model="wishDialogOpen"
      :department-id="departmentId"
      :round-id="activeWishRoundId"
      @submitted="onWishSubmitted"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useAuthStore } from '@/stores/auth'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton } from '@/components/form/base'
import GrossanlassWishSubmitDialog from '@/components/grossanlass/GrossanlassWishSubmitDialog.vue'
import {
  getGrossanlassPlanningRounds,
  type GrossanlassPlanningRound,
  type GrossanlassRoundStatus,
} from '@/api/grossanlassRounds'
import { getGrossanlassGroups } from '@/api/grossanlassGroups'
import {
  formatChf,
  getGrossanlassProcurementOverview,
  type GrossanlassProcurementOverview,
} from '@/api/grossanlassProcurement'

const props = defineProps<{
  departmentId: string
}>()

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const { isUserRole } = useDepartmentMemberRole()

const isLoading = ref(true)
const error = ref('')
const rounds = ref<GrossanlassPlanningRound[]>([])
const ressortRootCount = ref(0)
const procurementOverview = ref<GrossanlassProcurementOverview | null>(null)

const wishDialogOpen = ref(false)
const activeWishRoundId = ref<string | null>(null)

const canManageRounds = computed(() => !isUserRole.value)
const canManageProcurement = computed(() => {
  const r = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
  return ['mw', 'dc', 'matwart', 'depchef'].includes(r)
})

const openRounds = computed(() => rounds.value.filter((r) => r.status === 'open'))
const otherRounds = computed(() =>
  rounds.value.filter((r) => r.status !== 'open').sort((a, b) => {
    const order: Record<GrossanlassRoundStatus, number> = { scheduled: 0, closed: 1, open: 2 }
    return order[a.status] - order[b.status]
  }),
)

const planungLink = computed(() => `/${props.departmentId}/planung`)
const ressortsLink = computed(() => `/${props.departmentId}/ressorts`)
const meinRessortLink = computed(() => `/${props.departmentId}/mein-ressort`)
const beschaffungLink = computed(() => `/${props.departmentId}/beschaffung`)

function roundDetailLink(roundId: string, tab?: 'input' | 'responses') {
  const base = `/${props.departmentId}/planung/runden/${roundId}`
  if (tab === 'input') return { path: base, query: { tab: 'input' } }
  return base
}

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

function formatDateTime(iso: string | null): string {
  if (!iso) return '–'
  try {
    return new Date(iso).toLocaleString('de-CH', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}

function formatWindow(round: GrossanlassPlanningRound): string {
  const open = formatDateTime(round.opens_at)
  const close = formatDateTime(round.closes_at)
  if (open === '–' && close === '–') return t('grossanlass.planung.rounds.windowManual')
  return t('grossanlass.planung.rounds.windowRange', { open, close })
}

function openWishDialog(roundId: string) {
  activeWishRoundId.value = roundId
  wishDialogOpen.value = true
}

function clearWishRoundQuery() {
  if (!route.query.wishRound) return
  const { wishRound: _removed, ...rest } = route.query
  void router.replace({ query: rest })
}

function tryOpenWishFromQuery() {
  const wishRound = String(route.query.wishRound || '')
  if (!wishRound || isLoading.value) return
  const match = openRounds.value.find((r) => r.id === wishRound)
  if (match) {
    openWishDialog(match.id)
    clearWishRoundQuery()
  }
}

async function load() {
  if (!props.departmentId) return
  isLoading.value = true
  error.value = ''
  try {
    const [roundList, groups] = await Promise.all([
      getGrossanlassPlanningRounds(props.departmentId),
      getGrossanlassGroups(props.departmentId),
    ])
    rounds.value = roundList
    ressortRootCount.value = groups.filter((g) => !g.parent_id).length

    if (canManageProcurement.value) {
      try {
        procurementOverview.value = await getGrossanlassProcurementOverview(props.departmentId)
      } catch {
        procurementOverview.value = null
      }
    }
  } catch (e: any) {
    error.value = e.response?.data?.error || t('grossanlass.dashboard.errorLoad')
  } finally {
    isLoading.value = false
    tryOpenWishFromQuery()
  }
}

function onWishSubmitted() {
  void load()
}

watch(
  () => route.query.wishRound,
  () => tryOpenWishFromQuery(),
)

onMounted(load)
</script>

<style scoped>
.ga-dashboard {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.ga-dashboard__stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}

.stat-card {
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-card__value {
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.2;
  color: var(--color-text, #111827);
}

.stat-card__label {
  font-size: 0.78rem;
  color: var(--color-text-muted, #6b7280);
  line-height: 1.3;
}

.ga-dashboard__section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.section-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
}

.section-link {
  font-size: 0.875rem;
  color: var(--color-primary, #059669);
  text-decoration: none;
}

.section-link:hover {
  text-decoration: underline;
}

.round-cards {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.round-card {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 16px 18px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  background: var(--color-surface, #fff);
  border-left: 4px solid var(--color-primary, #059669);
}

.round-card__name {
  margin: 0 0 8px;
  font-size: 1rem;
  font-weight: 600;
}

.round-card__meta {
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  font-size: 0.85rem;
  color: var(--color-text-muted, #6b7280);
}

.round-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.status-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
}

.status-open {
  background: #d1fae5;
  color: #065f46;
}

.status-scheduled {
  background: #e0e7ff;
  color: #3730a3;
}

.status-closed {
  background: #f3f4f6;
  color: #4b5563;
}

.round-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  overflow: hidden;
}

.round-list__item + .round-list__item {
  border-top: 1px solid var(--color-border, #e5e7eb);
}

.round-list__link {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px;
  text-decoration: none;
  color: inherit;
}

.round-list__link:hover {
  background: var(--color-surface-muted, #f9fafb);
}

.round-list__name {
  font-weight: 500;
}

.quick-links {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 10px;
}

.quick-link-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  text-decoration: none;
  color: inherit;
  font-weight: 500;
  font-size: 0.9rem;
  background: var(--color-surface, #fff);
}

.quick-link-card:hover {
  border-color: var(--color-primary, #059669);
  color: var(--color-primary, #059669);
}
</style>
