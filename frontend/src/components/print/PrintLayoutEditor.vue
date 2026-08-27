<template>
  <section :class="embedded ? 'layout-editor' : 'card layout-editor'">
    <div v-if="!embedded" class="card-head">
      <h2>{{ t('printLayout.title') }}</h2>
      <EButton v-if="allowManage" variant="primary" size="small" :disabled="!hasPrinters" @click="openCreate()">
        {{ t('printLayout.add') }}
      </EButton>
    </div>
    <div v-else class="card-head">
      <p class="muted head-hint">{{ t('printLayout.hint') }}</p>
      <EButton v-if="allowManage" variant="primary" size="small" :disabled="!hasPrinters" @click="openCreate()">
        {{ t('printLayout.add') }}
      </EButton>
    </div>
    <p v-if="!embedded" class="muted">{{ t('printLayout.hint') }}</p>

    <ELoadingState v-if="loading" variant="inline" :message="t('printLayout.loading')" />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>

    <template v-else>
    <div class="layout-work">
      <div class="picker-block">
        <ESearchField
          v-model="layoutQuery"
          :label="t('printLayout.search')"
          hide-details
        />

        <details class="layout-subacc" open>
          <summary class="layout-subacc__summary">
            <span>{{ t('printLayout.myLayouts') }}</span>
            <span class="layout-subacc__chevron" aria-hidden="true">▾</span>
          </summary>
          <div class="layout-subacc__body">
            <EEmptyState
              v-if="!hasPrinters"
              variant="generic"
              compact
              :title="t('printLayout.needsPrintersTitle')"
              :description="t('printLayout.needsPrinters')"
              icon="mdi-printer-outline"
            />
            <EEmptyState
              v-else-if="layouts.length === 0"
              variant="generic"
              compact
              :title="t('printLayout.emptyTitle')"
              :description="t('printLayout.empty')"
              icon="mdi-view-dashboard-outline"
            />
            <p v-else-if="groupedLayouts.length === 0" class="muted">{{ t('printLayout.noSearchHits') }}</p>
            <template v-else>
              <div v-for="group in groupedLayouts" :key="'l-' + group.brand" class="brand-group">
                <h4 v-if="showLayoutBrandHeadings" class="brand-heading">{{ group.brand }}</h4>
                <div class="media-grid">
                  <PrintMediaCard
                    v-for="item in group.items"
                    :key="item.id"
                    :media="item.media"
                    :spec="item.sheet"
                    :cells="item.cells"
                    :title="item.name"
                    :extra="layoutScopeLabel(item)"
                    :selected="selected?.id === item.id"
                    @select="selectLayout(item)"
                  />
                </div>
              </div>
            </template>
          </div>
        </details>

        <details class="layout-subacc" :open="catalogOpen" @toggle="onCatalogToggle">
          <summary class="layout-subacc__summary">
            <span>{{ t('printLayout.pickLayout') }}</span>
            <span class="layout-subacc__chevron" aria-hidden="true">▾</span>
          </summary>
          <div class="layout-subacc__body">
            <p v-if="!hasPrinters" class="muted">{{ t('printLayout.needsPrinters') }}</p>
            <template v-else>
            <div class="brand-bar" role="group" :aria-label="t('common.manufacturer')">
              <button
                type="button"
                class="brand-chip"
                :class="{ 'is-on': !brandFilter }"
                @click="brandFilter = ''"
              >
                {{ t('printLayout.allBrands') }}
              </button>
              <button
                v-for="brand in mediaBrands"
                :key="brand"
                type="button"
                class="brand-chip"
                :class="{ 'is-on': brandFilter === brand }"
                @click="brandFilter = brand"
              >
                {{ brand }}
              </button>
            </div>
            <p v-if="groupedMedia.length === 0" class="muted">{{ t('printLayout.noSearchHits') }}</p>
            <div v-for="group in groupedMedia" :key="'m-' + group.brand" class="brand-group">
              <h4 v-if="showBrandHeadings" class="brand-heading">{{ group.brand }}</h4>
              <div class="media-grid">
                <PrintMediaCard
                  v-for="item in group.items"
                  :key="item.id"
                  :media="item"
                  :selected="selected?.media_id === item.id && selected.department_id === departmentId"
                  @select="pickPaper(item)"
                />
              </div>
            </div>
            </template>
          </div>
        </details>
      </div>

      <div v-if="selected" class="editor">
        <div class="editor-toolbar">
          <label v-if="canEditSelected && isOfficeSheet" class="file-btn">
            {{ t('printLayout.uploadPdf') }}
            <input type="file" accept="application/pdf" class="sr-only" @change="onPdf" />
          </label>
          <ECheckbox
            v-if="isOfficeSheet"
            v-model="includeTemplate"
            :label="t('printLayout.includeTemplate')"
            hide-details
            :disabled="!canEditSelected"
            @update:model-value="persistInclude"
          />
          <EButton
            v-if="canOfferShare"
            variant="secondary"
            size="small"
            @click="shareSelected"
          >
            {{ t('printLayout.shareOffer') }}
          </EButton>
          <EButton
            v-if="canConfirmShare"
            variant="primary"
            size="small"
            @click="shareSelected"
          >
            {{ t('printLayout.shareConfirm') }}
          </EButton>
          <span v-if="waitingForOtherMw" class="meta">{{ t('printLayout.shareWaiting') }}</span>
          <EButton v-if="canCopySelected" variant="secondary" size="small" @click="copySelected">
            {{ t('printLayout.copyToDepartment') }}
          </EButton>
          <EButton v-if="canEditSelected" variant="text" size="small" @click="saveFields">{{ t('common.save') }}</EButton>
          <EButton v-if="canEditSelected" variant="danger" size="small" @click="removeSelected">{{ t('common.remove') }}</EButton>
        </div>
        <div class="field-picks">
          <span class="field-picks__label">{{ t('printLayout.fieldsTitle') }}</span>
          <ECheckbox
            v-for="preset in fieldPresets"
            :key="preset.id"
            :model-value="fieldIsOn(preset.id)"
            hide-details
            :disabled="!canEditSelected"
            :label="t(`printLayout.field.${preset.id}`)"
            @update:model-value="onFieldToggle(preset.id, $event)"
          />
        </div>
        <p class="muted">{{ t('printLayout.fieldsHint') }}</p>
        <div v-if="canEditSelected && selectedFieldId" class="size-row">
          <EButton variant="secondary" size="small" @click="scaleSelected(0.85)">
            {{ t('printJob.fieldSmaller') }}
          </EButton>
          <EButton variant="secondary" size="small" @click="scaleSelected(1.15)">
            {{ t('printJob.fieldBigger') }}
          </EButton>
        </div>

        <div class="sheet-wrap" :style="{ aspectRatio: sheetAspect }">
          <canvas ref="underlayEl" class="underlay" />
          <svg
            class="sheet"
            :viewBox="`0 0 ${selected.sheet.sheet_width_mm} ${selected.sheet.sheet_height_mm}`"
            role="img"
            :aria-label="t('printLayout.sheetAria')"
          >
            <rect
              v-for="cell in selected.cells"
              :key="cell.index"
              :x="cell.x"
              :y="cell.y"
              :width="cell.w"
              :height="cell.h"
              :rx="selected.sheet.shape === 'round' ? cell.w / 2 : 0"
              class="cell"
              :class="{ 'cell--start': cell.index === startIndex }"
              @click="startIndex = cell.index"
            />
            <PrintFieldBoxes
              :fields="selected.fields"
              :cell-x="previewCell.x"
              :cell-y="previewCell.y"
              :cell-w="previewCell.w"
              :cell-h="previewCell.h"
              :editable="canEditSelected"
              :selected-id="selectedFieldId"
              @update:fields="onEditorFields"
              @update:selected-id="selectedFieldId = $event"
            />
          </svg>
        </div>
        <p class="muted">{{ t('printLayout.startHint', { n: startIndex + 1, total: selected.cells.length }) }}</p>
        <p v-if="isTscDesktop" class="muted">{{ t('printLayout.tscHint') }}</p>

        <div v-if="isBrotherQl" class="ql-row">
          <EButton variant="secondary" size="small" :disabled="!usbOk" @click="connectQl">
            {{ t('printLayout.connectQl') }}
          </EButton>
          <span v-if="qlStatus" class="meta">{{ t('printLayout.qlStatus', { width: qlStatus.mediaWidthMm ?? '–', type: qlStatus.mediaType }) }}</span>
          <span v-else-if="!usbOk" class="meta">{{ t('printLayout.usbHint') }}</span>
        </div>

        <div class="print-row">
          <EButton variant="primary" size="small" :loading="printing" @click="printSample">{{ t('printLayout.printSample') }}</EButton>
          <EButton v-if="qlDevice && isBrotherQl" variant="secondary" size="small" :loading="printing" @click="printQlSample">
            {{ t('printLayout.printQl') }}
          </EButton>
        </div>
      </div>
    </div>
    </template>

    <EDialog v-model="createOpen" :title="t('printLayout.add')" :max-width="720">
      <div class="dialog-grid">
        <ETextField v-model="createForm.name" :label="t('printLayout.name')" hide-details />
        <ESearchField v-model="createQuery" :label="t('printLayout.search')" hide-details />
        <div class="field-picks">
          <span class="field-picks__label">{{ t('printLayout.fieldsTitle') }}</span>
          <ECheckbox
            v-for="preset in fieldPresets"
            :key="'c-' + preset.id"
            :model-value="createForm.fields.includes(preset.id)"
            hide-details
            :label="t(`printLayout.field.${preset.id}`)"
            @update:model-value="toggleCreateField(preset.id, $event)"
          />
        </div>
        <p class="muted">{{ t('printLayout.pickPaper') }}</p>
        <div class="brand-bar" role="group" :aria-label="t('common.manufacturer')">
          <button
            type="button"
            class="brand-chip"
            :class="{ 'is-on': !createBrandFilter }"
            @click="createBrandFilter = ''"
          >
            {{ t('printLayout.allBrands') }}
          </button>
          <button
            v-for="brand in mediaBrands"
            :key="'c-' + brand"
            type="button"
            class="brand-chip"
            :class="{ 'is-on': createBrandFilter === brand }"
            @click="createBrandFilter = brand"
          >
            {{ brand }}
          </button>
        </div>
        <div class="dialog-paper">
          <p v-if="groupedCreateMedia.length === 0" class="muted">{{ t('printLayout.noSearchHits') }}</p>
          <div v-for="group in groupedCreateMedia" :key="'cd-' + group.brand" class="brand-group">
            <h4 v-if="showCreateBrandHeadings" class="brand-heading">{{ group.brand }}</h4>
            <div class="media-grid">
              <PrintMediaCard
                v-for="item in group.items"
                :key="'c-' + item.id"
                :media="item"
                :selected="createForm.media_id === item.id"
                @select="createForm.media_id = item.id"
              />
            </div>
          </div>
        </div>
        <ECheckbox v-model="createForm.request_global" :label="t('printLayout.shareOfferNow')" hide-details />
      </div>
      <template #actions>
        <EButton variant="text" @click="createOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="!createForm.name.trim() || !createForm.media_id" @click="createLayout">
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="duplicateOpen" :title="t('printLayout.duplicateTitle')" :max-width="560">
      <p class="muted">{{ t('printLayout.duplicateHint') }}</p>
      <ul class="dup-list">
        <li v-for="dup in duplicates" :key="dup.id" class="dup-item">
          <div>
            <strong>{{ dup.name }}</strong>
            <span class="meta">{{ dup.media_name }}</span>
            <span class="chip" :class="dup.global_requested ? 'chip--pending' : dup.scope === 'organisation' ? 'chip--org' : ''">
              {{ dup.global_requested ? t('printLayout.statusOffered') : dup.scope === 'global' ? t('printLayout.scopeAllMw') : t('printLayout.scopeOrg') }}
            </span>
          </div>
          <div class="dup-actions">
            <EButton variant="text" size="small" @click="openExistingLayout(dup.id)">{{ t('printLayout.openLayout') }}</EButton>
            <EButton v-if="dup.has_template" variant="text" size="small" @click="openTemplatePdf(dup.id)">
              {{ t('printLayout.openPdf') }}
            </EButton>
            <EButton
              v-if="allowManage && dup.scope !== 'global' && dup.created_by_user_id !== authStore.userId"
              variant="primary"
              size="small"
              @click="shareLayout(dup.id)"
            >
              {{ t('printLayout.shareConfirm') }}
            </EButton>
          </div>
        </li>
      </ul>
      <template #actions>
        <EButton variant="text" @click="duplicateOpen = false">{{ t('printLayout.duplicateKeep') }}</EButton>
      </template>
    </EDialog>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EButton from '@/components/form/base/EButton.vue'
