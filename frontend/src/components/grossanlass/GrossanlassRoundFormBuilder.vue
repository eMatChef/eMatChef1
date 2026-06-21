<template>
  <div class="form-builder" :class="{ 'form-builder--embedded': embedded }">
    <div v-if="!embedded" class="form-builder-header">
      <h3 class="section-title">{{ t('grossanlass.formBuilder.title') }}</h3>
      <p class="section-hint">{{ t('grossanlass.formBuilder.hint') }}</p>
    </div>
    <p v-else class="section-hint section-hint--embedded">{{ t('grossanlass.formBuilder.hint') }}</p>

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />
    <template v-else-if="draft">
      <ETextField
        v-model="draft.intro_text"
        :label="t('grossanlass.formBuilder.introLabel')"
        :placeholder="t('grossanlass.formBuilder.introPlaceholder')"
        hide-details="auto"
        class="mb-4"
        @blur="onFieldBlur"
      />

      <div class="fields-toolbar">
        <h4 class="fields-toolbar-title">{{ t('grossanlass.formBuilder.fieldsTitle') }}</h4>
        <div class="fields-toolbar-actions">
          <span v-if="autoSaveEnabled && autoSaveStatus === 'saving'" class="autosave-hint">
            {{ t('grossanlass.formBuilder.autoSaving') }}
          </span>
          <span v-else-if="autoSaveEnabled && autoSaveStatus === 'saved'" class="autosave-hint autosave-hint--ok">
            {{ t('grossanlass.formBuilder.autoSaved') }}
          </span>
          <span v-else-if="autoSaveEnabled && autoSaveStatus === 'pending'" class="autosave-hint">
            {{ t('grossanlass.formBuilder.autoSavePending') }}
          </span>
          <v-menu v-model="addMenuOpen" location="bottom end" :close-on-content-click="true">
          <template #activator="{ props: menuProps }">
            <EButton
              v-bind="menuProps"
              variant="secondary"
              size="small"
              :disabled="addOptions.length === 0"
            >
              <v-icon icon="mdi-plus" start size="18" />
              {{ t('grossanlass.formBuilder.addField') }}
            </EButton>
          </template>
          <v-list class="add-field-menu" density="compact" min-width="260">
            <template v-if="availableSystemOptions.length > 0">
              <v-list-subheader>{{ t('grossanlass.formBuilder.systemFieldsGroup') }}</v-list-subheader>
              <v-list-item
                v-for="opt in availableSystemOptions"
                :key="'sys-' + opt.system_key"
                :title="systemKeyLabel(opt.system_key)"
                :subtitle="systemOptionSubtitle(opt.system_key)"
                prepend-icon="mdi-cog-outline"
                @click="addField(opt)"
              />
            </template>
            <v-list-subheader>{{ t('grossanlass.formBuilder.customFieldsGroup') }}</v-list-subheader>
            <v-list-item
              v-for="opt in availableCustomOptions"
              :key="'cus-' + opt.custom_type"
              :title="customTypeLabel(opt.custom_type)"
              prepend-icon="mdi-form-textbox"
              @click="addField(opt)"
            />
          </v-list>
        </v-menu>
        </div>
      </div>

      <EEmptyState
        v-if="orderedFields.length === 0"
        variant="compact"
        icon="mdi-form-select"
        :title="t('grossanlass.formBuilder.noFields')"
        :description="t('grossanlass.formBuilder.noFieldsHint')"
      />

      <draggable
        v-else
        v-model="orderedFieldsModel"
        item-key="id"
        handle=".drag-handle"
        ghost-class="field-row--dragging"
        class="field-list"
        @end="onFieldsReordered"
      >
        <template #item="{ element: field, index }">
          <div
            class="field-row"
            :class="{ 'field-row--meta': field.role === 'meta' }"
          >
            <div class="field-row-order">
              <button type="button" class="drag-handle order-btn" :title="t('grossanlass.formBuilder.dragToSort')">
                <v-icon icon="mdi-drag-vertical" size="20" />
              </button>
              <span class="order-index">{{ index + 1 }}</span>
            </div>

            <div class="field-row-body">
              <div class="field-row-head">
                <span class="field-type-badge">{{ fieldTypeLabel(field) }}</span>
                <span v-if="field.role === 'meta'" class="meta-hint">{{ t('grossanlass.formBuilder.metaHint') }}</span>
                <span v-else-if="field.system_key === 'bauprojekt'" class="meta-hint">{{ t('grossanlass.formBuilder.systemFieldHint') }}</span>
                <span v-else-if="field.system_key === 'ressort_wahl'" class="meta-hint">{{ t('grossanlass.formBuilder.ressortWahlHint') }}</span>
                <span v-else-if="isLegacySystemInputField(field)" class="meta-hint">{{ t('grossanlass.formBuilder.legacySystemHint') }}</span>
              </div>

              <ETextField
                v-if="isEditableCustomField(field)"
                v-model="field.label"
                :label="t('grossanlass.formBuilder.fieldLabel')"
                density="compact"
                hide-details="auto"
                @blur="onFieldBlur"
              />
              <div v-else class="meta-label">{{ fieldTypeLabel(field) }}</div>

              <div v-if="field.custom_type === 'select'" class="select-options mt-2">
                <p class="select-options-label">{{ t('grossanlass.formBuilder.selectOptions') }}</p>
                <div
                  v-for="(_, optIndex) in getSelectOptionRows(field.id)"
                  :key="getSelectOptionRowKey(field.id, optIndex)"
                  class="select-options-row"
                >
                  <div class="select-options-input">
                    <ETextField
                      v-model="selectOptionsDraft[field.id][optIndex]"
                      :label="t('grossanlass.formBuilder.selectOptionLabel', { n: optIndex + 1 })"
                      density="compact"
                      hide-details="auto"
                      @blur="onSelectOptionBlur(field)"
                    />
                  </div>
                  <button
                    type="button"
                    class="select-options-btn select-options-btn--remove"
                    :title="t('grossanlass.formBuilder.removeOption')"
                    :disabled="getSelectOptionRows(field.id).length <= 1"
                    @click="removeSelectOption(field.id, optIndex)"
                  >
                    <v-icon icon="mdi-minus" size="18" />
                  </button>
                </div>
                <EButton
                  variant="secondary"
                  size="small"
                  class="select-options-add"
                  @mousedown.prevent
                  @click="addSelectOption(field.id)"
                >
                  <v-icon icon="mdi-plus" start size="18" />
                  {{ t('grossanlass.formBuilder.addOption') }}
                </EButton>
                <label class="toggle-chip mt-2">
                  <input v-model="selectFieldOptions(field).multiple" type="checkbox" @change="onCheckboxChange" />
                  {{ t('grossanlass.formBuilder.allowMultiple') }}
                </label>
                <p class="select-options-hint">{{ t('grossanlass.formBuilder.selectDisplayHint') }}</p>
              </div>

              <div v-if="field.system_key === 'bauprojekt'" class="bauprojekt-config mt-2">
                <p class="bauprojekt-config-title">{{ t('grossanlass.formBuilder.bauprojektOptionsTitle') }}</p>
                <label class="toggle-chip">
                  <input v-model="bauprojektConfig(field).allow_new_bauprojekt" type="checkbox" @change="onCheckboxChange" />
                  {{ t('grossanlass.formBuilder.allowNewBauprojekt') }}
                </label>
                <label class="toggle-chip">
                  <input v-model="bauprojektConfig(field).leader_scope" type="checkbox" @change="onCheckboxChange" />
                  {{ t('grossanlass.formBuilder.leaderScope') }}
                </label>
                <p class="bauprojekt-config-hint">{{ t('grossanlass.formBuilder.bauprojektOptionsHint') }}</p>
                <label class="toggle-chip mt-1">
                  <input v-model="field.required" type="checkbox" @change="onCheckboxChange" />
                  {{ t('grossanlass.formBuilder.required') }}
                </label>
              </div>

              <div v-else-if="field.system_key === 'ressort_wahl'" class="bauprojekt-config mt-2">
                <p class="bauprojekt-config-title">{{ t('grossanlass.formBuilder.ressortWahlOptionsTitle') }}</p>
                <label class="toggle-chip">
                  <input v-model="ressortWahlConfig(field).leader_scope" type="checkbox" @change="onCheckboxChange" />
                  {{ t('grossanlass.formBuilder.leaderScope') }}
                </label>
                <p class="bauprojekt-config-hint">{{ t('grossanlass.formBuilder.ressortWahlOptionsHint') }}</p>
                <label class="toggle-chip mt-1">
                  <input v-model="field.required" type="checkbox" @change="onCheckboxChange" />
                  {{ t('grossanlass.formBuilder.required') }}
                </label>
              </div>

              <div v-else-if="field.role === 'input'" class="field-row-options">
                <label class="toggle-chip">
                  <input v-model="field.required" type="checkbox" @change="onCheckboxChange" />
                  {{ t('grossanlass.formBuilder.required') }}
                </label>
              </div>
            </div>

            <button
              v-if="!isFixedSystemField(field)"
              type="button"
              class="icon-btn"
              :title="t('common.delete')"
              @click="removeField(field)"
            >
              <v-icon icon="mdi-delete-outline" size="18" />
            </button>
          </div>
        </template>
      </draggable>

      <div v-if="showActions" class="form-builder-actions">
        <EButton variant="primary" :loading="saving" @click="save">
          {{ t('grossanlass.formBuilder.save') }}
        </EButton>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import draggable from 'vuedraggable'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ETextField } from '@/components/form/base'
