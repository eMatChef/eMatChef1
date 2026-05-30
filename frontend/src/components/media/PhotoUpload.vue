<template>
  <label class="photo-upload" :class="{ 'photo-upload--disabled': isDisabled }">
    <slot>{{ label ?? t('media.upload') }}</slot>
    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      :multiple="multiple"
      :disabled="isDisabled"
      @change="onFileChange"
    />
    <span v-if="uploading" class="photo-upload-status">{{ t('media.uploading') }}</span>
    <ul v-if="!autoUpload && pendingFiles.length" class="photo-upload-pending">
      <li v-for="(file, index) in pendingFiles" :key="`${file.name}-${index}`">
        <span>{{ file.name }}</span>
        <button type="button" class="photo-upload-remove" @click.prevent="removePending(index)">×</button>
      </li>
    </ul>
  </label>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMediaUpload, extractMediaUploadError } from '@/composables/useMediaUpload'
import { IMAGE_UPLOAD_ACCEPT, MAX_IMAGE_BYTES } from '@/api/media'
import '@/styles/components/photo-gallery.css'

const props = withDefaults(
  defineProps<{
    uploadUrl?: string
    uploadFn?: (file: File) => Promise<unknown>
    accept?: string
    maxBytes?: number
    disabled?: boolean
    multiple?: boolean
    autoUpload?: boolean
    maxFiles?: number
    fieldName?: string
    label?: string
    files?: File[]
  }>(),
  {
    accept: IMAGE_UPLOAD_ACCEPT,
    maxBytes: MAX_IMAGE_BYTES,
    disabled: false,
    multiple: false,
    autoUpload: true,
    fieldName: 'photo',
  },
)

const emit = defineEmits<{
  uploaded: [payload: unknown]
  error: [message: string]
  selected: [files: File[]]
  'update:files': [files: File[]]
}>()

const { t } = useI18n()
const { uploading: urlUploading, upload, validateImageFile } = useMediaUpload()
const fnUploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const pendingFiles = ref<File[]>([...(props.files ?? [])])

const uploading = computed(() => urlUploading.value || fnUploading.value)
const isDisabled = computed(() => props.disabled || uploading.value)

watch(
  () => props.files,
  (next) => {
    if (next) {
      pendingFiles.value = [...next]
    }
  },
)

function syncPending(next: File[]) {
  pendingFiles.value = next
  emit('update:files', next)
  emit('selected', next)
}

function removePending(index: number) {
  syncPending(pendingFiles.value.filter((_, i) => i !== index))
}

async function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const files = input.files ? Array.from(input.files) : []
  input.value = ''
  if (files.length === 0 || isDisabled.value) return

  const valid: File[] = []
  for (const file of files) {
    if (validateImageFile(file, props.maxBytes) === 'tooLarge') {
      emit('error', t('media.tooLarge', { name: file.name }))
      continue
    }
    valid.push(file)
  }
  if (valid.length === 0) return

  if (!props.autoUpload) {
    const max = props.maxFiles ?? Number.POSITIVE_INFINITY
    const next = props.multiple ? [...pendingFiles.value] : []
    for (const file of valid) {
      if (next.length >= max) {
        emit('error', t('media.tooMany', { max }))
        break
      }
      next.push(file)
    }
    syncPending(next)
    return
  }

  await performUpload(valid[0])
}

async function performUpload(file: File) {
  try {
    let data: unknown
    if (props.uploadFn) {
      fnUploading.value = true
      data = await props.uploadFn(file)
    } else if (props.uploadUrl) {
      data = await upload(props.uploadUrl, file, props.fieldName)
    } else {
      emit('error', t('media.uploadError'))
      return
    }
    emit('uploaded', data)
  } catch (err: unknown) {
    emit('error', extractMediaUploadError(err) || t('media.uploadError'))
  } finally {
    fnUploading.value = false
  }
}

function clear() {
  syncPending([])
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

defineExpose({ clear, pendingFiles })
</script>
