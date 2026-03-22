<template>
  <span
    class="public-qr-tag"
    :class="{ 'is-empty': !url, 'is-clickable': clickable && !!url }"
    :style="tagStyle"
    :title="tooltipText"
    @click="handleActivate"
  >
    <img v-if="url && qrDataUrl" :src="qrDataUrl" alt="QR" />
    <span v-else class="public-qr-empty">-</span>
  </span>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import QRCode from 'qrcode'

interface Props {
  url?: string | null
  code?: string | null
  size?: number
  clickable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  url: null,
  code: null,
  size: 56,
  clickable: false,
})
const emit = defineEmits<{
  activate: []
}>()

const qrDataUrl = ref('')
const tagStyle = computed(() => {
  const px = `${Math.max(28, Number(props.size || 56))}px`
  return { width: px, height: px }
})

const tooltipText = computed(() => {
  if (!props.url) return 'Kein Public-QR vorhanden'
  return props.code ? `Public Code: ${props.code}` : 'Public QR'
})

watch(
  () => [props.url, props.size] as const,
  async ([nextUrl, nextSize]) => {
    const normalized = (nextUrl || '').trim()
    if (!normalized) {
      qrDataUrl.value = ''
      return
    }
    try {
      qrDataUrl.value = await QRCode.toDataURL(normalized, {
        width: nextSize,
        margin: 1,
      })
    } catch {
      qrDataUrl.value = ''
    }
  },
  { immediate: true }
)

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