import ECheckbox from '@/components/form/base/ECheckbox.vue'
import EDialog from '@/components/form/base/EDialog.vue'
import ESearchField from '@/components/form/base/ESearchField.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import PrintMediaCard from '@/components/print/PrintMediaCard.vue'
import PrintFieldBoxes from '@/components/print/PrintFieldBoxes.vue'
import { useAuthStore } from '@/stores/auth'
import { getDepartmentPrintCatalog, getDepartmentPrintPresets, type PrintDeviceModel, type PrintMedia } from '@/api/printCatalog'
import {
  copyDepartmentPrintLayout,
  createDepartmentPrintLayout,
  deleteDepartmentPrintLayout,
  fetchPrintLayoutTemplateBytes,
  listDepartmentPrintLayouts,
  requestDepartmentPrintLayoutGlobal,
  updateDepartmentPrintLayout,
  uploadPrintLayoutTemplate,
  type PrintLayout,
  type PrintLayoutDuplicate,
  type PrintLayoutField,
} from '@/api/printLayouts'
import { printCanvasToBrotherQl, readBrotherQlStatus, requestBrotherQlDevice, webUsbSupported, type BrotherQlStatus } from '@/print/brotherQlUsb'
import { defaultLayoutFields, fieldEnabled, LAYOUT_FIELD_PRESETS, toggleLayoutField, type LayoutFieldKey } from '@/print/layoutFields'
import { scaleField } from '@/print/layoutFieldGeom'
import { mediaCompatibleWithAnyModel, uniqueModelsFromPresets } from '@/print/mediaCompatibility'
import { buildLayoutPdf, downloadPdfBytes, renderPdfPageToCanvas } from '@/print/renderPrintLayout'

