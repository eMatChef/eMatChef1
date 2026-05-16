<template>
  <div class="storage-overview-tab">
    <div v-if="isLoading" class="storage-loading">
      <div class="spinner"></div>
      <p>{{ t('settings.storage.loadingOverview') }}</p>
    </div>

    <div v-else-if="error" class="storage-error">
      <p>{{ error }}</p>
      <button class="btn-outline" @click="load">{{ t('common.retry') }}</button>
    </div>

    <div
      v-else-if="(!filteredOverview || filteredOverview.racks.length === 0) && !comboViaPhysicalBlocks.length"
      class="storage-empty"
    >
      <template v-if="containerBatchId">
        <p>{{ t('settings.storage.overviewEmptyBatchTitle') }}</p>
        <p class="hint">{{ t('settings.storage.overviewEmptyBatchHint') }}</p>
      </template>
      <template v-else-if="materialId">
        <p>{{ t('settings.storage.overviewEmptyMaterialTitle') }}</p>
        <p class="hint">{{ t('settings.storage.overviewEmptyMaterialHint') }}</p>
      </template>
      <template v-else>
        <p>{{ t('settings.storage.overviewEmptyStructureTitle') }}</p>
        <p class="hint">{{ t('settings.storage.overviewEmptyStructureHint') }}</p>
      </template>
    </div>

    <div v-else class="storage-main">
      <div v-if="comboViaPhysicalBlocks.length" class="combo-derived-storage">
        <p class="combo-derived-intro">
          <strong>{{ t('settings.storage.overviewComboIntroStrong') }}</strong>
          {{ t('settings.storage.overviewComboIntroRest') }}
        </p>
        <div
          v-for="(block, bi) in comboViaPhysicalBlocks"
          :key="block.combo_component_id || `${block.parent_material_id}-${bi}`"
          class="combo-derived-card"
        >
          <div class="combo-derived-card-head">
            <router-link
              class="combo-derived-parent-link"
              :to="`/${departmentId}/materials/${block.parent_material_id}`"
            >
              {{ block.parent_name }}
            </router-link>
            <span class="combo-derived-qty">
              {{ t('settings.storage.overviewComboQtyInCombo', { qty: block.component_qty }) }}
              <template v-if="block.stored_qty_in_container != null">
                ·
                {{
                  block.stored_qty_in_container > 0
                    ? t('settings.storage.overviewComboStoredInCrate', { qty: block.stored_qty_in_container })
                    : t('settings.storage.overviewComboNotInCrate')
                }}
              </template>
            </span>
          </div>
          <ul class="combo-derived-loc-list">
            <li v-for="(loc, idx) in block.locations" :key="`${block.combo_component_id || block.parent_material_id}-${idx}`">
              {{ formatComboLocationLine(loc) }}
            </li>
          </ul>
        </div>
      </div>

      <div v-if="filteredOverview && filteredOverview.racks.length > 0" class="storage-tree">
      <div v-if="containerBatchId" class="storage-context-hint">
        {{ t('settings.storage.overviewSelectedBatchHint') }}
      </div>
      <div v-for="location in locationNodes" :key="location.id" class="storage-location">
        <div class="location-header">
          <button class="location-toggle-btn" @click="toggleLocation(location.id)">
            <span class="tree-caret" :class="{ expanded: expandedLocations.has(location.id) }">▶</span>
            <span class="location-name">{{ location.name }}</span>
            <span v-if="location.isPrimary" class="location-primary-badge">{{ t('settings.storage.primaryStorage') }}</span>
            <span class="location-count">{{ t('settings.storage.rackCount', location.racks.length) }}</span>
          </button>
          <div class="location-actions" v-if="!readonly">
            <StorageCrudActions
              v-if="location.addressId"
              :show-edit="false"
              :show-delete="false"
              @add="addRackForLocation(location)"
            />
          </div>
        </div>

        <div v-if="expandedLocations.has(location.id)" class="location-racks">
          <div v-for="rack in location.racks" :key="rack.id" class="storage-rack">
            <div class="rack-header">
              <button class="rack-toggle-btn" @click="toggleRack(rack.id)">
                <span class="tree-caret" :class="{ expanded: expandedRacks.has(rack.id) }">▶</span>
                <span class="rack-name">{{ rack.name }}</span>
                <span class="rack-slot-count">{{ t('settings.storage.slotsMeta', rack.slots.length) }}</span>
              </button>
              <div class="rack-actions" v-if="!readonly">
                <StorageCrudActions
                  :show-edit="false"
                  :show-add="true"
                  :show-delete="canDeleteRack(rack)"
                  @add="addSlotForRack(rack, location.id)"
                  @delete="deleteRackFromOverview(rack, location.id)"
                />
              </div>
            </div>

            <div v-if="expandedRacks.has(rack.id)" class="rack-slots">
              <div
                v-for="slot in rack.slots"
                :key="slot.id ?? 'rack-level'"
                class="storage-slot"
              >
                <div class="slot-header">
                  <span class="slot-name">{{ slot.name }}</span>
                  <StorageCrudActions
                    v-if="slot.id && slot.contents.length === 0"
                    size="sm"
                    :show-edit="false"
                    :show-add="false"
                    :show-delete="true"
                    @delete="deleteSlotFromOverview(rack, slot, location.id)"
                  />
                </div>
                <div v-if="slot.contents.length === 0" class="slot-empty">
                  {{ t('settings.storage.slotEmpty') }}
                </div>
                <ul v-else class="slot-contents">
                  <li
                    v-for="(row, idx) in slot.displayRows"
                    :key="slotRowKey(row, idx)"
                    class="content-item"
                    :class="{ 'content-item--container': row.type === 'container-group' || (row.type === 'single' && isContainerStoredItem(row.item)) }"
                  >
                    <template v-if="row.type === 'container-group'">
                      <div class="content-main">
                        <div class="container-head">
                          <span class="container-label">{{ row.container_label }}</span>
                        </div>
                        <button
                          v-if="useContainerContentCollapse && containerGroupItemCount(row) > 0"
                          type="button"
                          class="container-expand-trigger"
                          :aria-expanded="isContainerGroupExpanded(row.container_batch_id)"
                          :aria-label="t('settings.storage.ariaToggleContainer')"
                          @click.stop="toggleContainerGroup(row.container_batch_id)"
                        >
                          <span class="toggle-icon" aria-hidden="true">
                            {{ isContainerGroupExpanded(row.container_batch_id) ? '▼' : '▶' }}
                          </span>
                          <span class="container-collapsed-hint">
                            {{
                              containerGroupUsesCompositionList(row)
                                ? t('settings.storage.overviewContainerComponentCount', { count: containerGroupItemCount(row) })
                                : t('settings.storage.overviewContainerItemCount', { count: containerGroupItemCount(row) })
                            }}
                          </span>
                        </button>
                        <div
                          v-if="!useContainerContentCollapse || isContainerGroupExpanded(row.container_batch_id)"
                          class="container-contents-body"
                        >
                          <p
                            v-if="containerGroupUsesCompositionList(row)"
                            class="container-composition-note"
                          >
                            {{ t('settings.storage.overviewContainerCompositionNote') }}
                          </p>
                          <div
                            v-for="line in containerGroupVisibleLines(row)"
                            :key="line.lineKey || line.material_id"
                            class="container-preview-line"
                          >
                            <span class="container-article">{{ line.material_name }}</span>
                            <span class="container-preview-qty">
                              {{ t('settings.storage.overviewLineQty', { qty: line.qty }) }}
                              <template v-if="line.storedQty !== undefined">
                                <span v-if="line.storedQty > 0" class="container-stored-tag">
                                  · {{ t('settings.storage.overviewStoredInCrateQty', { qty: line.storedQty }) }}
                                </span>
                                <span v-else class="container-stored-tag container-stored-tag--none">
                                  · {{ t('settings.storage.overviewNotInCrate') }}
                                </span>
                              </template>
                            </span>
                          </div>
                          <span
                            v-if="!useContainerContentCollapse && row.moreCount > 0"
                            class="container-more-ellipsis"
                            :title="t('settings.storage.overviewMoreInContainer', { count: row.moreCount })"
                          >…</span>
                        </div>
                        <button
                          v-if="canOpenContainerMaterial(row.representative) && !embeddedDetailMaterialId"
                          class="container-link-btn"
                          @click.stop="openContainerMaterialFromStoredItem(row.representative)"
                        >
                          {{ t('settings.storage.openContainer') }}
                        </button>
                      </div>
                      <div class="content-item-right">
                        <span class="content-qty">{{ containerGroupQtyLabel(row) }}</span>
                        <div class="content-actions" v-if="showContainerGroupActionsBar(row)">
                          <StorageActionButton
                            v-if="canMoveContainerGroup(row)"
                            :title="t('settings.storage.moveQuantity')"
                            size="sm"
                            icon="move"
                            @click.stop="openMoveForContainerGroup(row, rack, slot)"
                          />
                          <StorageActionButton
                            v-if="canOpenContainerMaterial(row.representative) && showContainerOpenUi()"
                            :title="t('settings.storage.openContainer')"
                            size="sm"
                            icon="open"
                            @click.stop="navigateToContainerContentTab(row.container_batch_id)"
                          />
                        </div>
                      </div>
                    </template>
                    <template v-else>
                      <div class="content-main">
                        <template v-if="isContainerStoredItem(row.item)">
                          <span class="container-label">{{ getContainerDisplayLabel(row.item) }}</span>
                          <span class="container-article">{{ row.item.material_name }}</span>
                          <span v-if="getContainerOtherItemsCount(row.item) > 0" class="container-more">
                            {{ t('settings.storage.overviewMoreInContainer', { count: getContainerOtherItemsCount(row.item) }) }}
                          </span>
                          <button
                            v-if="canOpenContainerMaterial(row.item) && !embeddedDetailMaterialId"
                            class="container-link-btn"
                            @click.stop="openContainerMaterialFromStoredItem(row.item)"
                          >
                            {{ t('settings.storage.openContainer') }}
                          </button>
                        </template>
                        <template v-else>
                          <template v-if="row.item.container_label">
                            <span class="content-label">{{ row.item.container_label }}</span>
                            <span class="content-material-name">{{ row.item.material_name }}</span>
                          </template>
                          <template v-else>
                            <span class="content-name">{{ row.item.material_name }}</span>
                          </template>
                        </template>
                      </div>
                      <span class="content-qty">{{ t('settings.storage.overviewLineQty', { qty: row.item.qty }) }}</span>
                      <div
                        class="content-actions"
                        v-if="
                          !readonly ||
                          allowMoveActions ||
                          allowOpenActions ||
                          (embeddedDetailMaterialId &&
                            isContainerStoredItem(row.item) &&
                            canOpenContainerMaterial(row.item))
                        "
                      >
                        <StorageActionButton
                          v-if="
                            rowAllowsMoveForStoredItem(row.item) &&
                            (!row.mergedSourceCount || row.mergedSourceCount <= 1)
                          "
                          :title="t('settings.storage.moveQuantity')"
                          size="sm"
                          icon="move"
                          @click.stop="openMoveForItem(row.item, rack, slot)"
                        />
                        <StorageActionButton
                          v-if="
                            isContainerStoredItem(row.item) &&
                            canOpenContainerMaterial(row.item) &&
                            showContainerOpenUi()
                          "
                          :title="t('settings.storage.openContainer')"
                          size="sm"
                          icon="open"
                          @click.stop="navigateToContainerContentTab(row.item.container_batch_id!)"
                        />
                        <StorageActionButton
                          v-else-if="
                            !isContainerStoredItem(row.item) &&
                            (!readonly || allowOpenActions) &&
                            String(row.item.material_id || '').trim() !==
                              String((embeddedDetailMaterialId || '').trim())
                          "
                          :title="t('settings.storage.openMaterial')"
                          size="sm"
                          icon="open"
                          @click.stop="openMaterial(row.item)"
                        />
                      </div>
                    </template>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>

    <MoveQuantityModal
      v-if="showMoveModal && moveContext"
      :material-id="moveContext.materialId"
      :department-id="departmentId"
      :batch="moveContext.batch"
      :initial-from-allocation-id="moveContext.sourceAllocationId"
      :source-rack-id="moveContext.sourceRackId"
      :source-slot-id="moveContext.sourceSlotId"
      @close="showMoveModal = false; moveContext = null"
      @saved="handleMoveSaved"
    />

    <StorageBulkCreateModal
      :is-open="showRackCreateModal"
      :title="t('settings.storage.newRack')"
      :context-text="rackCreateLocation ? t('settings.storage.overviewLocationContext', { name: rackCreateLocation.name }) : ''"
      :base-label="t('settings.storage.bulkLabelBaseRacks')"
      :base-placeholder="t('settings.storage.bulkPlaceholderRackShort')"
      :suggestions="rackCreateSuggestions"
      :base-name="rackCreateBaseName"
      :pair-field-label="t('settings.storage.bulkLabelPairPerRack')"
      :pair-field-placeholder="t('settings.storage.slotPlaceholder')"
      :pair-items="rackGeneratedSlotPairs"
      :count-label="t('settings.storage.bulkLabelCountRacks')"
      :count="rackCreateCount"
      :preview-label="t('settings.storage.bulkPreviewRacks')"
      :generated-names="rackGeneratedNames"
      :save-disabled="rackGeneratedNames.length === 0 || rackGeneratedSlotPairs.some((pair) => !pair.rightValue.trim()) || isSubmittingAction"
      :is-saving="isSubmittingAction"
      @close="closeRackCreateModal"
      @save="createRackFromModal"
      @increment="incrementRackCreateCount"
      @decrement="decrementRackCreateCount"
      @update:baseName="rackCreateBaseName = $event"
      @update:pairItemValue="updateRackPairItemValue"
    />

    <StorageBulkCreateModal
      :is-open="showSlotCreateModal"
      :title="t('settings.storage.newSlot')"
      :context-text="slotCreateRack ? t('settings.storage.rackContext', { name: slotCreateRack.name }) : ''"
      :base-label="t('settings.storage.bulkLabelBaseSlots')"
      :base-placeholder="t('settings.storage.bulkPlaceholderSlotShort')"
      :suggestions="slotCreateSuggestions"
      :base-name="slotCreateBaseName"
      :count-label="t('settings.storage.bulkLabelCountSlots')"
      :count="slotCreateCount"
      :preview-label="t('settings.storage.bulkPreviewSlots')"
      :generated-names="slotGeneratedNames"
      :save-disabled="slotGeneratedNames.length === 0 || isSubmittingAction"
      :is-saving="isSubmittingAction"
      @close="closeSlotCreateModal"
      @save="createSlotFromModal"
      @increment="incrementSlotCreateCount"
      @decrement="decrementSlotCreateCount"
      @update:baseName="slotCreateBaseName = $event"
    />

    <StorageConfirmModal
      :is-open="showDeleteConfirmModal && !!deleteTarget"
      :title="deleteModalTitle"
      :message="deleteModalMessage"
      :is-loading="isSubmittingAction"
      @close="closeDeleteConfirmModal"
      @confirm="executeDeleteTarget"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getStorageOverview,
  getContainerBatches,
  getContainerBatchContents,
  createStorageRack,
  createStorageSlot,
  deleteStorageRack,
  deleteStorageSlot,
  type ContainerBatch,
  type StorageOverviewResponse,
  type StorageOverviewRack,
  type StorageOverviewSlot,
  type StorageSlotContent
} from '@/api/storageLocations'
import { getAddresses, type Address } from '@/api/addresses'
import {
  getMaterial,
  getMaterialStorageLocations,
  getComboComponents,
  type ComboComponent,
  type MaterialStorageLocationRow,
  type MaterialStorageLocationsResponse,
} from '@/api/materials'
import MoveQuantityModal from '@/components/material/MoveQuantityModal.vue'
import { useToast } from '@/composables/useToast'
import StorageBulkCreateModal from '@/components/storage/StorageBulkCreateModal.vue'
import StorageConfirmModal from '@/components/storage/StorageConfirmModal.vue'
import StorageActionButton from '@/components/storage/StorageActionButton.vue'
import StorageCrudActions from '@/components/storage/StorageCrudActions.vue'
import {
  getRackSuggestions,
  generateRackNames,
  getSlotPrefix,
  getSlotSuggestions,
  generateSequentialNames,
} from '@/utils/storageUi'

