<template>
  <span
    class="public-qr-tag"
    :class="{ 'is-empty': !url, 'is-clickable': clickable && !!url }"
    :style="tagStyle"
    :title="tooltipText"
    @click="handleActivate"
  >
    <img v-if="url && qrDisplaySrc" :src="qrDisplaySrc" :alt="imgAlt" />
    <span v-else class="public-qr-empty">-</span>
  </span>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'

const { t } = useI18n()

interface Props {
  url?: string | null
  code?: string | null
  size?: number
  clickable?: boolean
  /** Für Dateiname bei „Bild speichern unter“ (Artikelname + ID) */
  imageLabel?: string | null
  imageEntityId?: string | number | null
}

const props = withDefaults(defineProps<Props>(), {
  url: null,
  code: null,
  size: 56,
  clickable: false,
  imageLabel: null,
  imageEntityId: null,
})
const emit = defineEmits<{
  activate: []
}>()

const qrDataUrl = ref('')
const qrDisplaySrc = ref('')
let blobObjectUrl: string | null = null

function revokeBlobUrl() {
  if (blobObjectUrl) {
    URL.revokeObjectURL(blobObjectUrl)
    blobObjectUrl = null
  }
}

function buildPngFilename(label: string, entityId: string | number): string {
  const safeLabel = String(label || 'QR')
    .trim()
    .replace(/[/\\?%*:|"<>]/g, '-')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .slice(0, 120)
  const safeId = String(entityId).replace(/[/\\?%*:|"<>]/g, '')
  return `${safeLabel || 'qr'}-${safeId}.png`
}

const tagStyle = computed(() => {
  const px = `${Math.max(28, Number(props.size || 56))}px`
  return { width: px, height: px }
})

const tooltipText = computed(() => {
  if (!props.url) return t('components.publicQr.noQr')
  return props.code
    ? t('components.publicQr.tooltipWithCode', { code: props.code })
    : t('components.publicQr.publicLink')
})

const imgAlt = computed(() => {
  if (props.imageLabel != null && props.imageEntityId != null && props.imageEntityId !== '') {
    return t('components.publicQr.altWithLabel', {
      label: props.imageLabel,
      id: props.imageEntityId,
    })
  }
  if (props.code) return t('components.publicQr.altWithCode', { code: props.code })
  return t('components.publicQr.altGeneric')
})

watch(
  () =>
    [props.url, props.size, props.imageLabel, props.imageEntityId] as const,
  async ([nextUrl, nextSize, imageLabel, imageEntityId]) => {
    const normalized = (nextUrl || '').trim()
    revokeBlobUrl()
    qrDisplaySrc.value = ''
    if (!normalized) {
      qrDataUrl.value = ''
      return
    }
    try {
      qrDataUrl.value = await QRCode.toDataURL(normalized, {
        width: nextSize,
        margin: 1,
      })
      const hasName =
        imageLabel != null &&
        String(imageLabel).trim() !== '' &&
        imageEntityId != null &&
        String(imageEntityId).trim() !== ''
      if (hasName) {
        const fileName = buildPngFilename(String(imageLabel).trim(), imageEntityId as string | number)
        const res = await fetch(qrDataUrl.value)
        const blob = await res.blob()
        const file = new File([blob], fileName, { type: 'image/png' })
        const objectUrl = URL.createObjectURL(file)
        blobObjectUrl = objectUrl
        qrDisplaySrc.value = objectUrl
      } else {
        qrDisplaySrc.value = qrDataUrl.value
      }
    } catch {
      qrDataUrl.value = ''
      qrDisplaySrc.value = ''
    }
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  revokeBlobUrl()
})

function handleActivate() {
  if (!props.clickable || !props.url) return
  emit('activate')
}
</script>

<style scoped>
.public-qr-tag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  background: #fff;
  overflow: hidden;
}

.public-qr-tag img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.public-qr-tag.is-empty {
  background: #f9fafb;
}

.public-qr-tag.is-clickable {
  cursor: pointer;
}

.public-qr-tag.is-clickable:hover {
  border-color: #34d399;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.12);
}

.public-qr-empty {
  font-size: 12px;
  color: #9ca3af;
  font-weight: 600;
}
</style>
