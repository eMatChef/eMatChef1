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
  /** Verbrauch buchen (ohne Nachlieferung-only-Zeile) */
  showConsumption?: boolean
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, unknown>

const qty = computed(() => {
  const fn = ctx.consumableInlineQtyFor as ((id: string) => number) | undefined
  return fn?.(props.materialItemId) ?? 0
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

const showNachbuchung = computed(() => {
  const fn = ctx.showConsumableNachbuchungForMaterial as ((id: string) => boolean) | undefined
  if (fn) return fn(props.materialItemId)
  return injectPackCtxBool(ctx, 'canRequestConsumableNachbuchung')
})

const showConsumptionControls = computed(() => props.showConsumption !== false)

function setQty(next: number) {
  const fn = ctx.setConsumableInlineQty as ((id: string, qty: number) => void) | undefined
  const n = Number.isFinite(next) ? Math.floor(next) : 0
  fn?.(props.materialItemId, Math.max(0, Math.min(maxQty.value, n)))
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
    <span v-if="!compact && showConsumptionControls" class="pack-consumable-quick-row__label text-muted">
      {{ t('activities.packList.consumableInlineLabel') }}
    </span>
    <div v-if="showConsumptionControls" class="pack-consumable-quick-row__actions consumable-qty-row">
      <button
        type="button"
        class="btn-qty"
        :title="t('activities.packList.consumableInlineMinusTitle')"
        :disabled="posting || qty <= 0"
        @click="step(-1)"
      >
        −
      </button>
      <input
        :value="qty"
        type="number"
        min="0"
        :max="maxQty"
        class="consumable-qty-input pack-consumable-qty-input"
        :disabled="posting"
        :aria-label="t('activities.packList.consumableInlineQtyAria')"
        @input="setQty(parseInt(($event.target as HTMLInputElement).value, 10))"
      />
      <button
        type="button"
        class="btn-qty"
        :title="t('activities.packList.consumableInlinePlusTitle')"
        :disabled="posting || qty >= maxQty"
        @click="step(1)"
      >
        +
      </button>
      <PackShellCheckToggle
        ok-only
        :disabled="posting || qty < 1"
        :ok-title="t('activities.packList.consumableInlineConfirmTitle')"
        :ok-aria-label="t('activities.packList.consumableInlineConfirmAria')"
        @ok="onConfirm"
      />
    </div>
    <button
      v-if="showNachbuchung"
      type="button"
      class="btn btn-xs btn-outline pack-consumable-quick-row__nachbuchung"
      :disabled="posting"
      @click="onNachbuchung"
    >
      {{ t('activities.packList.consumableInlineNachbuchung') }}
    </button>
  </div>
</template>

<style src="@/styles/views/activities/detail-panel.css"></style>
<style src="@/styles/views/activities/detail-workflow.css"></style>
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
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 6px;
}

.pack-consumable-qty-input {
  width: 52px;
  min-width: 52px;
  text-align: center;
  padding: 4px 6px;
  font-size: 14px;
}

.pack-consumable-quick-row__nachbuchung {
  margin-top: 2px;
}
</style>