interface Props {
  departmentId: string
  materialId?: string
  readonly?: boolean
  allowMoveActions?: boolean
  allowOpenActions?: boolean
  /** true: Augen-Icon nur Material öffnen, ohne ?batch= (reine Lagerort-Navigation) */
  openMaterialWithoutBatchQuery?: boolean
  /** Material-Detail „Gelagert in“: Kiste öffnen → Tab „Inhalt Kiste/Tasche“ ohne neuen Push */
  embeddedDetailMaterialId?: string
  containerBatchId?: string
  searchQuery?: string
}

const props = withDefaults(defineProps<Props>(), {
  readonly: false,
  allowMoveActions: false,
  allowOpenActions: false,
  openMaterialWithoutBatchQuery: false,
  embeddedDetailMaterialId: '',
  containerBatchId: '',
  searchQuery: '',
})
const router = useRouter()
const { t } = useI18n()
const toast = useToast()

const overview = ref<StorageOverviewResponse | null>(null)
const storageAddresses = ref<Address[]>([])
const isLoading = ref(true)
const error = ref('')
const expandedLocations = ref<Set<string>>(new Set())
const expandedRacks = ref<Set<string>>(new Set())
/** Material-Detail: Kisteninhalt aufklappbar, standardmäßig zu. */
const expandedContainerBatchIds = ref<Set<string>>(new Set())
const showMoveModal = ref(false)
const moveContext = ref<{
  materialId: string
  batch: { id: string; qty: number; rack_id?: string | null; slot_id?: string | null; allocations?: any[] }
  sourceAllocationId?: string | null
  sourceRackId?: string | null
  sourceSlotId?: string | null
} | null>(null)
const showRackCreateModal = ref(false)
const rackCreateLocation = ref<StorageLocationNode | null>(null)
const rackCreateBaseName = ref('')
const rackCreateCount = ref(1)
const rackSlotNameOverrides = ref<Record<string, string>>({})
const showSlotCreateModal = ref(false)
const slotCreateRack = ref<StorageOverviewRack | null>(null)
const slotCreateLocationId = ref('')
const slotCreateBaseName = ref('A')
const slotCreateCount = ref(1)
const showDeleteConfirmModal = ref(false)
const deleteTarget = ref<{ type: 'rack' | 'slot'; locationId: string; rackId: string; slotId?: string; name: string } | null>(null)
const isSubmittingAction = ref(false)
const containerBatches = ref<ContainerBatch[]>([])
/** Lagerorte inkl. über physische Kombi (Elternmaterial) – nur wenn materialId gesetzt */
const materialStorageContext = ref<MaterialStorageLocationsResponse | null>(null)
/** Material-Detail „Gelagert in“: phys. Kombi mit verknüpfter Kisten-Charge */
const embeddedMaterialMeta = ref<{
  linkedContainerBatchId: string | null
  linkedContainerMaterialId: string | null
  materialType: string
} | null>(null)
/** Material-Detail phys. Kombi: Zusammensetzung für Kisten-Aufklappung */
const embeddedComboComponents = ref<ComboComponent[]>([])
/** Komponenten-Ansicht: Stückliste pro verknüpfter Kisten-Charge (parent → linked batch). */
type PhysicalComboShellContext = {
  parentMaterialId: string
  parentName: string
  linkedContainerBatchId: string
  components: ComboComponent[]
  storedQtyByMaterialId: Map<string, number>
}
const physicalComboShellByContainerBatch = ref<Map<string, PhysicalComboShellContext>>(new Map())
/** Phys. Kombi (eigenes Material-Detail): gebuchte Mengen in der verknüpften Kiste. */
const embeddedLinkedBatchStoredQty = ref<Map<string, number>>(new Map())

