<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PackShellCheckToggle from '@/components/activities/PackShellCheckToggle.vue'

defineOptions({ name: 'PackUnpackStoreControls' })

const props = withDefaults(
  defineProps<{
    qty: number
    max: number
    disabled?: boolean
    confirmTitle?: string
    confirmAriaLabel?: string
  }>(),
  { disabled: false },
)

const emit = defineEmits<{
  'update:qty': [value: number]
  store: [qty: number]
}>()

const { t } = useI18n()

function clampQty(raw: number): number {
  let qty = Math.floor(Number(raw)) || 1
  if (qty < 1) qty = 1
  const maxVal = Math.floor(Number(props.max))
  if (maxVal > 0 && qty > maxVal) qty = maxVal
  return qty
}

function setQty(next: number) {
  emit('update:qty', clampQty(next))
}

function step(delta: number) {
  setQty(props.qty + delta)
}

function parseFromInputEl(el: HTMLInputElement | null): number {
  if (!el) return clampQty(props.qty)
  return clampQty(parseInt(el.value, 10))
}

function onInput(event: Event) {
  setQty(parseInt((event.target as HTMLInputElement).value, 10) || 1)
}

function onStore(event?: Event) {
  if (props.disabled) return
  const root = (event?.currentTarget as HTMLElement | undefined)?.closest('.pack-unpack-store-controls')
  const input = root?.querySelector('input.pack-unpack-store-input') as HTMLInputElement | null
  const qty = parseFromInputEl(input)
  emit('update:qty', qty)
  emit('store', qty)
}

const okTitle = () => props.confirmTitle ?? t('activities.packList.unpackStoreConfirmTitle')
const okAria = () => props.confirmAriaLabel ?? t('activities.packList.unpackStoreConfirmAria')
</script>

<template>
  <div class="pack-card-actions pack-unpack-store-controls" @click.stop>
    <div class="pack-unpack-store-controls__row pack-shell-forward-variance-actions">
      <label class="pack-shell-forward-count-label">
        <span class="sr-only">{{ t('activities.packList.unpackStoreQtyAria') }}</span>
        <input
          :value="qty"
          type="number"
          min="1"
          :max="max"
          class="form-input pack-shell-forward-count-input pack-unpack-store-input"
          :disabled="disabled"
          @input="onInput"
          @keyup.enter="onStore($event)"
        />
      </label>
      <button
        type="button"
        class="shell-forward-variance-btn shell-forward-variance-btn--minus"
        :title="t('activities.packList.unpackStoreMinusTitle')"
        :disabled="disabled || qty <= 1"
        @click="step(-1)"
      >
        −
      </button>
      <button
        type="button"
        class="shell-forward-variance-btn shell-forward-variance-btn--plus"
        :title="t('activities.packList.unpackStorePlusTitle')"
        :disabled="disabled || qty >= max"
        @click="step(1)"
      >
        +
      </button>
      <PackShellCheckToggle
        ok-only
        :disabled="disabled"
        :ok-title="okTitle()"
        :ok-aria-label="okAria()"
        @ok="onStore()"
      />
    </div>
  </div>
</template>

<style scoped>
.pack-unpack-store-controls {
  flex-shrink: 0;
}

.pack-unpack-store-controls__row {
  flex-wrap: nowrap;
  align-items: center;
}
</style>
