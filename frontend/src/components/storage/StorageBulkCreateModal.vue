<template>
  <div v-if="isOpen" class="modal-overlay">
    <div class="modal-dialog">
      <h3>{{ title }}</h3>
      <p v-if="contextText" class="modal-context">{{ contextText }}</p>

      <div v-if="selectOptions.length > 0" class="form-group">
        <label>{{ selectLabel }}</label>
        <select :value="selectedValue" class="form-input" @change="onSelectChange">
          <option value="">{{ selectPlaceholder }}</option>
          <option v-for="option in selectOptions" :key="option.id" :value="option.id">
            {{ option.label }}
          </option>
        </select>
      </div>

      <div class="form-group">
        <label>{{ baseLabel }}</label>
        <div v-if="suggestions.length > 0" class="suggestion-chips">
          <button
            v-for="suggestion in suggestions"
            :key="suggestion"
            type="button"
            class="suggestion-chip"
            @click="$emit('update:baseName', suggestion)"
          >
            {{ suggestion }}
          </button>
        </div>
        <input
          :value="baseName"
          type="text"
          class="form-input"
          :placeholder="basePlaceholder"
          @input="onBaseInputChange"
        />
      </div>

      <div v-if="extraFieldLabel" class="form-group">
        <label>{{ extraFieldLabel }}</label>
        <input
          :value="extraFieldValue"
          type="text"
          class="form-input"
          :placeholder="extraFieldPlaceholder"
          @input="onExtraFieldInputChange"
        />
      </div>

      <div class="form-group">
        <label>{{ countLabel }}</label>
        <div class="count-stepper">
          <button type="button" class="stepper-btn" @click="$emit('decrement')">-</button>
          <span class="stepper-value">{{ count }}</span>
          <button type="button" class="stepper-btn" @click="$emit('increment')">+</button>
        </div>
        <p class="stepper-hint">{{ previewLabel }}</p>
        <div class="generated-list">
          <span v-for="name in generatedNames" :key="name" class="generated-chip">{{ name }}</span>
        </div>
      </div>

      <div v-if="pairItems.length > 0" class="form-group">
        <label>{{ pairFieldLabel }}</label>
        <div class="pair-list">
          <div v-for="(item, idx) in pairItems" :key="item.id" class="pair-row">
            <span class="pair-left">{{ item.leftLabel }}</span>
            <input
              :value="item.rightValue"
              type="text"
              class="form-input pair-input"
              :placeholder="pairFieldPlaceholder"
              @input="onPairInputChange(idx, $event)"
            />
          </div>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn-secondary" @click="$emit('close')">Abbrechen</button>
        <button class="btn-primary" :disabled="saveDisabled" @click="$emit('save')">
          {{ isSaving ? 'Speichern...' : 'Speichern' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface SelectOption {
  id: string
  label: string
}

interface PairItem {
  id: string
  leftLabel: string
  rightValue: string
}

const props = withDefaults(defineProps<{
  isOpen: boolean
  title: string
  contextText?: string
  selectLabel?: string
  selectPlaceholder?: string
  selectOptions?: SelectOption[]
  selectedValue?: string
  baseLabel?: string
  suggestions?: string[]
  baseName: string
  basePlaceholder?: string
  extraFieldLabel?: string
  extraFieldValue?: string
  extraFieldPlaceholder?: string
  pairFieldLabel?: string
  pairFieldPlaceholder?: string
  pairItems?: PairItem[]
  countLabel?: string
  count: number
  previewLabel?: string
  generatedNames: string[]
  saveDisabled: boolean
  isSaving: boolean
}>(), {
  contextText: '',
  selectLabel: 'Auswahl',
  selectPlaceholder: 'Bitte wählen...',
  selectOptions: () => [],
  selectedValue: '',
  baseLabel: 'Basisname',
  suggestions: () => [],
  basePlaceholder: '',
  extraFieldLabel: '',
  extraFieldValue: '',
  extraFieldPlaceholder: '',
  pairFieldLabel: 'Fach je Regal',
  pairFieldPlaceholder: 'z.B. A1',
  pairItems: () => [],
  countLabel: 'Anzahl',
  previewLabel: 'Es werden folgende Einträge erstellt:',
})

const emit = defineEmits<{
  close: []
  save: []
  increment: []
  decrement: []
  'update:selectedValue': [value: string]
  'update:baseName': [value: string]
  'update:extraFieldValue': [value: string]
  'update:pairItemValue': [payload: { index: number; value: string }]
}>()

function onSelectChange(event: Event) {
  emit('update:selectedValue', (event.target as HTMLSelectElement).value)
}

function onBaseInputChange(event: Event) {
  emit('update:baseName', (event.target as HTMLInputElement).value)
}

function onExtraFieldInputChange(event: Event) {
  emit('update:extraFieldValue', (event.target as HTMLInputElement).value)
}

function onPairInputChange(index: number, event: Event) {
  emit('update:pairItemValue', { index, value: (event.target as HTMLInputElement).value })
}
</script>

<style scoped>
/* Modal overlay/dialog base uses shared ui/modals.css */

.modal-dialog {
  width: min(460px, calc(100vw - 48px));
  max-height: calc(100vh - 48px);
}

.modal-dialog h3 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px 0;
}

.modal-context {
  font-size: 13px;
  color: #6b7280;
  margin: -4px 0 12px 0;
}

/* Form group/input base uses shared ui/forms.css */

.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

.suggestion-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}

.suggestion-chip {
  padding: 6px 12px;
  font-size: 13px;
  background: #f0fdfa;
  color: #0d9488;
  border: 1px solid #99f6e4;
  border-radius: 6px;
  cursor: pointer;
}

.count-stepper {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.stepper-btn {
  width: 30px;
  height: 30px;
  border: 1px solid #d1d5db;
  background: #f9fafb;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 700;
}

.stepper-value {
  min-width: 34px;
  text-align: center;
  font-weight: 700;
}

.stepper-hint {
  margin: 0 0 8px 0;
  font-size: 12px;
  color: #6b7280;
}

.generated-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.generated-chip {
  padding: 5px 10px;
  border-radius: 999px;
  background: #ecfeff;
  border: 1px solid #a5f3fc;
  color: #0f766e;
  font-size: 12px;
  font-weight: 600;
}

.pair-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pair-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pair-left {
  width: 42%;
  font-size: 13px;
  color: #374151;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pair-input {
  flex: 1;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 18px;
}

/* Buttons use shared ui/buttons.css */
</style>

