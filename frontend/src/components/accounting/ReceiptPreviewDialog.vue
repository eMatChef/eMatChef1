<template>
  <EDialog
    v-model="open"
    :max-width="dialogMaxWidth"
    :title="canRename ? undefined : (title || t('accounting.bookings.receiptPreviewTitle'))"
    scrollable
    card-class="receipt-preview-dialog-card"
  >
    <template v-if="canRename" #title>
      <AutoSaveField
        :key="renameFieldKey"
        v-model="nameDraft"
        :baseline="currentName"
        :label="t('settings.media.nameLabel')"
        :placeholder="t('settings.media.renameHint')"
        span-class="receipt-preview-title-field"
        :disabled="replacing"
        :save="saveName"
      />
    </template>

    <div v-if="resolvedLinks.length" class="receipt-preview-links">
      <span class="receipt-preview-links-label">{{ t('settings.media.linksTitle') }}:</span>
      <ul>
        <li v-for="(link, index) in resolvedLinks" :key="`${link.kind}-${link.path}-${index}`">
          <span class="receipt-preview-link-kind">{{ linkKindLabel(link.kind) }}:</span>
          <router-link v-if="departmentId" :to="`/${departmentId}${link.path}`">
            {{ link.label }}
          </router-link>
          <span v-else>{{ link.label }}</span>
        </li>
      </ul>
    </div>
    <p v-if="actionError" class="receipt-preview-action-error">{{ actionError }}</p>

    <div class="receipt-preview-body" :class="{ 'receipt-preview-body--pdf': isPdf }">
      <div v-if="loading" class="receipt-preview-status">
        <v-progress-circular indeterminate color="primary" size="40" />
      </div>
      <p v-else-if="loadError" class="receipt-preview-status receipt-preview-error">
        {{ loadError }}
      </p>
      <iframe
        v-else-if="isPdf && displayUrl"
        :src="displayUrl"
        class="receipt-preview-iframe"
        :title="title || 'PDF'"
      />
      <img
        v-else-if="displayUrl"
        :src="displayUrl"
        :alt="title || ''"
        class="receipt-preview-image"
      />
    </div>
    <input
      ref="replaceInputRef"
      type="file"
      class="receipt-preview-file-input"
      :accept="replaceAccept"
      :disabled="replacing"
      @change="onReplaceSelected"
    />
    <template #actions>
      <div class="receipt-preview-actions">
        <MaterialImagePicker
          v-if="canReplaceWithImagePicker"
          class="receipt-preview-image-picker"
          compact
          has-image
          :search-query="imageSearchQuery"
          :disabled="replacing"
          :upload-file="runReplaceFile"
          :import-url="canReplaceFromUrl ? runReplaceFromUrl : undefined"
          @error="onImagePickerError"
        />
        <EButton
          v-if="canReplaceFile"
          variant="secondary"
          size="small"
          :loading="replacing"
          :disabled="replacing"
          @click="replaceInputRef?.click()"
        >
          {{ t('settings.media.replace') }}
        </EButton>
        <EButton variant="primary" size="small" @click="open = false">
          {{ t('common.close') }}
        </EButton>
      </div>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/apiClient'
import { EButton, EDialog } from '@/components/form/base'
import AutoSaveField from '@/components/common/autoSave/AutoSaveField.vue'
import MaterialImagePicker from '@/components/media/MaterialImagePicker.vue'
import type { AutoSaveFieldValue } from '@/components/common/autoSave/types'
import {
  IMAGE_UPLOAD_ACCEPT,
  RECEIPT_UPLOAD_ACCEPT,
  isPdfMedia,
  resolveMediaPreviewUrl,
  type MediaPhoto,
} from '@/api/media'
import type { DepartmentMediaItem, DepartmentMediaLink } from '@/api/departmentMedia'

const open = defineModel<boolean>({ default: false })

const props = withDefaults(
  defineProps<{
    receipt: MediaPhoto | DepartmentMediaItem | null
    departmentId?: string
    replacing?: boolean
    actionError?: string
    renameSave?: (originalFilename: string) => Promise<void>
    replaceFile?: (file: File) => Promise<void>
    replaceFromUrl?: (url: string) => Promise<void>
  }>(),
  {
    departmentId: '',
    replacing: false,
    actionError: '',
    renameSave: undefined,
    replaceFile: undefined,
    replaceFromUrl: undefined,
  },
)

const emit = defineEmits<{
  replace: [file: File]
  'picker-error': [message: string]
}>()

const { t } = useI18n()

const loading = ref(false)
const loadError = ref('')
const displayUrl = ref('')
const replaceInputRef = ref<HTMLInputElement | null>(null)
let blobUrl: string | null = null

const title = computed(
  () => props.receipt?.original_filename || props.receipt?.filename || '',
)

const isPdf = computed(() => (props.receipt ? isPdfMedia(props.receipt) : false))

const dialogMaxWidth = computed(() => (isPdf.value ? 1024 : 520))

const sourceUrl = computed(() =>
  props.receipt?.url ? resolveMediaPreviewUrl(props.receipt.url) : '',
)

const nameDraft = ref<AutoSaveFieldValue>('')

const mediaItem = computed((): DepartmentMediaItem | null => {
  const receipt = props.receipt
  if (!receipt || !('context' in receipt) || !('source_path' in receipt)) {
    return null
  }
  return receipt as DepartmentMediaItem
})