const props = defineProps<{ departmentId: string; embedded?: boolean }>()

const { t } = useI18n()
const toast = useToast()
const authStore = useAuthStore()

const loading = ref(false)
const printing = ref(false)
const loadError = ref('')
const layouts = ref<PrintLayout[]>([])
const media = ref<PrintMedia[]>([])
const printerModels = ref<PrintDeviceModel[]>([])
const selected = ref<PrintLayout | null>(null)
const selectedFieldId = ref('qr')
const startIndex = ref(0)
const createOpen = ref(false)
const duplicateOpen = ref(false)
const duplicates = ref<PrintLayoutDuplicate[]>([])
const includeTemplate = ref(false)
const underlayEl = ref<HTMLCanvasElement | null>(null)
const usbOk = webUsbSupported()
const qlDevice = ref<USBDevice | null>(null)
const qlStatus = ref<BrotherQlStatus | null>(null)

const createForm = reactive({
  name: '',
  media_id: '',
  request_global: false,
  fields: ['qr', 'title', 'code'] as LayoutFieldKey[],
})
const fieldPresets = LAYOUT_FIELD_PRESETS
const brandFilter = ref('')
const createBrandFilter = ref('')
const layoutQuery = ref('')
const createQuery = ref('')
const catalogOpen = ref(false)
const hasPrinters = computed(() => printerModels.value.length > 0)

