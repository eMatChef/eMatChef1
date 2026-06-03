<template>
  <button
    type="button"
    class="icon-btn"
    :class="{ 'icon-btn-danger': danger }"
    :title="title"
    :aria-label="ariaLabel || title"
    :disabled="disabled"
    @click="$emit('click', $event)"
  >
    <v-icon
      :icon="loading ? 'mdi-loading' : icon"
      class="table-icon-sm"
      :class="{ 'mdi-spin': loading }"
      size="18"
    />
  </button>
</template>

<script setup lang="ts">
defineOptions({ name: 'TableIconButton' })

withDefaults(
  defineProps<{
    icon: string
    title?: string
    ariaLabel?: string
    danger?: boolean
    disabled?: boolean
    loading?: boolean
  }>(),
  {
    title: '',
    ariaLabel: '',
    danger: false,
    disabled: false,
    loading: false,
  },
)

defineEmits<{
  click: [event: MouseEvent]
}>()
</script>
