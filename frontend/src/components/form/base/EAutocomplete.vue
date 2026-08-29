<template>
  <div
    class="e-form-field autosave-field"
    :class="{ 'e-form-field--error': hasError, 'is-error': hasError }"
  >
    <div class="autosave-control">
      <div class="autosave-field-frame">
        <label v-if="label" class="field-outline-label autosave-label" :for="fieldId">{{ label }}</label>
        <v-autocomplete
          variant="outlined"
          density="comfortable"
          v-bind="passthroughAttrs"
          :id="fieldId"
          :model-value="model"
          v-model:search="searchText"
          :items="items"
          :item-title="itemTitle"
          :item-value="itemValue"
          :item-subtitle="itemSubtitle"
          :return-object="returnObject"
          :auto-select-first="autoSelectFirst"
          :placeholder="placeholder"
          :hint="hint"
          :persistent-hint="persistentHint"
          :rules="rules"
          :error-messages="errorMessages"
          :disabled="disabled"
          :readonly="readonly"
          :clearable="clearable"
          :hide-details="hideDetails"
          :loading="loading"
          :no-filter="noFilter"
          :chips="chips"
          :closable-chips="closableChips"
          :autocomplete="autocomplete"
          v-model:menu="menuOpen"
          :menu-props="mergedMenuProps"
          class="e-autocomplete"
          @update:model-value="onUpdate"
        >
          <template #item="slotProps">
            <slot name="item" v-bind="slotProps">
              <v-list-item v-bind="slotProps.props" />
            </slot>
          </template>
          <template v-if="$slots['no-data']" #no-data>
            <slot name="no-data" />
          </template>
          <template v-if="$slots.prepend" #prepend>
            <slot name="prepend" />
          </template>
          <template v-if="$slots.append" #append>
            <slot name="append" />
          </template>
        </v-autocomplete>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue'

defineOptions({ inheritAttrs: false, name: 'EAutocomplete' })

const props = withDefaults(
  defineProps<{
    id?: string
    label?: string
    items?: readonly unknown[]
    itemTitle?: string | ((item: unknown) => string)
    itemValue?: string | ((item: unknown) => unknown)
    itemSubtitle?: string | ((item: unknown) => string)
    returnObject?: boolean
    autoSelectFirst?: boolean
    placeholder?: string
    hint?: string
    persistentHint?: boolean
    rules?: readonly ((value: unknown) => boolean | string)[]
    errorMessages?: string | readonly string[]
    disabled?: boolean
    readonly?: boolean
    clearable?: boolean
    hideDetails?: boolean | 'auto'
    loading?: boolean
    /** Client-/Server-Filter liegt in der View */
    noFilter?: boolean
    chips?: boolean
    closableChips?: boolean
    /** Browser-Autofill unterdrücken (sonst oft fremde Vorschläge über dem Menü) */
    autocomplete?: string
    menuProps?: Record<string, unknown>
  }>(),
  {
    items: () => [],
    itemTitle: 'title',
    itemValue: 'value',
    returnObject: false,
    autoSelectFirst: false,
    hideDetails: 'auto',
    loading: false,
    noFilter: true,
    clearable: true,
    chips: false,
    closableChips: false,
    autocomplete: 'off',
  },
)

const model = defineModel<unknown>({ default: null })
const search = defineModel<string | null>('search', { default: '' })
const menuOpen = defineModel<boolean>('menu', { default: false })

const searchText = computed({
  get: () => search.value ?? '',
  set: (value: string) => {
    search.value = value
  },
})

const attrs = useAttrs()
const generatedId = useId()
const fieldId = computed(() => props.id ?? generatedId)

const passthroughAttrs = computed(() => {
  const { variant: _variant, ...rest } = attrs
  return rest
})

const mergedMenuProps = computed(() => {
  const fromProps = props.menuProps ?? {}
  const contentClass = [fromProps.contentClass, 'onboarding-tour-menu-union']
    .flat()
    .filter(Boolean)
    .join(' ')
  return {
    eager: true,
    maxHeight: 280,
    zIndex: 4000,
    scrollStrategy: 'none',
    disableInitialFocus: true,
    ...fromProps,
    contentClass,
  }
})

const hasError = computed(() => {
  if (props.errorMessages) {
    const messages = Array.isArray(props.errorMessages) ? props.errorMessages : [props.errorMessages]
    if (messages.some(Boolean)) return true
  }
  return false
})

function onUpdate(value: unknown) {
  model.value = value
}
</script>