type StorageLocationNode = {
  id: string
  addressId: string | null
  name: string
  isPrimary: boolean
  racks: StorageOverviewRack[]
}

const locationNodes = computed<StorageLocationNode[]>(() => {
  const racks = filteredOverview.value?.racks ?? []
  const map = new Map<string, StorageLocationNode>()
  for (const rack of racks) {
    const locId = rack.storage_address_id || '__unknown__'
    const address = rack.storage_address_id
      ? storageAddresses.value.find((a) => a.id === rack.storage_address_id)
      : null
    const locName = address
      ? (address.name || address.street_line || address.full_address || address.id)
      : (rack.storage_address_name || t('settings.storage.overviewNoStorageAddress'))
    const isPrimary = !!address?.is_primary
    const existing = map.get(locId)
    if (existing) {
      existing.racks.push(rack)
      continue
    }
    map.set(locId, {
      id: locId,
      addressId: address?.id || null,
      name: locName,
      isPrimary,
      racks: [rack],
    })
  }
  return Array.from(map.values()).sort((a, b) => {
    if (a.isPrimary && !b.isPrimary) return -1
    if (!a.isPrimary && b.isPrimary) return 1
    return a.name.localeCompare(b.name, 'de')
  })
})

const deleteModalTitle = computed(() => {
  const dt = deleteTarget.value
  if (!dt) return ''
  return dt.type === 'rack' ? t('settings.storage.deleteRackTitle') : t('settings.storage.deleteSlotTitle')
})

const deleteModalMessage = computed(() => {
  const dt = deleteTarget.value
  if (!dt) return ''
  if (dt.type === 'slot') {
    const name = (dt.name || '').trim()
    return name
      ? t('settings.storage.deleteSlotMessage', { name })
      : t('settings.storage.deleteSlotFallbackMessage')
  }
  const name = (dt.name || '').trim()
  const loc = locationNodes.value.find((l) => l.id === dt.locationId)
  const rack = loc?.racks.find((r) => r.id === dt.rackId)
  const count = rack?.slots?.length ?? 0
  return name
    ? t('settings.storage.deleteRackMessage', { name, count })
    : t('settings.storage.deleteRackFallbackMessage')
})

const normalizedSearchQuery = computed(() => (props.searchQuery || '').trim().toLocaleLowerCase('de-CH'))

const useContainerContentCollapse = computed(() => !!(props.embeddedDetailMaterialId || '').trim())
const containerMaterialByBatchId = computed(() => {
  const map = new Map<string, string>()
  for (const batch of containerBatches.value) {
    const materialId = (batch.material_id || '').trim()
    if (batch.id && materialId) map.set(batch.id, materialId)
  }
  return map
})

const comboViaPhysicalBlocks = computed(() => {
  if ((props.containerBatchId || '').trim()) return []
  return materialStorageContext.value?.via_physical_combo ?? []
})

function formatComboLocationLine(loc: MaterialStorageLocationRow): string {
  const parts: string[] = []
  if (loc.storage_address_name) parts.push(loc.storage_address_name)
  if (loc.location_label) parts.push(loc.location_label)
  else if (loc.rack_name) {
    parts.push(loc.slot_name ? `${loc.rack_name} / ${loc.slot_name}` : loc.rack_name)
  }
  if (loc.container_caption) parts.push(t('settings.storage.overviewLineContainer', { caption: loc.container_caption }))
  if (loc.qty > 0) parts.push(t('settings.storage.overviewLineQty', { qty: loc.qty }))
  return parts.join(' · ')
}

function matchesSearch(item: StorageSlotContent): boolean {
  const q = normalizedSearchQuery.value
  if (!q) return true
  const haystack = [
    item.material_name || '',
    item.container_label || '',
    item.container_batch_id || '',
    item.batch_id || '',
  ]
    .join(' ')
    .toLocaleLowerCase('de-CH')
  return haystack.includes(q)
}

