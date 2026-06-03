<template>
  <div class="sandbox-material-dt">
    <EFilterRow class="mb-4">
      <v-col class="e-filter-row__search">
        <ESearchField
          v-model="searchQuery"
          :label="t('devSandbox.materialTableSamples.search')"
        />
      </v-col>
    </EFilterRow>

    <p class="text-caption text-medium-emphasis mb-2">
      {{ display.mdAndUp ? t('devSandbox.materialTableSamples.mainTableLead') : t('devSandbox.materialTableSamples.mobileLead') }}
    </p>

    <!-- Desktop: v-data-table -->
    <div v-if="display.mdAndUp" class="materials-table-wrapper mb-8">
      <v-data-table
        class="sandbox-material-dt__table"
        :headers="headers"
        :items="filteredItems"
        :items-per-page="10"
        item-value="id"
        v-model:expanded="expanded"
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
              <span class="material-name">
                {{ item.name }}
                <span v-if="item.is_js_material" class="source-badge">J&amp;S</span>
                <span v-if="item.is_combo_draft" class="combo-draft-badge">
                  {{ t('materialsView.comboDraftBadge') }}
                </span>
              </span>
              <span v-if="item.manufacturer" class="material-manufacturer">{{ item.manufacturer }}</span>
              <span v-if="item.open_loss_qty > 0" class="loss-reported-badge">
                {{ t('materialsView.lossReported', { qty: item.open_loss_qty }) }}
              </span>
              <span v-if="item.is_combo && item.material_type_label" class="combo-type-badge physical_combo mt-1">
                <span class="combo-type-badge-emoji" aria-hidden="true">📦</span>
                {{ item.material_type_label }}
              </span>
            </div>
          </div>
        </template>

        <template #item.category="{ item }">
          <span v-if="item.category_parent" class="category-tag">
            <span class="category-parent">{{ item.category_parent }} →</span>
            <span class="category-child">{{ item.category_name }}</span>
          </span>
          <span v-else-if="item.category_name" class="category-tag">
            <span class="category-child">{{ item.category_name }}</span>
          </span>
          <span v-else class="no-category">-</span>
        </template>

        <template #item.total_stock="{ item }">
          <span class="stock-value" :class="stockTotalClass(item.total_stock)">
            {{ item.total_stock }}
          </span>
          <span v-if="item.pack_size && item.pack_unit" class="pack-info">
            {{ Math.floor(item.total_stock / item.pack_size) }} {{ item.pack_unit }}
          </span>
        </template>

        <template #item.issued_out="{ item }">
          <span v-if="item.issued_out > 0" class="stock-badge issued">{{ item.issued_out }}</span>
          <span v-else class="stock-zero">–</span>
        </template>

        <template #item.repair_stock="{ item }">
          <span v-if="item.repair_stock > 0" class="stock-badge repair">{{ item.repair_stock }}</span>
          <span v-else class="stock-zero">–</span>
        </template>

        <template #item.available="{ item }">
          <span
            class="stock-badge available"
            :class="{
              low: item.available < 3 && item.total_stock > 0,
              empty: item.available <= 0 && item.total_stock > 0,
            }"
          >
            {{ item.available }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <button
            type="button"
            class="action-btn"
            :title="t('materialsView.titleOpenDetails')"
            @click.stop="openDetail(item)"
          >
            <v-icon icon="mdi-eye-outline" size="16" />
          </button>
        </template>

        <template #expanded-row="{ columns, item }">
          <tr class="combo-components-row">
            <td :colspan="columns.length">
              <div class="combo-components-container">
                <table class="combo-sub-table">
                  <thead>
                    <tr>
                      <th>{{ t('materialsView.subColComponent') }}</th>
                      <th>{{ t('common.serialNumber') }}</th>
                      <th>{{ t('materialsView.subColQty') }}</th>
                      <th>{{ t('materialsView.subColAssignment') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="comp in item.components" :key="comp.id">
                      <td class="comp-name">
                        <span class="comp-link">{{ comp.name }}</span>
                      </td>
                      <td>
                        <span v-if="comp.serial" class="serial-code">{{ comp.serial }}</span>
                        <span v-else class="no-serial">–</span>
                      </td>
                      <td>{{ comp.qty }}</td>
                      <td>
                        <span class="assignment-badge" :class="comp.assignment === 'fixed' ? 'fix' : comp.assignment">
                          {{ comp.assignment_label }}
                        </span>
                      </td>
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
            :title="t('devSandbox.materialTableSamples.noResultsTitle')"
            :description="t('devSandbox.materialTableSamples.noResultsText')"
          />
        </template>
      </v-data-table>

      <p class="table-hint">{{ t('materialsView.tableHint') }}</p>
    </div>

    <!-- Mobile viewport: v-list -->
    <div v-else class="materials-table-wrapper sandbox-material-dt__mobile-live mb-8">
      <MaterialSandboxMobileList
        :items="filteredItems"
        @select="onMobileSelect"
        @detail="openDetail"
      />
      <p class="table-hint">{{ t('devSandbox.materialTableSamples.mobileHint') }}</p>
    </div>

    <!-- Desktop: Mobile-Vorschau (390px) -->
    <div v-if="display.mdAndUp" class="mb-8">
      <p class="text-caption text-medium-emphasis mb-2">
        {{ t('devSandbox.materialTableSamples.mobilePreviewLead') }}
      </p>
      <div class="sandbox-material-dt__mobile-frame">
        <div class="sandbox-material-dt__mobile-frame__label">
          {{ t('devSandbox.materialTableSamples.mobilePreviewLabel') }}
        </div>
        <MaterialSandboxMobileList
          :items="filteredItems"
          @select="onMobileSelect"
          @detail="openDetail"
        />
      </div>
    </div>

    <p class="text-caption text-medium-emphasis mb-2">
      {{ display.mdAndUp ? t('devSandbox.materialTableSamples.comboGroupLead') : t('devSandbox.materialTableSamples.comboGroupMobileLead') }}
    </p>

    <div v-if="display.mdAndUp" class="materials-table-wrapper">
      <v-data-table
        class="sandbox-material-dt__table sandbox-material-dt__table--grouped"
        :headers="comboGroupHeaders"
        :items="comboGroupItems"
        :group-by="comboGroupBy"
        item-value="id"
        hover
        hide-default-footer
      >
        <template #group-summary="{ item, columns }">
          <tr class="sandbox-material-dt__group-summary">
            <td :colspan="2" class="font-weight-medium">
              {{ t('devSandbox.materialTableSamples.groupSummaryLabel') }}
            </td>
            <td class="text-center">
              <span class="stock-value">{{ sumGroupField(item, 'total_stock') }}</span>
            </td>
            <td class="text-center">
              <span class="stock-badge available">{{ sumGroupField(item, 'available') }}</span>
            </td>
          </tr>
        </template>

        <template #item.name="{ item }">
          <span class="material-name">{{ item.name }}</span>
        </template>

        <template #item.material_type="{ item }">
          <span class="combo-type-badge" :class="item.material_type">
            {{ item.material_type_label }}
          </span>
        </template>

        <template #item.total_stock="{ item }">
          <span class="stock-value">{{ item.total_stock }}</span>
        </template>

        <template #item.available="{ item }">
          <span class="stock-badge available">{{ item.available }}</span>
        </template>
      </v-data-table>
    </div>

    <div v-else class="materials-table-wrapper sandbox-material-dt__combo-mobile">
      <div
        v-for="group in comboMobileGroups"
        :key="group.key"
        class="sandbox-material-dt__combo-mobile-group"
      >
        <v-list-subheader class="sandbox-material-dt__combo-mobile-subheader">
          {{ group.label }} ({{ group.items.length }})
        </v-list-subheader>
        <v-list density="compact" class="sandbox-material-mobile-list">
          <v-list-item
            v-for="item in group.items"
            :key="item.id"
            @click="onMobileSelect(item)"
          >
            <v-list-item-title class="material-name">{{ item.name }}</v-list-item-title>
            <template #append>
              <span class="stock-value">{{ item.total_stock }}</span>
              <span class="stock-badge available ms-2">{{ item.available }}</span>
            </template>
          </v-list-item>
        </v-list>
        <div class="sandbox-material-dt__combo-mobile-summary">
          <span>{{ t('devSandbox.materialTableSamples.groupSummaryLabel') }}</span>
          <span>
            {{ t('materialsView.colTotal') }} {{ group.totalStock }}
            · {{ t('materialsView.colAvailable') }} {{ group.available }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="display.mdAndUp" class="mt-6">
      <p class="text-caption text-medium-emphasis mb-2">
        {{ t('devSandbox.materialTableSamples.comboMobilePreviewLead') }}
      </p>
      <div class="sandbox-material-dt__mobile-frame">
        <div class="sandbox-material-dt__mobile-frame__label">
          {{ t('devSandbox.materialTableSamples.comboMobilePreviewLabel') }}
        </div>
        <div class="sandbox-material-dt__combo-mobile">
          <div
            v-for="group in comboMobileGroups"
            :key="`preview-${group.key}`"
            class="sandbox-material-dt__combo-mobile-group"
          >
            <v-list-subheader class="sandbox-material-dt__combo-mobile-subheader">
              {{ group.label }} ({{ group.items.length }})
            </v-list-subheader>
            <v-list density="compact" class="sandbox-material-mobile-list">
              <v-list-item v-for="item in group.items" :key="item.id">
                <v-list-item-title class="material-name">{{ item.name }}</v-list-item-title>
                <template #append>
                  <span class="stock-badge available">{{ item.available }}</span>
                </template>
              </v-list-item>
            </v-list>
            <div class="sandbox-material-dt__combo-mobile-summary">
              <span>{{ t('devSandbox.materialTableSamples.groupSummaryLabel') }}</span>
              <span>{{ group.totalStock }} / {{ group.available }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p v-if="lastClicked" class="text-caption text-medium-emphasis mt-3 mb-0">
      {{ t('devSandbox.materialTableSamples.lastClick', { name: lastClicked }) }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import EFilterRow from '@/components/layout/EFilterRow.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { ESearchField } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import MaterialSandboxMobileList from '@/views/dev/MaterialSandboxMobileList.vue'
import type { GroupSummaryLike, SandboxComboComponent, SandboxMaterialRow } from '@/views/dev/materialSandboxTypes'
import '@/styles/ui/tables.css'
import '@/styles/materials-view.css'
import '@/styles/components/sandbox-material-data-table.css'

defineOptions({ name: 'MaterialDataTableSandboxDemo' })

const { t } = useI18n()
const toast = useToast()
const display = useDisplay()

const searchQuery = ref('')
const lastClicked = ref('')
const expanded = ref<string[]>([])

const comboComponents: SandboxComboComponent[] = [
  {
    id: 'c1',
    name: 'Spiritus-Kocher',
    serial: 'KW-001',
    qty: 1,
    assignment: 'fixed',
    assignment_label: 'Fix',
  },
  {
    id: 'c2',
    name: 'Kochtopf 5L',
    serial: null,
    qty: 1,
    assignment: 'pool',
    assignment_label: 'Pool',
  },
  {
    id: 'c3',
    name: 'Windscreen',
    serial: 'WS-12',
    qty: 2,
    assignment: 'pool',
    assignment_label: 'Pool',
  },
]

const sampleItems: SandboxMaterialRow[] = [
  {
    id: '1',
    name: 'Zelt Trigon 6+2',
    manufacturer: 'Hajk',
    category_parent: 'Camp',
    category_name: 'Zelte',
    total_stock: 12,
    pack_size: 1,
    pack_unit: 'Stk',
    issued_out: 3,
    repair_stock: 1,
    available: 8,
  },
  {
    id: '2',
    name: 'Kühlbox 40L',
    manufacturer: 'Igloo',
    is_container: true,
    is_js_material: true,
    category_name: 'Küche',
    total_stock: 6,
    issued_out: 2,
    repair_stock: 0,
    available: 4,
  },
  {
    id: '3',
    name: 'Kochset Combo «Patrouille»',
    is_combo: true,
    material_type: 'physical_combo',
    material_type_label: 'Physische Combo',
    category_parent: 'Küche',
    category_name: 'Kocher',
    total_stock: 2,
    issued_out: 0,
    repair_stock: 0,
    available: 2,
    components: comboComponents,
  },
  {
    id: '4',
    name: 'Seil 10 m',
    manufacturer: 'Mammut',
    category_name: 'Seile',
    total_stock: 5,
    open_loss_qty: 1,
    issued_out: 1,
    repair_stock: 2,
    available: 2,
  },
  {
    id: '5',
    name: 'Gaskartusche 230g',
    is_consumable: true,
    category_name: 'Verbrauch',
    total_stock: 48,
    pack_size: 12,
    pack_unit: 'Pkg',
    issued_out: 12,
    repair_stock: 0,
    available: 36,
  },
]

const comboGroupItems: SandboxMaterialRow[] = [
  {
    id: 'g1',
    name: 'Kochset Combo «Patrouille»',
    material_type: 'physical_combo',
    material_type_label: 'Physische Combo',
    total_stock: 2,
    issued_out: 0,
    repair_stock: 0,
    available: 2,
  },
  {
    id: 'g2',
    name: 'Zelttasche Set 4P',
    material_type: 'physical_combo',
    material_type_label: 'Physische Combo',
    total_stock: 4,
    issued_out: 1,
    repair_stock: 0,
    available: 3,
  },
  {
    id: 'g3',
    name: 'Packliste Sommerlager',
    material_type: 'virtual_combo',
    material_type_label: 'Virtuelle Combo',
    total_stock: 1,
    issued_out: 0,
    repair_stock: 0,
    available: 1,
  },
  {
    id: 'g4',
    name: 'Packliste Winterlager',
    material_type: 'virtual_combo',
    material_type_label: 'Virtuelle Combo',
    total_stock: 1,
    issued_out: 0,
    repair_stock: 0,
    available: 1,
  },
]

const comboGroupBy = [{ key: 'material_type', order: 'asc' as const }]

const headers = computed(() => [
  { title: t('common.name'), key: 'name', sortable: true, minWidth: '260px' },
  { title: t('materialsView.colCategory'), key: 'category', sortable: false },
  { title: t('materialsView.colTotal'), key: 'total_stock', sortable: true, align: 'center' as const, width: '90px' },
  { title: t('materialsView.colIssuedOut'), key: 'issued_out', sortable: true, align: 'center' as const, width: '80px' },
  { title: t('materialsView.colRepair'), key: 'repair_stock', sortable: true, align: 'center' as const, width: '80px' },
  { title: t('materialsView.colAvailable'), key: 'available', sortable: true, align: 'center' as const, width: '90px' },
  { title: '', key: 'actions', sortable: false, align: 'end' as const, width: '72px' },
])

const comboGroupHeaders = computed(() => [
  { title: t('common.name'), key: 'name' },
  { title: t('materialsView.colType'), key: 'material_type' },
  { title: t('materialsView.colTotal'), key: 'total_stock', align: 'center' as const },
  { title: t('materialsView.colAvailable'), key: 'available', align: 'center' as const },
])

const filteredItems = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return sampleItems
  return sampleItems.filter(
    (row) =>
      row.name.toLowerCase().includes(q) ||
      row.manufacturer?.toLowerCase().includes(q) ||
      row.category_name?.toLowerCase().includes(q),
  )
})

const comboMobileGroups = computed(() => {
  const map = new Map<string, SandboxMaterialRow[]>()
  for (const item of comboGroupItems) {
    const key = item.material_type ?? 'other'
    const list = map.get(key) ?? []
    list.push(item)
    map.set(key, list)
  }
  return [...map.entries()].map(([key, items]) => ({
    key,
    label: items[0]?.material_type_label ?? key,
    items,
    totalStock: items.reduce((sum, row) => sum + row.total_stock, 0),
    available: items.reduce((sum, row) => sum + row.available, 0),
  }))
})

function iconClass(item: SandboxMaterialRow) {
  if (item.is_container) return 'container'
  if (item.is_combo) return 'container'
  return undefined
}

function iconName(item: SandboxMaterialRow) {
  if (item.is_container) return 'mdi-package-variant-closed'
  if (item.is_combo) return 'mdi-triangle-outline'
  if (item.is_food) return 'mdi-coffee'
  if (item.is_consumable) return 'mdi-minus-circle-outline'
  return 'mdi-cube-outline'
}

function stockTotalClass(total: number) {
  if (total <= 0) return 'empty'
  if (total < 3) return 'low'
  return undefined
}

function sumGroupField(summary: GroupSummaryLike, field: 'total_stock' | 'available') {
  return summary.items.reduce((sum, entry) => {
    const raw = entry.raw ?? (entry as unknown as SandboxMaterialRow)
    return sum + (raw[field] ?? 0)
  }, 0)
}

function onRowClick(_event: Event, { item }: { item: SandboxMaterialRow }) {
  lastClicked.value = item.name
}

function onMobileSelect(item: SandboxMaterialRow) {
  lastClicked.value = item.name
}

function openDetail(item: SandboxMaterialRow) {
  lastClicked.value = item.name
  toast.info(t('devSandbox.materialTableSamples.detailToast', { name: item.name }), 4000)
}
</script>
