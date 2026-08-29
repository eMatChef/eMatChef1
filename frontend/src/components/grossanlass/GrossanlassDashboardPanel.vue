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
        <router-link
          v-if="canManageProcurement && procurementOverview"
          :to="kostenLink"
          class="stat-card stat-card--link"
        >
          <span class="stat-card__value">{{ formatChf(dashboardBudgetAmount) }}</span>
          <span class="stat-card__label">{{ t(dashboardBudgetLabelKey) }}</span>
        </router-link>
        <div v-if="canManageProcurement && procurementOverview" class="stat-card">
          <span class="stat-card__value">{{ procurementOverview.totals.ordered_not_received_count }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statAwaitingDelivery') }}</span>
        </div>
        <router-link
          v-if="canManageProcurement && inquiryStats"
          :to="anfragenLink"
          class="stat-card stat-card--link"
        >
          <span class="stat-card__value">{{ inquiryStats.entwurf }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statInquiryDrafts') }}</span>
        </router-link>
        <router-link
          v-if="canManageProcurement && inquiryStats"
          :to="anfragenLink"
          class="stat-card stat-card--link"
        >
          <span class="stat-card__value">{{ inquiryStats.gesendet }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statInquiryWaiting') }}</span>
        </router-link>
        <router-link
          v-if="canManageProcurement && inquiryStats"
          :to="anfragenLink"
          class="stat-card stat-card--link"
        >
          <span class="stat-card__value">{{ inquiryStats.antwort }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statInquiryReplies') }}</span>
        </router-link>
        <router-link
          v-if="canManageProcurement && inquiryStats"
          :to="anfragenLink"
          class="stat-card stat-card--link"
        >
          <span class="stat-card__value">{{ inquiryStats.zusage }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statInquiryYes') }}</span>
        </router-link>
      </div>

      <!-- Offene Formulare — nur anzeigen, wenn welche offen sind -->
      <section v-if="openRounds.length > 0" class="ga-dashboard__section">
        <div class="section-header">
          <h2 class="section-title">{{ t('grossanlass.dashboard.openRoundsTitle') }}</h2>
          <router-link :to="planungLink" class="section-link">{{ t('grossanlass.dashboard.allRounds') }}</router-link>
        </div>

        <div class="round-cards">
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
            :to="materialsLink"
            class="quick-link-card"
          >
            <v-icon icon="mdi-package-variant" size="22" />
            <span>{{ t('sidebar.materials') }}</span>
          </router-link>
          <router-link
            v-if="canManageProcurement"
            :to="materialUebersichtLink"
            class="quick-link-card"
          >
            <v-icon icon="mdi-truck-delivery-outline" size="22" />
            <span>{{ t('sidebar.materialUebersicht') }}</span>
          </router-link>
          <router-link
            v-if="canManageProcurement"
            :to="beschaffungLink"
            class="quick-link-card"
          >
            <v-icon icon="mdi-cart-outline" size="22" />
            <span>{{ t('sidebar.beschaffung') }}</span>
          </router-link>
          <router-link
            v-if="canManageProcurement"
            :to="kostenLink"
            class="quick-link-card"
          >
            <v-icon icon="mdi-cash-multiple" size="22" />
            <span>{{ t('sidebar.kosten') }}</span>
          </router-link>
        </div>
      </section>

      <!-- Gast-Abteilungen: echte Planungsdaten -->
      <section v-if="known && hasGuestDepartments" class="ga-dashboard__section">
        <div class="section-header">
          <h2 class="section-title">{{ t('grossanlass.dashboard.previewParticipantsTitle') }}</h2>
          <router-link :to="teilnehmerLink" class="section-link">
            {{ t('grossanlass.dashboard.participantsAll') }}
          </router-link>
        </div>
        <p class="participants-lead">{{ t('grossanlass.dashboard.participantsLiveText') }}</p>
        <ul v-if="liveParticipants.length" class="participants-list">
          <li v-for="row in liveParticipants" :key="row.id">
            {{ row.name }}
            <span class="participants-list__org" v-if="row.organisation_name">{{ row.organisation_name }}</span>
            · {{ t(`grossanlass.planung.struktur.status.${row.status}`) }}
          </li>
        </ul>
        <p v-else class="participants-empty">{{ t('grossanlass.dashboard.previewParticipantsEmpty') }}</p>

        <div class="preview-freigabe">
          <div>
            <h3>{{ t('grossanlass.dashboard.previewFreigabeTitle') }}</h3>
            <p>{{ published ? t('grossanlass.planung.freigabe.publishedHint') : t('grossanlass.dashboard.previewFreigabeText') }}</p>
          </div>
          <router-link :to="freigabeLink">
            <EButton variant="secondary" size="small">
              {{ t('grossanlass.dashboard.previewFreigabeAction') }}
            </EButton>
          </router-link>
        </div>
      </section>

      <!-- Materialübersicht: Bestand und Konflikte -->
      <section v-if="canManageProcurement" class="ga-dashboard__section">
        <div class="section-header">
          <h2 class="section-title">{{ t('grossanlass.dashboard.stockTitle') }}</h2>
          <router-link :to="materialUebersichtLink" class="section-link">
            {{ t('grossanlass.dashboard.stockAll') }}
          </router-link>
        </div>

        <div class="ga-dashboard__stats">
          <router-link :to="materialUebersichtLink" class="stat-card stat-card--link">
            <span class="stat-card__value">{{ stock.lager }}</span>
            <span class="stat-card__label">{{ t('grossanlass.dashboard.previewStockLager') }}</span>
          </router-link>
          <router-link :to="ausgabeLink" class="stat-card stat-card--link">
            <span class="stat-card__value">{{ stock.assigned }}</span>
            <span class="stat-card__label">{{ t('grossanlass.dashboard.previewStockAssigned') }}</span>
          </router-link>
          <router-link :to="ausgabeLink" class="stat-card stat-card--link">
            <span class="stat-card__value">{{ stock.out }}</span>
            <span class="stat-card__label">{{ t('grossanlass.dashboard.previewStockOut') }}</span>
          </router-link>
        </div>
        <p v-if="conflictCount > 0" class="conflicts-link">
          <router-link :to="konflikteLink">
            {{ t('grossanlass.dashboard.conflictsLink', { count: conflictCount }) }}
          </router-link>
        </p>
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
import { EButton } from '@/components/form/base'
import GrossanlassWishSubmitDialog from '@/components/grossanlass/GrossanlassWishSubmitDialog.vue'
import { getGrossanlassUebersicht, type GaUebersichtPayload } from '@/api/grossanlassUebersicht'
import {
  getGrossanlassPlanung,
  type GrossanlassParticipant,
  type GrossanlassPlanungOverview,
} from '@/api/grossanlassPlanung'
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
import { getGrossanlassInquiries } from '@/api/grossanlassInquiries'
import { useGrossanlassGuestDepartments } from '@/composables/useGrossanlassGuestDepartments'

const props = defineProps<{
  departmentId: string
}>()

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const { isUserRole } = useDepartmentMemberRole()
const { hasGuestDepartments, known, setHasGuestDepartments } = useGrossanlassGuestDepartments(
  () => props.departmentId,
)

const isLoading = ref(true)
const error = ref('')
const rounds = ref<GrossanlassPlanningRound[]>([])
const ressortRootCount = ref(0)
const procurementOverview = ref<GrossanlassProcurementOverview | null>(null)
const inquiryStats = ref<{ entwurf: number; gesendet: number; antwort: number; zusage: number } | null>(null)
const planung = ref<GrossanlassPlanungOverview | null>(null)
const uebersicht = ref<GaUebersichtPayload | null>(null)

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
const ressortsLink = computed(() => `/${props.departmentId}/einstellungen/ressorts`)
const meinRessortLink = computed(() => `/${props.departmentId}/mein-ressort`)
const beschaffungLink = computed(() => `/${props.departmentId}/beschaffung/bedarf`)
const kostenLink = computed(() => `/${props.departmentId}/kosten`)
const anfragenLink = computed(() => `/${props.departmentId}/beschaffung/anfragen`)
const dashboardBudgetAmount = computed(() => {
  const totals = procurementOverview.value?.totals
  if (!totals) return null
  return totals.rahmen_chf ?? totals.soll_chf
})
const dashboardBudgetLabelKey = computed(() =>
  procurementOverview.value?.totals.rahmen_chf != null
    ? 'grossanlass.dashboard.statBudgetRahmen'
    : 'grossanlass.dashboard.statBudgetSoll',
)
const materialsLink = computed(() => `/${props.departmentId}/materialien`)
const materialUebersichtLink = computed(() => `/${props.departmentId}/material-uebersicht`)
const konflikteLink = computed(() => `/${props.departmentId}/material-uebersicht/konflikte`)
const ausgabeLink = computed(() => `/${props.departmentId}/material-uebersicht/ausgabe`)
const freigabeLink = computed(() => `/${props.departmentId}/einstellungen/freigabe`)
const teilnehmerLink = computed(() => `/${props.departmentId}/einstellungen/teilnehmer`)
const stock = computed(() => {
  const issues = uebersicht.value?.issues ?? []
  return {
    lager: issues.filter((row) => row.place === 'lager').length,
    assigned: issues.filter((row) => row.place === 'assigned').length,
    out: issues.filter((row) => row.place === 'out').length,
  }
})
const conflictCount = computed(() => uebersicht.value?.conflicts.length ?? 0)
const liveParticipants = computed<GrossanlassParticipant[]>(() => planung.value?.participants ?? [])
const published = computed(() => planung.value?.config.status === 'published')

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
    const [roundList, groups, pack] = await Promise.all([
      getGrossanlassPlanningRounds(props.departmentId),
      getGrossanlassGroups(props.departmentId),
      getGrossanlassPlanung(props.departmentId).catch(() => null),
    ])
    rounds.value = roundList
    ressortRootCount.value = groups.filter((g) => !g.parent_id).length
    planung.value = pack
    if (pack) {
      setHasGuestDepartments(pack.config.has_guest_departments === true)
    }

    if (canManageProcurement.value) {
      try {
        procurementOverview.value = await getGrossanlassProcurementOverview(props.departmentId)
      } catch {
        procurementOverview.value = null
      }
      try {
        const inquiries = await getGrossanlassInquiries(props.departmentId)
        inquiryStats.value = {
          entwurf: inquiries.filter((row) => row.status === 'entwurf').length,
          gesendet: inquiries.filter((row) => row.status === 'gesendet').length,
          antwort: inquiries.filter((row) => row.status === 'antwort').length,
          zusage: inquiries.filter((row) => row.status === 'zusage').length,
        }
      } catch {
        inquiryStats.value = null
      }
      try {
        uebersicht.value = await getGrossanlassUebersicht(props.departmentId)
      } catch {
        uebersicht.value = null
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
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
  gap: 12px;
}

.stat-card {
  container-type: inline-size;
  min-width: 0;
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-card__value {
  font-size: clamp(0.95rem, 10cqi, 1.35rem);
  font-weight: 700;
  line-height: 1.2;
  color: var(--color-text, #111827);
  overflow-wrap: anywhere;
  font-variant-numeric: tabular-nums;
}

.stat-card--link {
  text-decoration: none;
  color: inherit;
}
.stat-card--link:hover {
  border-color: var(--color-primary, #16a34a);
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

.preview-freigabe {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 12px 14px;
  border-radius: 8px;
  background: #fff;
  border: 1px solid #e5e7eb;
}

.preview-freigabe h3 {
  margin: 0 0 4px;
  font-size: 0.95rem;
}

.preview-freigabe p,
.participants-lead,
.participants-empty {
  margin: 0;
  font-size: 0.85rem;
  color: #64748b;
}

.participants-empty {
  font-style: italic;
}

.participants-list {
  margin: 0;
  padding-left: 18px;
  font-size: 0.9rem;
  color: #334155;
}

.participants-list__org {
  color: #64748b;
  font-size: 0.85rem;
}

.participants-list__org::before {
  content: ' · ';
}

.conflicts-link {
  margin: 0;
  font-size: 0.85rem;
}

.conflicts-link a {
  color: var(--color-error, #b91c1c);
  font-weight: 600;
  text-decoration: none;
}

.conflicts-link a:hover {
  text-decoration: underline;
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
