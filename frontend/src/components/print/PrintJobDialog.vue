<template>
  <EDialog v-model="open" :title="t('printJob.title')" :max-width="880" :retain-focus="false">
    <ELoadingState v-if="loading" variant="inline" :message="t('printJob.loading')" />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <div v-else class="job-body" :class="{ 'job-body--plain': !hasPrinters }">
      <div class="job-grid">
        <p v-if="items.length" class="muted">{{ t('printJob.itemCount', items.length) }}</p>
        <div v-if="!hasPrinters" class="empty-printers">
          <p class="muted">{{ t('printJob.noPrinters') }}</p>
          <router-link
            class="setup-link"
            :to="addPrinterTo"
            @click="store.close()"
          >
            {{ t('printJob.addPrinterLink') }}
          </router-link>
        </div>
        <template v-else>
          <ESelect
            v-model="printerId"
            :label="t('printJob.printer')"
            :items="printerItems"
            hide-details
          />
          <ESelect
            v-model="layoutId"
            :label="t('printJob.designPick')"
            :items="layoutItems"
            :disabled="!printerId"
            hide-details
          />
          <p v-if="printerId && layoutsForPrinter.length === 0" class="muted">{{ t('printJob.noLayouts') }}</p>
          <label v-if="selectedLayout && selectedLayout.cells.length > 1" class="start-field">
            <span>{{ t('tasksPrint.startCell') }}</span>
            <input
              v-model.number="startCell"
              type="number"
              min="1"
              :max="selectedLayout.cells.length"
            />
          </label>
          <div class="design-picks">
            <span class="field-picks__label">{{ t('printJob.styleTitle') }}</span>
            <button
              type="button"
              class="design-chip"
              :class="{ 'is-on': face.design === 'label' }"
              @click="face.design = 'label'"
            >
              {{ t('printJob.designLabel') }}
            </button>
            <button
              type="button"
              class="design-chip"
              :class="{ 'is-on': face.design === 'badge' }"
              @click="face.design = 'badge'"
            >
              {{ t('printJob.designBadge') }}
            </button>
          </div>
          <div class="field-picks">
            <ECheckbox v-model="face.color" hide-details :label="t('printJob.designColor')" />
            <ECheckbox v-model="face.rounded" hide-details :label="t('printJob.designRounded')" />
          </div>
          <div class="field-picks">
            <span class="field-picks__label">{{ t('printLayout.fieldsTitle') }}</span>
            <ECheckbox
              v-for="id in availableFields"
              :key="id"
              :model-value="fieldKeys.includes(id)"
              hide-details
              :label="t(`printLayout.field.${id}`)"
              @update:model-value="onFieldToggle(id, $event)"
            />
          </div>
          <p class="muted">{{ t('printJob.fieldsHint') }}</p>
          <div v-if="face.design === 'label'" class="size-row">
            <EButton variant="secondary" size="small" :disabled="!jobFields.length" @click="scaleSelected(0.85)">
              {{ t('printJob.fieldSmaller') }}
            </EButton>
            <EButton variant="secondary" size="small" :disabled="!jobFields.length" @click="scaleSelected(1.15)">
              {{ t('printJob.fieldBigger') }}
            </EButton>
          </div>
          <p v-if="face.design === 'label'" class="muted">{{ t('printJob.arrangeHint') }}</p>
          <p v-else class="muted">{{ t('printJob.arrangeBadgeHint') }}</p>
          <p v-if="items.length > 1 && selectedLayout && selectedLayout.cells.length > 1" class="muted">
            {{ t('printJob.sheetHint') }}
          </p>
          <p v-if="continueHint" class="muted">{{ continueHint }}</p>
          <p v-if="isBrotherQl" class="muted">{{ t('printJob.qlHint') }}</p>
          <p v-else-if="selectedLayout" class="muted">{{ t('printJob.officeHint') }}</p>
        </template>
      </div>
      <PrintJobPreview
        v-if="selectedPrinter || selectedLayout"
        :printer="selectedPrinter"
        :layout="selectedLayout"
        :items="items"
        :start-cell="startCell"
        :enabled-fields="fieldKeys"
        :face="face"
        :fields="jobFields"
        :editable="face.design === 'label'"
        :selected-field-id="selectedFieldId"
        @update:start-cell="startCell = $event"
        @update:fields="jobFields = $event"
        @update:selected-id="selectedFieldId = $event"
      />
    </div>
    <template #actions>
      <EButton variant="text" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton v-if="!loading && !hasPrinters" variant="primary" @click="goAddPrinter">
        {{ t('printJob.addPrinter') }}
      </EButton>
      <EButton
        v-if="!loading && hasPrinters && canQueue"
        variant="secondary"
        :loading="queuing"
        :disabled="!canPrint || printing"
        @click="addToCart"
      >
        {{ t('printJob.addToCart') }}
      </EButton>
      <EButton
        v-if="!loading && hasPrinters"
        variant="primary"
        :loading="printing"
        :disabled="!canPrint || queuing"
        @click="runPrint"
      >
        {{ t('common.print') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EButton from '@/components/form/base/EButton.vue'
import ECheckbox from '@/components/form/base/ECheckbox.vue'
import EDialog from '@/components/form/base/EDialog.vue'
import ESelect from '@/components/form/base/ESelect.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import PrintJobPreview from '@/components/print/PrintJobPreview.vue'
import { getDepartmentPrintPresets, type DepartmentPrintPreset } from '@/api/printCatalog'
import { listDepartmentPrintLayouts, type PrintLayout, type PrintLayoutField } from '@/api/printLayouts'
import { isBrotherQlLayout } from '@/print/printCartLayout'
import { defaultFieldsFor, executePrintJob } from '@/print/printJob'
import { layoutKeysFromContent, layoutWithEnabledFields, type PrintContentKey } from '@/print/layoutFields'
import { cloneLayoutFields, scaleField } from '@/print/layoutFieldGeom'
import {
  fieldsStorageKey,
  layoutStorageKey,
  loadNextStartCell,
  loadPrintFace,
  loadJobFieldBoxes,
  printerStorageKey,
  saveNextStartCell,
  savePrintChoiceLabels,
  savePrintFace,
  saveJobFieldBoxes,
} from '@/print/printChoice'
import { defaultPrintFace, type PrintFace } from '@/print/printFace'
import { nextStartCell } from '@/print/sheetPlacement'
import { mediaCompatibleWithModel, uniquePresetsByDevice } from '@/print/mediaCompatibility'
import type { AddPrintCartItemRequest } from '@/api/tasks'
import { usePrintJobStore } from '@/stores/printJob'
import { usePrintCartStore } from '@/stores/printCart'
import { usePrintCart } from '@/composables/usePrintCart'

const store = usePrintJobStore()
const printCartStore = usePrintCartStore()
const { addItems: addToPrintCart } = usePrintCart()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()

const loading = ref(false)
const printing = ref(false)
const queuing = ref(false)
const loadError = ref('')
const presets = ref<DepartmentPrintPreset[]>([])
const layouts = ref<PrintLayout[]>([])
const printerId = ref('')
const layoutId = ref('')
const startCell = ref(1)
const fieldKeys = ref<PrintContentKey[]>(['qr', 'title', 'code'])
const face = ref<PrintFace>(defaultPrintFace('label'))
const jobFields = ref<PrintLayoutField[]>([])
const selectedFieldId = ref('')

const open = computed({
  get: () => store.isOpen,
  set: (value: boolean) => {
    if (!value) store.close()
  },
})
const items = computed(() => store.items)
const availableFields = computed(() => store.availableFields)
const printers = computed(() => uniquePresetsByDevice(presets.value))
const hasPrinters = computed(() => printers.value.length > 0)
const addPrinterTo = computed(() => ({
  name: 'SettingsPrint' as const,
  params: { departmentId: store.departmentId },
  query: { add: '1' },
}))
const selectedPrinter = computed(
  () => printers.value.find((item) => item.device_model_id === printerId.value) || null,
)
const layoutsForPrinter = computed(() => {
  const model = selectedPrinter.value?.device_model
  if (!model) return []
  return layouts.value.filter((item) => mediaCompatibleWithModel(model, item.media))
})
const selectedLayout = computed(
  () => layoutsForPrinter.value.find((item) => item.id === layoutId.value) || null,
)
const isBrotherQl = computed(() => !!selectedLayout.value && isBrotherQlLayout(selectedLayout.value))
const printerItems = computed(() =>
  printers.value.map((item) => ({
    title: `${item.name} · ${item.device_model.brand} ${item.device_model.name}`,
    value: item.device_model_id,
  })),
)
const layoutItems = computed(() =>
  layoutsForPrinter.value.map((item) => ({
    title: `${item.name} · ${item.media.name}`,
    value: item.id,
  })),
)
const canPrint = computed(
  () => !!selectedLayout.value && items.value.length > 0 && !printing.value && !queuing.value && fieldKeys.value.length > 0,
)
const canQueue = computed(
  () => items.value.length > 0 && items.value.every((item) => !!item.cart),
)
const continueHint = computed(() => {
  const layout = selectedLayout.value
  if (!layout || isBrotherQl.value || layout.cells.length <= 1) return ''
  const start = Math.max(1, startCell.value)
  if (start <= 1) return ''
  const next = nextStartCell(items.value.length, layout.cells.length, start - 1)
  return t('printJob.continueHint', { start, next, total: layout.cells.length })
})

function onFieldToggle(id: PrintContentKey, on: boolean | unknown) {
  if (on === true) {
    if (!fieldKeys.value.includes(id)) fieldKeys.value = [...fieldKeys.value, id]
    return
  }
  if (fieldKeys.value.length <= 1) return
  fieldKeys.value = fieldKeys.value.filter((item) => item !== id)
}

function applyStartCellForLayout(layout: PrintLayout) {
  const n = layout.cells.length
  if (n <= 1) {
    startCell.value = 1
    return
  }
  const stored = loadNextStartCell(store.departmentId, layout.id)
  startCell.value = stored ? Math.min(Math.max(1, stored), n) : 1
}

function loadJobFieldsForLayout(layout: PrintLayout) {
  const stored = loadJobFieldBoxes(store.departmentId, layout.id)
  const base = stored?.length ? stored : cloneLayoutFields(layout.fields)
  jobFields.value = layoutWithEnabledFields(
    { ...layout, fields: base },
    layoutKeysFromContent(fieldKeys.value),
  ).fields
  if (!jobFields.value.some((item) => item.id === selectedFieldId.value)) {
    selectedFieldId.value = jobFields.value[0]?.id || ''
  }
}

function persistJobFields(layout: PrintLayout) {
  saveJobFieldBoxes(store.departmentId, layout.id, jobFields.value)
}

function scaleSelected(factor: number) {
  if (face.value.design !== 'label' || !jobFields.value.length) return
  if (!jobFields.value.some((item) => item.id === selectedFieldId.value)) {
    selectedFieldId.value = jobFields.value[0]?.id || ''
  }
  const id = selectedFieldId.value
  jobFields.value = jobFields.value.map((item) => (item.id === id ? scaleField(item, factor) : item))
}

function persistChoiceLabels() {
  const layout = selectedLayout.value
  const printer = selectedPrinter.value
  if (!store.departmentId || !layout || !printer) return
  savePrintChoiceLabels(store.departmentId, {
    printer: `${printer.name} · ${printer.device_model.brand} ${printer.device_model.name}`,
    layout: `${layout.name} · ${layout.media.name}`,
  })
  printCartStore.syncFormat(store.departmentId)
  savePrintFace(store.departmentId, store.kind, face.value)
}

function applyStoredChoices() {
  const dept = store.departmentId
  const storedPrinter = localStorage.getItem(printerStorageKey(dept)) || ''
  const defaultPrinter =
    printers.value.find((item) => item.device_model_id === storedPrinter)
    || printers.value.find((item) => item.is_default)
    || printers.value[0]
  printerId.value = defaultPrinter?.device_model_id || ''
  const storedLayout = localStorage.getItem(layoutStorageKey(dept)) || ''
  const match = layoutsForPrinter.value.find((item) => item.id === storedLayout)
  layoutId.value = match?.id || layoutsForPrinter.value[0]?.id || ''
  const storedFieldsRaw = localStorage.getItem(fieldsStorageKey(dept, store.kind)) || ''
  const storedFields = storedFieldsRaw
    .split(',')
    .map((item) => item.trim())
    .filter((item): item is PrintContentKey => availableFields.value.includes(item as PrintContentKey))
  fieldKeys.value = storedFields.length ? storedFields : defaultFieldsFor(availableFields.value)
  face.value = loadPrintFace(dept, store.kind)
  const layout = selectedLayout.value
  if (layout) {
    applyStartCellForLayout(layout)
    loadJobFieldsForLayout(layout)
  }
  persistChoiceLabels()
}

async function load() {
  if (!store.departmentId) return
  loading.value = true
  loadError.value = ''
  try {
    const [nextPresets, nextLayouts] = await Promise.all([
      getDepartmentPrintPresets(store.departmentId),
      listDepartmentPrintLayouts(store.departmentId),
    ])
    presets.value = nextPresets
    layouts.value = nextLayouts.filter((item) => item.status === 'published')
    applyStoredChoices()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('printJob.loadError')
  } finally {
    loading.value = false
  }
}

async function runPrint() {
  const layout = selectedLayout.value
  if (!layout || !canPrint.value) return
  printing.value = true
  try {
    const mode = await executePrintJob({
      departmentId: store.departmentId,
      layout,
      items: items.value,
      enabledFields: fieldKeys.value,
      startIndex: Math.max(0, startCell.value - 1),
      face: face.value,
      fields: jobFields.value,
    })
    toast.success(mode === 'ql' ? t('printLayout.qlSent') : t('printJob.printOk'))
    localStorage.setItem(printerStorageKey(store.departmentId), printerId.value)
    localStorage.setItem(layoutStorageKey(store.departmentId), layout.id)
    localStorage.setItem(fieldsStorageKey(store.departmentId, store.kind), fieldKeys.value.join(','))
    persistJobFields(layout)
    if (!isBrotherQlLayout(layout) && layout.cells.length > 1) {
      saveNextStartCell(
        store.departmentId,
        layout.id,
        nextStartCell(items.value.length, layout.cells.length, Math.max(0, startCell.value - 1)),
      )
    }
    persistChoiceLabels()
    const after = store.onPrinted
    store.close()
    if (after) await after()
  } catch (e: unknown) {
    toast.error((e as Error).message || t('printJob.printError'))
  } finally {
    printing.value = false
  }
}

async function addToCart() {
  const layout = selectedLayout.value
  if (!layout || !canQueue.value || !canPrint.value) return
  const payloads = items.value
    .map((item) => item.cart)
    .filter((row): row is AddPrintCartItemRequest => !!row)
  if (!payloads.length) return
  queuing.value = true
  try {
    localStorage.setItem(printerStorageKey(store.departmentId), printerId.value)
    localStorage.setItem(layoutStorageKey(store.departmentId), layout.id)
    localStorage.setItem(fieldsStorageKey(store.departmentId, store.kind), fieldKeys.value.join(','))
    persistJobFields(layout)
    persistChoiceLabels()
    const ok = await addToPrintCart(payloads)
    if (ok) store.close()
  } finally {
    queuing.value = false
  }
}

function goAddPrinter() {
  const to = addPrinterTo.value
  store.close()
  if (!to.params.departmentId) return
  void router.push(to)
}

watch(
  () => store.isOpen,
  (value) => {
    if (value) void load()
  },
)

watch(printerId, () => {
  if (!layoutsForPrinter.value.some((item) => item.id === layoutId.value)) {
    layoutId.value = layoutsForPrinter.value[0]?.id || ''
  }
})

watch(layoutId, () => {
  const layout = selectedLayout.value
  if (!layout || loading.value || !store.isOpen) return
  applyStartCellForLayout(layout)
  loadJobFieldsForLayout(layout)
})

watch(fieldKeys, () => {
  const layout = selectedLayout.value
  if (!layout || !store.isOpen) return
  jobFields.value = layoutWithEnabledFields(
    { ...layout, fields: jobFields.value.length ? jobFields.value : layout.fields },
    layoutKeysFromContent(fieldKeys.value),
  ).fields
})

watch(selectedLayout, (layout) => {
  if (!layout) return
  if (startCell.value > layout.cells.length) startCell.value = 1
  if (store.isOpen && !loading.value) persistChoiceLabels()
})

watch(selectedPrinter, () => {
  if (store.isOpen && !loading.value) persistChoiceLabels()
})

watch(face, () => {
  if (store.isOpen && !loading.value) persistChoiceLabels()
}, { deep: true })
</script>

<style scoped>
.job-body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(220px, 280px);
  gap: 20px;
  align-items: start;
}
.job-body--plain {
  grid-template-columns: 1fr;
}
.job-grid { display: flex; flex-direction: column; gap: 12px; }
.muted { margin: 0; color: #6b7280; font-size: 13px; }
.empty-printers {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
}
.setup-link {
  color: #15803d;
  font-size: 14px;
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 3px;
}
.error { color: #b91c1c; }
.start-field { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: #4b5563; }
.start-field input {
  min-height: 32px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 4px 8px;
}
.field-picks { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; }
.field-picks__label { font-size: 13px; font-weight: 700; color: #334155; }
.size-row { display: flex; flex-wrap: wrap; gap: 8px; }
.design-picks {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.design-chip {
  border: 1px solid #d1d5db;
  background: #fff;
  border-radius: 999px;
  padding: 4px 12px;
  font-size: 13px;
  color: #334155;
  cursor: pointer;
}
.design-chip.is-on {
  border-color: #16a34a;
  background: #ecfdf3;
  color: #166534;
  font-weight: 700;
}
@media (max-width: 720px) {
  .job-body { grid-template-columns: 1fr; }
}
</style>
