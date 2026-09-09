<template>
  <div class="form-builder form-builder--embedded">
    <GrossanlassPreviewBanner />
    <p class="section-hint section-hint--embedded">{{ hint }}</p>

    <ETextField
      v-model="introText"
      :label="t('grossanlass.formBuilder.introLabel')"
      :placeholder="t('grossanlass.formBuilder.introPlaceholder')"
      hide-details="auto"
      class="mb-4"
    />

    <div class="fields-toolbar">
      <h4 class="fields-toolbar-title">{{ t('grossanlass.formBuilder.fieldsTitle') }}</h4>
      <v-menu location="bottom end" :close-on-content-click="true">
        <template #activator="{ props: menuProps }">
          <EButton v-bind="menuProps" variant="secondary" size="small">
            <v-icon icon="mdi-plus" start size="18" />
            {{ t('grossanlass.formBuilder.addField') }}
          </EButton>
        </template>
        <v-list class="add-field-menu" density="compact" min-width="260">
          <v-list-subheader>{{ t('grossanlass.formBuilder.customFieldsGroup') }}</v-list-subheader>
          <v-list-item
            v-for="type in PREVIEW_CUSTOM_FIELD_TYPES"
            :key="type"
            :title="t(`grossanlass.formBuilder.customTypes.${type}`)"
            prepend-icon="mdi-form-textbox"
            @click="addField(type)"
          />
        </v-list>
      </v-menu>
    </div>

    <EEmptyState
      v-if="fields.length === 0"
      compact
      icon="mdi-form-select"
      :title="t('grossanlass.formBuilder.noFields')"
      :description="t('grossanlass.planung.wishForms.builderEmptyHint')"
    />

    <draggable
      v-else
      v-model="fields"
      item-key="id"
      handle=".drag-handle"
      ghost-class="field-row--dragging"
      class="field-list"
    >
      <template #item="{ element: field, index }">
        <div class="field-row">
          <div class="field-row-order">
            <button type="button" class="drag-handle order-btn" :title="t('grossanlass.formBuilder.dragToSort')">
              <v-icon icon="mdi-drag-vertical" size="20" />
            </button>
            <span class="order-index">{{ index + 1 }}</span>
          </div>

          <div class="field-row-body">
            <div class="field-row-head">
              <span class="field-type-badge">{{ t(`grossanlass.formBuilder.customTypes.${field.type}`) }}</span>
              <span v-if="field.core" class="meta-hint">{{ t('grossanlass.planung.wishForms.coreField') }}</span>
            </div>

            <ETextField
              v-model="field.label"
              :label="t('grossanlass.formBuilder.fieldLabel')"
              hide-details="auto"
            />

            <div v-if="field.type === 'select'" class="select-options mt-2">
              <p class="select-options-label">{{ t('grossanlass.formBuilder.selectOptions') }}</p>
              <div
                v-for="(_, optIndex) in field.choices ?? []"
                :key="`${field.id}-opt-${optIndex}`"
                class="select-options-row"
              >
                <ETextField
                  v-model="field.choices![optIndex]"
                  :label="t('grossanlass.formBuilder.selectOptionLabel', { n: optIndex + 1 })"
                  hide-details="auto"
                  class="select-options-input"
                />
                <button
                  type="button"
                  class="select-options-btn select-options-btn--remove"
                  :title="t('grossanlass.formBuilder.removeOption')"
                  :disabled="(field.choices?.length ?? 0) <= 1"
                  @click="removeSelectOption(field, optIndex)"
                >
                  <v-icon icon="mdi-minus" size="18" />
                </button>
              </div>
              <EButton variant="secondary" size="small" class="select-options-add" @click="addSelectOption(field)">
                <v-icon icon="mdi-plus" start size="18" />
                {{ t('grossanlass.formBuilder.addOption') }}
              </EButton>
              <label class="toggle-chip mt-2">
                <input v-model="field.multiple" type="checkbox" />
                {{ t('grossanlass.formBuilder.allowMultiple') }}
              </label>
              <p class="select-options-hint">{{ t('grossanlass.formBuilder.selectDisplayHint') }}</p>
            </div>

            <div class="field-row-options">
              <label class="toggle-chip">
                <input v-model="field.required" type="checkbox" />
                {{ t('grossanlass.formBuilder.required') }}
              </label>
            </div>
          </div>

          <button
            v-if="!field.core"
            type="button"
            class="icon-btn"
            :title="t('common.delete')"
            @click="removeField(field.id)"
          >
            <v-icon icon="mdi-delete-outline" size="18" />
          </button>
        </div>
      </template>
    </draggable>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import draggable from 'vuedraggable'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { EButton, ETextField } from '@/components/form/base'
