<template>
  <div v-if="open" class="public-qr-action-modal modal-overlay" @click.self="emit('close')">
    <div class="modal-dialog">
      <h3>{{ resolvedTitle }}</h3>
      <p v-if="label" class="qr-modal-text">{{ label }}</p>
      <p v-if="code" class="qr-modal-meta">
        {{ t('components.publicQrAction.labelCode') }}: {{ code }}
      </p>
      <div class="modal-actions">
        <button type="button" class="btn-secondary btn-sm" @click="emit('close')">
          {{ t('common.cancel') }}
        </button>
        <button type="button" class="btn-outline btn-sm" @click="emit('add-to-print-cart')">
          {{ t('components.publicQrAction.btnAddToPrintCart') }}
        </button>
        <button v-if="hasUrl" type="button" class="btn-outline btn-sm" @click="onOpenLink">
          {{ t('components.publicQrAction.btnOpenQrPage') }}
        </button>
        <button v-if="hasUrl" type="button" class="btn-outline btn-sm" @click="onCopyLink">
          {{ t('components.publicQrAction.btnCopyQrLink') }}
        </button>
        <button v-if="hasUrl" type="button" class="btn-primary btn-sm" @click="emit('print')">
          {{ t('components.publicQrAction.btnPrint') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { copyTextToClipboard } from '@/utils/clipboard'
import { useToast } from '@/composables/useToast'

const props = withDefaults(
  defineProps<{
    open: boolean
    label?: string | null
    code?: string | null
    url?: string | null
    title?: string | null
  }>(),
  {
    label: null,
    code: null,
    url: null,
    title: null,
  },
)

const emit = defineEmits<{
  close: []
  'add-to-print-cart': []
  print: []
}>()

const { t } = useI18n()
const toast = useToast()

const resolvedTitle = computed(
  () => props.title?.trim() || t('components.publicQrAction.title'),
)
const hasUrl = computed(() => String(props.url || '').trim() !== '')

function onOpenLink() {
  const url = String(props.url || '').trim()
  if (!url) {
    toast.info(t('components.publicQrAction.toastNoPublicLink'))
    return
  }
  window.open(url, '_blank', 'noopener,noreferrer')
}

async function onCopyLink() {
  const url = String(props.url || '').trim()
  if (!url) {
    toast.info(t('components.publicQrAction.toastNoPublicLink'))
    return
  }
  const ok = await copyTextToClipboard(url)
  if (ok) {
    toast.success(t('components.publicQrAction.toastQrLinkCopied'))
    return
  }
  toast.error(t('settings.addressModal.clipboardDenied'))
}
</script>

<style scoped>
.qr-modal-text {
  margin: 0;
  font-size: 14px;
  color: #111827;
  font-weight: 600;
}

.qr-modal-meta {
  margin: 8px 0 16px;
  font-size: 12px;
  color: #6b7280;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
