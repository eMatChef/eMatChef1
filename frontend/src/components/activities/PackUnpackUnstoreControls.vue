<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PackMoveControls from '@/components/activities/PackMoveControls.vue'

defineOptions({ name: 'PackUnpackUnstoreControls' })

withDefaults(
  defineProps<{
    qty: number
    max: number
    disabled?: boolean
  }>(),
  { disabled: false },
)

const emit = defineEmits<{
  'update:qty': [value: number]
  move: [qty: number]
}>()

const { t } = useI18n()
</script>

<template>
  <PackMoveControls
    direction="back"
    class="pack-unpack-unstore-controls"
    :qty="qty"
    :max="max"
    :disabled="disabled"
    :back-title="t('activities.packList.unpackUnstoreTitle')"
    @update:qty="emit('update:qty', $event)"
    @move="emit('move', $event)"
  />
</template>
