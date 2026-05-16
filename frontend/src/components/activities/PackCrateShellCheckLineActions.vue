<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PackShellCheckToggle from '@/components/activities/PackShellCheckToggle.vue'
import type { ShellForwardLineReview } from '@/components/activities/packCrateForwardCheck'

defineOptions({ name: 'PackCrateShellCheckLineActions' })

const props = defineProps<{
  materialName: string
  expectedQty: number
  review: ShellForwardLineReview
  isExtra?: boolean
  minusDisabled?: boolean
  plusDisabled?: boolean
  inputDisabled?: boolean
  checkDisabled?: boolean
}>()

const emit = defineEmits<{
  'update:countedQty': [value: number]
  ok: []
}>()

const { t } = useI18n()

const varianceKind = computed((): 'ok' | 'short' | 'surplus' | 'unset' => {
  if (props.review.status === 'ok') return 'ok'
  const counted = props.review.countedQty
  if (counted < props.expectedQty) return 'short'
  if (counted > props.expectedQty) return 'surplus'
  return 'unset'
})

function setCounted(next: number) {
  emit('update:countedQty', Math.max(0, Math.floor(next) || 0))
}
</script>

<template>
  <div
    class="pack-crate-shell-check-line"
    :class="{
      'pack-crate-shell-check-line--ok': review.status === 'ok',
      'pack-crate-shell-check-line--short': review.status === 'problem' && varianceKind === 'short',
      'pack-crate-shell-check-line--surplus': review.status === 'problem' && varianceKind === 'surplus',
      'pack-crate-shell-check-line--pending': review.status === null && varianceKind === 'unset',
    }"
  >
    <div class="pack-crate-shell-check-line__main">
      <span class="pack-crate-shell-check-line__name">{{ materialName }}</span>
      <span class="pack-crate-shell-check-line__soll text-muted">
        {{ t('activities.packList.shellForwardExpectedQty', { n: expectedQty }) }}
      </span>
    </div>
    <div class="pack-crate-shell-check-line__actions pack-shell-forward-variance-actions">
      <button
        type="button"
        class="shell-forward-variance-btn shell-forward-variance-btn--minus"
        :class="{ 'shell-forward-variance-btn--active': varianceKind === 'short' }"
        :title="t('activities.packList.shellForwardMinusTitle')"
        :disabled="minusDisabled"
        @click="setCounted(review.countedQty - 1)"
      >
        −
      </button>
      <label class="pack-shell-forward-count-label">
        <span class="sr-only">{{ t('activities.packList.shellForwardCountedQty') }}</span>
        <input
          :value="review.countedQty"
          type="number"
          min="0"
          class="form-input pack-shell-forward-count-input"
          :disabled="inputDisabled"
          @input="setCounted(parseInt(($event.target as HTMLInputElement).value, 10) || 0)"
        />
      </label>
      <button
        type="button"
        class="shell-forward-variance-btn shell-forward-variance-btn--plus"
        :class="{ 'shell-forward-variance-btn--active': varianceKind === 'surplus' }"
        :title="t('activities.packList.shellForwardPlusTitle')"
        :disabled="plusDisabled"
        @click="setCounted(review.countedQty + 1)"
      >
        +
      </button>
      <PackShellCheckToggle
        ok-only
        :ok-active="review.status === 'ok'"
        :disabled="checkDisabled"
        :ok-title="t('activities.packList.shellForwardLineOkTitle')"
        :ok-aria-label="t('activities.packList.shellForwardLineOkAria', { name: materialName })"
        @ok="emit('ok')"
      />
    </div>
  </div>
</template>

<style src="@/styles/views/activities/pack-workflow-modals.css"></style>
<style src="@/styles/views/activities/pack-shell-combo.css"></style>
<style scoped>
.pack-crate-shell-check-line__main {
  flex: 1 1 140px;
  min-width: 0;
}

.pack-crate-shell-check-line__name {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.pack-crate-shell-check-line__soll {
  font-size: 12px;
}

.pack-crate-shell-check-line__actions {
  flex: 0 0 auto;
}

.pack-crate-shell-check-line--ok {
  border-color: #bbf7d0;
  background: #f0fdf4;
  border-left: 3px solid #16a34a;
}

.pack-crate-shell-check-line--short {
  border-color: #fecaca;
  background: #fef2f2;
  border-left: 3px solid #dc2626;
}

.pack-crate-shell-check-line--surplus {
  border-color: #fed7aa;
  background: #fff7ed;
  border-left: 3px solid #ea580c;
}

.pack-crate-shell-check-line--pending {
  border-color: #e2e8f0;
  background: #fafafa;
  border-left: 3px solid #cbd5e1;
}

.pack-shell-forward-variance-actions {
  flex-wrap: wrap;
  gap: 4px;
}

.shell-forward-variance-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  font-size: 18px;
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

.pack-shell-forward-count-input {
  width: 3.5rem;
  text-align: center;
  padding: 4px 6px;
}
</style>