import {
  availableFormBuilderAddOptions,
  createFormBuilderField,
  ensureFixedSystemFields,
  normalizeSystemFieldLabels,
  formBuilderFieldTypeLabel,
  getGrossanlassRoundForm,
  isEditableCustomField,
  isFixedSystemField,
  isLegacySystemInputField,
  nextFormFieldSortOrder,
  sortFormFields,
  updateGrossanlassRoundForm,
  type FormBuilderAddKind,
  type GrossanlassRoundForm,
  type GrossanlassRoundFormField,
} from '@/api/grossanlassRoundForm'

const props = withDefaults(
  defineProps<{
    departmentId: string
    roundId: string
    readonly?: boolean
    embedded?: boolean
    showActions?: boolean
    silentSave?: boolean
    autoSave?: boolean
  }>(),
  {
    embedded: false,
    showActions: true,
    silentSave: false,
    autoSave: true,
  },
)

const emit = defineEmits<{
  saved: [form: GrossanlassRoundForm]
}>()

const { t } = useI18n()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const draft = ref<GrossanlassRoundForm | null>(null)
const selectOptionsDraft = ref<Record<string, string[]>>({})
const selectOptionRowKeys = ref<Record<string, string[]>>({})
const addMenuOpen = ref(false)
const skipAutoSave = ref(false)
const hasUnsavedChanges = ref(false)
const autoSaveStatus = ref<'idle' | 'pending' | 'saving' | 'saved' | 'error'>('idle')

