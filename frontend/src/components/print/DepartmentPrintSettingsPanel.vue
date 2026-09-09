<template>
  <div class="print-settings-panel">
    <div class="header">
      <h1>{{ t('printSettings.title') }}</h1>
      <p class="description">{{ t('printSettings.description') }}</p>
    </div>

    <ELoadingState v-if="loading" variant="inline" :message="t('printSettings.loading')" />

    <p v-else-if="loadError" class="error">{{ loadError }}</p>

    <template v-else>
      <p v-if="showDevices && !catalog?.can_manage_presets" class="muted">{{ t('printSettings.noPermission') }}</p>

      <details v-if="showDevices && useAccordions" class="info-card print-accordion" :open="openAccordion === 'printers'">
        <summary class="print-accordion__summary" @click="onAccordionClick('printers', $event)">
          <span class="print-accordion__title">{{ t('printSettings.accordionPrinters') }}</span>
          <span class="print-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="print-accordion__body">
          <PrinterFavoritesBlock
            :catalog="catalog"
            :presets="presets"
            @add="openPresetDialog()"
            @edit="openPresetDialog"
            @remove="removePreset"
            @default="setDefault"
            @propose-model="proposeKind = 'model'"
            @propose-media="proposeKind = 'media'"
            @request-global="requestGlobal"
          />
        </div>
      </details>

      <section v-else-if="showDevices" class="card">
        <PrinterFavoritesBlock
          :catalog="catalog"
          :presets="presets"
          @add="openPresetDialog()"
          @edit="openPresetDialog"
          @remove="removePreset"
          @default="setDefault"
          @propose-model="proposeKind = 'model'"
          @propose-media="proposeKind = 'media'"
          @request-global="requestGlobal"
        />
      </section>

      <details v-if="showLayouts && useAccordions && departmentId" class="info-card print-accordion" :open="openAccordion === 'layouts'">
        <summary class="print-accordion__summary" @click="onAccordionClick('layouts', $event)">
          <span class="print-accordion__title">{{ t('printSettings.accordionLayouts') }}</span>
          <span class="print-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="print-accordion__body">
          <PrintLayoutEditor :department-id="departmentId" embedded />
        </div>
      </details>
      <PrintLayoutEditor
        v-else-if="showLayouts && departmentId"
        :department-id="departmentId"
      />
    </template>

    <EDialog v-model="presetDialogOpen" :title="presetEdit ? t('printSettings.editFavorite') : t('printSettings.addFavorite')" :max-width="680">
      <div class="dialog-grid">
        <ETextField v-model="presetForm.name" :label="t('printSettings.favoriteName')" hide-details />
        <ESelect
          v-model="presetForm.family"
          :label="t('printSettings.family')"
          :items="familyItems"
          hide-details
          @update:model-value="onFamilyChange"
        />
        <ESelect
          v-model="presetForm.device_model_id"
          :label="t('printSettings.device')"
          :items="modelItems"
          :disabled="!presetForm.family"
          hide-details
          @update:model-value="onModelChange"
        />
        <p v-if="hideMediaPicker" class="muted">{{ t('printSettings.officePrinterHint') }}</p>
        <template v-else>
          <p class="muted">{{ t('printLayout.pickPaper') }}</p>
          <div v-if="presetForm.device_model_id" class="media-grid">
            <PrintMediaCard
              v-for="item in mediaForModel"
              :key="item.id"
              :media="item"
              :cut-length-mm="item.is_continuous ? Number(presetForm.cut_length_mm) : null"
              :selected="presetForm.media_id === item.id"
              @select="presetForm.media_id = item.id"
            />
          </div>
          <p v-else class="muted">{{ t('printSettings.pickDeviceFirst') }}</p>
          <ETextField
            v-if="selectedMedia?.is_continuous"
            v-model="presetForm.cut_length_mm"
            type="number"
            :label="t('printSettings.cutLength')"
            hide-details
          />
        </template>
        <ECheckbox v-model="presetForm.is_default" :label="t('printSettings.markDefault')" hide-details />
      </div>
      <template #actions>
        <EButton variant="text" @click="presetDialogOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :loading="saving" :disabled="!canSavePreset" @click="savePreset">
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="proposeOpen"
      :title="proposeKind === 'model' ? t('printSettings.proposeModel') : t('printSettings.proposeMedia')"
      :max-width="520"
    >
      <div class="dialog-grid">
        <ESelect v-model="proposeForm.family" :label="t('printSettings.family')" :items="familyItems" hide-details />
        <ETextField v-model="proposeForm.brand" :label="t('printSettings.brand')" hide-details />
        <ETextField v-if="proposeKind === 'model'" v-model="proposeForm.name" :label="t('printSettings.modelName')" hide-details />
        <template v-else>
          <ETextField v-model="proposeForm.sku" :label="t('printSettings.sku')" hide-details />
          <ETextField v-model="proposeForm.name" :label="t('printSettings.mediaName')" hide-details />
          <ETextField v-model="proposeForm.width_mm" type="number" :label="t('printSettings.widthMm')" hide-details />
          <ECheckbox v-model="proposeForm.is_continuous" :label="t('printSettings.continuous')" hide-details />
          <ETextField
            v-if="!proposeForm.is_continuous"
            v-model="proposeForm.height_mm"
            type="number"
            :label="t('printSettings.heightMm')"
            hide-details
          />
          <ETextField
            v-else
            v-model="proposeForm.default_cut_length_mm"
            type="number"
            :label="t('printSettings.cutLength')"
            hide-details
          />
        </template>
        <ECheckbox v-model="proposeForm.request_global" :label="t('printSettings.requestGlobalNow')" hide-details />
      </div>
      <template #actions>
        <EButton variant="text" @click="proposeKind = ''">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :loading="saving" @click="submitPropose">
          {{ t('printSettings.submitPropose') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EButton from '@/components/form/base/EButton.vue'
import ECheckbox from '@/components/form/base/ECheckbox.vue'
import EDialog from '@/components/form/base/EDialog.vue'
import ESelect from '@/components/form/base/ESelect.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import PrintLayoutEditor from '@/components/print/PrintLayoutEditor.vue'
import PrintMediaCard from '@/components/print/PrintMediaCard.vue'
import PrinterFavoritesBlock from '@/components/print/PrinterFavoritesBlock.vue'
import {
  createDepartmentPrintPreset,
  deleteDepartmentPrintPreset,
  getDepartmentPrintCatalog,
  getDepartmentPrintPresets,
  proposeDepartmentPrintMedia,
  proposeDepartmentPrintModel,
  requestDepartmentPrintMediaGlobal,
  requestDepartmentPrintModelGlobal,
  updateDepartmentPrintPreset,
  type DepartmentPrintCatalog,
  type DepartmentPrintPreset,
} from '@/api/printCatalog'
import { defaultMediaForModel, mediaCompatibleWithModel } from '@/print/mediaCompatibility'

const props = defineProps<{
  departmentId: string
  sections?: 'all' | 'devices' | 'layouts'
}>()

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const catalog = ref<DepartmentPrintCatalog | null>(null)
const presets = ref<DepartmentPrintPreset[]>([])
const presetDialogOpen = ref(false)
const presetEdit = ref<DepartmentPrintPreset | null>(null)
const proposeKind = ref<'' | 'model' | 'media'>('')

const presetForm = reactive({
  name: '',
  family: '',
  device_model_id: '',
  media_id: '',
  cut_length_mm: '55',
  is_default: false,
})

const proposeForm = reactive({
  family: 'brother_ql',
  brand: '',
  name: '',
  sku: '',
  width_mm: '38',
  height_mm: '29',
  is_continuous: true,
  default_cut_length_mm: '55',
  request_global: false,
})

const proposeOpen = computed({
  get: () => proposeKind.value !== '',
  set: (open: boolean) => {
    if (!open) proposeKind.value = ''
  },
})

const showDevices = computed(() => props.sections !== 'layouts')
const showLayouts = computed(() => props.sections !== 'devices')
const useAccordions = computed(() => showDevices.value && showLayouts.value)
const openAccordion = ref<'printers' | 'layouts' | ''>('layouts')

function onAccordionClick(id: 'printers' | 'layouts', event: MouseEvent) {
  event.preventDefault()
  openAccordion.value = openAccordion.value === id ? '' : id
}

const familyItems = computed(() =>
  (catalog.value?.families || []).map((item) => ({ title: item.label, value: item.id })),
)

const modelsForFamily = computed(() =>
  (catalog.value?.published_models || []).filter((model) => !presetForm.family || model.family === presetForm.family),
)

const modelItems = computed(() =>
  modelsForFamily.value.map((model) => ({
    title: `${model.brand} ${model.name}`,
    value: model.id,
  })),
)

const selectedModel = computed(() =>
  (catalog.value?.published_models || []).find((model) => model.id === presetForm.device_model_id) || null,
)

const mediaForModel = computed(() => {
  const model = selectedModel.value
  if (!model) return []
  return (catalog.value?.published_media || []).filter((media) => mediaCompatibleWithModel(model, media))
})

const hideMediaPicker = computed(() => presetForm.family === 'office_a4')

const selectedMedia = computed(() =>
  (catalog.value?.published_media || []).find((media) => media.id === presetForm.media_id) || null,
)

const canSavePreset = computed(() =>
  Boolean(presetForm.name.trim() && presetForm.device_model_id && presetForm.media_id),
)

function onFamilyChange() {
  presetForm.device_model_id = ''
  presetForm.media_id = ''
}

function onModelChange() {
  presetForm.media_id = ''
  const model = selectedModel.value
  if (!model) return
  const preferred = defaultMediaForModel(model, catalog.value?.published_media || [])
  if (preferred) presetForm.media_id = preferred.id
}

function openPresetDialog(preset?: DepartmentPrintPreset) {
  presetEdit.value = preset || null
  if (preset) {
    presetForm.name = preset.name
    presetForm.family = preset.device_model.family
    presetForm.device_model_id = preset.device_model_id
    presetForm.media_id = preset.media_id
    presetForm.cut_length_mm = String(preset.cut_length_mm || preset.media.default_cut_length_mm || 55)
    presetForm.is_default = preset.is_default
  } else {
    presetForm.name = ''
    presetForm.family = catalog.value?.families[0]?.id || ''
    presetForm.device_model_id = ''
    presetForm.media_id = ''
    presetForm.cut_length_mm = '55'
    presetForm.is_default = presets.value.length === 0
  }
  presetDialogOpen.value = true
}

async function load() {
  if (!props.departmentId) return
  loading.value = true
  loadError.value = ''
  try {
    const [nextCatalog, nextPresets] = await Promise.all([
      getDepartmentPrintCatalog(props.departmentId),
      getDepartmentPrintPresets(props.departmentId),
    ])
    catalog.value = nextCatalog
    presets.value = nextPresets
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('printSettings.loadError')
  } finally {
    loading.value = false
    if (route.query.add === '1' && catalog.value?.can_manage_presets) {
      openAccordion.value = 'printers'
      openPresetDialog()
      const nextQuery = { ...route.query }
      delete nextQuery.add
      void router.replace({ query: nextQuery })
    }
  }
}

async function savePreset() {
  if (!props.departmentId || !canSavePreset.value) return
  saving.value = true
  try {
    const payload = {
      name: presetForm.name.trim(),
      device_model_id: presetForm.device_model_id,
      media_id: presetForm.media_id,
      cut_length_mm: selectedMedia.value?.is_continuous ? Number(presetForm.cut_length_mm) : null,
      is_default: presetForm.is_default,
    }
    if (presetEdit.value) {
      await updateDepartmentPrintPreset(props.departmentId, presetEdit.value.id, payload)
    } else {
      await createDepartmentPrintPreset(props.departmentId, payload)
    }
    presetDialogOpen.value = false
    toast.success(t('printSettings.saveSuccess'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printSettings.saveError'))
  } finally {
    saving.value = false
  }
}

async function setDefault(preset: DepartmentPrintPreset) {
  try {
    await updateDepartmentPrintPreset(props.departmentId, preset.id, { is_default: true })
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printSettings.saveError'))
  }
}

async function removePreset(preset: DepartmentPrintPreset) {
  try {
    await deleteDepartmentPrintPreset(props.departmentId, preset.id)
    toast.success(t('printSettings.deleted'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printSettings.saveError'))
  }
}

async function submitPropose() {
  if (!props.departmentId) return
  saving.value = true
  try {
    if (proposeKind.value === 'model') {
      await proposeDepartmentPrintModel(props.departmentId, {
        family: proposeForm.family,
        brand: proposeForm.brand.trim(),
        name: proposeForm.name.trim(),
        request_global: proposeForm.request_global,
      })
    } else {
      await proposeDepartmentPrintMedia(props.departmentId, {
        family: proposeForm.family,
        brand: proposeForm.brand.trim(),
        sku: proposeForm.sku.trim(),
        name: proposeForm.name.trim(),
        width_mm: Number(proposeForm.width_mm),
        height_mm: proposeForm.is_continuous ? null : Number(proposeForm.height_mm),
        is_continuous: proposeForm.is_continuous,
        default_cut_length_mm: proposeForm.is_continuous ? Number(proposeForm.default_cut_length_mm) : null,
        request_global: proposeForm.request_global,
      })
    }
    proposeKind.value = ''
    toast.success(
      proposeForm.request_global ? t('printSettings.proposeSuccessReview') : t('printSettings.proposeSuccess'),
    )
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printSettings.saveError'))
  } finally {
    saving.value = false
  }
}

async function requestGlobal(kind: 'model' | 'media', id: string) {
  if (!props.departmentId) return
  try {
    if (kind === 'model') await requestDepartmentPrintModelGlobal(props.departmentId, id)
    else await requestDepartmentPrintMediaGlobal(props.departmentId, id)
    toast.success(t('printSettings.requestGlobalSuccess'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printSettings.saveError'))
  }
}

watch(() => props.departmentId, () => { void load() })
onMounted(() => { void load() })
</script>

<style scoped>
.print-settings-panel { display: flex; flex-direction: column; gap: 16px; }
.header h1 { margin: 0; font-size: 24px; }
.description { margin: 4px 0 0; color: #6b7280; }
.muted { color: #6b7280; font-size: 14px; margin: 0 0 12px; }
.error { color: #b91c1c; }
.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
.info-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}
.print-accordion { padding: 0; overflow: hidden; }
.print-accordion__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  cursor: pointer;
  list-style: none;
  user-select: none;
}
.print-accordion__summary::-webkit-details-marker { display: none; }
.print-accordion__title { font-size: 1rem; font-weight: 650; color: #0f172a; }
.print-accordion__chevron { flex-shrink: 0; color: #64748b; transition: transform 0.15s ease; }
.print-accordion[open] .print-accordion__chevron { transform: rotate(180deg); }
.print-accordion__body { padding: 0 16px 16px; border-top: 1px solid #e5e7eb; }
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
  gap: 8px;
  max-height: 340px;
  overflow: auto;
}
.dialog-grid { display: flex; flex-direction: column; gap: 12px; padding: 4px 0 8px; }
</style>