import {
  PREVIEW_CUSTOM_FIELD_TYPES,
  type GaPreviewFieldType,
  type GaPreviewFormField,
} from '@/views/grossanlass/grossanlassWishFormsPreviewData'
import { findPreviewWishForm } from '@/views/grossanlass/grossanlassWishFormsPreviewStore'

const props = defineProps<{
  formId: string
}>()

const { t } = useI18n()

const form = computed(() => findPreviewWishForm(t, props.formId))

const hint = computed(() =>
  form.value?.purpose === 'free'
    ? t('grossanlass.planung.wishForms.builderHintFree')
    : t('grossanlass.planung.wishForms.builderHintCompany'),
)

const introText = computed({
  get: () => form.value?.intro_text ?? '',
  set: (value: string) => {
    if (form.value) form.value.intro_text = value
  },
})

const fields = computed({
  get: () => form.value?.fields ?? [],
  set: (next: GaPreviewFormField[]) => {
    if (form.value) form.value.fields = next
  },
})

function addField(type: GaPreviewFieldType) {
  if (!form.value) return
  form.value.fields.push({
    id: `pf-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 6)}`,
    type,
    label: t(`grossanlass.formBuilder.customTypes.${type}`),
    required: false,
    choices: type === 'select' ? [''] : undefined,
    multiple: type === 'select' ? false : undefined,
  })
}

function removeField(id: string) {
  if (!form.value) return
  form.value.fields = form.value.fields.filter((field) => field.id !== id)
}

function addSelectOption(field: GaPreviewFormField) {
  if (!field.choices) field.choices = ['']
  else field.choices.push('')
}

function removeSelectOption(field: GaPreviewFormField, index: number) {
  if (!field.choices || field.choices.length <= 1) return
  field.choices.splice(index, 1)
}
</script>

<style scoped>
.form-builder--embedded {
  border: none;
  padding: 0;
  margin-bottom: 0;
  background: transparent;
}

.section-hint--embedded {
  margin: 0 0 14px;
}

.section-hint {
  color: #6b7280;
  font-size: 0.88rem;
}

.fields-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.fields-toolbar-title {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 600;
}

.field-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.field-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}

.field-row-order {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
  padding-top: 2px;
}

.order-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  cursor: grab;
  color: #4b5563;
}

.drag-handle:active {
  cursor: grabbing;
}

.field-row--dragging {
  opacity: 0.55;
}

.order-index {
  font-size: 0.72rem;
  font-weight: 600;
  color: #9ca3af;
  margin-top: 2px;
}

.field-row-body {
  flex: 1;
  min-width: 0;
}

.field-row-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.field-type-badge {
  font-size: 0.72rem;
  font-weight: 600;
  color: #4b5563;
  background: #e5e7eb;
  padding: 2px 8px;
  border-radius: 999px;
  white-space: nowrap;
}

.meta-hint {
  font-size: 0.75rem;
  color: #9ca3af;
}

.field-row-options {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 10px;
}

.toggle-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.82rem;
  color: #374151;
}

.icon-btn {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  width: 32px;
  height: 32px;
  cursor: pointer;
  color: #dc2626;
  flex-shrink: 0;
}

.mt-2 {
  margin-top: 8px;
}

.mb-4 {
  margin-bottom: 16px;
}

.add-field-menu :deep(.v-list-subheader) {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #6b7280;
}

.select-options {
  width: 100%;
}

.select-options-label {
  margin: 0 0 8px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #374151;
}

.select-options-hint {
  margin: 6px 0 0;
  font-size: 0.75rem;
  color: #6b7280;
  line-height: 1.4;
}

.select-options-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-bottom: 8px;
  width: 100%;
}

.select-options-input {
  flex: 1 1 auto;
  min-width: 0;
  width: 100%;
}

.select-options-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  margin-top: 4px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  color: #4b5563;
  flex-shrink: 0;
}

.select-options-btn:disabled {
  opacity: 0.35;
  cursor: default;
}

.select-options-btn--remove {
  color: #dc2626;
}

.select-options-add {
  margin-top: 4px;
}
</style>