type ContainerContentLine = {
  material_id: string
  material_name: string
  qty: number
  lineKey?: string
  storedQty?: number
}

type SlotDisplayRow =
  | {
      type: 'container-group'
      container_batch_id: string
      container_label: string
      contentLines: ContainerContentLine[]
      previewLines: ContainerContentLine[]
      moreCount: number
      totalQty: number
      representative: StorageSlotContent
    }
  /** mergedSourceCount > 1: Summe mehrerer Chargen am gleichen Fach — Verschieben nur über Materialdetail */
  | { type: 'single'; item: StorageSlotContent; mergedSourceCount?: number }

type StorageOverviewSlotWithRows = StorageOverviewSlot & { displayRows: SlotDisplayRow[] }

/** Vorschau-Zeilen pro Kiste in der Übersicht; Zeile 4 = „…“ wenn mehr Artikel. */
const CONTAINER_PREVIEW_LINE_LIMIT = 3

function aggregateContainerPreview(
  items: StorageSlotContent[],
  options?: { excludeMaterialIds?: Set<string> },
): {
  contentLines: ContainerContentLine[]
  previewLines: ContainerContentLine[]
  moreCount: number
  totalQty: number
} {
  const exclude = options?.excludeMaterialIds
  const byMat = new Map<string, ContainerContentLine>()
  for (const item of items) {
    const id = item.material_id
    if (exclude?.has(id)) continue
    const ex = byMat.get(id)
    if (ex) ex.qty += item.qty
    else byMat.set(id, { material_id: id, material_name: item.material_name, qty: item.qty })
  }
  const contentLines = Array.from(byMat.values()).sort((a, b) =>
    a.material_name.localeCompare(b.material_name, 'de')
  )
  const totalQty = contentLines.reduce((s, x) => s + x.qty, 0)
  const previewLines = contentLines.slice(0, CONTAINER_PREVIEW_LINE_LIMIT)
  const moreCount = Math.max(0, contentLines.length - CONTAINER_PREVIEW_LINE_LIMIT)
  return { contentLines, previewLines, moreCount, totalQty }
}

function dedupeContainerLinesByLabel(
  lines: ContainerContentLine[],
  label: string,
): ContainerContentLine[] {
  const labelKey = label.toLocaleLowerCase('de')
  return lines.filter((l) => l.material_name.toLocaleLowerCase('de') !== labelKey)
}

/** Schlüssel für Standalone-Zeilen: Bulk nach material_id; serialisiert je Charge/Einheit (eigene Zeile pro Kiste). */
function standaloneMergeKey(item: StorageSlotContent): string {
  if (item.tracking_type === 'serialized') {
    const bid = (item.batch_id || '').trim() || `a:${item.allocation_id ?? ''}`
    return `${item.material_id}\x1e${bid}`
  }
  return item.material_id
}

/** Eine Zeile pro Kiste (container_batch_id); Standalone-Artikel am gleichen Fach nach material_id (bzw. Charge bei serialisiert) zusammengefasst. */
function buildSlotDisplayRows(contents: StorageSlotContent[]): SlotDisplayRow[] {
  const groups = new Map<string, StorageSlotContent[]>()
  for (const item of contents) {
    const cid = (item.container_batch_id || '').trim()
    if (cid) {
      if (!groups.has(cid)) groups.set(cid, [])
      groups.get(cid)!.push(item)
    }
  }
  const hideBatchIds = new Set(groups.keys())

  const standaloneByKey = new Map<string, StorageSlotContent[]>()
  for (const item of contents) {
    const cid = (item.container_batch_id || '').trim()
    if (cid) continue
    if (hideBatchIds.has(item.batch_id)) continue
    const mk = standaloneMergeKey(item)
    if (!standaloneByKey.has(mk)) standaloneByKey.set(mk, [])
    standaloneByKey.get(mk)!.push(item)
  }

  const rows: SlotDisplayRow[] = []
  const emittedContainers = new Set<string>()
  const emittedStandaloneKeys = new Set<string>()

  for (const item of contents) {
    const cid = (item.container_batch_id || '').trim()
    if (cid) {
      if (emittedContainers.has(cid)) continue
      emittedContainers.add(cid)
      const groupItems = groups.get(cid) || [item]
      const label =
        groupItems.find((i) => (i.container_label || '').trim())?.container_label?.trim() ||
        t('settings.storage.containerBatchLabel', { id: cid })
      const rep = groupItems[0]
      /** Im Material-Detail alle Inhalte zeigen (inkl. des gerade angesehenen Artikels). */
      let { contentLines, previewLines, moreCount, totalQty } = aggregateContainerPreview(groupItems)
      contentLines = dedupeContainerLinesByLabel(contentLines, label)
      previewLines = dedupeContainerLinesByLabel(previewLines, label)
      moreCount = Math.max(0, contentLines.length - CONTAINER_PREVIEW_LINE_LIMIT)
      previewLines = contentLines.slice(0, CONTAINER_PREVIEW_LINE_LIMIT)
      rows.push({
        type: 'container-group',
        container_batch_id: cid,
        container_label: label,
        contentLines,
        previewLines,
        moreCount,
        totalQty,
        representative: rep,
      })
      continue
    }
    if (hideBatchIds.has(item.batch_id)) continue

    const mk = standaloneMergeKey(item)
    if (emittedStandaloneKeys.has(mk)) continue
    emittedStandaloneKeys.add(mk)

    const matGroup = standaloneByKey.get(mk) || [item]
    if (matGroup.length === 1) {
      rows.push({ type: 'single', item: matGroup[0] })
      continue
    }
    const totalQty = matGroup.reduce((s, i) => s + i.qty, 0)
    const first = matGroup[0]
    rows.push({
      type: 'single',
      item: { ...first, qty: totalQty },
      mergedSourceCount: matGroup.length,
    })
  }
  return rows
}

function filterSlotContentsForOverview(items: StorageSlotContent[]): StorageSlotContent[] {
  const byContainer = (props.containerBatchId || '').trim()
  const byMaterial = (props.materialId || '').trim()
  const hasSearch = normalizedSearchQuery.value.length > 0

  const groups = new Map<string, StorageSlotContent[]>()
  for (const item of items) {
    const cid = (item.container_batch_id || '').trim()
    if (cid) {
      if (!groups.has(cid)) groups.set(cid, [])
      groups.get(cid)!.push(item)
    }
  }

  function groupVisible(groupItems: StorageSlotContent[]): boolean {
    if (byContainer) {
      return groupItems.some((i) => (i.container_batch_id || '') === byContainer)
    }
    if (!byMaterial) {
      if (!hasSearch) return true
      return groupItems.some((i) => matchesSearch(i))
    }
    const cid = (groupItems[0]?.container_batch_id || '').trim()
    const containerOwnerMaterialId = cid ? containerMaterialByBatchId.value.get(cid) : undefined
    /** Kisten-Artikel: Inhalt hat andere material_ids — Gruppe trotzdem anzeigen, wenn die Charge zur Kiste (diesem Material) gehört. */
    const candidates =
      containerOwnerMaterialId === byMaterial
        ? groupItems
        : groupItems.filter((i) => i.material_id === byMaterial)
    if (candidates.length === 0) return false
    if (!hasSearch) return true
    return candidates.some((i) => matchesSearch(i))
  }

  function standaloneVisible(item: StorageSlotContent): boolean {
    if (byContainer) {
      return (item.container_batch_id || '') === byContainer
    }
    if (byMaterial && item.material_id !== byMaterial) {
      return false
    }
    return matchesSearch(item)
  }

  const hideBatchIds = new Set(groups.keys())
  const out: StorageSlotContent[] = []
  const addedGroups = new Set<string>()

  for (const item of items) {
    const cid = (item.container_batch_id || '').trim()
    if (cid) {
      if (addedGroups.has(cid)) continue
      const grp = groups.get(cid) || []
      if (groupVisible(grp)) {
        addedGroups.add(cid)
        out.push(...grp)
      }
      continue
    }
    if (hideBatchIds.has(item.batch_id)) continue
    if (standaloneVisible(item)) out.push(item)
  }
  return out
}

