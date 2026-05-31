<template>
  <SearchFieldInput
    ref="searchRef"
    v-bind="passthroughAttrs"
    :input-id="fieldId"
    :model-value="model ?? ''"
    :label="label"
    :clearable="clearable"
    :clear-aria-label="clearAriaLabel"
    class="e-search-field"
    @update:model-value="onUpdate"
    @focus="$emit('focus', $event)"
    @blur="$emit('blur', $event)"
    @input="$emit('input', $event)"
    @keydown="$emit('keydown', $event)"
    @clear="$emit('clear')"
  >
    <slot />
  </SearchFieldInput>
</template>

<script setup lang="ts">
import { computed, ref, useAttrs, useId } from 'vue'
import SearchFieldInput from '@/components/common/SearchFieldInput.vue'

defineOptions({ inheritAttrs: false, name: 'ESearchField' })

const props = withDefaults(
  defineProps<{
    id?: string
    label?: string
    clearable?: boolean
    clearAriaLabel?: string
  }>(),
  {
    clearable: true,
  },
)

const model = defineModel<string>({ default: '' })
const attrs = useAttrs()
const generatedId = useId()
const fieldId = computed(() => props.id ?? generatedId)
const searchRef = ref<InstanceType<typeof SearchFieldInput> | null>(null)

const passthroughAttrs = computed(() => attrs)

defineEmits<{
  focus: [event: FocusEvent]
  blur: [event: FocusEvent]
  input: [event: Event]
  keydown: [event: KeyboardEvent]
  clear: []
}>()

function onUpdate(value: string) {
  model.value = value
}

defineExpose({
  focus: () => searchRef.value?.focus(),
  blur: () => searchRef.value?.blur(),
  get inputRef() {
    return searchRef.value?.inputRef ?? null
  },
})
</script>