const canPropose = ref(false)
const allowManage = computed(() => canPropose.value)
const isOwnDepartment = computed(
  () => !!selected.value && selected.value.department_id === props.departmentId,
)
const isCreator = computed(
  () => !!selected.value && selected.value.created_by_user_id === authStore.userId,
)
const canEditSelected = computed(
  () => allowManage.value && isOwnDepartment.value && selected.value?.scope !== 'global',
)
const canCopySelected = computed(
  () => allowManage.value && !!selected.value && selected.value.department_id !== props.departmentId,
)
const canOfferShare = computed(
  () => allowManage.value && !!selected.value && selected.value.scope !== 'global' && isCreator.value && !selected.value.global_requested,
)
const canConfirmShare = computed(
  () => allowManage.value && !!selected.value && selected.value.scope !== 'global' && !isCreator.value,
)
const waitingForOtherMw = computed(
  () => allowManage.value && !!selected.value && selected.value.scope !== 'global' && isCreator.value && selected.value.global_requested,
)
const previewCell = computed(() => {
  const layout = selected.value
  if (!layout) return { x: 0, y: 0, w: 1, h: 1, col: 0, row: 0, index: 0 }
  return layout.cells[startIndex.value] || layout.cells[0]
})
const sheetAspect = computed(() => {
  const spec = selected.value?.sheet
  if (!spec) return '1 / 1'
  return `${spec.sheet_width_mm} / ${spec.sheet_height_mm}`
})
const selectedFamily = computed(() => selected.value?.media.family || '')
const isOfficeSheet = computed(() => selectedFamily.value === 'office_a4')
const isBrotherQl = computed(() => selectedFamily.value === 'brother_ql')
const isTscDesktop = computed(() => selectedFamily.value === 'tsc_desktop')