let autoSaveTimer: ReturnType<typeof setTimeout> | null = null
let autoSaveIndicatorTimer: ReturnType<typeof setTimeout> | null = null
let localEditGeneration = 0

const autoSaveEnabled = computed(() => props.autoSave !== false && !props.readonly)

const orderedFields = computed(() => sortFormFields(draft.value?.fields || []))

const orderedFieldsModel = computed({
  get: () => orderedFields.value,
  set: (list: GrossanlassRoundFormField[]) => {
    if (!draft.value) return
    draft.value.fields = list.map((f, i) => ({ ...f, sort_order: (i + 1) * 10 }))
  },
})

const addOptions = computed(() => availableFormBuilderAddOptions(draft.value?.fields || []))

const availableSystemOptions = computed(() =>
  addOptions.value.filter((o): o is Extract<FormBuilderAddKind, { kind: 'system' }> => o.kind === 'system'),
)

const availableCustomOptions = computed(() =>
  addOptions.value.filter((o): o is Extract<FormBuilderAddKind, { kind: 'custom' }> => o.kind === 'custom'),
)

function fieldKey(field: GrossanlassRoundFormField): string {
  return field.id || `${field.system_key || field.custom_type}-${field.sort_order}`
}

function systemKeyLabel(key: 'bauprojekt'): string {
  return t(`grossanlass.formBuilder.systemKeys.${key}`)
}

function customTypeLabel(type: string): string {
  return t(`grossanlass.formBuilder.customTypes.${type}`)
}