function slotRowKey(row: SlotDisplayRow, idx: number): string {
  if (row.type === 'container-group') {
    return `cg-${row.container_batch_id}`
  }
  if (row.mergedSourceCount && row.mergedSourceCount > 1) {
    return `agg-${standaloneMergeKey(row.item)}`
  }
  return `${row.item.batch_id}-${row.item.allocation_id ?? idx}`
}

/** Nur bei Filterkontext (Material/Kiste/Suche): leere Fächer/Regale ausblenden. In der Materialübersicht ohne Filter volle Struktur wie in den Einstellungen. */
const hideEmptySlotsAndRacks = computed(() => {
  const byMaterial = (props.materialId || '').trim()
  const byContainer = (props.containerBatchId || '').trim()
  return !!(byMaterial || byContainer || normalizedSearchQuery.value.length > 0)
})

const filteredOverview = computed<{ racks: Array<StorageOverviewRack & { slots: StorageOverviewSlotWithRows[] }> } | null>(() => {
  const raw = overview.value
  if (!raw) return null
  const stripEmpty = hideEmptySlotsAndRacks.value
  const filteredRacks = raw.racks
    .map((rack) => {
      const filteredSlots = (rack.slots || [])
        .map((slot) => {
          const contents = filterSlotContentsForOverview(slot.contents || [])
          const displayRows = buildSlotDisplayRows(contents)
          return {
            ...slot,
            contents,
            displayRows,
          }
        })
        .filter((slot) => (stripEmpty ? slot.contents.length > 0 : true))

      return {
        ...rack,
        slots: filteredSlots,
      }
    })
    .filter((rack) => (stripEmpty ? rack.slots.length > 0 : true))

  return { racks: filteredRacks }
})

const primaryStorageAddressId = computed(() => {
  return storageAddresses.value.find((a) => a.is_primary)?.id || ''
})

const rackCreateSuggestions = computed(() => {
  return getRackSuggestions(rackCreateLocation.value?.racks.map((rack) => rack.name) || [])
})

const rackGeneratedNames = computed(() => {
  return generateRackNames(
    rackCreateBaseName.value,
    rackCreateCount.value,
    rackCreateLocation.value?.racks.map((rack) => rack.name) || []
  )
})

const rackGeneratedSlotPairs = computed(() => {
  return rackGeneratedNames.value.map((rackName) => ({
    id: rackName,
    leftLabel: rackName,
    rightValue: rackSlotNameOverrides.value[rackName] ?? `${getSlotPrefix(rackName)}1`,
  }))
})

const slotCreateSuggestions = computed(() => {
  const rack = slotCreateRack.value
  if (!rack) return []
  return getSlotSuggestions(rack.name, (rack.slots || []).map((s) => s.name))
})

const slotGeneratedNames = computed(() => {
  const rack = slotCreateRack.value
  if (!rack) return []
  return generateSequentialNames(
    slotCreateBaseName.value,
    slotCreateCount.value,
    (rack.slots || []).map((s) => s.name)
  )
})

function toggleLocation(locationId: string) {
  const isCurrentlyOpen = expandedLocations.value.has(locationId)
  if (isCurrentlyOpen) {
    expandedLocations.value = new Set()
    expandedRacks.value = new Set()
    return
  }

  const next = new Set(expandedLocations.value)
  next.clear()
  next.add(locationId)
  expandedLocations.value = next

  const location = locationNodes.value.find((node) => node.id === locationId)
  if (location && location.racks.length < 10) {
    expandedRacks.value = new Set(location.racks.map((rack) => rack.id))
  } else {
    expandedRacks.value = new Set()
  }
}

function toggleRack(rackId: string) {
  const next = new Set(expandedRacks.value)
  if (next.has(rackId)) {
    next.delete(rackId)
  } else {
    next.add(rackId)
  }
  expandedRacks.value = next
}

function canDeleteRack(rack: StorageOverviewRack): boolean {
  const totalItems = rack.slots.reduce((sum, slot) => sum + slot.contents.length, 0)
  return totalItems === 0
}

async function addRackForLocation(location: StorageLocationNode) {
  if (!location.addressId) {
    toast.error(t('settings.storage.toastRackCreateForbidden'))
    return
  }
  rackCreateLocation.value = location
  rackCreateBaseName.value = rackCreateSuggestions.value[0] || t('settings.storage.rackBaseDefault')
  rackCreateCount.value = 1
  rackSlotNameOverrides.value = {}
  showRackCreateModal.value = true
}

async function addSlotForRack(rack: StorageOverviewRack, locationId: string) {
  slotCreateRack.value = rack
  slotCreateLocationId.value = locationId
  slotCreateBaseName.value = getSlotPrefix(rack.name)
  slotCreateCount.value = 1
  showSlotCreateModal.value = true
}

function closeRackCreateModal() {
  showRackCreateModal.value = false
  rackCreateLocation.value = null
  rackCreateBaseName.value = ''
  rackCreateCount.value = 1
  rackSlotNameOverrides.value = {}
}

function closeSlotCreateModal() {
  showSlotCreateModal.value = false
  slotCreateRack.value = null
  slotCreateLocationId.value = ''
  slotCreateBaseName.value = ''
  slotCreateCount.value = 1
}

function closeDeleteConfirmModal() {
  showDeleteConfirmModal.value = false
  deleteTarget.value = null
}

async function createRackFromModal() {
  if (!rackCreateLocation.value?.addressId) return
  const names = rackGeneratedNames.value
  if (names.length === 0) return
  isSubmittingAction.value = true
  try {
    for (const name of names) {
      const initialSlotNameForRack = (rackSlotNameOverrides.value[name] ?? `${getSlotPrefix(name)}1`).trim()
      if (!initialSlotNameForRack) {
        toast.error(t('settings.storage.slotNameRequired'))
        return
      }
      await createStorageRack({
        department_id: props.departmentId,
        storage_address_id: rackCreateLocation.value.addressId,
        name,
        initial_slot_name: initialSlotNameForRack,
      })
    }
    await load(rackCreateLocation.value.id, false)
    closeRackCreateModal()
    toast.success(t('settings.storage.toastRacksCreated', { count: names.length }))
  } catch (e: any) {
    toast.error(e?.response?.data?.error || t('settings.storage.toastRackCreateFailed'))
  } finally {
    isSubmittingAction.value = false
  }
}

function updateRackPairItemValue(payload: { index: number; value: string }) {
  const rackName = rackGeneratedNames.value[payload.index]
  if (!rackName) return
  rackSlotNameOverrides.value = {
    ...rackSlotNameOverrides.value,
    [rackName]: payload.value,
  }
}

async function createSlotFromModal() {
  if (!slotCreateRack.value) return
  const names = slotGeneratedNames.value
  if (names.length === 0) return
  isSubmittingAction.value = true
  try {
    for (const name of names) {
      await createStorageSlot({
        rack_id: slotCreateRack.value.id,
        name,
      })
    }
    await load(slotCreateLocationId.value, true, slotCreateRack.value.id)
    closeSlotCreateModal()
    toast.success(t('settings.storage.toastSlotsCreated', { count: names.length }))
  } catch (e: any) {
    toast.error(e?.response?.data?.error || t('settings.storage.toastSlotCreateFailed'))
  } finally {
    isSubmittingAction.value = false
  }
}

function decrementRackCreateCount() {
  rackCreateCount.value = Math.max(1, rackCreateCount.value - 1)
}

function incrementRackCreateCount() {
  rackCreateCount.value = Math.min(99, rackCreateCount.value + 1)
}

function decrementSlotCreateCount() {
  slotCreateCount.value = Math.max(1, slotCreateCount.value - 1)
}

function incrementSlotCreateCount() {
  slotCreateCount.value = Math.min(99, slotCreateCount.value + 1)
}

