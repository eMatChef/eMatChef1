<template>
  <div class="material-image-picker">
    <div ref="menuRoot" class="material-image-picker-actions">
      <button
        type="button"
        class="material-image-picker-trigger"
        :disabled="disabled || uploading"
        @click="toggleMenu"
      >
        {{ hasImage ? t('media.material.replaceImage') : t('media.material.addImage') }}
      </button>

      <div v-if="menuOpen" class="material-image-picker-menu" role="menu">
        <button type="button" role="menuitem" @click="openFilePicker(false)">
          {{ t('media.material.optionUpload') }}
        </button>
        <button type="button" role="menuitem" @click="openFilePicker(true)">
          {{ t('media.material.optionCamera') }}
        </button>
        <button type="button" role="menuitem" @click="openUrlPanel">
          {{ t('media.material.optionUrl') }}
        </button>
      </div>
    </div>

    <div v-if="urlPanelOpen" class="material-image-picker-url">
      <p class="material-image-picker-url-hint">{{ t('media.material.urlHint') }}</p>
      <a
        class="material-image-picker-google"
        :href="googleImageSearchUrl"
        target="_blank"
        rel="noopener noreferrer"
      >
        {{ t('media.material.googleSearch', { name: searchQuery }) }}
      </a>
      <label class="material-image-picker-url-field">
        <span>{{ t('media.material.urlLabel') }}</span>
        <input
          v-model="imageUrl"
          type="url"
          inputmode="url"
          :placeholder="t('media.material.urlPlaceholder')"
          :disabled="disabled || uploading"
          @keydown.enter.prevent="submitUrl"
        />
      </label>
      <div class="material-image-picker-url-actions">
        <button type="button" class="btn btn-secondary btn-sm" @click="closeUrlPanel">
          {{ t('common.cancel') }}
        </button>
        <button
          type="button"
          class="btn btn-primary btn-sm"
          :disabled="disabled || uploading || !imageUrl.trim()"
          @click="submitUrl"
        >
          {{ uploading ? t('media.uploading') : t('media.material.applyUrl') }}
        </button>
      </div>
    </div>

    <p v-if="uploading" class="material-image-picker-status">{{ t('media.uploading') }}</p>

    <input
      ref="fileInputRef"
      type="file"
      class="material-image-picker-input"
      :accept="IMAGE_UPLOAD_ACCEPT"
      tabindex="-1"
      @change="onFileSelected"
    />
    <input
      ref="cameraInputRef"
      type="file"
      class="material-image-picker-input"
      :accept="IMAGE_UPLOAD_ACCEPT"
      capture="environment"
      tabindex="-1"
      @change="onFileSelected"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { extractMediaUploadError, validateImageFile } from '@/api/media'
import { IMAGE_UPLOAD_ACCEPT } from '@/api/media'

const props = withDefaults(
  defineProps<{
    hasImage?: boolean
    searchQuery?: string
    disabled?: boolean
    uploadFile: (file: File) => Promise<unknown>
    importUrl: (url: string) => Promise<unknown>
  }>(),
  {
    hasImage: false,
    searchQuery: '',
    disabled: false,
  },
)

const emit = defineEmits<{
  uploaded: [payload: unknown]
  error: [message: string]
}>()

const { t } = useI18n()

const menuOpen = ref(false)
const urlPanelOpen = ref(false)
const uploading = ref(false)
const imageUrl = ref('')
const menuRoot = ref<HTMLElement | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const cameraInputRef = ref<HTMLInputElement | null>(null)

const googleImageSearchUrl = computed(() => {
  const q = props.searchQuery.trim() || 'material'
  return `https://www.google.com/search?tbm=isch&q=${encodeURIComponent(q)}`
})

function toggleMenu() {
  menuOpen.value = !menuOpen.value
  if (!menuOpen.value) {
    urlPanelOpen.value = false
  }
}

function closeMenu() {
  menuOpen.value = false
}

function openUrlPanel() {
  closeMenu()
  urlPanelOpen.value = true
}

function closeUrlPanel() {
  urlPanelOpen.value = false
  imageUrl.value = ''
}

function openFilePicker(useCamera: boolean) {
  closeMenu()
  closeUrlPanel()
  const input = useCamera ? cameraInputRef.value : fileInputRef.value
  if (input) {
    input.value = ''
    input.click()
  }
}

async function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || props.disabled || uploading.value) return

  if (validateImageFile(file) === 'tooLarge') {
    emit('error', t('media.tooLarge', { name: file.name }))
    return
  }

  uploading.value = true
  try {
    const result = await props.uploadFile(file)
    emit('uploaded', result)
  } catch (err: unknown) {
    emit('error', extractMediaUploadError(err) || t('media.uploadError'))
  } finally {
    uploading.value = false
  }
}

async function submitUrl() {
  const url = imageUrl.value.trim()
  if (!url || props.disabled || uploading.value) return

  uploading.value = true
  try {
    const result = await props.importUrl(url)
    closeUrlPanel()
    emit('uploaded', result)
  } catch (err: unknown) {
    emit('error', extractMediaUploadError(err) || t('media.uploadError'))
  } finally {
    uploading.value = false
  }
}

function onDocumentClick(event: MouseEvent) {
  if (!menuOpen.value) return
  const root = menuRoot.value
  if (root && !root.contains(event.target as Node)) {
    closeMenu()
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))
</script>

<style scoped>
.material-image-picker {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 10px;
}

.material-image-picker-actions {
  position: relative;
}

.material-image-picker-trigger {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
}

.material-image-picker-trigger:hover:not(:disabled) {
  border-color: #9ca3af;
  background: #f9fafb;
}

.material-image-picker-trigger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.material-image-picker-menu {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 4px);
  z-index: 20;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 4px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}

.material-image-picker-menu button {
  border: none;
  background: transparent;
  text-align: left;
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 13px;
  color: #374151;
  cursor: pointer;
}

.material-image-picker-menu button:hover {
  background: #f3f4f6;
}

.material-image-picker-url {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
}

.material-image-picker-url-hint {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
}

.material-image-picker-google {
  font-size: 13px;
  color: #2563eb;
  text-decoration: none;
}

.material-image-picker-google:hover {
  text-decoration: underline;
}

.material-image-picker-url-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: #374151;
}

.material-image-picker-url-field input {
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13px;
}

.material-image-picker-url-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.material-image-picker-status {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
}

.material-image-picker-input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}
</style>