function fieldTypeLabel(field: GrossanlassRoundFormField): string {
  return formBuilderFieldTypeLabel(field, t)
}

function systemOptionSubtitle(_key: 'bauprojekt'): string {
  return t('grossanlass.formBuilder.systemFieldHint')
}

function ressortWahlConfig(field: GrossanlassRoundFormField) {
  if (!field.config) {
    field.config = { leader_scope: false }
  }
  if (field.config.leader_scope === undefined) {
    field.config.leader_scope = false
  }
  return field.config
}

function bauprojektConfig(field: GrossanlassRoundFormField) {
  if (!field.config) {
    field.config = { allow_new_bauprojekt: true, leader_scope: false }
  }
  if (field.config.allow_new_bauprojekt === undefined) {
    field.config.allow_new_bauprojekt = true
  }
  if (field.config.leader_scope === undefined) {
    field.config.leader_scope = false
  }
  return field.config
}

function selectFieldOptions(field: GrossanlassRoundFormField) {
  if (!field.options) {
    field.options = { choices: [], multiple: false }
  }
  if (field.options.multiple === undefined) {
    field.options.multiple = false
  }
  return field.options
}

function cloneForm(form: GrossanlassRoundForm): GrossanlassRoundForm {
  return JSON.parse(JSON.stringify(form)) as GrossanlassRoundForm
}

function newOptionRowKey(): string {
  return `opt_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`
}

function initSelectDrafts(fields: GrossanlassRoundFormField[], mergeLocal = false) {
  const map: Record<string, string[]> = {}
  const keysMap: Record<string, string[]> = { ...selectOptionRowKeys.value }

  for (const f of fields) {
    if (f.custom_type !== 'select') continue
    const choices = (f.options?.choices || []).map((s) => String(s).trim()).filter(Boolean)
    let rows = choices.length > 0 ? [...choices] : ['']
    const localRows = selectOptionsDraft.value[f.id]
    const localKeys = keysMap[f.id] || []

    if (mergeLocal && localRows?.length) {
      const localFilled = localRows.map((s) => s.trim()).filter(Boolean)
      if (localFilled.join('\x00') === choices.join('\x00') && localRows.length >= rows.length) {
        rows = [...localRows]
      }
    }

    const keys: string[] = []
    for (let i = 0; i < rows.length; i++) {
      keys.push(localKeys[i] || newOptionRowKey())
    }
    keysMap[f.id] = keys
    map[f.id] = rows
  }

  selectOptionsDraft.value = map
  selectOptionRowKeys.value = keysMap
}

function normalizeDraftFields() {
  if (!draft.value) return
  draft.value.fields = normalizeSystemFieldLabels(
    ensureFixedSystemFields(draft.value.fields),
  )
  draft.value.fields = sortFormFields(draft.value.fields).map((f, i) => ({
    ...f,
    sort_order: (i + 1) * 10,
    enabled: true,
  }))
  for (const f of draft.value.fields) {
    if (f.system_key === 'bauprojekt') {
      bauprojektConfig(f)
    }
    if (f.system_key === 'ressort_wahl') {
      ressortWahlConfig(f)
    }
  }
}

async function load() {
  if (!props.departmentId || !props.roundId) return
  loading.value = true
  skipAutoSave.value = true
  hasUnsavedChanges.value = false
  autoSaveStatus.value = 'idle'
  try {
    const form = await getGrossanlassRoundForm(props.departmentId, props.roundId)
    draft.value = cloneForm(form)
    normalizeDraftFields()
    initSelectDrafts(form.fields)
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.formBuilder.errorLoad'))
  } finally {
    loading.value = false
    skipAutoSave.value = false
  }
}

function markDirty() {
  if (!autoSaveEnabled.value || loading.value || skipAutoSave.value) return
  localEditGeneration++
  hasUnsavedChanges.value = true
  autoSaveStatus.value = 'pending'
}

function cancelPendingAutoSave() {
  if (autoSaveTimer) {
    clearTimeout(autoSaveTimer)
    autoSaveTimer = null
  }
}

function onFieldBlur() {
  if (!autoSaveEnabled.value || !hasUnsavedChanges.value) return
  scheduleAutoSave()
}

function onSelectOptionBlur(field: GrossanlassRoundFormField) {
  syncSelectOptions(field)
  onFieldBlur()
}