function deleteRackFromOverview(rack: StorageOverviewRack, locationId: string) {
  if (!canDeleteRack(rack)) return
  deleteTarget.value = { type: 'rack', locationId, rackId: rack.id, name: rack.name }
  showDeleteConfirmModal.value = true
}

function deleteSlotFromOverview(rack: StorageOverviewRack, slot: StorageOverviewSlot, locationId: string) {
  if (!slot.id || slot.contents.length > 0) return
  deleteTarget.value = { type: 'slot', locationId, rackId: rack.id, slotId: slot.id, name: slot.name }
  showDeleteConfirmModal.value = true
}

async function executeDeleteTarget() {
  if (!deleteTarget.value) return
  isSubmittingAction.value = true
  try {
    if (deleteTarget.value.type === 'rack') {
      await deleteStorageRack(deleteTarget.value.rackId)
      await load(deleteTarget.value.locationId, false)
      toast.success(t('settings.storage.toastRackDeleted'))
    } else if (deleteTarget.value.slotId) {
      await deleteStorageSlot(deleteTarget.value.slotId)
      await load(deleteTarget.value.locationId, true, deleteTarget.value.rackId)
      toast.success(t('settings.storage.toastSlotDeleted'))
    }
    closeDeleteConfirmModal()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || t('settings.storage.deleteError'))
  } finally {
    isSubmittingAction.value = false
  }
}

async function load(
  preferredLocationId?: string,
  keepRackOpen = false,
  preferredRackId?: string
) {
  if (!props.departmentId) return
  isLoading.value = true
  error.value = ''
  try {
    const [addressResult, storageOverview, containerBatchResult] = await Promise.all([
      getAddresses(props.departmentId, 'storage').catch(() => ({ addresses: [] as Address[] })),
      getStorageOverview(props.departmentId),
      getContainerBatches(props.departmentId).catch(() => [] as ContainerBatch[]),
      loadEmbeddedMaterialMeta(),
    ])
    storageAddresses.value = addressResult.addresses || []
    overview.value = storageOverview
    containerBatches.value = containerBatchResult || []

    const targetLocationId = preferredLocationId || primaryStorageAddressId.value
    if (targetLocationId) {
      const mainLocation = locationNodes.value.find((loc) => loc.id === targetLocationId)
      expandedLocations.value = new Set([targetLocationId])
      if (keepRackOpen && preferredRackId) {
        expandedRacks.value = new Set([preferredRackId])
      } else if (mainLocation && mainLocation.racks.length < 10) {
        expandedRacks.value = new Set(mainLocation.racks.map((rack) => rack.id))
      } else {
        expandedRacks.value = new Set()
      }
    } else {
      expandedLocations.value = new Set()
      expandedRacks.value = new Set()
    }
  } catch (e: any) {
    error.value = e?.response?.data?.error || t('settings.storage.loadError')
  } finally {
    isLoading.value = false
  }
}

async function fetchStoredQtyByMaterialForContainerBatch(
  containerBatchId: string,
): Promise<Map<string, number>> {
  const map = new Map<string, number>()
  try {
    const data = await getContainerBatchContents(containerBatchId)
    for (const line of data.contents || []) {
      const mid = (line.material_id || '').trim()
      if (!mid) continue
      map.set(mid, (map.get(mid) ?? 0) + (line.qty ?? 0))
    }
  } catch {
    /* ignore */
  }
  return map
}

async function loadPhysicalComboShellContexts() {
  const blocks = comboViaPhysicalBlocks.value
  if (blocks.length === 0) {
    physicalComboShellByContainerBatch.value = new Map()
    return
  }
  const next = new Map<string, PhysicalComboShellContext>()
  const seenParents = new Set<string>()
  for (const block of blocks) {
    const pid = block.parent_material_id
    if (!pid || seenParents.has(pid)) continue
    seenParents.add(pid)
    try {
      const linkedFromApi = (block.parent_linked_container_batch_id || '').trim()
      let linked = linkedFromApi
      let parentName = block.parent_name
      if (!linked) {
        const parent = await getMaterial(pid)
        linked = (parent.linked_container_batch_id || parent.linked_container_batch?.id || '').trim()
        parentName = parent.name || parentName
      }
      if (!linked) continue
      if (next.has(linked)) continue
      const [components, storedQtyByMaterialId] = await Promise.all([
        getComboComponents(pid),
        fetchStoredQtyByMaterialForContainerBatch(linked),
      ])
      next.set(linked, {
        parentMaterialId: pid,
        parentName,
        linkedContainerBatchId: linked,
        components,
        storedQtyByMaterialId,
      })
    } catch {
      /* ignore single parent load failure */
    }
  }
  physicalComboShellByContainerBatch.value = next
}

async function loadMaterialStorageContext() {
  const mid = (props.materialId || '').trim()
  if (!mid || !props.departmentId || (props.containerBatchId || '').trim()) {
    materialStorageContext.value = null
    physicalComboShellByContainerBatch.value = new Map()
    return
  }
  try {
    materialStorageContext.value = await getMaterialStorageLocations(mid, props.departmentId)
    await loadPhysicalComboShellContexts()
  } catch {
    materialStorageContext.value = null
    physicalComboShellByContainerBatch.value = new Map()
  }
}

async function loadEmbeddedMaterialMeta() {
  const mid = (props.materialId || '').trim()
  const emb = (props.embeddedDetailMaterialId || '').trim()
  if (!mid || emb !== mid) {
    embeddedMaterialMeta.value = null
    return
  }
  try {
    const m = await getMaterial(mid)
    const linkedBatch = m.linked_container_batch
    embeddedMaterialMeta.value = {
      linkedContainerBatchId:
        (m.linked_container_batch_id || linkedBatch?.id || '').trim() || null,
      linkedContainerMaterialId: (linkedBatch?.material_id || '').trim() || null,
      materialType: m.material_type || '',
    }
    await loadEmbeddedComboComponents()
  } catch {
    embeddedMaterialMeta.value = null
    embeddedComboComponents.value = []
  }
}

type ContainerGroupRow = Extract<SlotDisplayRow, { type: 'container-group' }>

function isContainerGroupExpanded(containerBatchId: string): boolean {
  return expandedContainerBatchIds.value.has(containerBatchId.trim())
}

function toggleContainerGroup(containerBatchId: string): void {
  const cid = containerBatchId.trim()
  if (!cid) return
  const next = new Set(expandedContainerBatchIds.value)
  if (next.has(cid)) next.delete(cid)
  else next.add(cid)
  expandedContainerBatchIds.value = next
}

function physicalComboContextForContainerGroup(row: ContainerGroupRow): PhysicalComboShellContext | undefined {
  const direct = physicalComboShellByContainerBatch.value.get(row.container_batch_id.trim())
  if (direct) return direct
  const shellMat = shellMaterialIdForContainerBatch(row.container_batch_id)
  if (!shellMat) return undefined
  for (const ctx of physicalComboShellByContainerBatch.value.values()) {
    const linkedShellMat = shellMaterialIdForContainerBatch(ctx.linkedContainerBatchId)
    if (linkedShellMat && linkedShellMat === shellMat) return ctx
  }
  return undefined
}

function comboComponentsForContainerGroup(row: ContainerGroupRow): ComboComponent[] {
  if (containerGroupIsEmbeddedPhysicalComboShell(row)) {
    return embeddedComboComponents.value
  }
  return physicalComboContextForContainerGroup(row)?.components ?? []
}

function containerGroupUsesCompositionList(row: ContainerGroupRow): boolean {
  if (!useContainerContentCollapse.value) return false
  return comboComponentsForContainerGroup(row).length > 0
}

function containerGroupItemCount(row: ContainerGroupRow): number {
  if (containerGroupUsesCompositionList(row)) {
    return comboComponentsForContainerGroup(row).length
  }
  return row.contentLines.length
}

