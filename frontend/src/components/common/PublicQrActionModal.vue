<template>
  <!-- Mobile: Vuetify-Dialog — QR oben, Aktionen unten, X + Klick daneben schliesst -->
  <EDialog
    v-if="sheetLayout"
    :model-value="open"
    class="public-qr-action-sheet"
    max-width="400"
    card-variant="elevated"
    @update:model-value="onSheetOpenChange"
  >
    <template #title>
      <div class="public-qr-sheet-title">
        <span class="public-qr-sheet-title-text">{{ resolvedTitle }}</span>
        <v-btn
          icon
          variant="text"
          size="small"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <v-icon icon="mdi-close" size="22" />
        </v-btn>
      </div>
    </template>

    <div class="public-qr-sheet-body">
      <PublicQrTag
        v-if="hasUrl"
        class="public-qr-sheet-qr"
        :url="url"
        :code="code"
        :size="200"
        :clickable="false"
        :image-label="imageLabel"
        :image-entity-id="imageEntityId"
      />
      <p v-if="label" class="qr-sheet-label">{{ label }}</p>
      <p v-if="code" class="qr-sheet-code">
        {{ t('components.publicQrAction.labelCode') }}: {{ code }}
      </p>
    </div>

    <template #actions>
      <div class="public-qr-sheet-actions">
        <EButton
          v-if="hasUrl"
          variant="primary"
          block
          @click="emit('print')"
        >
          {{ t('common.print') }}
        </EButton>
        <EButton variant="secondary" block @click="emit('add-to-print-cart')">
          {{ t('components.publicQrAction.btnAddToPrintCart') }}
        </EButton>
        <EButton v-if="hasUrl" variant="secondary" block @click="onOpenLink">
          {{ t('components.publicQrAction.btnOpenQrPage') }}
        </EButton>
        <EButton v-if="hasUrl" variant="secondary" block @click="onCopyLink">
          {{ t('components.publicQrAction.btnCopyQrLink') }}
        </EButton>
      </div>
    </template>
  </EDialog>

  <!-- Desktop: klassisches Overlay -->
  <div v-else-if="open" class="public-qr-action-modal modal-overlay" @click.self="emit('close')">
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
          {{ t('common.print') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog } from '@/components/form/base'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import { useSmAndUp } from '@/composables/useSmAndUp'
import { copyTextToClipboard } from '@/utils/clipboard'
import { useToast } from '@/composables/useToast'

const props = withDefaults(
  defineProps<{
    open: boolean
    label?: string | null
    code?: string | null
    url?: string | null
    title?: string | null
    imageLabel?: string | null
    imageEntityId?: string | number | null
    /** Erzwingt Sheet-Layout (Mobile-Dialog mit QR-Vorschau) */
    sheetLayout?: boolean | null
  }>(),
  {
    label: null,
    code: null,
    url: null,
    title: null,
    imageLabel: null,
    imageEntityId: null,
    sheetLayout: null,
  },
)

const emit = defineEmits<{
  close: []
  'add-to-print-cart': []
  print: []
}>()

const { t } = useI18n()
const toast = useToast()
const smAndUp = useSmAndUp()

const sheetLayout = computed(() => {
  if (props.sheetLayout != null) return props.sheetLayout
  return !smAndUp.value
})

const resolvedTitle = computed(
  () => props.title?.trim() || t('components.publicQrAction.title'),
)
const hasUrl = computed(() => String(props.url || '').trim() !== '')

function onSheetOpenChange(value: boolean) {
  if (!value) emit('close')
}

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

.public-qr-sheet-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
}

.public-qr-sheet-title-text {
  flex: 1;
  min-width: 0;
  padding-top: 2px;
}

.public-qr-sheet-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
}

.public-qr-sheet-qr {
  border-radius: 12px;
}

.qr-sheet-label {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #111827;
  line-height: 1.35;
}

.qr-sheet-code {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.public-qr-sheet-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}
</style>