function mediaBrand(item: PrintMedia) {
  const brand = (item.brand || '').trim()
  return brand || t('printLayout.brandUnknown')
}

function compareBrandThenName(a: PrintMedia, b: PrintMedia) {
  const brand = mediaBrand(a).localeCompare(mediaBrand(b), 'de')
  if (brand !== 0) return brand
  return a.name.localeCompare(b.name, 'de')
}

function mediaSearchText(item: PrintMedia) {
  return [item.name, item.sku, item.brand, item.catalog_key, String(item.width_mm), String(item.height_mm ?? '')].join(' ')
}

function matchesSearch(text: string, query: string) {
  const q = query.trim().toLowerCase()
  if (!q) return true
  return q.split(/\s+/).every((part) => text.toLowerCase().includes(part))
}

function groupMedia(items: PrintMedia[], filter: string, query = '') {
  const filtered = items
    .filter((item) => (!filter || mediaBrand(item) === filter) && matchesSearch(mediaSearchText(item), query))
    .slice()
    .sort(compareBrandThenName)
  const groups = new Map<string, PrintMedia[]>()
  for (const item of filtered) {
    const brand = mediaBrand(item)
    const list = groups.get(brand) || []
    list.push(item)
    groups.set(brand, list)
  }
  return [...groups.entries()].map(([brand, groupItems]) => ({ brand, items: groupItems }))
}

const mediaBrands = computed(() => {
  const set = new Set(media.value.map((item) => mediaBrand(item)))
  return [...set].sort((a, b) => a.localeCompare(b, 'de'))
})
const groupedMedia = computed(() => groupMedia(media.value, brandFilter.value, layoutQuery.value))
const groupedCreateMedia = computed(() => groupMedia(media.value, createBrandFilter.value, createQuery.value))
const groupedLayouts = computed(() => {
  const filtered = layouts.value
    .filter((item) => {
      const hay = [item.name, mediaSearchText(item.media), layoutScopeLabel(item)].join(' ')
      return matchesSearch(hay, layoutQuery.value)
    })
    .slice()
    .sort((a, b) => {
      const brand = mediaBrand(a.media).localeCompare(mediaBrand(b.media), 'de')
      if (brand !== 0) return brand
      return a.name.localeCompare(b.name, 'de')
    })
  const groups = new Map<string, typeof layouts.value>()
  for (const item of filtered) {
    const brand = mediaBrand(item.media)
    const list = groups.get(brand) || []
    list.push(item)
    groups.set(brand, list)
  }
  return [...groups.entries()].map(([brand, items]) => ({ brand, items }))
})
const showBrandHeadings = computed(() => !brandFilter.value && groupedMedia.value.length > 1)
const showLayoutBrandHeadings = computed(() => groupedLayouts.value.length > 1)
const showCreateBrandHeadings = computed(() => !createBrandFilter.value && groupedCreateMedia.value.length > 1)

function onCatalogToggle(event: Event) {
  const el = event.target
  if (el instanceof HTMLDetailsElement) catalogOpen.value = el.open
}

watch(mediaBrands, (brands) => {
  if (brandFilter.value && !brands.includes(brandFilter.value)) brandFilter.value = ''
})

watch(layoutQuery, (query) => {
  const q = query.trim()
  if (!q) return
  const catalogHits = groupMedia(media.value, '', q)
  if (catalogHits.length === 0) return
  catalogOpen.value = true
  if (groupedMedia.value.length === 0) brandFilter.value = ''
})

