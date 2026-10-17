<template>
  <div class="orders">
    <header class="orders__head">
      <p class="orders__intro">{{ t('grossanlass.planung.auftraege.intro') }}</p>
      <EButton v-if="canAdd" variant="primary" @click="openCreate">
        <v-icon icon="mdi-plus" start size="18" /> {{ t('grossanlass.auftraege.create.button') }}
      </EButton>
    </header>

    <section class="orders__bar">
      <ESwitch
        v-model="showArchived"
        class="orders__mine"
        :label="t('grossanlass.aufgaben.archive.showArchived')"
        hide-details
        density="compact"
        data-testid="show-archived"
      />
      <ESwitch
        v-model="mineOnly"
        class="orders__mine"
        :label="t('grossanlass.auftraege.mineOnly')"
        hide-details
        density="compact"
      />
      <div class="orders__types" role="group" :aria-label="t('grossanlass.auftraege.filterType')">
        <EButton
          v-for="option in typeOptions"
          :key="option"
          size="small"
          :variant="filters.type === option ? 'primary' : 'secondary'"
          @click="filters.type = option"
        >
          {{ t(`grossanlass.auftraege.filter.${option}`) }}<span class="count">{{ typeCounts[option] }}</span>
        </EButton>
      </div>
      <div class="orders__selects">
        <ESearchField v-model="filters.search" :label="t('grossanlass.auftraege.search')" />
        <ESelect v-model="filters.ressort" :items="ressortItems" :label="t('grossanlass.auftraege.f.ressort')" clearable hide-details />
        <ESelect v-model="filters.status" :items="statusItems" :label="t('grossanlass.auftraege.f.status')" clearable hide-details />
      </div>
    </section>

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />
    <EEmptyState
      v-else-if="!shown.length"
      variant="generic"
      icon="mdi-clipboard-text-outline"
      :title="t('grossanlass.auftraege.emptyTitle')"
      :description="t('grossanlass.auftraege.emptyText')"
    >
      <template v-if="canAdd" #actions>
        <EButton @click="openCreate">{{ t('grossanlass.auftraege.create.button') }}</EButton>
      </template>
    </EEmptyState>

    <div v-else class="orders__grid">
      <article v-for="order in shown" :key="order.id" class="order" :class="`order--${order.type}`" @dblclick="openOrder(order.group)">
        <header class="order__head">
          <v-chip size="small" variant="flat" :color="TYPE_COLOR[order.type]" :prepend-icon="TYPE_ICON[order.type]">
            {{ t(`grossanlass.auftraege.type.${order.type}`) }}
          </v-chip>
          <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[order.status]">
            {{ t(gaBuildStatusI18nKey(order.status)) }}
          </v-chip>
        </header>
        <v-chip v-if="order.group.archived" size="x-small" variant="tonal" color="warning" prepend-icon="mdi-archive-outline" data-testid="order-archived-chip">
          {{ t('grossanlass.aufgaben.archive.chip') }}
        </v-chip>
        <h4 class="order__title">{{ order.title }}</h4>
        <p v-if="order.path" class="order__crumb">{{ order.path }}</p>
        <p class="order__meta">
          <v-icon icon="mdi-clock-outline" size="14" />
          {{ formatBauprojektWindow(order.windowStart, order.windowEnd) || '–' }}
        </p>
        <p class="order__meta">
          <v-icon icon="mdi-map-marker-outline" size="14" />
          {{ order.place || t('grossanlass.planung.auftraege.noPlace') }}
        </p>
        <p class="order__meta">
          <v-icon icon="mdi-account-outline" size="14" />
          {{ order.responsible.join(', ') || t('grossanlass.aufgaben.unassigned') }}
        </p>
        <p class="order__meta">
          <v-icon icon="mdi-account-group-outline" size="14" />
          <template v-if="order.card?.staffing.need">
            {{ t('grossanlass.planung.auftraege.staffing', { assigned: order.card.staffing.assigned, need: order.card.staffing.need }) }}
          </template>
          <template v-else-if="order.card?.staffing.assigned">
            {{ t('grossanlass.planung.auftraege.staffingNoNeed', { assigned: order.card.staffing.assigned }) }}
          </template>
          <template v-else>{{ t('grossanlass.planung.auftraege.staffingNone') }}</template>
        </p>
        <div class="progress">
          <span>{{ t('grossanlass.auftraege.materialProgress') }}</span>
          <v-progress-linear :model-value="materialPercent(order.card) ?? 0" height="6" rounded color="primary" />
          <small>
            {{ order.card && order.card.material.items
              ? t('grossanlass.planung.auftraege.materialDetail', { ready: order.card.material.at_place_items, total: order.card.material.items })
              : t('grossanlass.planung.auftraege.materialNone') }}
          </small>
        </div>
        <div class="progress">
          <span>{{ t('grossanlass.planung.auftraege.tasksLabel') }}</span>
          <v-progress-linear :model-value="tasksPercent(order.card) ?? 0" height="6" rounded color="success" />
          <small>
            {{ order.card && order.card.tasks.total
              ? t('grossanlass.planung.auftraege.tasksDetail', { done: order.card.tasks.done, total: order.card.tasks.total })
              : t('grossanlass.planung.auftraege.tasksNone') }}
          </small>
        </div>
        <EButton size="small" variant="secondary" @click="openOrder(order.group)" @dblclick.stop>
          {{ t('grossanlass.aufgaben.open') }}
        </EButton>
      </article>
    </div>

    <GrossanlassOrderDialog
      v-model="dialogOpen"
      :department-id="departmentId"
      :groups="groups"
      :initial-group="dialogGroup"
      :initial-type="dialogType"
      :initial-tab="dialogTab"
      @saved="refreshSoft"
      @changed="refreshSoft"
      @deleted="refreshSoft"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { EButton, ESearchField, ESelect, ESwitch } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassOrderDialog from '@/components/grossanlass/GrossanlassOrderDialog.vue'