function onCheckboxChange() {
  markDirty()
  scheduleAutoSave()
}

function scheduleAutoSave() {
  if (!autoSaveEnabled.value || loading.value || skipAutoSave.value || !hasUnsavedChanges.value) return
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  autoSaveTimer = setTimeout(() => {
    void runAutoSave()
  }, 80)
}

async function runAutoSave(): Promise<boolean> {
  if (!autoSaveEnabled.value || loading.value || skipAutoSave.value || saving.value || !hasUnsavedChanges.value) {
    return true
  }
  autoSaveStatus.value = 'saving'
  const result = await save({ auto: true })
  if (result === 'stale') {
    autoSaveStatus.value = 'pending'
    return true
  }
  const ok = result === true
  autoSaveStatus.value = ok ? 'saved' : 'error'
  if (ok) {
    hasUnsavedChanges.value = false
    if (autoSaveIndicatorTimer) clearTimeout(autoSaveIndicatorTimer)
    autoSaveIndicatorTimer = setTimeout(() => {
      if (autoSaveStatus.value === 'saved') autoSaveStatus.value = 'idle'
    }, 2200)
  }
  return ok
}

function onFieldsReordered() {
  markDirty()
  scheduleAutoSave()
}

function addField(kind: FormBuilderAddKind) {
  if (!draft.value) return
  const sortOrder = nextFormFieldSortOrder(draft.value.fields)
  const field = createFormBuilderField(kind, sortOrder)
  draft.value.fields.push(field)
  normalizeDraftFields()
  if (field.custom_type === 'select') {
    selectOptionsDraft.value[field.id] = ['']
    selectOptionRowKeys.value[field.id] = [newOptionRowKey()]
  }
  addMenuOpen.value = false
  markDirty()
  scheduleAutoSave()
}

function removeField(field: GrossanlassRoundFormField) {
  if (!draft.value || isFixedSystemField(field)) return
  draft.value.fields = draft.value.fields.filter((f) => f.id !== field.id)
  delete selectOptionsDraft.value[field.id]
  delete selectOptionRowKeys.value[field.id]
  normalizeDraftFields()
  markDirty()
  scheduleAutoSave()
}

function getSelectOptionRows(fieldId: string): string[] {
  const rows = selectOptionsDraft.value[fieldId]
  return rows && rows.length > 0 ? rows : ['']
}

function ensureSelectOptionsDraft(fieldId: string): string[] {
  if (!selectOptionsDraft.value[fieldId]) {
    selectOptionsDraft.value[fieldId] = ['']
  }
  return selectOptionsDraft.value[fieldId]
}

function ensureSelectOptionKeys(fieldId: string): string[] {
  if (!selectOptionRowKeys.value[fieldId]) {
    selectOptionRowKeys.value[fieldId] = [newOptionRowKey()]
  }
  return selectOptionRowKeys.value[fieldId]
}

function getSelectOptionRowKey(fieldId: string, index: number): string {
  const keys = ensureSelectOptionKeys(fieldId)
  while (keys.length <= index) {
    keys.push(newOptionRowKey())
  }
  return keys[index]
}

function addSelectOption(fieldId: string) {
  cancelPendingAutoSave()
  ensureSelectOptionsDraft(fieldId).push('')
  ensureSelectOptionKeys(fieldId).push(newOptionRowKey())
  markDirty()
}

function removeSelectOption(fieldId: string, index: number) {
  cancelPendingAutoSave()
  const rows = ensureSelectOptionsDraft(fieldId)
  const keys = ensureSelectOptionKeys(fieldId)
  if (rows.length <= 1) {
    rows[0] = ''
  } else {
    rows.splice(index, 1)
    keys.splice(index, 1)
  }
  const field = draft.value?.fields.find((f) => f.id === fieldId)
  if (field) {
    syncSelectOptions(field)
  }
  markDirty()
  scheduleAutoSave()
}

function syncSelectOptions(field: GrossanlassRoundFormField) {
  const rows = selectOptionsDraft.value[field.id] || []
  const choices = rows.map((s) => s.trim()).filter(Boolean)
  const opts = selectFieldOptions(field)
  field.options = { choices, multiple: opts.multiple === true }
}