async function load() {
  if (!props.departmentId) return
  loading.value = true
  loadError.value = ''
  try {
    const [nextLayouts, catalog, presets] = await Promise.all([
      listDepartmentPrintLayouts(props.departmentId),
      getDepartmentPrintCatalog(props.departmentId),
      getDepartmentPrintPresets(props.departmentId),
    ])
    printerModels.value = uniqueModelsFromPresets(presets)
    const models = printerModels.value
    layouts.value = nextLayouts
      .filter((item) => mediaCompatibleWithAnyModel(models, item.media))
      .slice()
      .sort((a, b) => {
        const aMine = a.department_id === props.departmentId ? 0 : 1
        const bMine = b.department_id === props.departmentId ? 0 : 1
        if (aMine !== bMine) return aMine - bMine
        return a.name.localeCompare(b.name, 'de')
      })
    media.value = (catalog.published_media || []).filter((item) => mediaCompatibleWithAnyModel(models, item))
    canPropose.value = catalog.can_propose
    if (selected.value) {
      selected.value = layouts.value.find((item) => item.id === selected.value?.id) || layouts.value[0] || null
    } else {
      selected.value = layouts.value[0] || null
    }
    includeTemplate.value = selected.value?.include_template_on_print || false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('printLayout.loadError')
  } finally {
    loading.value = false
  }
}

function selectLayout(item: PrintLayout) {
  selected.value = item
  includeTemplate.value = item.include_template_on_print
  startIndex.value = 0
}

function nextDesignName(mediaName: string): string {
  const base = mediaName.trim() || t('printLayout.add')
  const names = new Set(
    layouts.value
      .filter((layout) => layout.department_id === props.departmentId)
      .map((layout) => layout.name),
  )
  if (!names.has(base)) return base
  let n = 2
  while (names.has(`${base} ${n}`)) n += 1
  return `${base} ${n}`
}

function pickPaper(item: PrintMedia) {
  openCreate(item)
}

function openCreate(mediaItem?: PrintMedia) {
  createForm.name = mediaItem ? nextDesignName(mediaItem.name) : ''
  createForm.media_id = mediaItem?.id || ''
  createForm.request_global = false
  createForm.fields = ['qr', 'title', 'code']
  createBrandFilter.value = ''
  createQuery.value = ''
  createOpen.value = true
}

function fieldIsOn(id: LayoutFieldKey) {
  return fieldEnabled(selected.value?.fields || [], id)
}

async function onFieldToggle(id: LayoutFieldKey, on: boolean | unknown) {
  if (!selected.value || !canEditSelected.value) return
  const enable = on === true
  if (!enable && selected.value.fields.length <= 1) return
  selected.value.fields = toggleLayoutField(selected.value.fields, id, enable)
  await saveFields(true)
}

function toggleCreateField(id: LayoutFieldKey, on: boolean | unknown) {
  if (on === true) {
    if (!createForm.fields.includes(id)) createForm.fields.push(id)
  } else {
    createForm.fields = createForm.fields.filter((item) => item !== id)
  }
}

async function loadUnderlay() {
  const canvas = underlayEl.value
  const layout = selected.value
  if (!canvas || !layout?.has_template) {
    if (canvas) {
      canvas.width = 1
      canvas.height = 1
    }
    return
  }
  try {
    const bytes = await fetchPrintLayoutTemplateBytes(props.departmentId, layout.id)
    const width = canvas.parentElement?.clientWidth || 480
    await renderPdfPageToCanvas(bytes, canvas, width)
  } catch {
    /* preview optional */
  }
}