import { getGrossanlassGroups, getGrossanlassOrderCards, type GrossanlassGroup, type GrossanlassOrderCard } from '@/api/grossanlassGroups'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import { formatBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'
import { GA_BUILD_STATUSES, gaBuildStatusI18nKey, watchGrossanlassBuildPeriods } from '@/utils/grossanlassBuildStatus'
import {
  STATUS_COLOR,
  TYPE_COLOR,
  TYPE_ICON,
  buildOrderRows,
  emptyOrderFilters,
  materialPercent,
  matchesOrder,
  orderCounts,
  ressortNameOf,
  tasksPercent,
  type GaOrderFilter,
  type GaOrderType,
} from './gaAuftraegeModel'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const departmentId = computed(() => String(route.params.departmentId || ''))
watchGrossanlassBuildPeriods(departmentId)

const groups = ref<GrossanlassGroup[]>([])
const cards = ref<GrossanlassOrderCard[]>([])
const loading = ref(true)
const typeOptions: GaOrderFilter[] = ['all', 'order', 'build']
const filters = reactive(emptyOrderFilters())
const dialogOpen = ref(false)
const dialogGroup = ref<GrossanlassGroup | null>(null)
const dialogType = ref<GaOrderType>('order')
// Bestehende Aufträge öffnen direkt in «Aufgaben & Material», neue starten mit «Grunddaten».
const dialogTab = ref<'basics' | 'tasks'>('basics')

const { canCreateChild, isInAssignedRessortBranch, isInOwnAssignedBranch } = useGrossanlassRessortScope(groups)

/** Alles, was die Rolle sehen darf (MW/CMW: alle Ressorts). */
const visibleGroups = computed(() => groups.value.filter((group) => isInAssignedRessortBranch(group)))
/** Reiner Ansichtsfilter: nur Zweige meiner tatsächlichen Mitgliedschaften (inkl. Mehrfachzugehörigkeit), keine Rechteänderung. */
const mineOnly = ref(false)
/** Archivierte Bauaufträge sind standardmässig ausgeblendet; der Schalter nimmt sie mit. */
const showArchived = ref(false)
watch(showArchived, () => { void refreshSoft() })
const scopedGroups = computed(() =>
  mineOnly.value ? visibleGroups.value.filter((group) => isInOwnAssignedBranch(group)) : visibleGroups.value,
)
const rows = computed(() => buildOrderRows(scopedGroups.value, groups.value, cards.value))
const typeCounts = computed(() => orderCounts(rows.value))
const shown = computed(() => rows.value.filter((row) => matchesOrder(row, filters, groups.value)))
const ressortItems = computed(() => [...new Set(rows.value.map((row) => ressortNameOf(row, groups.value)))].filter(Boolean).sort())
const statusItems = computed(() =>
  GA_BUILD_STATUSES.map((value) => ({ value, title: t(gaBuildStatusI18nKey(value)) })),
)

const canAdd = computed(() => visibleGroups.value.some((group) => canCreateChild(group)))

function openCreate() {
  dialogGroup.value = null
  dialogTab.value = 'basics'
  dialogType.value = filters.type === 'build' ? 'build' : 'order'
  dialogOpen.value = true
}
function openOrder(group: GrossanlassGroup) {
  dialogGroup.value = group
  dialogTab.value = 'tasks'
  dialogOpen.value = true
}
async function refresh() {
  if (!departmentId.value) return
  groups.value = await getGrossanlassGroups(departmentId.value, { includeArchived: showArchived.value })
  // Kennzahlen sind ergänzend: ohne sie bleiben die Karten ohne Fortschritt statt falscher Werte.
  cards.value = await getGrossanlassOrderCards(departmentId.value, { includeArchived: showArchived.value }).catch(() => [])
}
/** Soft-Reload nach Speichern/Löschen: Liste neu laden, ohne Seite oder Dialog zu schliessen. */
async function refreshSoft() {
  try {
    await refresh()
  } catch {
    // Liste bleibt auf altem Stand; der Dialog hat den Fehler bereits gemeldet.
  }
}

onMounted(async () => {
  try {
    await refresh()
  } catch {
    groups.value = []
  } finally {
    loading.value = false
  }
  const projectId = String(route.query.project || '')
  const wantsCreate = String(route.query.create || '') === '1'
  if (projectId) {
    const found = groups.value.find((group) => group.id === projectId)
    if (found) openOrder(found)
  }
  if (wantsCreate && canAdd.value) openCreate()
  if (projectId || wantsCreate) {
    const { project: _project, create: _create, ...rest } = route.query
    void router.replace({ query: rest })
  }
})
</script>

<style scoped>
.orders {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 4px 0 28px;
}
.orders__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}
.orders__intro {
  flex: 1 1 320px;
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
}
.orders__bar {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.orders__mine {
  align-self: flex-start;
}
.orders__types {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.count {
  margin-left: 6px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.1);
  font-size: 0.72rem;
}
.orders__selects {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 10px;
}
.orders__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 12px;
}
.order {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #38bdf8;
  border-radius: 12px;
  background: #fff;
}
.order--build {
  border-left-color: #f97316;
}
.order__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.order__title {
  margin: 0;
  font-size: 1.02rem;
}
.order__crumb {
  margin: 0;
  color: #64748b;
  font-size: 0.82rem;
}
.order__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.86rem;
  color: #334155;
}
.progress {
  display: grid;
  grid-template-columns: 100px minmax(0, 1fr);
  gap: 2px 8px;
  align-items: center;
  font-size: 0.78rem;
  color: #64748b;
}
.progress small {
  grid-column: 2;
}
</style>
