<template>
  <EDialog
    v-model="open"
    :max-width="1024"
    :title="title || t('accounting.bookings.receiptPreviewTitle')"
    scrollable
    card-class="receipt-preview-dialog-card"
  >
    <div class="receipt-preview-body">
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
    <template #actions>
      <EButton
        v-if="sourceUrl"
        variant="secondary"
        size="small"
        @click="openInNewTab"
      >
        {{ t('accounting.bookings.receiptOpenExternal') }}
      </EButton>
      <EButton variant="primary" size="small" @click="open = false">
        {{ t('common.close') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/apiClient'
import { EButton, EDialog } from '@/components/form/base'
import { isPdfMedia, resolveMediaPreviewUrl, type MediaPhoto } from '@/api/media'

const open = defineModel<boolean>({ default: false })

const props = defineProps<{
  receipt: MediaPhoto | null
}>()

const { t } = useI18n()

const loading = ref(false)
const loadError = ref('')
const displayUrl = ref('')
let blobUrl: string | null = null

const title = computed(
  () => props.receipt?.original_filename || props.receipt?.filename || '',
)

const isPdf = computed(() => (props.receipt ? isPdfMedia(props.receipt) : false))

const sourceUrl = computed(() =>
  props.receipt?.url ? resolveMediaPreviewUrl(props.receipt.url) : '',
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
    const { data } = await apiClient.get<Blob>(url, { responseType: 'blob' })
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

function openInNewTab() {
  if (!sourceUrl.value) return
  window.open(sourceUrl.value, '_blank', 'noopener,noreferrer')
}
</script>

<style scoped>
.receipt-preview-body {
  min-height: 60vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 8px;
  overflow: hidden;
}

.receipt-preview-status {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 40vh;
  padding: 24px;
}

.receipt-preview-error {
  color: #b91c1c;
  text-align: center;
  max-width: 36ch;
  line-height: 1.5;
}

.receipt-preview-iframe {
  width: 100%;
  min-height: 70vh;
  border: none;
  background: #fff;
}

.receipt-preview-image {
  max-width: 100%;
  max-height: 75vh;
  object-fit: contain;
  background: #fff;
}
</style>
