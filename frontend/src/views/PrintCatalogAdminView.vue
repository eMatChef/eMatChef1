<template>
  <div class="print-catalog-admin">
    <div class="header">
      <h1>{{ t('printCatalogAdmin.title') }}</h1>
      <p class="description">{{ t('printCatalogAdmin.description') }}</p>
    </div>

    <ELoadingState v-if="loading" variant="inline" :message="t('printCatalogAdmin.loading')" />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>

    <template v-else>
      <div class="toolbar">
        <EButton v-if="catalog?.is_superadmin" variant="primary" size="small" @click="createKind = 'model'">
          {{ t('printCatalogAdmin.addModel') }}
        </EButton>
        <EButton v-if="catalog?.is_superadmin" variant="secondary" size="small" @click="createKind = 'media'">
          {{ t('printCatalogAdmin.addMedia') }}
        </EButton>
      </div>

      <section class="card">
        <h2>{{ t('printCatalogAdmin.pendingTitle') }}</h2>
        <p v-if="pendingModels.length + pendingMedia.length + pendingLayouts.length === 0" class="muted">
          {{ t('printCatalogAdmin.pendingEmpty') }}
        </p>
        <ul v-else class="list">
          <li v-for="model in pendingModels" :key="'m-' + model.id" class="row">
            <div>
              <span class="chip">{{ t('printSettings.status.pending') }}</span>
              <strong>{{ model.brand }} {{ model.name }}</strong>
              <span class="meta">{{ familyLabel(model.family) }}</span>
            </div>
            <div class="row-actions">
              <EButton variant="primary" size="small" @click="review('model', model.id, 'approve')">
                {{ t('printCatalogAdmin.approveGlobal') }}
              </EButton>
              <EButton variant="danger" size="small" @click="review('model', model.id, 'reject')">
                {{ t('printCatalogAdmin.reject') }}
              </EButton>
            </div>
          </li>
          <li v-for="media in pendingMedia" :key="'e-' + media.id" class="row">
            <div>
              <span class="chip">{{ t('printSettings.status.pending') }}</span>
              <strong>{{ media.name }}</strong>
              <span class="meta">{{ media.sku }} · {{ familyLabel(media.family) }}</span>
            </div>
            <div class="row-actions">
              <EButton variant="primary" size="small" @click="review('media', media.id, 'approve')">
                {{ t('printCatalogAdmin.approveGlobal') }}
              </EButton>
              <EButton variant="danger" size="small" @click="review('media', media.id, 'reject')">
                {{ t('printCatalogAdmin.reject') }}
              </EButton>
            </div>
          </li>
          <li v-for="layout in pendingLayouts" :key="'l-' + layout.id" class="row">
            <div>
              <span class="chip">{{ t('printSettings.status.pending') }}</span>
              <strong>{{ layout.name }}</strong>
              <span class="meta">{{ layout.media.name }}</span>
            </div>
            <div class="row-actions">
              <EButton variant="primary" size="small" @click="review('layout', layout.id, 'approve')">
                {{ t('printCatalogAdmin.approveGlobal') }}
              </EButton>
              <EButton variant="danger" size="small" @click="review('layout', layout.id, 'reject')">
                {{ t('printCatalogAdmin.reject') }}
              </EButton>
            </div>
          </li>
        </ul>
      </section>

      <section class="card">
        <h2>{{ t('printCatalogAdmin.modelsTitle') }}</h2>
        <ul class="list">
          <li v-for="model in publishedModels" :key="model.id" class="row">
            <div>
              <span class="chip" :class="{ 'chip--org': model.scope === 'organisation' }">
                {{ t(`printSettings.scope.${model.scope}`) }}
              </span>
              <strong>{{ model.brand }} {{ model.name }}</strong>
              <span class="meta">{{ familyLabel(model.family) }}</span>
            </div>
            <EButton
              v-if="catalog?.is_superadmin && model.scope === 'organisation'"
              variant="text"
              size="small"
              @click="review('model', model.id, 'promote_global')"
            >
              {{ t('printCatalogAdmin.promoteGlobal') }}
            </EButton>
          </li>
        </ul>
      </section>

      <section class="card">
        <h2>{{ t('printCatalogAdmin.mediaTitle') }}</h2>
        <ul class="list">
          <li v-for="media in publishedMedia" :key="media.id" class="row">
            <div>
              <span class="chip" :class="{ 'chip--org': media.scope === 'organisation' }">
                {{ t(`printSettings.scope.${media.scope}`) }}
              </span>
              <strong>{{ media.name }}</strong>
              <span class="meta">
                {{ media.sku }}
                · {{ media.width_mm }}×{{ media.height_mm ?? '∞' }} mm
              </span>
            </div>
            <EButton
              v-if="catalog?.is_superadmin && media.scope === 'organisation'"
              variant="text"
              size="small"
              @click="review('media', media.id, 'promote_global')"
            >
              {{ t('printCatalogAdmin.promoteGlobal') }}
            </EButton>
          </li>
        </ul>
      </section>

      <section class="card">
        <h2>{{ t('printCatalogAdmin.layoutsTitle') }}</h2>
        <p v-if="publishedLayouts.length === 0" class="muted">{{ t('printCatalogAdmin.layoutsEmpty') }}</p>
        <ul v-else class="list">
          <li v-for="layout in publishedLayouts" :key="layout.id" class="row">
            <div>
              <span class="chip" :class="{ 'chip--org': layout.scope === 'organisation' }">
                {{ t(`printSettings.scope.${layout.scope}`) }}
              </span>
              <strong>{{ layout.name }}</strong>
              <span class="meta">{{ layout.media.name }}</span>
            </div>
            <EButton
              v-if="catalog?.is_superadmin && layout.scope === 'organisation'"
              variant="text"
              size="small"
              @click="review('layout', layout.id, 'promote_global')"
            >
              {{ t('printCatalogAdmin.promoteGlobal') }}
            </EButton>
          </li>
        </ul>
      </section>
    </template>

    <EDialog
      v-model="createOpen"
      :title="createKind === 'model' ? t('printCatalogAdmin.addModel') : t('printCatalogAdmin.addMedia')"
      :max-width="520"
    >
      <div class="dialog-grid">
        <ESelect v-model="form.family" :label="t('printSettings.family')" :items="familyItems" hide-details />
        <ETextField v-model="form.brand" :label="t('printSettings.brand')" hide-details />
        <ETextField v-if="createKind === 'model'" v-model="form.name" :label="t('printSettings.modelName')" hide-details />
        <template v-else>
          <ETextField v-model="form.sku" :label="t('printSettings.sku')" hide-details />
          <ETextField v-model="form.name" :label="t('printSettings.mediaName')" hide-details />
          <ETextField v-model="form.width_mm" type="number" :label="t('printSettings.widthMm')" hide-details />
          <ECheckbox v-model="form.is_continuous" :label="t('printSettings.continuous')" hide-details />
          <ETextField
            v-if="!form.is_continuous"
            v-model="form.height_mm"
            type="number"
            :label="t('printSettings.heightMm')"
            hide-details
          />
        </template>
      </div>
      <template #actions>
        <EButton variant="text" @click="createKind = ''">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :loading="saving" @click="submitCreate">{{ t('common.save') }}</EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EButton from '@/components/form/base/EButton.vue'