async function save(options?: { auto?: boolean }): Promise<boolean | 'stale'> {
  if (!draft.value || props.readonly) return false
  const editGenAtStart = localEditGeneration
  saving.value = true
  try {
    normalizeDraftFields()
    for (const f of draft.value.fields) {
      if (f.custom_type === 'select') {
        syncSelectOptions(f)
      }
      if (f.system_key === 'bauprojekt') {
        bauprojektConfig(f)
      }
      if (f.system_key === 'ressort_wahl') {
        ressortWahlConfig(f)
      }
    }
    const payload = {
      intro_text: draft.value.intro_text?.trim() || null,
      fields: draft.value.fields.map((f, i) => ({
        ...f,
        sort_order: (i + 1) * 10,
        enabled: true,
        id: f.id.startsWith('new_') ? undefined : f.id,
      })),
    }
    const saved = await updateGrossanlassRoundForm(props.departmentId, props.roundId, payload as any)

    if (options?.auto && editGenAtStart !== localEditGeneration) {
      skipAutoSave.value = false
      return 'stale'
    }

    skipAutoSave.value = true
    draft.value = cloneForm(saved)
    normalizeDraftFields()
    initSelectDrafts(saved.fields, options?.auto === true)
    skipAutoSave.value = false
    hasUnsavedChanges.value = false
    const silent = props.silentSave || options?.auto
    if (!silent) {
      toast.success(t('grossanlass.formBuilder.saved'))
    }
    emit('saved', saved)
    return true
  } catch (e: any) {
    if (!options?.auto) {
      toast.error(e.response?.data?.error || t('grossanlass.formBuilder.errorSave'))
    }
    return false
  } finally {
    saving.value = false
  }
}

async function flushAutoSave(): Promise<boolean> {
  if (autoSaveTimer) {
    clearTimeout(autoSaveTimer)
    autoSaveTimer = null
  }
  if (saving.value) {
    await new Promise<void>((resolve) => {
      const stop = watch(saving, (v) => {
        if (!v) {
          stop()
          resolve()
        }
      })
    })
    return autoSaveStatus.value !== 'error'
  }
  if (hasUnsavedChanges.value || autoSaveStatus.value === 'pending' || autoSaveStatus.value === 'error') {
    return runAutoSave()
  }
  return true
}

defineExpose({ save, load, loading, saving, flushAutoSave })

watch(
  () => draft.value,
  markDirty,
  { deep: true },
)

watch(
  () => selectOptionsDraft.value,
  markDirty,
  { deep: true },
)

watch(() => [props.departmentId, props.roundId], load, { immediate: true })
onMounted(load)
onBeforeUnmount(() => {
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  if (autoSaveIndicatorTimer) clearTimeout(autoSaveIndicatorTimer)
})
</script>

<style scoped>
.form-builder {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px 18px;
  margin-bottom: 24px;
  background: #fafafa;
}

.form-builder--embedded {
  border: none;
  padding: 0;
  margin-bottom: 0;
  background: transparent;
}

.section-hint--embedded {
  margin: 0 0 14px;
}

.fields-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.fields-toolbar-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.autosave-hint {
  font-size: 0.78rem;
  color: #6b7280;
  white-space: nowrap;
}

.autosave-hint--ok {
  color: #059669;
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

.field-row--meta {
  background: #f9fafb;
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

.bauprojekt-config {
  padding: 10px 12px;
  border-radius: 8px;
  background: #f3f4f6;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.bauprojekt-config-title {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
}

.bauprojekt-config-hint {
  margin: 0;
  font-size: 0.75rem;
  color: #6b7280;
  line-height: 1.4;
}

.mt-1 {
  margin-top: 4px;
}

.mt-2 {
  margin-top: 8px;
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

.meta-label {
  font-size: 0.9rem;
  color: #374151;
  font-weight: 500;
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

.form-builder-actions {
  margin-top: 16px;
}

.section-title {
  margin: 0 0 6px;
  font-size: 1.05rem;
  font-weight: 600;
}

.section-hint {
  margin: 0 0 14px;
  color: #6b7280;
  font-size: 0.88rem;
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

.select-options-input :deep(.e-form-field) {
  width: 100%;
  margin-bottom: 0;
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
