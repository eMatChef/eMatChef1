<template>
  <div class="department-address-autocomplete activity-address-select-row">
    <div ref="wrapperRef" class="autocomplete-wrapper activity-address-autocomplete">
      <input
        :id="inputId"
        ref="inputRef"
        v-model="search"
        type="text"
        class="form-input"
        :placeholder="placeholder"
        autocomplete="off"
        @input="onSearchInput"
        @focus="onSearchFocus"
        @blur="hideDropdownDelayed"
        @keydown="onSearchKeydown"
      />
    </div>
    <button
      v-if="showEditButton && selectedId"
      type="button"
      class="edit-inline-btn"
      :title="editButtonTitle"
      :aria-label="editButtonTitle"
      @click="onEditButtonClick"
    >
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path
          d="M11.3333 2C11.5084 1.82489 11.7163 1.68601 11.9444 1.59124C12.1726 1.49648 12.4163 1.44775 12.6625 1.44775C12.9087 1.44775 13.1524 1.49648 13.3806 1.59124C13.6087 1.68601 13.8166 1.82489 13.9917 2C14.1668 2.17511 14.3057 2.383 14.4005 2.61117C14.4952 2.83934 14.5439 3.08305 14.5439 3.32917C14.5439 3.57529 14.4952 3.819 14.4005 4.04717C14.3057 4.27534 14.1668 4.48323 13.9917 4.65833L5.325 13.325L2 14L2.675 10.675L11.3333 2Z"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </button>
    <button
      v-if="showAddButton"
      type="button"
      class="add-inline-btn"
      data-onboarding="activity-venue-add"
      :title="addButtonTitle"
      :aria-label="addButtonTitle"
      @click="onAddButtonClick"
    >
      +
    </button>

    <Teleport to="body">
      <div
        v-if="showDropdown && showAddressResults"
        class="autocomplete-dropdown activity-address-autocomplete-dropdown activity-address-autocomplete-dropdown--teleported"
        :style="dropdownStyle"
        @mousedown.prevent
      >
        <div
          v-if="filteredExtraItems.length && extraDividerText"
          class="autocomplete-divider"
          role="separator"
        >
          {{ extraDividerText }}
        </div>
        <div
          v-for="item in filteredExtraItems"
          :key="'extra-' + item.id"
          class="autocomplete-item activity-address-ac-item"
          @mousedown.prevent="selectExtra(item)"
        >
          <div class="activity-address-ac-main">
            <span class="item-name">{{ item.title }}</span>
            <span
              v-if="item.badge"
              class="address-type-badge address-type-badge--compact inquiry"
            >{{ item.badge }}</span>
          </div>
          <span class="item-city">{{ item.subtitle || '' }}</span>
        </div>
        <div
          v-if="filteredExtraItems.length && grouped.totalCount > 0 && addressGroupLabel"
          class="autocomplete-divider"
          role="separator"
        >
          {{ addressGroupLabel }}
        </div>
        <div
          v-for="a in grouped.primary"
          :key="a.id"
          class="autocomplete-item activity-address-ac-item"
          @mousedown.prevent="selectAddress(a)"
        >
          <div class="activity-address-ac-main">
            <span class="item-name">{{ addressDisplayName(a) }}</span>
            <span
              class="address-type-badge address-type-badge--compact"
              :class="a.type"
              :title="typeTitle(a)"
            >{{ a.type_label }}</span>
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
            <span
              class="address-type-badge address-type-badge--compact"
              :class="a.type"
              :title="typeTitle(a)"
            >{{ a.type_label }}</span>
          </div>
          <span class="item-city">{{ a.city_line || a.city || '' }}</span>
        </div>
      </div>
      <div
        v-else-if="showDropdown && showInlineCreate"
        class="autocomplete-dropdown activity-address-autocomplete-dropdown activity-address-autocomplete-dropdown--teleported"
        :style="dropdownStyle"
        @mousedown.prevent
      >
        <div
          class="autocomplete-item autocomplete-item--create"
          @mousedown.prevent="onInlineCreate"
        >
          <span class="item-name">{{ inlineCreateLabel }}</span>
        </div>
      </div>
      <div
        v-else-if="showDropdown && showEmptyAddressesHint"
        class="autocomplete-dropdown activity-address-autocomplete-dropdown activity-address-autocomplete-dropdown--teleported"
        :style="dropdownStyle"
        @mousedown.prevent
      >
        <div class="autocomplete-item autocomplete-empty">
          <span class="item-name">{{ emptyAddressesLabel }}</span>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch, type CSSProperties } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Address } from '@/api/addresses'