const canReplace = computed(() => mediaItem.value?.can_replace === true)
const canRename = computed(() => mediaItem.value?.can_rename === true)

const canReplaceWithImagePicker = computed(
  () => canReplace.value && !isPdf.value && mediaItem.value?.kind !== 'documents',
)

const canReplaceFile = computed(
  () => canReplace.value && !isPdf.value && !canReplaceWithImagePicker.value,
)

const canReplaceFromUrl = computed(
  () =>
    canReplaceWithImagePicker.value
    && typeof props.replaceFromUrl === 'function'
    && mediaItem.value?.context === 'material_item',
)

const imageSearchQuery = computed(
  () => mediaItem.value?.context_label || title.value || '',
)

const currentName = computed(
  () => props.receipt?.original_filename || props.receipt?.filename || '',
)

const renameFieldKey = computed(
  () =>
    `${mediaItem.value?.context || ''}:${mediaItem.value?.context_id || ''}:${mediaItem.value?.filename || ''}`,
)

const resolvedLinks = computed((): DepartmentMediaLink[] => mediaItem.value?.links ?? [])

const replaceAccept = computed(() =>
  mediaItem.value?.kind === 'documents' ? RECEIPT_UPLOAD_ACCEPT : IMAGE_UPLOAD_ACCEPT,
)

function linkKindLabel(kind: string): string {
  const key = `settings.media.linkKind.${kind}`
  const label = t(key)
  return label === key ? kind : label
}

async function runReplaceFile(file: File) {
  if (props.replaceFile) {
    await props.replaceFile(file)
    return
  }
  emit('replace', file)
}

async function runReplaceFromUrl(url: string) {
  if (!props.replaceFromUrl) {
    throw new Error(t('settings.media.replaceError'))
  }
  await props.replaceFromUrl(url)
}

function onImagePickerError(message: string) {
  emit('picker-error', message)
}

function onReplaceSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (file) {
    void runReplaceFile(file)
  }
}

async function saveName(value: AutoSaveFieldValue) {
  const next = String(value ?? '').trim()
  if (!next) {
    throw new Error(t('settings.media.renameEmpty'))
  }
  if (!props.renameSave) return
  await props.renameSave(next)
}

watch(
  () => [open.value, currentName.value] as const,
  ([isOpen, name]) => {
    if (isOpen) {
      nameDraft.value = name
    }
  },
  { immediate: true },
)

function revokeBlobUrl() {
  if (blobUrl) {
    URL.revokeObjectURL(blobUrl)
    blobUrl = null
  }
}

function resetPreview() {
  revokeBlobUrl()
  displayUrl.value = ''
  loadError.value = ''
  loading.value = false
}

/** Blob-URL statt direkter API-URL — umgeht frame-ancestors/X-Frame-Options der API. */
async function loadPreviewBlob() {
  const url = sourceUrl.value
  if (!url) {
    resetPreview()
    return
  }

  revokeBlobUrl()
  displayUrl.value = ''
  loadError.value = ''
  loading.value = true

  try {
    const { data } = await apiClient.get<Blob>(url, {
      responseType: 'blob',
      headers: { Accept: '*/*' },
      transformRequest: [
        (payload, headers) => {
          if (headers && typeof headers === 'object') {
            delete (headers as Record<string, unknown>)['Content-Type']
          }
          return payload
        },
      ],
    })
    if (data.type && data.type.includes('json')) {
      throw new Error('preview-json')
    }
    blobUrl = URL.createObjectURL(data)
    displayUrl.value = blobUrl
  } catch {
    loadError.value = t('accounting.bookings.receiptPreviewLoadError')
  } finally {
    loading.value = false
  }
}

watch(
  () => [open.value, sourceUrl.value] as const,
  ([isOpen, url]) => {
    if (isOpen && url) {
      void loadPreviewBlob()
    } else {
      resetPreview()
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  revokeBlobUrl()
})
</script>

<style scoped>
.receipt-preview-body {
  min-height: 8rem;
  max-height: 28vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 8px;
  overflow: hidden;
}

.receipt-preview-body--pdf {
  min-height: 0;
  max-height: min(58vh, 560px);
}

.receipt-preview-status {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 8rem;
  padding: 16px;
}

.receipt-preview-error {
  color: #b91c1c;
  text-align: center;
  max-width: 36ch;
  line-height: 1.5;
}

.receipt-preview-iframe {
  width: 100%;
  height: min(58vh, 560px);
  min-height: 280px;
  border: none;
  background: #fff;
}

.receipt-preview-image {
  max-width: 100%;
  max-height: 28vh;
  object-fit: contain;
  background: #fff;
}

.receipt-preview-title-field {
  width: 100%;
}

.receipt-preview-links {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px 10px;
  margin: 0 0 12px;
  font-size: 0.9rem;
}

.receipt-preview-links-label {
  color: #64748b;
  font-weight: 600;
  flex-shrink: 0;
}

.receipt-preview-links ul {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.receipt-preview-links li {
  margin: 0;
}

.receipt-preview-link-kind {
  color: #64748b;
  margin-right: 4px;
}

.receipt-preview-file-input {
  display: none;
}

.receipt-preview-action-error {
  margin: 0 0 12px;
  color: #b91c1c;
  font-size: 0.9rem;
}

.receipt-preview-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.receipt-preview-image-picker {
  margin-top: 0;
}
</style>
