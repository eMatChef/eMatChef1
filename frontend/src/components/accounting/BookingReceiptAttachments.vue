<template>
  <div class="receipt-attachments">
    <div v-if="receipts.length" class="receipt-attachments-list">
      <div
        v-for="(item, index) in receipts"
        :key="mediaPhotoKey(item, index)"
        class="receipt-attachments-item"
      >
        <button
          v-if="isPdfMedia(item)"
          type="button"
          class="receipt-pdf-link receipt-open-btn"
          :title="t('accounting.bookings.receiptPreviewOpen')"
          @click="openPreview(item)"
        >
          <v-icon icon="mdi-file-pdf-box" size="28" color="error" />
          <span class="receipt-pdf-name">{{ item.original_filename || item.filename || 'PDF' }}</span>
        </button>
        <button
          v-else
          type="button"
          class="receipt-image-link receipt-open-btn"
          :title="t('accounting.bookings.receiptPreviewOpen')"
          @click="openPreview(item)"
        >
          <img
            :src="item.url"
            :alt="item.original_filename || ''"
            class="receipt-image-thumb"
          />
        </button>
        <EButton
          v-if="!readonly"
          variant="text"
          size="small"
          color="error"
          class="receipt-remove"
          :title="t('accounting.bookings.receiptRemove')"
          :loading="removing === item.filename"
          @click="onRemove(item)"
        >
          <v-icon icon="mdi-delete-outline" size="18" />
        </EButton>
      </div>
    </div>
    <p v-else-if="showEmpty" class="receipt-empty">{{ t('accounting.bookings.receiptEmpty') }}</p>

    <template v-if="!readonly && canUploadMore">
      <EButton
        variant="secondary"
        size="small"
        :disabled="disabled || !hasUploadTarget || uploading"
        :loading="uploading"
        @click="fileInputRef?.click()"
      >
        <v-icon icon="mdi-paperclip" start size="18" />
        {{ uploading ? t('media.uploading') : t('accounting.bookings.receiptUpload') }}
      </EButton>
      <input
        ref="fileInputRef"
        type="file"
        :accept="RECEIPT_UPLOAD_ACCEPT"
        :disabled="disabled || !hasUploadTarget || uploading"
        class="receipt-file-input"
        @change="onFileSelected"
      />
      <p class="receipt-accept-hint">{{ t('accounting.bookings.receiptAcceptHint') }}</p>
    </template>
    <p v-else-if="!readonly && !canUploadMore" class="receipt-limit-hint">
      {{ t('accounting.bookings.receiptMax', { max: MAX_RECEIPTS_PER_BOOKING }) }}
    </p>
    <p v-if="!hasUploadTarget && !readonly" class="receipt-hint">{{ t('accounting.bookings.receiptSaveFirst') }}</p>

    <ReceiptPreviewDialog v-model="previewOpen" :receipt="previewReceipt" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import ReceiptPreviewDialog from '@/components/accounting/ReceiptPreviewDialog.vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import {
  MAX_RECEIPT_BYTES,
  MAX_RECEIPTS_PER_BOOKING,
  RECEIPT_UPLOAD_ACCEPT,
  extractMediaUploadError,
  isPdfMedia,
  mediaPhotoKey,
  validateReceiptFile,
  type MediaPhoto,
} from '@/api/media'
import {
  deleteAcquisitionFollowupReceipt,
  uploadAcquisitionFollowupReceipt,
} from '@/api/accountingAcquisitionFollowups'
import { deleteBookingReceipt, uploadBookingReceipt } from '@/api/accountingBookings'
import { useToast } from '@/composables/useToast'

const props = withDefaults(
  defineProps<{
    departmentId: string
    bookingId?: string | null
    /** Ausstehender Anschaffungs-Auftrag (vor Erfassung der Buchung) */
    followUpId?: string | null
    receipts: MediaPhoto[]
    readonly?: boolean
    disabled?: boolean
    showEmpty?: boolean
  }>(),
  {
    bookingId: null,
    followUpId: null,
    readonly: false,
    disabled: false,
    showEmpty: true,
  },
)

const emit = defineEmits<{
  'update:receipts': [receipts: MediaPhoto[]]
}>()

const { t } = useI18n()
const toast = useToast()
const uploading = ref(false)
const removing = ref<string | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const previewOpen = ref(false)
const previewReceipt = ref<MediaPhoto | null>(null)

function openPreview(item: MediaPhoto) {
  previewReceipt.value = item
  previewOpen.value = true
}

const canUploadMore = computed(() => props.receipts.length < MAX_RECEIPTS_PER_BOOKING)
const hasUploadTarget = computed(() => !!(props.bookingId || props.followUpId))

async function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !hasUploadTarget.value) return

  const validation = validateReceiptFile(file)
  if (validation === 'tooLarge') {
    toast.error(t('media.tooLarge', { name: file.name }))
    return
  }
  if (validation === 'invalidType') {
    toast.error(t('accounting.bookings.receiptInvalidType'))
    return
  }

  uploading.value = true
  try {
    const next = props.followUpId && !props.bookingId
      ? await uploadAcquisitionFollowupReceipt(props.departmentId, props.followUpId, file)
      : await uploadBookingReceipt(props.departmentId, props.bookingId as string, file)
    emit('update:receipts', next)
    toast.success(t('accounting.bookings.receiptUploaded'))
  } catch (err: unknown) {
    toast.error(extractMediaUploadError(err) || t('media.uploadError'))
  } finally {
    uploading.value = false
  }
}

async function onRemove(item: MediaPhoto) {
  if (!hasUploadTarget.value || !item.filename) return
  removing.value = item.filename
  try {
    const next = props.followUpId && !props.bookingId
      ? await deleteAcquisitionFollowupReceipt(props.departmentId, props.followUpId, item.filename)
      : await deleteBookingReceipt(props.departmentId, props.bookingId as string, item.filename)
    emit('update:receipts', next)
    toast.success(t('accounting.bookings.receiptRemoved'))
  } catch {
    toast.error(t('accounting.common.deleteFailed'))
  } finally {
    removing.value = null
  }
}
</script>

<style scoped>
.receipt-attachments-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 12px;
}

.receipt-attachments-item {
  position: relative;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px;
  background: #fff;
  max-width: 140px;
}

.receipt-image-thumb {
  display: block;
  max-width: 120px;
  max-height: 90px;
  object-fit: cover;
  border-radius: 4px;
}

.receipt-open-btn {
  border: none;
  background: transparent;
  padding: 0;
  cursor: pointer;
  text-align: inherit;
}

.receipt-open-btn:hover {
  opacity: 0.9;
}

.receipt-pdf-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  color: inherit;
  min-width: 100px;
}

.receipt-pdf-name {
  font-size: 11px;
  text-align: center;
  word-break: break-word;
  color: #374151;
}

.receipt-remove {
  position: absolute;
  top: 2px;
  right: 2px;
  min-width: 28px !important;
  padding: 0 !important;
}

.receipt-empty,
.receipt-hint,
.receipt-limit-hint,
.receipt-accept-hint {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 8px;
}

.receipt-upload-label {
  display: inline-block;
  cursor: pointer;
}

.receipt-file-input {
  display: none;
}
</style>