import {
  formatAddressSelectionLabel,
  groupDepartmentAddressesForSearch,
} from '@/utils/departmentAddressSearch'

/** Über Tour-Dimmer (20040), unter Tour-Karte (20060). */
const DROPDOWN_Z_INDEX = 20055

const props = withDefaults(
  defineProps<{
    addresses: Address[]
    selectedId?: string | null
    primaryType: string
    inputId?: string
    placeholder?: string
    addButtonTitle?: string
    editButtonTitle?: string
    emptyAddressesLabel?: string
    otherAddressesDividerLabel?: string
    extraItems?: Array<{
      id: string
      title: string
      subtitle?: string
      badge?: string
    }>
    extraItemsDividerLabel?: string
    addressGroupLabel?: string
    selectedExtraLabel?: string
    inlineCreateLabelKey?: string
    addressFallbackNameKey?: string
    addressTypeTitleKey?: string
    minQueryLength?: number
    showAddButton?: boolean
    showEditButton?: boolean
  }>(),
  {
    selectedId: null,
    minQueryLength: 1,
    showAddButton: true,
    showEditButton: false,
    inlineCreateLabelKey: 'addresses.search.createPrimaryInline',
    addressFallbackNameKey: 'activities.wizard.form.addressFallbackName',
    addressTypeTitleKey: 'activities.wizard.form.addressTypeTitle',
    emptyAddressesLabel: '',
    otherAddressesDividerLabel: '',
    addButtonTitle: '',
    editButtonTitle: '',
    placeholder: '',
    extraItems: () => [],
    extraItemsDividerLabel: '',
    addressGroupLabel: '',
    selectedExtraLabel: '',
  },
)

const emit = defineEmits<{
  'update:selectedId': [id: string | null]
  'select-extra': [id: string | null]
  create: [query: string]
  edit: [id: string]
}>()

const { t } = useI18n()
const search = ref('')
const showDropdown = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)
const wrapperRef = ref<HTMLElement | null>(null)
const dropdownStyle = ref<CSSProperties>({})
let positionListenersBound = false

const searchTrimmed = computed(() => search.value.trim())

const grouped = computed(() =>
  groupDepartmentAddressesForSearch(props.addresses, searchTrimmed.value, props.primaryType, {
    // Ohne Suche: nur Primärtyp (z. B. Eventstandorte), nicht alle Adresstypen
    maxOther: searchTrimmed.value ? 20 : 0,
  }),
)

const extraDividerText = computed(() => props.extraItemsDividerLabel)

const filteredExtraItems = computed(() => {
  const items = props.extraItems ?? []
  const q = searchTrimmed.value.toLowerCase()
  const matched = q
    ? items.filter((item) =>
        [item.title, item.subtitle, item.badge].some((field) =>
          (field ?? '').toLowerCase().includes(q),
        ),
      )
    : items
  return matched.slice(0, 20)
})

const showAddressResults = computed(
  () => grouped.value.totalCount > 0 || filteredExtraItems.value.length > 0,
)

const showInlineCreate = computed(
  () =>
    searchTrimmed.value.length >= props.minQueryLength &&
    grouped.value.totalCount === 0 &&
    filteredExtraItems.value.length === 0,
)

