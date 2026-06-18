<template>
  <div class="purchase-receipt-input">
    <label v-if="label">{{ label }}</label>
    <p v-if="hint" class="field-hint">{{ hint }}</p>
    <input
      ref="fileInputRef"
      type="file"
      :accept="RECEIPT_UPLOAD_ACCEPT"
      class="purchase-receipt-file-input"
      @change="onFileSelected"
    />
    <div v-if="previewName" class="purchase-receipt-preview">
      <span>{{ previewName }}</span>
      <button type="button" class="purchase-receipt-clear" @click="clearFile">×</button>
    </div>
    <button v-else type="button" class="btn-outline btn-sm" @click="fileInputRef?.click()">
      {{ t('components.purchaseReceipt.chooseFile') }}
    </button>
    <p class="field-hint purchase-receipt-accept">{{ t('accounting.bookings.receiptAcceptHint') }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RECEIPT_UPLOAD_ACCEPT, validateReceiptFile } from '@/api/media'
import { useToast } from '@/composables/useToast'

defineProps<{
  label?: string
  hint?: string
}>()

const model = defineModel<File | null>({ default: null })

const { t } = useI18n()
const toast = useToast()
const fileInputRef = ref<HTMLInputElement | null>(null)
const previewName = ref('')

function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  const validation = validateReceiptFile(file)
  if (validation === 'tooLarge') {
    toast.error(t('media.tooLarge', { name: file.name }))
    return
  }
  if (validation === 'invalidType') {
    toast.error(t('accounting.bookings.receiptInvalidType'))
    return
  }

  model.value = file
  previewName.value = file.name
}

function clearFile() {
  model.value = null
  previewName.value = ''
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}
</script>

<style scoped>
.purchase-receipt-input label {
  display: block;
  font-weight: 500;
  margin-bottom: 4px;
}

.purchase-receipt-file-input {
  display: none;
}

.purchase-receipt-preview {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #f9fafb;
  font-size: 13px;
}

.purchase-receipt-clear {
  border: none;
  background: transparent;
  color: #9ca3af;
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
  padding: 0 2px;
}

.purchase-receipt-accept {
  margin-top: 6px;
}
</style>
