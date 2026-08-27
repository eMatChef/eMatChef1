<template>
  <div class="ga-preview-table-block">
    <MaterialJourneyScanBar
      v-model="searchQuery"
      :loading="loading"
      :session-log="[]"
      :suggestions="scanSuggestions"
      label-key="grossanlass.materials.scanLabel"
      placeholder-key="grossanlass.materials.scanPlaceholder"
      @submit="onScanSubmit"
      @clear="searchQuery = ''"
      @select-suggestion="onSelectSuggestion"
    />

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />

    <div v-else-if="display.mdAndUp" class="materials-table-wrapper mt-4">
      <v-data-table
        class="material-list-dt__table"
        :headers="headers"
        :items="filteredItems"
        :items-per-page="-1"
        item-value="id"
        v-model:expanded="expanded"
        :row-props="rowProps"
        show-expand
        hover
        hide-default-footer
        @click:row="onRowClick"
      >
        <template #item.data-table-expand="{ item, internalItem, isExpanded, toggleExpand }">
          <v-btn
            v-if="item.components?.length"
            icon
            variant="text"
            size="small"
            density="compact"
            :aria-expanded="isExpanded(internalItem)"
            :aria-label="t('materialsView.expandComboTitle')"
            @click.stop="toggleExpand(internalItem)"
            @dblclick.stop
          >
            <v-icon
              :icon="isExpanded(internalItem) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
              size="20"
            />
          </v-btn>
        </template>

        <template #item.name="{ item }">
          <div class="name-cell">
            <div class="material-icon" :class="iconClass(item)">
              <v-icon :icon="iconName(item)" size="20" />
            </div>
            <div class="name-info">
              <span class="material-name">{{ item.name }}</span>
              <span class="material-manufacturer">{{ item.barcode }}</span>
              <span class="combo-type-badge" :class="lifecycleBadgeClass(item.lifecycle)">
                {{ lifecycleLabel(item.lifecycle) }}
              </span>
            </div>
          </div>
        </template>

        <template #item.total_stock="{ item }">
          <span class="stock-value">{{ item.total_stock }}</span>
        </template>
        <template #item.issued_out="{ item }">
          <span v-if="item.issued_out > 0" class="stock-badge issued">{{ item.issued_out }}</span>
          <span v-else class="stock-zero">–</span>
        </template>
        <template #item.available="{ item }">
          <span class="stock-badge available">{{ item.available }}</span>
        </template>
        <template #item.source="{ item }">
          {{ item.source || '–' }}
        </template>
        <template #item.validFrom="{ item }">
          {{ item.validFrom || '–' }}
        </template>
        <template #item.validTo="{ item }">
          {{ item.validTo || '–' }}
        </template>
        <template #item.plate="{ item }">
          {{ item.plate || '–' }}
        </template>
        <template #item.vehicleStatus="{ item }">
          {{ item.vehicleStatus || '–' }}
        </template>
        <template #item.location="{ item }">
          {{ item.location || '–' }}
        </template>

        <template #expanded-row="{ columns, item }">
          <tr class="combo-components-row">
            <td :colspan="columns.length">
              <div class="combo-components-container">
                <table class="combo-sub-table">
                  <thead>
                    <tr>
                      <th>{{ t('grossanlass.materialUebersicht.colWho') }}</th>
                      <th>{{ t('grossanlass.materialUebersicht.colRessort') }}</th>
                      <th>{{ t('grossanlass.materialUebersicht.colWhen') }}</th>
                      <th>{{ t('materialsView.subColQty') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="comp in item.components" :key="comp.id">
                      <td class="comp-name">{{ comp.name }}</td>
                      <td>
                        <span class="assignment-badge" :class="comp.assignment === 'fixed' ? 'fix' : 'bulk'">
                          {{ comp.assignment_label }}
                        </span>
                      </td>
                      <td>
                        <span v-if="comp.serial" class="serial-code">{{ comp.serial }}</span>
                        <span v-else class="no-serial">–</span>
                      </td>
                      <td>{{ comp.qty }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </td>
          </tr>
        </template>

        <template #no-data>
          <EEmptyState
            variant="search"
            compact
            :title="t('materialsView.noResultsTitle')"
            :description="t('materialsView.noResultsDescription')"
          />
        </template>
      </v-data-table>
      <p class="table-hint">{{ t('grossanlass.materials.tableHint') }}</p>
    </div>

    <div v-else class="materials-table-wrapper mt-4">
      <MaterialSandboxMobileList :items="filteredItems" @detail="openDetail" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import MaterialJourneyScanBar, {
  type MaterialJourneyScanSuggestion,
} from '@/components/activities/materialJourney/MaterialJourneyScanBar.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import MaterialSandboxMobileList from '@/views/dev/MaterialSandboxMobileList.vue'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { gaUebersichtKey } from '@/views/grossanlass/gaUebersicht'
import {
  findPreviewRowByCode,
  searchPreviewRows,
  type GaLifecycle,
  type GaMaterialsTabId,
  type GaPreviewRow,
} from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import '@/styles/ui/tables.css'
import '@/styles/ui/storage.css'
import '@/styles/materials-view.css'
import '@/styles/components/material-list-data-table.css'

const props = defineProps<{
  tab: GaMaterialsTabId
}>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const display = useDisplay()
const searchQuery = ref('')
const expanded = ref<string[]>([])
let clickTimer: ReturnType<typeof setTimeout> | null = null

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const { loading, rows } = useGaCommitmentCatalog()
const uebersicht = inject(gaUebersichtKey, null)

const tabItems = computed(() => {
  const issued = uebersicht?.data.value?.issued_by_object ?? {}
  return rows.value
    .filter((row) => row.tabs.includes(props.tab))
    .map((row) => {
      const out = issued[row.id] ?? row.issued_out
      const available = row.releasedForEinsatz === false
        ? 0
        : Math.max(0, row.total_stock - out)
      return { ...row, issued_out: out, available }
    })
})

const filteredItems = computed(() => searchPreviewRows(tabItems.value, searchQuery.value))

const scanSuggestions = computed<MaterialJourneyScanSuggestion[]>(() =>
  tabItems.value.map((row) => ({
    id: row.id,
    label: row.name,
    subtitle: row.barcode,
    categoryName: row.category_name ?? null,
  })),
)

const headers = computed(() => {
  const nameCol = { title: t('common.name'), key: 'name', sortable: true, minWidth: '260px' }
  if (props.tab === 'leihweise') {
    return [
      nameCol,
      { title: t('grossanlass.materials.colSource'), key: 'source', sortable: true },
      { title: t('grossanlass.materials.colFrom'), key: 'validFrom', sortable: false },
      { title: t('grossanlass.materials.colTo'), key: 'validTo', sortable: false },
    ]
  }
  if (props.tab === 'fahrzeuge') {
    return [
      nameCol,
      { title: t('grossanlass.materials.colPlate'), key: 'plate', sortable: true },
      { title: t('grossanlass.materials.colStatus'), key: 'vehicleStatus', sortable: true },
    ]
  }
  if (props.tab === 'eigen') {
    return [
      nameCol,
      { title: t('grossanlass.materials.colLocation'), key: 'location', sortable: true },
      { title: t('materialsView.colTotal'), key: 'total_stock', sortable: true, align: 'center' as const, width: '90px' },
      { title: t('materialsView.colAvailable'), key: 'available', sortable: true, align: 'center' as const, width: '90px' },
    ]
  }
  return [
    nameCol,
    { title: t('materialsView.colTotal'), key: 'total_stock', sortable: true, align: 'center' as const, width: '90px' },
    { title: t('materialsView.colIssuedOut'), key: 'issued_out', sortable: true, align: 'center' as const, width: '90px' },
    { title: t('materialsView.colAvailable'), key: 'available', sortable: true, align: 'center' as const, width: '90px' },
  ]
})

function toggleExpanded(id: string) {
  if (expanded.value.includes(id)) {
    expanded.value = expanded.value.filter((itemId) => itemId !== id)
    return
  }
  expanded.value = [...expanded.value, id]
}

function openDetail(item: { id: string }) {
  const id = departmentId.value
  if (!id) return
  void router.push({
    path: `/${id}/materialien/artikel/${item.id}`,
    query: { from: props.tab },
  })
}

function rowProps({ item }: { item: GaPreviewRow }) {
  return {
    onDblclick: (event: MouseEvent) => {
      event.preventDefault()
      if (clickTimer) {
        clearTimeout(clickTimer)
        clickTimer = null
      }
      openDetail(item)
    },
  }
}

function onRowClick(_event: Event, { item }: { item: GaPreviewRow }) {
  if (clickTimer) return
  clickTimer = setTimeout(() => {
    clickTimer = null
    if (!item.components?.length) return
    toggleExpanded(item.id)
  }, 280)
}

onUnmounted(() => {
  if (clickTimer) clearTimeout(clickTimer)
})

function expandMatch(query: string) {
  const hit = findPreviewRowByCode(tabItems.value, query) ?? filteredItems.value[0]
  if (hit) expanded.value = [hit.id]
}

function onScanSubmit() {
  expandMatch(searchQuery.value)
}

function onSelectSuggestion(item: MaterialJourneyScanSuggestion) {
  const row = tabItems.value.find((r) => r.id === item.id)
  searchQuery.value = row?.barcode || item.label
  expanded.value = [item.id]
}

function lifecycleLabel(kind: GaLifecycle): string {
  return t(`grossanlass.materials.lifecycle.${kind}`)
}

function lifecycleBadgeClass(kind: GaLifecycle): string {
  if (kind === 'loan' || kind === 'cut_consumable') return 'virtual_combo'
  return 'physical_combo'
}

function iconClass(item: GaPreviewRow) {
  if (item.is_consumable) return 'consumable'
  if (item.is_combo) return 'container'
  return undefined
}

function iconName(item: GaPreviewRow) {
  if (item.is_consumable) return 'mdi-minus-circle-outline'
  if (item.tabs.includes('fahrzeuge')) return 'mdi-truck-outline'
  if (item.lifecycle === 'loan') return 'mdi-handshake-outline'
  if (item.is_combo) return 'mdi-triangle-outline'
  return 'mdi-cube-outline'
}
</script>

<style scoped>
.ga-preview-table-block { padding: 0 0 8px; }
.mt-4 { margin-top: 16px; }
.name-info .combo-type-badge { margin-top: 4px; }
</style>
