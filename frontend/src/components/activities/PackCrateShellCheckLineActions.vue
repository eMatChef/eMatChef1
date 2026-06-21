<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PackShellCheckToggle from '@/components/activities/PackShellCheckToggle.vue'
import type { ShellForwardLineReview } from '@/components/activities/packCrateForwardCheck'
import '@/styles/views/activities/pack-shell-combo.css'

defineOptions({ name: 'PackCrateShellCheckLineActions' })

const props = defineProps<{
  materialName: string
  expectedQty: number
  /** Erwartete Seriennummer zur Sichtprüfung in der Kiste */
  serialHint?: string | null
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

function currentCounted(): number {
  return Math.max(0, Math.floor(Number(props.review.countedQty)) || 0)
}

function setCounted(next: number) {
  emit('update:countedQty', Math.max(0, Math.floor(Number(next)) || 0))
}

function stepCounted(delta: number) {
  setCounted(currentCounted() + delta)
}
</script>

<template>
  <div class="pack-crate-shell-check-line">
    <div class="pack-crate-shell-check-line__main pack-shell-forward-li-meta">
      <div class="pack-shell-forward-li-name">{{ materialName }}</div>
      <div class="pack-shell-forward-li-sub text-muted">
        <span v-if="!isExtra">{{ t('activities.packList.shellForwardExpectedQty', { n: expectedQty }) }}</span>
        <span v-else>{{ t('activities.packList.shellForwardExtraCountOnly') }}</span>
        <span
          v-if="serialHint"
          class="pack-shell-forward-li-serial"
          :title="t('activities.packList.shellForwardSerialCheckTitle')"
        >
          {{ t('activities.packList.shellForwardSerialSn', { serial: serialHint }) }}
        </span>
      </div>
    </div>
    <div class="pack-crate-shell-check-line__actions pack-shell-forward-variance-actions">
      <button
        type="button"
        class="shell-forward-variance-btn shell-forward-variance-btn--minus"
        :class="{ 'shell-forward-variance-btn--active': varianceKind === 'short' }"
        :title="t('activities.packList.shellForwardMinusTitle')"
        :disabled="minusDisabled"
        @click="stepCounted(-1)"
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
        @click="stepCounted(1)"
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

<style scoped>
.pack-crate-shell-check-line__main {
  flex: 1 1 140px;
  min-width: 0;
}

.pack-crate-shell-check-line__actions {
  flex: 0 0 auto;
}
</style>
