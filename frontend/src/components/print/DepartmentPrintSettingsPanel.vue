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

      <section v-if="showDevices" class="card">
        <div class="card-head">
          <h2>{{ t('printSettings.favoritesTitle') }}</h2>
          <EButton
            v-if="catalog?.can_manage_presets"
            variant="primary"
            size="small"
            @click="openPresetDialog()"
          >
            {{ t('printSettings.addFavorite') }}
          </EButton>
        </div>

        <EEmptyState
          v-if="presets.length === 0"
          variant="generic"
          compact
          :title="t('printSettings.favoritesEmptyTitle')"
          :description="t('printSettings.favoritesEmpty')"
          icon="mdi-printer-outline"
        />

        <ul v-else class="preset-list">
          <li v-for="preset in presets" :key="preset.id" class="preset-row">
            <div>
              <strong>{{ preset.name }}</strong>
              <span v-if="preset.is_default" class="chip">{{ t('printSettings.defaultChip') }}</span>
              <p class="meta">
                {{ preset.device_model.brand }} {{ preset.device_model.name }}
                · {{ preset.media.name }}
                <template v-if="preset.media.is_continuous && preset.cut_length_mm">
                  · {{ t('printSettings.cutLengthValue', { mm: preset.cut_length_mm }) }}
                </template>
              </p>
            </div>
            <div v-if="catalog?.can_manage_presets" class="row-actions">
              <EButton
                v-if="!preset.is_default"
                variant="text"
                size="small"
                @click="setDefault(preset)"
              >
                {{ t('printSettings.setDefault') }}
              </EButton>
              <EButton variant="text" size="small" @click="openPresetDialog(preset)">
                {{ t('common.edit') }}
              </EButton>
              <EButton variant="danger" size="small" @click="removePreset(preset)">
                {{ t('common.remove') }}
              </EButton>
            </div>
          </li>
        </ul>
      </section>

      <section v-if="showDevices && catalog?.can_propose" class="card">
        <h2>{{ t('printSettings.proposeTitle') }}</h2>
        <p class="muted">{{ t('printSettings.proposeHint') }}</p>
        <div class="row-actions">
          <EButton variant="secondary" size="small" @click="proposeKind = 'model'">
            {{ t('printSettings.proposeModel') }}
          </EButton>
          <EButton variant="secondary" size="small" @click="proposeKind = 'media'">
            {{ t('printSettings.proposeMedia') }}
          </EButton>
        </div>
        <ul v-if="ownOrgItems.length" class="pending-list">
          <li v-for="item in ownOrgItems" :key="item.id">
            <span class="chip" :class="item.global_requested ? 'chip--pending' : 'chip--org'">
              {{ item.global_requested ? t('printSettings.status.pendingGlobal') : t('printSettings.scope.organisation') }}
            </span>
            {{ item.label }}
            <EButton
              v-if="!item.global_requested"
              variant="text"
              size="small"
              @click="requestGlobal(item.kind, item.rawId)"
            >
              {{ t('printSettings.requestGlobal') }}
            </EButton>
          </li>
        </ul>
      </section>
    </template>

    <PrintLayoutEditor
      v-if="showLayouts && departmentId"
      :department-id="departmentId"
    />

    <EDialog v-model="presetDialogOpen" :title="presetEdit ? t('printSettings.editFavorite') : t('printSettings.addFavorite')" :max-width="520">
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
        <ESelect
          v-model="presetForm.media_id"
          :label="t('printSettings.media')"
          :items="mediaItems"
          :disabled="!presetForm.device_model_id"
          hide-details
        />
        <ETextField
          v-if="selectedMedia?.is_continuous"
          v-model="presetForm.cut_length_mm"
          type="number"
          :label="t('printSettings.cutLength')"
          hide-details
        />
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
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EButton from '@/components/form/base/EButton.vue'
import ECheckbox from '@/components/form/base/ECheckbox.vue'
import EDialog from '@/components/form/base/EDialog.vue'
import ESelect from '@/components/form/base/ESelect.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import PrintLayoutEditor from '@/components/print/PrintLayoutEditor.vue'
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

const props = defineProps<{
  departmentId: string
  sections?: 'all' | 'devices' | 'layouts'
}>()

const { t } = useI18n()
const toast = useToast()

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
  return (catalog.value?.published_media || []).filter((media) => {
    if (media.family !== model.family) return false
    if (!model.compatible_media_keys.length) return true
    return model.compatible_media_keys.includes(media.catalog_key)
  })
})

const mediaItems = computed(() =>
  mediaForModel.value.map((media) => ({ title: media.name, value: media.id })),
)

const selectedMedia = computed(() =>
  (catalog.value?.published_media || []).find((media) => media.id === presetForm.media_id) || null,
)

const canSavePreset = computed(() =>
  Boolean(presetForm.name.trim() && presetForm.device_model_id && presetForm.media_id),
)

const ownOrgItems = computed(() => {
  const models = (catalog.value?.models || [])
    .filter((item) => item.scope === 'organisation')
    .map((item) => ({
      id: `m-${item.id}`,
      rawId: item.id,
      kind: 'model' as const,
      label: `${item.brand} ${item.name}`,
      global_requested: item.global_requested,
    }))
  const media = (catalog.value?.media || [])
    .filter((item) => item.scope === 'organisation')
    .map((item) => ({
      id: `e-${item.id}`,
      rawId: item.id,
      kind: 'media' as const,
      label: item.name,
      global_requested: item.global_requested,
    }))
  return [...models, ...media]
})

function onFamilyChange() {
  presetForm.device_model_id = ''
  presetForm.media_id = ''
}

function onModelChange() {
  presetForm.media_id = ''
  const first = mediaForModel.value[0]
  if (first) presetForm.media_id = first.id
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
.card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
.card h2 { margin: 0 0 8px; font-size: 16px; }
.preset-list, .pending-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.pending-list li { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
.preset-row { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
.preset-row:last-child { border-bottom: 0; padding-bottom: 0; }
.meta { margin: 4px 0 0; color: #64748b; font-size: 13px; }
.chip {
  display: inline-block;
  margin-left: 8px;
  font-size: 11px;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
  background: #dcfce7;
  color: #166534;
}
.chip--pending { background: #ffedd5; color: #c2410c; margin-right: 8px; margin-left: 0; }
.chip--org { background: #e0e7ff; color: #3730a3; margin-right: 8px; margin-left: 0; }
.row-actions { display: flex; flex-wrap: wrap; gap: 4px; }
.dialog-grid { display: flex; flex-direction: column; gap: 12px; padding: 4px 0 8px; }
</style>