import ECheckbox from '@/components/form/base/ECheckbox.vue'
import EDialog from '@/components/form/base/EDialog.vue'
import ESelect from '@/components/form/base/ESelect.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  createAdminPrintMedia,
  createAdminPrintModel,
  getAdminPrintCatalog,
  reviewAdminPrintMedia,
  reviewAdminPrintModel,
  type AdminPrintCatalog,
} from '@/api/printCatalog'
import { reviewAdminPrintLayout } from '@/api/printLayouts'

const { t } = useI18n()
const toast = useToast()

const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const catalog = ref<AdminPrintCatalog | null>(null)
const createKind = ref<'' | 'model' | 'media'>('')

const form = reactive({
  family: 'brother_ql',
  brand: '',
  name: '',
  sku: '',
  width_mm: '62',
  height_mm: '29',
  is_continuous: false,
})

const createOpen = computed({
  get: () => createKind.value !== '',
  set: (open: boolean) => {
    if (!open) createKind.value = ''
  },
})

const familyItems = computed(() =>
  (catalog.value?.families || []).map((item) => ({ title: item.label, value: item.id })),
)

const pendingModels = computed(() =>
  (catalog.value?.models || []).filter((item) => item.status === 'pending' || item.global_requested),
)
const pendingMedia = computed(() =>
  (catalog.value?.media || []).filter((item) => item.status === 'pending' || item.global_requested),
)
const pendingLayouts = computed(() =>
  (catalog.value?.layouts || []).filter((item) => item.status === 'pending' || item.global_requested),
)
const publishedModels = computed(() =>
  (catalog.value?.models || []).filter((item) => item.status === 'published' && !item.global_requested),
)
const publishedMedia = computed(() =>
  (catalog.value?.media || []).filter((item) => item.status === 'published' && !item.global_requested),
)
const publishedLayouts = computed(() =>
  (catalog.value?.layouts || []).filter((item) => item.status === 'published' && !item.global_requested),
)

