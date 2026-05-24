<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import PackShellCheckToggle from '@/components/activities/PackShellCheckToggle.vue'
import {
  injectPackCtxBool,
  PACK_WAREHOUSE_ISSUE_INJECT_KEY,
} from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackConsumableQuickRow' })

const props = defineProps<{
  materialItemId: string
  /** Kompakter Modus in Kistenzeilen */
  compact?: boolean
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, unknown>

const qty = computed(() => {
  const fn = ctx.consumableInlineQtyFor as ((id: string) => number) | undefined
  return fn?.(props.materialItemId) ?? 1
})

const maxQty = computed(() => {
  const fn = ctx.maxInlineConsumptionQtyForMaterial as ((id: string) => number) | undefined
  return Math.max(1, fn?.(props.materialItemId) ?? 1)
})

const posting = computed(() => {
  const id = ctx.consumableInlinePostingId as { value?: string | null } | string | null | undefined
  const current = typeof id === 'object' && id != null && 'value' in id ? id.value : id
  return current === props.materialItemId
})

const canNachbuchung = computed(() => injectPackCtxBool(ctx, 'canRequestConsumableNachbuchung'))

function setQty(next: number) {
  const fn = ctx.setConsumableInlineQty as ((id: string, qty: number) => void) | undefined
  fn?.(props.materialItemId, Math.max(1, Math.min(maxQty.value, Math.floor(next) || 1)))
}

function step(delta: number) {
  setQty(qty.value + delta)
}

function onConfirm() {
  const fn = ctx.submitConsumableInlineForMaterial as ((id: string) => void | Promise<void>) | undefined
  void fn?.(props.materialItemId)
}

function onNachbuchung() {
  const fn = ctx.emitConsumableNachbuchungForMaterial as ((id: string) => void) | undefined
  fn?.(props.materialItemId)
}
</script>

<template>
  <div
    class="pack-consumable-quick-row"
    :class="{ 'pack-consumable-quick-row--compact': compact }"
    @click.stop
  >
    <span v-if="!compact" class="pack-consumable-quick-row__label text-muted">
      {{ t('activities.packList.consumableInlineLabel') }}
    </span>
    <div class="pack-consumable-quick-row__actions pack-shell-forward-variance-actions">
      <button
        type="button"
        class="shell-forward-variance-btn shell-forward-variance-btn--minus"
        :title="t('activities.packList.consumableInlineMinusTitle')"
        :disabled="posting || qty <= 1"
        @click="step(-1)"
      >
        −
      </button>
      <label class="pack-shell-forward-count-label">
        <span class="sr-only">{{ t('activities.packList.consumableInlineQtyAria') }}</span>
        <input
          :value="qty"
          type="number"
          min="1"
          :max="maxQty"
          class="form-input pack-shell-forward-count-input"
          :disabled="posting"
          @input="setQty(parseInt(($event.target as HTMLInputElement).value, 10) || 1)"
        />
      </label>
      <button
        type="button"
        class="shell-forward-variance-btn shell-forward-variance-btn--plus"
        :title="t('activities.packList.consumableInlinePlusTitle')"
        :disabled="posting || qty >= maxQty"
        @click="step(1)"
      >
        +
      </button>
      <PackShellCheckToggle
        ok-only
        :disabled="posting"
        :ok-title="t('activities.packList.consumableInlineConfirmTitle')"
        :ok-aria-label="t('activities.packList.consumableInlineConfirmAria')"
        @ok="onConfirm"
      />
    </div>
    <button
      v-if="canNachbuchung"
      type="button"
      class="pack-consumable-quick-row__nachbuchung link-btn"
      :disabled="posting"
      @click="onNachbuchung"
    >
      {{ t('activities.packList.consumableInlineNachbuchung') }}
    </button>
  </div>
</template>

<style src="@/styles/views/activities/pack-workflow-modals.css"></style>
<style scoped>
.pack-consumable-quick-row {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  margin-top: 6px;
  width: 100%;
}

.pack-consumable-quick-row--compact {
  margin-top: 4px;
}

.pack-consumable-quick-row__label {
  font-size: 12px;
  font-weight: 500;
}

.pack-consumable-quick-row__actions {
  flex-wrap: nowrap;
}

.pack-consumable-quick-row__nachbuchung {
  font-size: 12px;
}
</style>
