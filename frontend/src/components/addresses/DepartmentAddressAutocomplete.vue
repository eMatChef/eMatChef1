<template>
  <div class="department-address-autocomplete activity-address-select-row">
    <div class="autocomplete-wrapper activity-address-autocomplete">
      <input
        :id="inputId"
        v-model="search"
        type="text"
        class="form-input"
        :placeholder="placeholder"
        autocomplete="off"
        @input="onSearchInput"
        @focus="showDropdown = true"
        @blur="hideDropdownDelayed"
      />
      <div
        v-if="showDropdown && grouped.totalCount > 0"
        class="autocomplete-dropdown activity-address-autocomplete-dropdown"
      >
        <div
          v-for="a in grouped.primary"
          :key="a.id"
          class="autocomplete-item activity-address-ac-item"
          @mousedown.prevent="selectAddress(a)"
        >
          <div class="activity-address-ac-main">
            <span class="item-name">{{ addressDisplayName(a) }}</span>
            <span class="address-type-badge address-type-badge--compact" :class="a.type" :title="typeTitle(a)">{{ a.type_label }}</span>
          </div>
          <span class="item-city">{{ a.city_line || a.city || '' }}</span>
        </div>
        <div v-if="grouped.showDivider" class="autocomplete-divider" role="separator">
          {{ otherAddressesDividerLabel }}
        </div>
        <div
          v-for="a in grouped.other"
          :key="a.id"
          class="autocomplete-item activity-address-ac-item"
          @mousedown.prevent="selectAddress(a)"
        >
          <div class="activity-address-ac-main">
            <span class="item-name">{{ addressDisplayName(a) }}</span>
            <span class="address-type-badge address-type-badge--compact" :class="a.type" :title="typeTitle(a)">{{ a.type_label }}</span>
          </div>
          <span class="item-city">{{ a.city_line || a.city || '' }}</span>
        </div>
      </div>
      <div
        v-else-if="showDropdown && showInlineCreate"
        class="autocomplete-dropdown activity-address-autocomplete-dropdown"
      >
        <div
          class="autocomplete-item autocomplete-item--create"
          @mousedown.prevent="onInlineCreate"
        >
          <span class="item-name">{{ inlineCreateLabel }}</span>
        </div>
      </div>
      <div
        v-else-if="showDropdown && addresses.length === 0"
        class="autocomplete-dropdown activity-address-autocomplete-dropdown"
      >
        <div class="autocomplete-item autocomplete-empty">
          <span class="item-name">{{ emptyAddressesLabel }}</span>
        </div>
      </div>
    </div>
    <button
      v-if="showAddButton"
      type="button"
      class="add-inline-btn"
      :title="addButtonTitle"
      :aria-label="addButtonTitle"
      @click="onAddButtonClick"
    >
      +
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Address } from '@/api/addresses'
import {
  formatAddressSelectionLabel,
  groupDepartmentAddressesForSearch,
} from '@/utils/departmentAddressSearch'

const props = withDefaults(
  defineProps<{
    addresses: Address[]
    selectedId?: string | null
    primaryType: string
    inputId?: string
    placeholder?: string
    addButtonTitle?: string
    emptyAddressesLabel?: string
    otherAddressesDividerLabel?: string
    inlineCreateLabelKey?: string
    addressFallbackNameKey?: string
    addressTypeTitleKey?: string
    minQueryLength?: number
    showAddButton?: boolean
  }>(),
  {
    selectedId: null,
    minQueryLength: 1,
    showAddButton: true,
    inlineCreateLabelKey: 'addresses.search.createPrimaryInline',
    addressFallbackNameKey: 'activities.wizard.form.addressFallbackName',
    addressTypeTitleKey: 'activities.wizard.form.addressTypeTitle',
    emptyAddressesLabel: '',
    otherAddressesDividerLabel: '',
    addButtonTitle: '',
    placeholder: '',
  },
)

const emit = defineEmits<{
  'update:selectedId': [id: string | null]
  create: [query: string]
}>()

const { t } = useI18n()
const search = ref('')
const showDropdown = ref(false)

const searchTrimmed = computed(() => search.value.trim())

const grouped = computed(() =>
  groupDepartmentAddressesForSearch(props.addresses, searchTrimmed.value, props.primaryType),
)

const showInlineCreate = computed(
  () =>
    searchTrimmed.value.length >= props.minQueryLength &&
    grouped.value.totalCount === 0 &&
    props.addresses.length >= 0,
)

const inlineCreateLabel = computed(() => {
  const q = searchTrimmed.value
  if (!q) return t('addresses.search.createPrimaryInlineGeneric')
  return t(props.inlineCreateLabelKey, { query: q })
})

const otherAddressesDividerLabel = computed(
  () => props.otherAddressesDividerLabel || t('addresses.search.otherAddressesDivider'),
)

watch(
  () => [props.selectedId, props.addresses] as const,
  () => {
    const id = props.selectedId
    if (!id) return
    const a = props.addresses.find((x) => x.id === id)
    if (a) search.value = formatAddressSelectionLabel(a)
  },
  { immediate: true },
)

function addressDisplayName(a: Address): string {
  return (
    a.name ||
    a.company ||
    a.street_line ||
    t(props.addressFallbackNameKey)
  )
}

function typeTitle(a: Address): string {
  return t(props.addressTypeTitleKey, { type: a.type_label })
}

function onSearchInput() {
  showDropdown.value = true
  if (props.selectedId) emit('update:selectedId', null)
}

function selectAddress(a: Address) {
  emit('update:selectedId', a.id)
  search.value = formatAddressSelectionLabel(a)
  showDropdown.value = false
}

function hideDropdownDelayed() {
  window.setTimeout(() => {
    showDropdown.value = false
  }, 200)
}

function onInlineCreate() {
  emit('create', searchTrimmed.value)
  showDropdown.value = false
}

function onAddButtonClick() {
  emit('create', searchTrimmed.value)
}

function clearSearch() {
  search.value = ''
  showDropdown.value = false
}

defineExpose({ clearSearch })
</script>

<style src="@/styles/components/department-address-autocomplete.css"></style>
