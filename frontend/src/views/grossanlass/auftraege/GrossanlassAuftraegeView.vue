<template>
  <div class="orders">
    <header class="orders__head">
      <p class="orders__intro">
        {{ t('grossanlass.auftraege.intro') }}
        <v-chip size="x-small" variant="tonal" color="warning" class="ml-2">{{ t('grossanlass.auftraege.prototype') }}</v-chip>
      </p>
      <v-menu>
        <template #activator="{ props: menuProps }">
          <EButton v-bind="menuProps" variant="primary">
            <v-icon icon="mdi-plus" start size="18" /> {{ t('grossanlass.auftraege.create.button') }}
          </EButton>
        </template>
        <v-list>
          <v-list-item prepend-icon="mdi-clipboard-text-outline" :title="t('grossanlass.auftraege.create.normal')" :subtitle="t('grossanlass.auftraege.create.typeText.order')" @click="openCreate('order')" />
          <v-list-item prepend-icon="mdi-hammer-wrench" :title="t('grossanlass.auftraege.create.build')" :subtitle="t('grossanlass.auftraege.create.typeText.build')" @click="openCreate('build')" />
        </v-list>
      </v-menu>
    </header>

    <section class="orders__bar">
      <div class="orders__types" role="group" :aria-label="t('grossanlass.auftraege.filterType')">
        <EButton v-for="option in typeOptions" :key="option" size="small" :variant="filters.type === option ? 'primary' : 'secondary'" @click="filters.type = option">
          {{ t(`grossanlass.auftraege.filter.${option}`) }}<span class="count">{{ typeCounts[option] }}</span>
        </EButton>
      </div>
      <div class="orders__selects">
        <ESearchField v-model="filters.search" :label="t('grossanlass.auftraege.search')" />
        <ESelect v-model="filters.ressort" :items="ressortItems" :label="t('grossanlass.auftraege.f.ressort')" clearable hide-details />
        <ESelect v-model="filters.status" :items="statusItems" :label="t('grossanlass.auftraege.f.status')" clearable hide-details />
      </div>
    </section>

    <EEmptyState v-if="!shown.length" variant="generic" icon="mdi-clipboard-text-outline" :title="t('grossanlass.auftraege.emptyTitle')" :description="t('grossanlass.auftraege.emptyText')" />

    <div class="orders__grid">
      <article v-for="order in shown" :key="order.id" class="order" :class="`order--${order.type}`">
        <header class="order__head">
          <v-chip size="small" variant="flat" :color="TYPE_COLOR[order.type]" :prepend-icon="TYPE_ICON[order.type]">{{ t(`grossanlass.auftraege.type.${order.type}`) }}</v-chip>
          <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[order.status]">{{ t(`grossanlass.auftraege.status.${order.status}`) }}</v-chip>
        </header>
        <h4 class="order__title">{{ order.title }}</h4>
        <p class="order__crumb">{{ order.ressort }} · {{ order.bereich }}<template v-if="order.build"> · {{ order.build.project }}</template></p>
        <p class="order__meta"><v-icon icon="mdi-clock-outline" size="14" /> {{ rangeLabel(order.startsAt, order.endsAt, locale) }}</p>
        <p v-if="order.build?.site || order.location" class="order__meta"><v-icon icon="mdi-map-marker-outline" size="14" /> {{ order.build?.site || order.location }}</p>
        <p class="order__meta"><v-icon icon="mdi-account-outline" size="14" /> {{ order.responsible.join(', ') || t('grossanlass.aufgaben.unassigned') }}</p>
        <p v-if="order.helperNeed.length" class="order__meta">
          <v-icon icon="mdi-account-group-outline" size="14" />
          {{ t('grossanlass.auftraege.staffed', { assigned: staffingOf(order).assigned, need: staffingOf(order).need }) }}
        </p>
        <template v-if="order.build">
          <div class="progress"><span>{{ t('grossanlass.auftraege.materialProgress') }}</span><v-progress-linear :model-value="materialProgress(order) ?? 0" height="6" rounded color="primary" /></div>
          <div class="progress"><span>{{ t('grossanlass.auftraege.buildProgress') }}</span><v-progress-linear :model-value="buildProgress(order)" height="6" rounded color="success" /></div>
        </template>
        <div class="order__links">
          <v-chip size="x-small" variant="tonal" prepend-icon="mdi-clipboard-list">{{ order.links.taskIds.length }}</v-chip>
          <v-chip v-if="order.links.packProjectId" size="x-small" variant="tonal" prepend-icon="mdi-package-variant-closed" />
          <v-chip v-if="order.links.dispoNeedIds.length || order.transport" size="x-small" variant="tonal" prepend-icon="mdi-truck-fast-outline">{{ order.links.dispoNeedIds.length }}</v-chip>
        </div>
        <EButton size="small" variant="secondary" @click="openDetail(order.id)">{{ t('grossanlass.aufgaben.open') }}</EButton>
      </article>
    </div>

    <GrossanlassAuftragCreateDialog v-model="createOpen" :initial-type="createType" @created="onCreated" />
    <GrossanlassAuftragDetailDialog v-model="detailOpen" :order-id="detailId" />
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ESearchField, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useToast } from '@/composables/useToast'
import GrossanlassAuftragCreateDialog from './GrossanlassAuftragCreateDialog.vue'
import GrossanlassAuftragDetailDialog from './GrossanlassAuftragDetailDialog.vue'
import {
  buildProgress,
  counts,
  emptyOrderFilters,
  helperStaffing,
  materialProgress,
  matchesOrder,
  useGaAuftraegeMock,
  type GaOrder,
  type GaOrderFilter,
  type GaOrderType,
} from './gaAuftraegeMock'
import { STATUS_COLOR, TYPE_COLOR, TYPE_ICON, rangeLabel } from './gaAuftraegeUi'
import { useGaAufgabenMock } from '@/views/grossanlass/aufgaben/gaAufgabenMock'

const { t, locale } = useI18n()
const toast = useToast()
const { orders } = useGaAuftraegeMock()
const { tasks } = useGaAufgabenMock()

const typeOptions: GaOrderFilter[] = ['all', 'order', 'build']
const filters = reactive(emptyOrderFilters())
const createOpen = ref(false)
const createType = ref<GaOrderType>('order')
const detailOpen = ref(false)
const detailId = ref<string | null>(null)

const typeCounts = computed(() => {
  void orders.value
  return counts()
})
const shown = computed(() => orders.value.filter((order) => matchesOrder(order, filters)))
const ressortItems = computed(() => [...new Set(orders.value.map((order) => order.ressort))].sort())
const statusItems = computed(() => (['draft', 'planned', 'active', 'done'] as const).map((value) => ({ value, title: t(`grossanlass.auftraege.status.${value}`) })))

function staffingOf(order: GaOrder) {
  void tasks.value
  return helperStaffing(order)
}
function openCreate(type: GaOrderType) {
  createType.value = type
  createOpen.value = true
}
function openDetail(id: string) {
  detailId.value = id
  detailOpen.value = true
}
function onCreated(id: string) {
  toast.success(t('grossanlass.auftraege.created'))
  detailId.value = id
  detailOpen.value = true
}
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
  gap: 8px;
  align-items: center;
  font-size: 0.78rem;
  color: #64748b;
}
.order__links {
  display: flex;
  gap: 6px;
}
</style>
