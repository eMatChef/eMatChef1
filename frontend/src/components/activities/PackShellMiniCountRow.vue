<script setup lang="ts">
import PackShellCheckToggle from '@/components/activities/PackShellCheckToggle.vue'

const props = withDefaults(
  defineProps<{
    label: string
    expectedQty: number
    countedQty: number
    /** null = noch nicht bestätigt, auch wenn Ist = Soll */
    reviewStatus: 'ok' | 'problem' | null
    disabled?: boolean
    minusTitle?: string
    plusTitle?: string
    okTitle?: string
    okAriaLabel?: string
  }>(),
  { disabled: false },
)

const emit = defineEmits<{
  'update:countedQty': [value: number]
  ok: []
}>()

function varianceKind(): 'ok' | 'short' | 'surplus' | 'unset' {
  if (props.countedQty === props.expectedQty) return 'ok'
  if (props.countedQty < props.expectedQty) return 'short'
  if (props.countedQty > props.expectedQty) return 'surplus'
  return 'unset'
}

function setCounted(next: number) {
  emit('update:countedQty', Math.max(0, next))
}
</script>

<template>
  <li
    class="pack-shell-mini-count-row"
    :class="{
      'pack-shell-mini-count-row--ok': reviewStatus === 'ok',
      'pack-shell-mini-count-row--short': reviewStatus === 'problem' && varianceKind() === 'short',
      'pack-shell-mini-count-row--surplus': reviewStatus === 'problem' && varianceKind() === 'surplus',
      'pack-shell-mini-count-row--pending':
        reviewStatus === null && varianceKind() !== 'ok',
    }"
  >
    <div class="pack-shell-mini-count-row__main">
      <span class="pack-shell-mini-count-row__label">{{ label }}</span>
      <span class="pack-shell-mini-count-row__soll text-muted">
        {{ $t('activities.packList.shellForwardInventoryLocationSoll', { n: expectedQty }) }}
      </span>
    </div>
    <div class="pack-shell-mini-count-row__controls">
      <button
        type="button"
        class="shell-forward-variance-btn shell-forward-variance-btn--minus shell-forward-variance-btn--mini"
        :class="{ 'shell-forward-variance-btn--active': varianceKind() === 'short' }"
        :title="minusTitle"
        :disabled="disabled"
        @click="setCounted(countedQty - 1)"
      >
        −
      </button>
      <label class="pack-shell-forward-count-label">
        <span class="sr-only">{{ $t('activities.packList.shellForwardCountedQty') }}</span>
        <input
          :value="countedQty"
          type="number"
          min="0"
          class="form-input pack-shell-forward-count-input pack-shell-forward-count-input--mini"
          :disabled="disabled"
          @input="setCounted(parseInt(($event.target as HTMLInputElement).value, 10) || 0)"
        />
      </label>
      <button
        type="button"
        class="shell-forward-variance-btn shell-forward-variance-btn--plus shell-forward-variance-btn--mini"
        :class="{ 'shell-forward-variance-btn--active': varianceKind() === 'surplus' }"
        :title="plusTitle"
        :disabled="disabled"
        @click="setCounted(countedQty + 1)"
      >
        +
      </button>
      <PackShellCheckToggle
        ok-only
        :ok-active="reviewStatus === 'ok'"
        :ok-title="okTitle ?? ''"
        :ok-aria-label="okAriaLabel ?? okTitle ?? ''"
        :disabled="disabled"
        @ok="emit('ok')"
      />
    </div>
  </li>
</template>

<style scoped>
.pack-shell-mini-count-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 12px;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
}

.pack-shell-mini-count-row--ok {
  border-color: #bbf7d0;
  background: #f0fdf4;
}

.pack-shell-mini-count-row--short {
  border-color: #fecaca;
  background: #fef2f2;
}

.pack-shell-mini-count-row--surplus {
  border-color: #fed7aa;
  background: #fff7ed;
}

.pack-shell-mini-count-row--pending {
  border-color: #e2e8f0;
  background: #fafafa;
}

.pack-shell-mini-count-row__main {
  flex: 1 1 160px;
  min-width: 0;
}

.pack-shell-mini-count-row__label {
  display: block;
  font-size: 13px;
  line-height: 1.35;
}

.pack-shell-mini-count-row__soll {
  font-size: 12px;
}

.pack-shell-mini-count-row__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.shell-forward-variance-btn {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  font-size: 16px;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
}

.shell-forward-variance-btn--minus.shell-forward-variance-btn--active {
  border-color: #dc2626;
  background: #fef2f2;
  color: #b91c1c;
}

.shell-forward-variance-btn--plus.shell-forward-variance-btn--active {
  border-color: #ea580c;
  background: #fff7ed;
  color: #c2410c;
}

.shell-forward-variance-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.pack-shell-forward-count-input--mini {
  width: 3rem;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