const showEmptyAddressesHint = computed(
  () =>
    props.addresses.length === 0 &&
    (props.extraItems?.length ?? 0) === 0 &&
    !!props.emptyAddressesLabel,
)

const dropdownOpen = computed(
  () =>
    showDropdown.value &&
    (showAddressResults.value || showInlineCreate.value || showEmptyAddressesHint.value),
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
  () => [props.selectedId, props.addresses, props.selectedExtraLabel] as const,
  () => {
    const id = props.selectedId
    if (id) {
      const a = props.addresses.find((x) => x.id === id)
      if (a) search.value = formatAddressSelectionLabel(a)
      return
    }
    if (props.selectedExtraLabel) {
      search.value = props.selectedExtraLabel
    }
  },
  { immediate: true },
)

watch(dropdownOpen, async (open) => {
  if (!open) {
    unbindPositionListeners()
    return
  }
  await nextTick()
  syncDropdownPosition()
  bindPositionListeners()
})

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

function syncDropdownPosition() {
  const el = wrapperRef.value ?? inputRef.value
  if (!el) return

  const rect = el.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const width = Math.min(Math.max(rect.width, 240), vw - 16)
  const left = Math.max(8, Math.min(rect.left, vw - width - 8))
  const spaceBelow = vh - rect.bottom - 8
  const spaceAbove = rect.top - 8
  const openBelow = spaceBelow >= 120 || spaceBelow >= spaceAbove

  if (openBelow) {
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      left: `${left}px`,
      width: `${width}px`,
      maxHeight: `${Math.min(220, Math.max(spaceBelow - 4, 80))}px`,
      zIndex: DROPDOWN_Z_INDEX,
    }
    return
  }

  dropdownStyle.value = {
    position: 'fixed',
    left: `${left}px`,
    width: `${width}px`,
    bottom: `${vh - rect.top + 4}px`,
    maxHeight: `${Math.min(220, Math.max(spaceAbove - 4, 80))}px`,
    zIndex: DROPDOWN_Z_INDEX,
  }
}

function bindPositionListeners() {
  if (positionListenersBound) return
  positionListenersBound = true
  window.addEventListener('resize', syncDropdownPosition, { passive: true })
  window.addEventListener('scroll', syncDropdownPosition, { passive: true, capture: true })
}

function unbindPositionListeners() {
  if (!positionListenersBound) return
  positionListenersBound = false
  window.removeEventListener('resize', syncDropdownPosition)
  window.removeEventListener('scroll', syncDropdownPosition, true)
}

function onSearchFocus() {
  showDropdown.value = true
}

function onSearchInput() {
  showDropdown.value = true
  if (props.selectedId) emit('update:selectedId', null)
  if (props.selectedExtraLabel) emit('select-extra', null)
}

function onSearchKeydown(event: KeyboardEvent) {
  if (event.key !== 'Enter') return
  event.preventDefault()
  event.stopPropagation()
  if (!showDropdown.value) return
  const firstExtra = filteredExtraItems.value[0]
  if (firstExtra) {
    selectExtra(firstExtra)
    return
  }
  const first = grouped.value.primary[0] ?? grouped.value.other[0]
  if (first) {
    selectAddress(first)
    return
  }
  if (showInlineCreate.value) {
    onInlineCreate()
  }
}

function selectAddress(a: Address) {
  emit('select-extra', null)
  emit('update:selectedId', a.id)
  search.value = formatAddressSelectionLabel(a)
  showDropdown.value = false
}

function selectExtra(item: { id: string; title: string }) {
  emit('update:selectedId', null)
  emit('select-extra', item.id)
  search.value = item.title
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

function onEditButtonClick() {
  if (!props.selectedId) return
  emit('edit', props.selectedId)
}

function clearSearch() {
  search.value = ''
  showDropdown.value = false
}

onUnmounted(unbindPositionListeners)

defineExpose({ clearSearch })
</script>

<style src="@/styles/components/department-address-autocomplete.css"></style>
