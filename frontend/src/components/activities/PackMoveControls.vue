<script setup lang="ts">
import { IconArrowLeft, IconArrowRight, IconArrowUp } from '@/components/icons'

defineOptions({ name: 'PackMoveControls' })

withDefaults(
  defineProps<{
    direction: 'forward' | 'back' | 'assign-up'
    qty: number
    max: number
    disabled?: boolean
    forwardTitle?: string
    backTitle?: string
    actionsClass?: string
  }>(),
  {
    disabled: false,
    actionsClass: '',
  },
)

const emit = defineEmits<{
  'update:qty': [value: number]
  move: []
}>()

function onInput(event: Event) {
  const raw = parseInt((event.target as HTMLInputElement).value, 10)
  emit('update:qty', Number.isFinite(raw) ? raw : 0)
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
    <div class="pack-move-inline">
      <template v-if="direction === 'back'">
        <button
          type="button"
          class="btn-moveback-arrow"
          :disabled="disabled"
          :title="backTitle"
          @click="emit('move')"
        >
          <IconArrowLeft />
        </button>
        <input
          :value="qty"
          type="number"
          min="1"
          :max="max"
          class="pack-moveback-input"
          @input="onInput"
          @keyup.enter="emit('move')"
        />
      </template>
      <template v-else-if="direction === 'assign-up'">
        <input
          :value="qty"
          type="number"
          min="1"
          :max="max"
          class="pack-move-input"
          @input="onInput"
          @keyup.enter="emit('move')"
        />
        <button
          type="button"
          class="btn-move-arrow btn-move-arrow--up"
          :disabled="disabled"
          :title="forwardTitle"
          @click="emit('move')"
        >
          <IconArrowUp />
        </button>
      </template>
      <template v-else>
        <input
          :value="qty"
          type="number"
          min="1"
          :max="max"
          class="pack-move-input"
          @input="onInput"
          @keyup.enter="emit('move')"
        />
        <button
          type="button"
          class="btn-move-arrow"
          :disabled="disabled"
          :title="forwardTitle"
          @click="emit('move')"
        >
          <IconArrowRight />
        </button>
      </template>
    </div>
  </div>
</template>