function storedQtyMapForContainerGroup(row: ContainerGroupRow): Map<string, number> {
  const ctx = physicalComboContextForContainerGroup(row)
  if (ctx?.storedQtyByMaterialId.size) return ctx.storedQtyByMaterialId
  if (containerGroupIsEmbeddedPhysicalComboShell(row) && embeddedLinkedBatchStoredQty.value.size) {
    return embeddedLinkedBatchStoredQty.value
  }
  return new Map(row.contentLines.map((l) => [l.material_id, l.qty]))
}

function containerGroupVisibleLines(row: ContainerGroupRow): ContainerContentLine[] {
  const components = comboComponentsForContainerGroup(row)
  if (containerGroupUsesCompositionList(row)) {
    const storageByMat = storedQtyMapForContainerGroup(row)
    return components
      .slice()
      .sort((a, b) => a.component_material.name.localeCompare(b.component_material.name, 'de'))
      .map((comp) => ({
        lineKey: comp.id,
        material_id: comp.component_material.id,
        material_name: comp.component_material.name,
        qty: comp.qty,
        storedQty: storageByMat.get(comp.component_material.id) ?? 0,
      }))
  }
  if (useContainerContentCollapse.value) return row.contentLines
  return row.previewLines
}

async function loadEmbeddedComboComponents() {
  const mid = (props.materialId || '').trim()
  const emb = (props.embeddedDetailMaterialId || '').trim()
  if (!mid || emb !== mid || embeddedMaterialMeta.value?.materialType !== 'physical_combo') {
    embeddedComboComponents.value = []
    embeddedLinkedBatchStoredQty.value = new Map()
    return
  }
  try {
    const linked = (embeddedMaterialMeta.value?.linkedContainerBatchId || '').trim()
    const [components, storedMap] = await Promise.all([
      getComboComponents(mid),
      linked ? fetchStoredQtyByMaterialForContainerBatch(linked) : Promise.resolve(new Map<string, number>()),
    ])
    embeddedComboComponents.value = components
    embeddedLinkedBatchStoredQty.value = storedMap
  } catch {
    embeddedComboComponents.value = []
    embeddedLinkedBatchStoredQty.value = new Map()
  }
}

/** Phys. Kombi: diese Kisten-Charge ist die feste Komponente (Referenz-Kiste am Regalplatz). */
function containerGroupIsEmbeddedPhysicalComboShell(row: ContainerGroupRow): boolean {
  const linked = embeddedMaterialMeta.value?.linkedContainerBatchId
  if (!linked || embeddedMaterialMeta.value?.materialType !== 'physical_combo') return false
  if (row.container_batch_id === linked) return true
  const shellMat = shellMaterialIdForContainerBatch(row.container_batch_id)
  const linkedMat = embeddedMaterialMeta.value.linkedContainerMaterialId
  return !!(shellMat && linkedMat && shellMat === linkedMat)
}

function containerGroupIsPhysicalComboLinkedShell(row: ContainerGroupRow): boolean {
  if (containerGroupIsEmbeddedPhysicalComboShell(row)) return true
  return !!physicalComboContextForContainerGroup(row)
}

function containerGroupQtyLabel(row: ContainerGroupRow): string {
  if (containerGroupIsPhysicalComboLinkedShell(row)) {
    return t('settings.storage.overviewLineQty', { qty: 1 })
  }
  return t('settings.storage.overviewLineQty', { qty: row.totalQty })
}

function showContainerGroupActionsBar(row: ContainerGroupRow): boolean {
  return (
    !props.readonly ||
    props.allowMoveActions ||
    props.allowOpenActions ||
    (!!(props.embeddedDetailMaterialId || '').trim() && containerGroupIsEmbeddedPhysicalComboShell(row))
  )
}

function shellMaterialIdForContainerBatch(containerBatchId: string): string | undefined {
  const cid = containerBatchId.trim()
  if (!cid) return undefined
  return (
    containerMaterialByBatchId.value.get(cid) ||
    (embeddedMaterialMeta.value?.linkedContainerBatchId === cid
      ? embeddedMaterialMeta.value.linkedContainerMaterialId || undefined
      : undefined)
  )
}

function canMoveContainerGroup(row: ContainerGroupRow): boolean {
  if (!props.allowMoveActions) return false
  if (!containerGroupIsEmbeddedPhysicalComboShell(row)) return false
  return !!shellMaterialIdForContainerBatch(row.container_batch_id)
}

function canOpenContainerMaterial(item: StorageSlotContent): boolean {
  const containerId = (item.container_batch_id || '').trim()
  if (!containerId) return false
  return !!shellMaterialIdForContainerBatch(containerId)
}

function showContainerOpenUi(): boolean {
  const emb = (props.embeddedDetailMaterialId || '').trim()
  return !!(emb || !props.readonly || props.allowOpenActions)
}

function navigateToContainerContentTab(containerBatchId: string) {
  const cid = containerBatchId.trim()
  if (!cid) return
  const materialId = shellMaterialIdForContainerBatch(cid)
  if (!materialId) return
  const deptId = String(router.currentRoute.value.params.departmentId || props.departmentId || '')
  if (!deptId) return
  const embedded = (props.embeddedDetailMaterialId || '').trim()
  const query: Record<string, string> = {
    tab: 'container-content',
    containerBatch: cid,
  }
  const linkedShell =
    embeddedMaterialMeta.value?.linkedContainerBatchId &&
    embeddedMaterialMeta.value.linkedContainerBatchId === cid
  if (embedded && (materialId === embedded || linkedShell)) {
    router.replace({
      path: router.currentRoute.value.path,
      query: { ...router.currentRoute.value.query, ...query },
    })
    return
  }
  router.push({ path: `/${deptId}/materials/${materialId}`, query })
}

function openContainerMaterialFromStoredItem(item: StorageSlotContent) {
  const containerId = (item.container_batch_id || '').trim()
  if (!containerId) return
  navigateToContainerContentTab(containerId)
}

/** Verschieben: Bulk immer; serialisiert nur im Material-Detail für die eigene Einheit (z. B. phys. Kombi). */
function rowAllowsMoveForStoredItem(item: StorageSlotContent): boolean {
  if (item.tracking_type !== 'serialized') return true
  if (!props.allowMoveActions) return false
  const mid = (props.materialId || '').trim()
  if (!mid) return false
  return item.material_id === mid
}

async function openMoveForItem(item: StorageSlotContent, rack: StorageOverviewRack, slot: StorageOverviewSlot) {
  try {
    const material = await getMaterial(item.material_id)
    const batch = material.batches?.find((b: any) => b.id === item.batch_id)
    if (!batch) {
      error.value = t('settings.storage.overviewOpenMoveBatchNotFound')
      return
    }
    moveContext.value = {
      materialId: item.material_id,
      batch,
      sourceAllocationId: item.allocation_id ?? null,
      sourceRackId: rack.id,
      sourceSlotId: slot.id ?? null,
    }
    showMoveModal.value = true
  } catch (e: any) {
    error.value = e?.response?.data?.error || t('settings.storage.overviewOpenMoveLoadError')
  }
}

/** Kisten-Gruppe: phys. Kombi → verknüpfte Kisten-Charge am Regal verschieben (wie Zelt als Einheit). */
async function openMoveForContainerGroup(
  row: ContainerGroupRow,
  rack: StorageOverviewRack,
  slot: StorageOverviewSlot,
) {
  if (containerGroupIsEmbeddedPhysicalComboShell(row)) {
    const shellMaterialId = shellMaterialIdForContainerBatch(row.container_batch_id)
    if (!shellMaterialId) {
      error.value = t('settings.storage.overviewOpenMoveBatchNotFound')
      return
    }
    try {
      const material = await getMaterial(shellMaterialId)
      const batch = material.batches?.find((b) => b.id === row.container_batch_id)
      if (!batch) {
        error.value = t('settings.storage.overviewOpenMoveBatchNotFound')
        return
      }
      moveContext.value = {
        materialId: shellMaterialId,
        batch,
        sourceAllocationId: null,
        sourceRackId: rack.id,
        sourceSlotId: slot.id ?? null,
      }
      showMoveModal.value = true
    } catch (e: unknown) {
      const ax = e as { response?: { data?: { error?: string } } }
      error.value = ax.response?.data?.error || t('settings.storage.overviewOpenMoveLoadError')
    }
    return
  }
  if (rowAllowsMoveForStoredItem(row.representative)) {
    await openMoveForItem(row.representative, rack, slot)
  }
}