function familyLabel(id: string): string {
  return catalog.value?.families.find((item) => item.id === id)?.label || id
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    catalog.value = await getAdminPrintCatalog()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('printCatalogAdmin.loadError')
  } finally {
    loading.value = false
  }
}

async function review(kind: 'model' | 'media' | 'layout', id: string, action: 'approve' | 'reject' | 'promote_global') {
  try {
    if (kind === 'model') await reviewAdminPrintModel(id, action)
    else if (kind === 'media') await reviewAdminPrintMedia(id, action)
    else await reviewAdminPrintLayout(id, action)
    toast.success(t('printCatalogAdmin.reviewSuccess'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printCatalogAdmin.saveError'))
  }
}

async function submitCreate() {
  saving.value = true
  try {
    if (createKind.value === 'model') {
      await createAdminPrintModel({
        family: form.family,
        brand: form.brand.trim(),
        name: form.name.trim(),
      })
    } else {
      await createAdminPrintMedia({
        family: form.family,
        brand: form.brand.trim(),
        sku: form.sku.trim(),
        name: form.name.trim(),
        width_mm: Number(form.width_mm),
        height_mm: form.is_continuous ? null : Number(form.height_mm),
        is_continuous: form.is_continuous,
      })
    }
    createKind.value = ''
    toast.success(t('printCatalogAdmin.saveSuccess'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printCatalogAdmin.saveError'))
  } finally {
    saving.value = false
  }
}

onMounted(() => { void load() })
</script>

<style scoped>
.print-catalog-admin { display: flex; flex-direction: column; gap: 16px; }
.header h1 { margin: 0; font-size: 24px; }
.description, .muted { color: #6b7280; margin: 4px 0 0; }
.error { color: #b91c1c; }
.toolbar { display: flex; gap: 8px; flex-wrap: wrap; }
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.card h2 { margin: 0 0 12px; font-size: 16px; }
.list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.row { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.meta { display: block; color: #64748b; font-size: 13px; margin-top: 2px; }
.chip {
  display: inline-block;
  margin-right: 8px;
  font-size: 11px;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
  background: #dbeafe;
  color: #1d4ed8;
}
.chip--org { background: #ffedd5; color: #c2410c; }
.row-actions { display: flex; flex-wrap: wrap; gap: 4px; }
.dialog-grid { display: flex; flex-direction: column; gap: 12px; }
</style>
