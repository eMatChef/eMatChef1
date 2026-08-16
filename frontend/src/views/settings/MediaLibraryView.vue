<template>
  <div class="media-browser">
    <div class="settings-header">
      <div>
        <h1>{{ t('settings.media.title') }}</h1>
        <p class="subtitle">{{ t('settings.media.subtitle') }}</p>
      </div>
    </div>

    <div class="media-browser-toolbar">
      <div class="media-browser-tabs" role="tablist">
        <button
          type="button"
          role="tab"
          class="media-browser-tab"
          :class="{ 'media-browser-tab--active': kind === 'photos' }"
          :aria-selected="kind === 'photos'"
          @click="kind = 'photos'"
        >
          {{ t('settings.media.tabPhotos') }}
        </button>
        <button
          type="button"
          role="tab"
          class="media-browser-tab"
          :class="{ 'media-browser-tab--active': kind === 'documents' }"
          :aria-selected="kind === 'documents'"
          @click="kind = 'documents'"
        >
          {{ t('settings.media.tabDocuments') }}
        </button>
      </div>
      <ESearchField v-model="searchQuery" :label="t('settings.media.searchPlaceholder')" />
      <ESelect
        v-model="contextFilter"
        :items="contextItems"
        :label="t('settings.media.filterContext')"
        hide-details
      />
    </div>

    <ELoadingState v-if="isLoading" variant="page" :message="t('settings.media.loading')" />

    <p v-else-if="loadError" class="media-browser-error">{{ loadError }}</p>

    <p v-else-if="items.length === 0" class="media-browser-empty">
      {{ t('settings.media.empty') }}
    </p>

    <div v-else class="media-browser-grid">
      <article
        v-for="item in items"
        :key="item.id || item.filename || item.url"
        class="media-browser-card"
      >
        <button type="button" class="media-browser-thumb-btn" @click="openPreview(item)">
          <v-icon v-if="isPdfMedia(item)" icon="mdi-file-pdf-box" size="40" color="error" />
          <img
            v-else
            :src="resolveMediaPreviewUrl(item.url)"
            :alt="item.original_filename || ''"
          />
        </button>
        <div class="media-browser-card-body">
          <span class="media-browser-name">{{ item.original_filename || item.filename }}</span>
          <span class="media-browser-meta">{{ contextLabel(item.context) }} · {{ item.context_label }}</span>
        </div>
      </article>
    </div>

    <p v-if="!isLoading && items.length" class="media-browser-count">
      {{ t('settings.media.count', { count: items.length }) }}
    </p>

    <ReceiptPreviewDialog
      v-model="previewOpen"
      :receipt="previewItem"
      :department-id="departmentId"
      :replacing="replacing"
      :action-error="previewError"
      :rename-save="onRename"
      :replace-file="onReplace"
      :replace-from-url="onReplaceFromUrl"
      @picker-error="onPickerError"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ESearchField, ESelect } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import ReceiptPreviewDialog from '@/components/accounting/ReceiptPreviewDialog.vue'
import {
  listDepartmentMedia,
  renameDepartmentMedia,
  replaceDepartmentMedia,
  type DepartmentMediaItem,
  type DepartmentMediaKind,
} from '@/api/departmentMedia'
import {
  extractMediaUploadError,
  isPdfMedia,
  resolveMediaPreviewUrl,
  validateImageFile,
  validateReceiptFile,
} from '@/api/media'
import { importMaterialPhotoFromUrl } from '@/api/materials'
import '@/styles/views/settings-media-library.css'

defineOptions({ name: 'MediaLibraryView' })

const { t } = useI18n()
const route = useRoute()

const departmentId = computed(() => String(route.params.departmentId || ''))

const kind = ref<DepartmentMediaKind>('photos')
const searchQuery = ref('')
const contextFilter = ref('')
const isLoading = ref(true)
const loadError = ref('')
const items = ref<DepartmentMediaItem[]>([])
const previewOpen = ref(false)
const previewItem = ref<DepartmentMediaItem | null>(null)
const replacing = ref(false)
const previewError = ref('')

const contextItems = computed(() => [
  { title: t('settings.media.contextAll'), value: '' },
  { title: t('settings.media.contextMaterial'), value: 'material_item' },
  { title: t('settings.media.contextWorkshop'), value: 'workshop_ticket' },
  { title: t('settings.media.contextIssues'), value: 'issue_report' },
  { title: t('settings.media.contextAccounting'), value: 'accounting_booking' },
  { title: t('settings.media.contextFollowUp'), value: 'accounting_follow_up' },
  { title: t('settings.media.contextJsOrder'), value: 'activity_js_order' },
  { title: t('settings.media.contextQuotes'), value: 'grossanlass_procurement_quote' },
])

function contextLabel(context: string): string {
  const found = contextItems.value.find((item) => item.value === context)
  return found?.title || context
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  loadError.value = ''
  try {
    const data = await listDepartmentMedia(departmentId.value, {
      kind: kind.value,
      context: contextFilter.value,
      q: searchQuery.value.trim(),
    })
    items.value = data.items
  } catch (err) {
    loadError.value = extractMediaUploadError(err) || t('settings.media.loadError')
    items.value = []
  } finally {
    isLoading.value = false
  }
}

function openPreview(item: DepartmentMediaItem) {
  previewItem.value = item
  previewOpen.value = true
  previewError.value = ''
}

function applyUpdatedItem(current: DepartmentMediaItem, updated: DepartmentMediaItem) {
  previewItem.value = updated
  items.value = items.value.map((item) =>
    item.context === current.context &&
    item.context_id === current.context_id &&
    item.filename === current.filename
      ? updated
      : item,
  )
}

async function onReplace(file: File) {
  const current = previewItem.value
  if (!current || !departmentId.value) return
  const invalid =
    current.kind === 'documents' ? validateReceiptFile(file) : validateImageFile(file)
  if (invalid === 'tooLarge') {
    previewError.value = t('media.tooLarge', { name: file.name })
    return
  }
  if (invalid) {
    previewError.value = t('settings.media.replaceError')
    return
  }
  replacing.value = true
  previewError.value = ''
  try {
    const updated = await replaceDepartmentMedia(departmentId.value, current, file)
    applyUpdatedItem(current, updated)
  } catch (err) {
    previewError.value = extractMediaUploadError(err) || t('settings.media.replaceError')
    throw err
  } finally {
    replacing.value = false
  }
}

async function onReplaceFromUrl(url: string) {
  const current = previewItem.value
  if (!current || current.context !== 'material_item' || !current.context_id) {
    previewError.value = t('settings.media.replaceError')
    throw new Error(t('settings.media.replaceError'))
  }
  replacing.value = true
  previewError.value = ''
  try {
    await importMaterialPhotoFromUrl(current.context_id, url)
    await load()
    const next = items.value.find(
      (item) =>
        item.context === current.context
        && item.context_id === current.context_id,
    )
    if (next) {
      previewItem.value = next
    }
  } catch (err) {
    previewError.value = extractMediaUploadError(err) || t('settings.media.replaceError')
    throw err
  } finally {
    replacing.value = false
  }
}

function onPickerError(message: string) {
  previewError.value = message
}

async function onRename(originalFilename: string) {
  const current = previewItem.value
  if (!current || !departmentId.value) return
  previewError.value = ''
  const updated = await renameDepartmentMedia(departmentId.value, current, originalFilename)
  applyUpdatedItem(current, updated)
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch([kind, contextFilter], () => {
  void load()
})
watch(searchQuery, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    void load()
  }, 250)
})

onMounted(() => {
  void load()
})
</script>