function openMaterial(item: StorageSlotContent) {
  const deptId = router.currentRoute.value.params.departmentId
  if (deptId) {
    const query =
      props.openMaterialWithoutBatchQuery || !item.batch_id ? {} : { batch: item.batch_id }
    router.push({ path: `/${deptId}/materials/${item.material_id}`, query })
  }
}

function handleMoveSaved() {
  load()
  void loadMaterialStorageContext()
}

function isContainerStoredItem(item: StorageSlotContent): boolean {
  return !!(item.container_batch_id || item.container_label)
}

function getContainerDisplayLabel(item: StorageSlotContent): string {
  if (item.container_label) return item.container_label
  if (item.container_batch_id) return t('settings.storage.containerBatchLabel', { id: item.container_batch_id })
  return t('settings.storage.containerGenericLabel')
}

function getContainerOtherItemsCount(item: StorageSlotContent): number {
  const containerId = item.container_batch_id || null
  if (!containerId || !overview.value) return 0
  const allItems = overview.value.racks
    .flatMap((rack) => rack.slots || [])
    .flatMap((slot) => slot.contents || [])
  const others = allItems.filter((entry) =>
    (entry.container_batch_id || null) === containerId &&
    entry.material_id !== item.material_id
  )
  const uniqueOtherMaterials = new Set(others.map((entry) => entry.material_id))
  return uniqueOtherMaterials.size
}

watch(
  () => [props.materialId, props.departmentId, props.containerBatchId, props.embeddedDetailMaterialId],
  () => {
    void loadMaterialStorageContext()
    void loadEmbeddedMaterialMeta()
    if ((props.embeddedDetailMaterialId || '').trim()) {
      void load()
    }
  },
  { immediate: true }
)
watch(() => props.departmentId, () => { load() }, { immediate: true })
onMounted(() => { load() })
</script>

<style scoped>
.storage-main {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.combo-derived-storage {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 16px 18px;
}

.combo-derived-intro {
  margin: 0 0 14px;
  font-size: 14px;
  color: #475569;
  line-height: 1.5;
}

.combo-derived-card {
  padding: 12px 0 0;
  border-top: 1px solid #f1f5f9;
}

.combo-derived-card:first-of-type {
  border-top: none;
  padding-top: 0;
}

.combo-derived-card-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 10px 14px;
  margin-bottom: 8px;
}

.combo-derived-parent-link {
  font-weight: 600;
  color: #2563eb;
  text-decoration: none;
}

.combo-derived-parent-link:hover {
  text-decoration: underline;
}

.combo-derived-qty {
  font-size: 13px;
  color: #64748b;
}

.combo-derived-loc-list {
  margin: 0;
  padding-left: 1.2rem;
  font-size: 14px;
  color: #334155;
  line-height: 1.55;
}

.storage-overview-tab {
  padding: 24px;
  background: #f8fafc;
  min-height: 400px;
}

.storage-loading,
.storage-error,
.storage-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 80px 20px;
  gap: 16px;
  color: #6b7280;
}

.storage-error p,
.storage-empty p {
  margin: 0;
}

.storage-empty .hint {
  font-size: 13px;
  color: #9ca3af;
}

.storage-tree {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.storage-context-hint {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  color: #1e40af;
  font-size: 13px;
  font-weight: 600;
}

.storage-location {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}

.location-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-bottom: 1px solid #e5e7eb;
  background: #f8fafc;
}

.location-toggle-btn {
  flex: 1;
  width: 100%;
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px;
  text-align: left;
}

.location-name {
  font-weight: 700;
  color: #111827;
}

.location-primary-badge {
  padding: 2px 8px;
  border-radius: 999px;
  background: #ccfbf1;
  color: #0f766e;
  font-size: 11px;
  font-weight: 700;
}

.location-count {
  margin-left: auto;
  font-size: 12px;
  color: #6b7280;
}

.location-actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.location-racks {
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.storage-rack {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
}

.rack-header {
  display: flex;
  align-items: center;
  gap: 6px;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
  padding: 8px 10px;
}

.rack-toggle-btn {
  flex: 1;
  width: 100%;
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px;
  text-align: left;
}

.rack-actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.tree-caret {
  display: inline-flex;
  font-size: 11px;
  color: #6b7280;
  transition: transform 0.15s ease;
}

.tree-caret.expanded {
  transform: rotate(90deg);
}

.rack-name {
  font-weight: 600;
  font-size: 15px;
  color: #111827;
}

.rack-slot-count {
  margin-left: auto;
  font-size: 12px;
  color: #6b7280;
}

.rack-slots {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0;
}

.storage-slot {
  border-right: 1px solid #e5e7eb;
  border-bottom: 1px solid #e5e7eb;
  padding: 16px;
  min-height: 80px;
}

.storage-slot:last-child {
  border-right: none;
}

.slot-header {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: space-between;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  margin-bottom: 10px;
}

.slot-empty {
  font-size: 13px;
  color: #9ca3af;
  font-style: italic;
}

.slot-contents {
  list-style: none;
  margin: 0;
  padding: 0;
}

.content-item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 10px;
  background: #f9fafb;
  border-radius: 6px;
  margin-bottom: 6px;
  font-size: 14px;
}

.content-item--container {
  align-items: flex-start;
}

.content-item:last-child {
  margin-bottom: 0;
}

.content-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.content-label {
  font-weight: 600;
  font-size: 14px;
  color: #111827;
}

.container-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
}

.container-label {
  font-weight: 700;
  font-size: 13px;
  color: #374151;
}

.container-expand-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 2px;
  padding: 2px 0;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
}

.container-expand-trigger:hover .container-collapsed-hint {
  color: #374151;
}

.container-expand-trigger .toggle-icon {
  font-size: 10px;
  line-height: 1;
  color: #6b7280;
  flex-shrink: 0;
}

.container-expand-trigger:hover .toggle-icon {
  color: #374151;
}

.container-collapsed-hint {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
}

.container-contents-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: 4px;
  padding-left: 16px;
}

.container-contents-body .container-preview-line {
  padding-left: 0;
}

.container-composition-note {
  margin: 0 0 6px 0;
  font-size: 11px;
  color: #9ca3af;
  line-height: 1.35;
}

.container-stored-tag {
  font-size: 11px;
  font-weight: 500;
  color: #059669;
}

.container-stored-tag--none {
  color: #9ca3af;
}

.container-article {
  font-size: 14px;
  color: #111827;
}

.container-preview-line {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 4px 12px;
}

.container-preview-qty {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  font-size: 13px;
  color: #374151;
  text-align: right;
}

.container-preview-qty .container-stored-tag {
  display: block;
}

.container-more {
  font-size: 12px;
  color: #9ca3af;
}

.container-more-ellipsis {
  font-size: 16px;
  font-weight: 600;
  line-height: 1;
  color: #9ca3af;
  letter-spacing: 0.12em;
  cursor: default;
}

.container-link-btn {
  width: fit-content;
  border: none;
  background: transparent;
  color: #2563eb;
  font-size: 12px;
  font-weight: 600;
  padding: 0;
  cursor: pointer;
}

.container-link-btn:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.content-material-name {
  font-size: 12px;
  color: #6b7280;
}

.content-name {
  font-weight: 500;
  color: #374151;
  overflow: hidden;
  text-overflow: ellipsis;
}

.content-item-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
}

.content-qty {
  flex-shrink: 0;
  color: #6b7280;
  font-size: 13px;
}

.content-actions {
  display: flex;
  gap: 4px;
}

.storage-loading p {
  color: #6b7280;
  font-size: 15px;
}

.modal-context {
  font-size: 13px;
  color: #6b7280;
  margin: -8px 0 12px 0;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 18px;
}
</style>
