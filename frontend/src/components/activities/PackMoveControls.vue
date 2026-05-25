<script setup lang="ts">
import { IconArrowLeft, IconArrowRight, IconArrowUp } from '@/components/icons'

defineOptions({ name: 'PackMoveControls' })

const props = withDefaults(
  defineProps<{
    direction: 'forward' | 'back' | 'assign-up'
    /** Kiste gewählt: gleicher Pfeil, 90° gedreht + grün — Position unverändert */
    intoCrate?: boolean
    qty: number
    /** Max. Menge beim Buchen (API / Pipeline) */
    max: number
    /** Oberes Limit im Eingabefeld (z. B. MW: bis bestellt); Standard = max */
    inputMax?: number
    /** Roter Rahmen, wenn qty < dieser Wert (z. B. bestellte Menge) */
    warnIfBelow?: number
    disabled?: boolean
    forwardTitle?: string
    backTitle?: string
    actionsClass?: string
  }>(),
  {
    intoCrate: false,
    disabled: false,
    actionsClass: '',
  },
)

const inputCap = () => {
  const cap = Math.floor(Number(props.inputMax ?? props.max))
  return cap > 0 ? cap : 0
}

const showPartialOrderWarn = () => {
  const below = props.warnIfBelow
  if (below == null || below < 1) return false
  return Math.floor(Number(props.qty)) < below
}

const emit = defineEmits<{
  'update:qty': [value: number]
  move: [qty: number]
}>()

function parseQtyFromInputEl(el: HTMLInputElement | null): number {
  if (!el) return props.qty
  const raw = parseInt(el.value, 10)
  let qty = Number.isFinite(raw) ? raw : props.qty
  if (qty < 1) qty = 1
  const maxVal = inputCap()
  if (maxVal > 0 && qty > maxVal) qty = maxVal
  return qty
}

function onInput(event: Event) {
  const qty = parseQtyFromInputEl(event.target as HTMLInputElement)
  emit('update:qty', qty)
}

function onMoveClick(event: MouseEvent | KeyboardEvent) {
  const root = (event.currentTarget as HTMLElement).closest('.pack-move-inline')
  const input = root?.querySelector('input.pack-move-input, input.pack-moveback-input') as HTMLInputElement | null
  const qty = parseQtyFromInputEl(input)
  emit('update:qty', qty)
  emit('move', qty)
}
</script>

<template>
  <div
    class="pack-card-actions"
    :class="[
      direction === 'back' ? 'pack-card-actions-left' : '',
      direction === 'assign-up' ? 'pack-card-actions-assign-up' : '',
      actionsClass,
    ]"
  >
    <div
      class="pack-move-inline"
      :class="{ 'pack-move-inline--below-ordered': showPartialOrderWarn() }"
    >
      <template v-if="direction === 'back'">
        <button
          type="button"
          class="btn-moveback-arrow"
          :class="{ 'btn-moveback-arrow--into-crate': intoCrate }"
          :disabled="disabled"
          :title="intoCrate ? forwardTitle : backTitle"
          @click="onMoveClick"
        >
          <IconArrowUp v-if="intoCrate" />
          <IconArrowLeft v-else />
        </button>
        <input
          :value="qty"
          type="number"
          min="1"
          :max="max"
          class="pack-moveback-input"
          :class="{ 'pack-moveback-input--into-crate': intoCrate }"
          @input="onInput"
          @keyup.enter="onMoveClick"
        />
      </template>
      <template v-else-if="direction === 'assign-up'">
        <input
          :value="qty"
          type="number"
          min="1"
          :max="inputCap() || max"
          class="pack-move-input"
          @input="onInput"
          @keyup.enter="onMoveClick"
        />
        <button
          type="button"
          class="btn-move-arrow btn-move-arrow--up"
          :disabled="disabled"
          :title="forwardTitle"
          @click="onMoveClick"
        >
          <IconArrowUp />
        </button>
      </template>
      <template v-else>
        <input
          :value="qty"
          type="number"
          min="1"
          :max="inputCap() || max"
          class="pack-move-input"
          @input="onInput"
          @keyup.enter="onMoveClick"
        />
        <button
          type="button"
          class="btn-move-arrow"
          :class="{ 'btn-move-arrow--into-crate': intoCrate }"
          :disabled="disabled"
          :title="forwardTitle"
          @click="onMoveClick"
        >
          <IconArrowUp v-if="intoCrate" />
          <IconArrowRight v-else />
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.btn-move-arrow--into-crate :deep(svg) {
  transform: rotate(-90deg);
  transform-origin: center center;
  transition: transform 0.15s ease;
}
</style>