async function createLayout() {
  try {
    const created = await createDepartmentPrintLayout(props.departmentId, {
      name: createForm.name.trim(),
      media_id: createForm.media_id,
      request_global: createForm.request_global,
      fields: defaultLayoutFields(createForm.fields),
    })
    createOpen.value = false
    createForm.name = ''
    createForm.media_id = ''
    toast.success(t('printLayout.saveSuccess'))
    await load()
    selected.value = created
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function saveFields(silent?: boolean) {
  if (!selected.value) return
  try {
    selected.value = await updateDepartmentPrintLayout(props.departmentId, selected.value.id, {
      fields: selected.value.fields,
      include_template_on_print: includeTemplate.value,
    })
    if (silent !== true) toast.success(t('printLayout.saveSuccess'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function persistInclude() {
  if (!selected.value || !canEditSelected.value) return
  try {
    selected.value = await updateDepartmentPrintLayout(props.departmentId, selected.value.id, {
      include_template_on_print: includeTemplate.value,
    })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function onPdf(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !selected.value) return
  try {
    const result = await uploadPrintLayoutTemplate(props.departmentId, selected.value.id, file)
    const { duplicate_templates: found = [], ...layout } = result
    selected.value = layout
    toast.success(t('printLayout.pdfOk'))
    await loadUnderlay()
    if (found.length > 0) {
      duplicates.value = found
      duplicateOpen.value = true
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function shareLayout(layoutId: string) {
  try {
    const next = await requestDepartmentPrintLayoutGlobal(props.departmentId, layoutId)
    if (selected.value?.id === layoutId) selected.value = next
    if (next.scope === 'global') {
      toast.success(t('printLayout.shareConfirmSuccess'))
    } else {
      toast.success(t('printLayout.shareOfferSuccess'))
    }
    duplicateOpen.value = false
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function shareSelected() {
  if (!selected.value) return
  await shareLayout(selected.value.id)
}

async function copySelected() {
  if (!selected.value) return
  try {
    const copy = await copyDepartmentPrintLayout(props.departmentId, selected.value.id)
    toast.success(t('printLayout.copySuccess'))
    await load()
    selected.value = copy
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

function openExistingLayout(layoutId: string) {
  const found = layouts.value.find((item) => item.id === layoutId)
  if (found) selectLayout(found)
  duplicateOpen.value = false
}

async function openTemplatePdf(layoutId: string) {
  try {
    const bytes = await fetchPrintLayoutTemplateBytes(props.departmentId, layoutId)
    const url = URL.createObjectURL(new Blob([new Uint8Array(bytes)], { type: 'application/pdf' }))
    window.open(url, '_blank', 'noopener')
  } catch {
    toast.error(t('printLayout.saveError'))
  }
}

function layoutScopeLabel(item: PrintLayout) {
  if (item.global_requested) return t('printLayout.statusOffered')
  if (item.scope === 'global') return t('printLayout.scopeAllMw')
  if (item.department_id === props.departmentId) return t('printLayout.scopeMine')
  return t('printLayout.scopeOrg')
}

function layoutChipClass(item: PrintLayout) {
  if (item.global_requested) return 'chip--pending'
  if (item.scope === 'organisation') return 'chip--org'
  return ''
}

async function removeSelected() {
  if (!selected.value) return
  try {
    await deleteDepartmentPrintLayout(props.departmentId, selected.value.id)
    selected.value = null
    toast.success(t('printLayout.deleted'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

function onEditorFields(next: PrintLayoutField[]) {
  if (!selected.value) return
  selected.value.fields = next
}

function scaleSelected(factor: number) {
  const layout = selected.value
  const id = selectedFieldId.value
  if (!layout || !id || !canEditSelected.value) return
  layout.fields = layout.fields.map((item) => (item.id === id ? scaleField(item, factor) : item))
}

async function templateBytes(): Promise<ArrayBuffer | null> {
  if (!selected.value?.has_template) return null
  try {
    return await fetchPrintLayoutTemplateBytes(props.departmentId, selected.value.id)
  } catch {
    return null
  }
}

async function printSample() {
  if (!selected.value) return
  printing.value = true
  try {
    const bytes = await buildLayoutPdf({
      layout: selected.value,
      startIndex: startIndex.value,
      templateBytes: await templateBytes(),
      items: [
        { label: 'Beispiel Material', public_url: 'https://app.ematchef.test/i/demo', public_code: 'DEMO-001' },
        { label: 'Zweites Etikett', public_url: 'https://app.ematchef.test/i/demo2', public_code: 'DEMO-002' },
      ],
    })
    downloadPdfBytes(bytes, `${selected.value.name}.pdf`)
    toast.success(t('printLayout.pdfReady'))
  } catch (e: unknown) {
    toast.error((e as Error).message || t('printLayout.saveError'))
  } finally {
    printing.value = false
  }
}

async function connectQl() {
  try {
    const device = await requestBrotherQlDevice()
    qlDevice.value = device
    qlStatus.value = await readBrotherQlStatus(device)
    toast.success(t('printLayout.qlConnected'))
  } catch (e: unknown) {
    toast.error((e as Error).message || t('printLayout.qlError'))
  }
}

async function printQlSample() {
  if (!qlDevice.value || !selected.value) return
  printing.value = true
  try {
    const cell = previewCell.value
    const canvas = document.createElement('canvas')
    const dpi = 300
    canvas.width = Math.max(32, Math.round((cell.w / 25.4) * dpi))
    canvas.height = Math.max(32, Math.round((cell.h / 25.4) * dpi))
    const ctx = canvas.getContext('2d')
    if (!ctx) throw new Error('Canvas')
    ctx.fillStyle = '#fff'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.fillStyle = '#111'
    ctx.font = `${Math.round(canvas.height * 0.12)}px sans-serif`
    ctx.fillText('eMatChef', 8, canvas.height / 2)
    await printCanvasToBrotherQl(qlDevice.value, canvas)
    toast.success(t('printLayout.qlSent'))
  } catch (e: unknown) {
    toast.error((e as Error).message || t('printLayout.qlError'))
  } finally {
    printing.value = false
  }
}

watch(
  () => selected.value?.id,
  async () => {
    const n = selected.value?.cells.length || 1
    if (startIndex.value >= n) startIndex.value = 0
    await nextTick()
    await loadUnderlay()
  },
)
watch(() => props.departmentId, () => { void load() })
onMounted(() => { void load() })

defineExpose({ layouts, selected, startIndex, load })
</script>

<style scoped>
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.card h2 { margin: 0; font-size: 16px; }
.head-hint { margin: 0; }
.muted { color: #6b7280; font-size: 14px; }
.error { color: #b91c1c; }
.layout-work { display: flex; flex-direction: column; gap: 16px; margin-top: 12px; }
.picker-block { display: flex; flex-direction: column; gap: 10px; }
.layout-subacc {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  overflow: hidden;
}
.layout-subacc__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  cursor: pointer;
  list-style: none;
  user-select: none;
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}
.layout-subacc__summary::-webkit-details-marker { display: none; }
.layout-subacc__chevron { flex-shrink: 0; color: #64748b; transition: transform 0.15s ease; }
.layout-subacc[open] .layout-subacc__chevron { transform: rotate(180deg); }
.layout-subacc__body { padding: 0 12px 12px; border-top: 1px solid #e5e7eb; }
.layout-subacc__body .brand-bar { margin-top: 10px; }
.brand-bar { display: flex; flex-wrap: wrap; gap: 6px; }
.brand-chip {
  border: 1px solid #d1d5db;
  background: #fff;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 650;
  color: #334155;
  cursor: pointer;
}
.brand-chip.is-on { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; }
.brand-group { margin-top: 8px; }
.brand-heading { margin: 0 0 6px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
  gap: 8px;
}
.dialog-paper { max-height: 320px; overflow: auto; padding: 2px; }
.field-picks { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-bottom: 10px; }
.field-picks__label { font-size: 13px; font-weight: 700; color: #334155; }
.size-row { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 10px; }
.layout-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.layout-pick { width: 100%; text-align: left; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px; background: #fff; cursor: pointer; }
.layout-item.is-on .layout-pick { border-color: #10b981; background: #ecfdf5; }
.meta { display: block; color: #64748b; font-size: 12px; }
.chip { display: inline-block; margin-top: 4px; font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; }
.chip--org { background: #e0e7ff; color: #3730a3; }
.chip--pending { background: #ffedd5; color: #c2410c; }
.editor-toolbar, .ql-row, .print-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
.sheet-wrap { position: relative; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f8fafc; }
.underlay { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: fill; opacity: 0.45; pointer-events: none; }
.sheet { position: relative; width: 100%; height: auto; display: block; background: transparent; }
.cell { fill: rgba(255,255,255,0.35); stroke: #94a3b8; stroke-width: 0.35; cursor: pointer; }
.cell--start { stroke: #059669; stroke-width: 0.9; fill: rgba(16,185,129,0.12); }
.file-btn { display: inline-flex; align-items: center; border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 10px; font-size: 13px; cursor: pointer; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
.dialog-grid { display: flex; flex-direction: column; gap: 12px; }
.dup-list { list-style: none; margin: 12px 0 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.dup-item { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
.dup-actions { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; }
@media (max-width: 800px) {
  .media-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
}
</style>
