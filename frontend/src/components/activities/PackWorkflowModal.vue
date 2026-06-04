<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EDialog } from '@/components/form/base'

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

const emit = defineEmits<{ cancel: [] }>()

const { t } = useI18n()

const maxWidth = computed(() => (props.size === 'md' ? 512 : 672))

function onOpenChange(value: boolean) {
  if (!value) emit('cancel')
}
</script>

<template>
  <EDialog
    :model-value="open"
    :max-width="maxWidth"
    :persistent="lockBackdrop"
    scrollable
    card-class="pack-workflow-dialog-card"
    @update:model-value="onOpenChange"
  >
    <template v-if="$slots.title || title" #title>
      <div class="pack-workflow-dialog__title-row">
        <span class="pack-workflow-dialog__title-text">
          <slot name="title">{{ title }}</slot>
        </span>
        <v-btn
          icon
          variant="text"
          size="small"
          :aria-label="t('activities.common.close')"
          @click="emit('cancel')"
        >
          <v-icon icon="mdi-close" size="22" />
        </v-btn>
      </div>
    </template>

    <div v-if="$slots.intro" class="pack-workflow-dialog__intro">
      <slot name="intro" />
    </div>
    <slot />

    <template v-if="$slots.footer" #actions>
      <div class="pack-workflow-dialog__footer">
        <slot name="footer" />
      </div>
    </template>
  </EDialog>
</template>

<style src="@/styles/views/activities/pack-workflow-modals.css"></style>
<style scoped>
.pack-workflow-dialog__title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
}

.pack-workflow-dialog__title-text {
  flex: 1;
  min-width: 0;
}

.pack-workflow-dialog__intro {
  margin-bottom: 4px;
}

.pack-workflow-dialog__footer {
  display: flex;
  width: 100%;
  justify-content: flex-end;
}

.pack-workflow-dialog__footer :deep(.pack-modal-actions) {
  width: 100%;
  margin-top: 0;
}
</style>
