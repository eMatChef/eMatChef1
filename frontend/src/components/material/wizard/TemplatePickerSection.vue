<template>
  <div class="template-picker-section">
    <div class="template-picker-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
        <polyline points="10 9 9 9 8 9"/>
      </svg>
      <span>{{ t('components.templatePickerSection.headerTitle') }}</span>
    </div>
    <div class="template-picker-body">
      <div class="autocomplete-wrapper">
        <input
          :value="search"
          type="text"
          class="form-input"
          :placeholder="t('components.templatePickerSection.searchPlaceholder')"
          @input="(e) => $emit('update:search', (e.target as HTMLInputElement).value)"
          @focus="$emit('focus')"
          @blur="$emit('blur')"
        />
        <div v-if="showDropdown && filteredTemplates.length > 0" class="autocomplete-dropdown template-dropdown">
          <div
            v-for="tmpl in filteredTemplates"
            :key="tmpl.id"
            class="template-dropdown-item"
            @mousedown="$emit('select', tmpl)"
          >
            <div class="template-item-left">
              <span class="template-item-name">{{ tmpl.name }}</span>
              <span class="template-item-meta">
                <span v-if="tmpl.manufacturer" class="template-item-mfr">{{ tmpl.manufacturer }}</span>
                <span v-if="tmpl.capacity" class="template-item-cap">{{
                  t('components.templatePickerSection.capacityShort', { n: tmpl.capacity })
                }}</span>
                <span class="template-item-comp">{{
                  t('components.templatePickerSection.componentParts', { n: tmpl.component_count })
                }}</span>
              </span>
            </div>
            <span class="template-item-type" :class="tmpl.material_type">
              {{
                tmpl.material_type === 'physical_combo'
                  ? t('components.templatePickerSection.typePhysical')
                  : t('components.templatePickerSection.typeVirtual')
              }}
            </span>
          </div>
        </div>
        <div v-else-if="showDropdown && search.length >= 2 && filteredTemplates.length === 0" class="autocomplete-dropdown template-dropdown">
          <div class="combo-empty">{{ t('components.templatePickerSection.noTemplatesFound') }}</div>
        </div>
      </div>
    </div>
    <!-- Aus Kiste übernehmen (nur bei Combo; Inhalt = Materialien in dieser Kiste) -->
    <div v-if="showContainerBatchPicker" class="template-picker-divider">
      <span>{{ t('components.templatePickerSection.dividerOr') }}</span>
    </div>
    <ContainerBatchContentsPicker
      v-if="showContainerBatchPicker"
      :container-batch-id="containerBatchId"
      :container-batches="containerBatches"
      :is-loading="isLoadingContainerContents"
      :selected-contents="selectedContainerContents"
      @update:container-batch-id="$emit('update:containerBatchId', $event)"
      @load="$emit('loadContainerContents')"
    />
    <div class="template-picker-divider">
      <span>{{ t('components.templatePickerSection.dividerManual') }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import ContainerBatchContentsPicker from './ContainerBatchContentsPicker.vue'

const { t } = useI18n()
import type { Template } from '@/api/templates'
import type { ContainerBatch, ContainerBatchContentsResponse } from '@/api/storageLocations'

defineProps<{
  search: string
  showDropdown: boolean
  filteredTemplates: Template[]
  showContainerBatchPicker: boolean
  containerBatchId: string
  containerBatches: ContainerBatch[]
  isLoadingContainerContents: boolean
  selectedContainerContents: ContainerBatchContentsResponse | null
}>()
defineEmits<{
  'update:search': [value: string]
  focus: []
  blur: []
  select: [template: Template]
  'update:containerBatchId': [value: string]
  loadContainerContents: []
}>()
</script>
