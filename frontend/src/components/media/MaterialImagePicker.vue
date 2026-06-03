<template>
  <div class="material-image-picker">
    <v-menu v-model="menuOpen" location="bottom" :close-on-content-click="true">
      <template #activator="{ props: activatorProps }">
        <EButton
          v-bind="activatorProps"
          variant="secondary"
          block
          :disabled="disabled || uploading"
        >
          {{ hasImage ? t('media.material.replaceImage') : t('media.material.addImage') }}
        </EButton>
      </template>
      <v-list density="compact" class="material-image-picker-menu">
        <v-list-item :title="t('media.material.optionUpload')" @click="openFilePicker(false)" />
        <v-list-item :title="t('media.material.optionCamera')" @click="openFilePicker(true)" />
        <v-list-item :title="t('media.material.optionUrl')" @click="openUrlPanel" />
      </v-list>
    </v-menu>

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
      <ETextField
        v-model="imageUrl"
        type="url"
        :label="t('media.material.urlLabel')"
        :placeholder="t('media.material.urlPlaceholder')"
        :disabled="disabled || uploading"
        hide-details
        @keydown.enter.prevent="submitUrl"
      />
      <div class="material-image-picker-url-actions">
        <EButton variant="secondary" size="small" @click="closeUrlPanel">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="disabled || uploading || !imageUrl.trim()"
          :loading="uploading"
          @click="submitUrl"
        >
          {{ uploading ? t('media.uploading') : t('media.material.applyUrl') }}
        </EButton>
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
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ETextField } from '@/components/form/base'
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
const fileInputRef = ref<HTMLInputElement | null>(null)
const cameraInputRef = ref<HTMLInputElement | null>(null)

const googleImageSearchUrl = computed(() => {
  const q = props.searchQuery.trim() || 'material'
  return `https://www.google.com/search?tbm=isch&q=${encodeURIComponent(q)}`
})

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
</script>

<style scoped>
.material-image-picker {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 10px;
}

.material-image-picker-menu {
  min-width: 200px;
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
