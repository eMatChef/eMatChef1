<script setup lang="ts">
import { computed, useId } from 'vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title?: string
    size?: 'md' | 'lg'
    /** Kein Schliessen per Klick neben dem Dialog */
    lockBackdrop?: boolean
  }>(),
  {
    title: '',
    size: 'lg',
    lockBackdrop: true,
  },
)

defineEmits<{
  cancel: []
}>()

const titleId = useId()
const sizeClass = computed(() =>
  props.size === 'md' ? 'pack-workflow-modal--md' : 'pack-workflow-modal--lg',
)
</script>

<template>
  <div
    v-if="open"
    class="pack-workflow-modal-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="title ? titleId : undefined"
  >
    <div class="pack-workflow-modal" :class="sizeClass" @click.stop>
      <button
        type="button"
        class="pack-modal-dismiss-x"
        :aria-label="$t('activities.common.close')"
        @click="$emit('cancel')"
      >
        ×
      </button>

      <header v-if="$slots.title || title || $slots.intro" class="pack-workflow-modal__header">
        <h3 v-if="$slots.title || title" :id="titleId" class="pack-modal-title">
          <slot name="title">{{ title }}</slot>
        </h3>
        <slot name="intro" />
      </header>

      <div class="pack-workflow-modal__body">
        <slot />
      </div>

      <footer v-if="$slots.footer" class="pack-workflow-modal__footer">
        <slot name="footer" />
      </footer>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style scoped>
.pack-workflow-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.45);
}

.pack-workflow-modal {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 100%;
  max-height: min(92vh, 56rem);
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.18);
  overflow: hidden;
}

.pack-workflow-modal--md {
  max-width: 32rem;
}

.pack-workflow-modal--lg {
  max-width: 42rem;
}

.pack-workflow-modal__header {
  flex-shrink: 0;
  padding: 20px 20px 0;
}

.pack-workflow-modal__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 12px 20px;
  -webkit-overflow-scrolling: touch;
}

.pack-workflow-modal__footer {
  flex-shrink: 0;
  padding: 12px 20px 20px;
  border-top: 1px solid #e5e7eb;
  background: #fafafa;
}
</style>
