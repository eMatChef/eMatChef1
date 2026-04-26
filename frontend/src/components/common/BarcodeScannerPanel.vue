<template>
  <div class="scanner-panel">
    <video ref="scannerVideo" class="scanner-video" autoplay muted playsinline></video>
    <p class="hint">{{ displayHint }}</p>
    <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onUnmounted, ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { BrowserMultiFormatReader } from '@zxing/browser'
import { BarcodeFormat, DecodeHintType } from '@zxing/library'

const props = withDefaults(defineProps<{
  active: boolean
  mode?: 'qr' | 'all' | '1d'
  hint?: string
}>(), {
  mode: 'all',
  hint: undefined,
})

const { t } = useI18n()

const displayHint = computed(() => props.hint || t('components.barcodeScanner.hintDefault'))

const emit = defineEmits<{
  detected: [payload: { text: string; format: string }]
  error: [message: string]
}>()

const scannerVideo = ref<HTMLVideoElement | null>(null)
const errorMessage = ref<string | null>(null)

let reader: BrowserMultiFormatReader | null = null
let controls: { stop: () => void } | null = null

function getFormats(mode: 'qr' | 'all' | '1d'): BarcodeFormat[] {
  if (mode === 'qr') return [BarcodeFormat.QR_CODE]
  if (mode === '1d') {
    return [
      BarcodeFormat.CODE_128,
      BarcodeFormat.CODE_39,
      BarcodeFormat.CODE_93,
      BarcodeFormat.EAN_13,
      BarcodeFormat.EAN_8,
      BarcodeFormat.UPC_A,
      BarcodeFormat.UPC_E,
      BarcodeFormat.ITF,
      BarcodeFormat.CODABAR
    ]
  }
  return [
    BarcodeFormat.QR_CODE,
    BarcodeFormat.DATA_MATRIX,
    BarcodeFormat.AZTEC,
    BarcodeFormat.PDF_417,
    BarcodeFormat.CODE_128,
    BarcodeFormat.CODE_39,
    BarcodeFormat.CODE_93,
    BarcodeFormat.EAN_13,
    BarcodeFormat.EAN_8,
    BarcodeFormat.UPC_A,
    BarcodeFormat.UPC_E,
    BarcodeFormat.ITF,
    BarcodeFormat.CODABAR
  ]
}

async function startScanner() {
  errorMessage.value = null
  await nextTick()
  if (!scannerVideo.value) return

  try {
    const hints = new Map()
    hints.set(DecodeHintType.POSSIBLE_FORMATS, getFormats(props.mode))
    reader = new BrowserMultiFormatReader(hints)
    controls = await reader.decodeFromVideoDevice(undefined, scannerVideo.value, (result) => {
      if (!result) return
      emit('detected', {
        text: result.getText(),
        format: String(result.getBarcodeFormat())
      })
    })
  } catch (err: any) {
    const message = err?.message || t('components.barcodeScanner.cameraStartError')
    errorMessage.value = message
    emit('error', message)
  }
}

function stopScanner() {
  controls?.stop()
  controls = null
}

watch(
  () => props.active,
  async (active) => {
    if (active) {
      await startScanner()
      return
    }
    stopScanner()
  },
  { immediate: true }
)

onUnmounted(() => {
  stopScanner()
})
</script>

<style scoped>
.scanner-panel { margin-top: 14px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; }
.scanner-video { width: 100%; max-width: 420px; border-radius: 8px; background: #111827; }
.hint { margin-top: 8px; color: #6b7280; font-size: 12px; }
.error { color: #b91c1c; margin-top: 12px; }
</style>
