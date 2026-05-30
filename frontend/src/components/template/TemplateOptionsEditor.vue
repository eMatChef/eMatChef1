<template>
  <div class="combo-options-editor">
    <div class="coe-head">
      <div>
        <h3 class="coe-title">{{ t('components.templateOptions.title') }}</h3>
        <p class="coe-intro">{{ t('components.templateOptions.intro') }}</p>
      </div>
      <div v-if="!readonly" class="coe-head-actions">
        <button type="button" class="btn-outline-small" @click="openOptionEditor(null, null)">
          {{ t('components.comboOptions.btnAddToggle') }}
        </button>
        <button type="button" class="btn-outline-small" @click="addGroup">
          {{ t('components.comboOptions.btnAddGroup') }}
        </button>
      </div>
    </div>

    <!-- Options-Gruppen -->
    <div v-for="group in groupsModel" :key="group.__key" class="coe-group-card">
      <div class="coe-group-head">
        <input
          v-model="group.name"
          type="text"
          class="form-input coe-group-name"
          :placeholder="t('components.comboOptions.phGroupName')"
          :disabled="readonly"
        />
        <select v-model="group.selection_type" class="form-select coe-select-sm" :disabled="readonly">
          <option value="exclusive">{{ t('components.comboOptions.selExclusive') }}</option>
          <option value="multi">{{ t('components.comboOptions.selMulti') }}</option>
          <option value="quantity">{{ t('components.comboOptions.selQuantity') }}</option>
        </select>
        <label class="coe-minmax">
          {{ t('components.comboOptions.labelMin') }}
          <input v-model.number="group.min_select" type="number" min="0" class="form-input coe-num" :disabled="readonly" />
        </label>
        <label class="coe-minmax">
          {{ t('components.comboOptions.labelMax') }}
          <input
            :value="group.max_select ?? ''"
            type="number"
            min="0"
            class="form-input coe-num"
            :placeholder="t('components.comboOptions.phUnlimited')"
            :disabled="readonly"
            @change="(e) => setGroupMax(group, e)"
          />
        </label>
        <button v-if="!readonly" type="button" class="btn-outline-small" @click="openOptionEditor(null, group.__key)">
          {{ t('components.comboOptions.btnAddOptionToGroup') }}
        </button>
        <button v-if="!readonly" type="button" class="icon-btn icon-btn-danger" :title="t('components.comboOptions.btnDeleteGroup')" @click="removeGroup(group)">✕</button>
      </div>
      <ul class="coe-option-list">
        <li v-for="opt in optionsForGroup(group.__key)" :key="opt.__key" class="coe-option-row">
          <span class="coe-option-name">{{ opt.name }}</span>
          <span class="coe-deltas">{{ formatDeltas(opt) }}</span>
          <span v-if="opt.default_selected" class="coe-badge">{{ t('components.comboOptions.badgeDefault') }}</span>
          <button v-if="!readonly" type="button" class="icon-btn" :title="t('common.edit')" @click="openOptionEditor(opt, group.__key)">✎</button>
          <button v-if="!readonly" type="button" class="icon-btn icon-btn-danger" :title="t('common.delete')" @click="removeOption(opt)">✕</button>
        </li>
        <li v-if="optionsForGroup(group.__key).length === 0" class="coe-empty-row">{{ t('components.comboOptions.groupEmpty') }}</li>
      </ul>
    </div>

    <!-- Eigenständige Toggle-Optionen -->
    <div class="coe-toggle-section">
      <h4 class="coe-subtitle">{{ t('components.comboOptions.toggleSectionTitle') }}</h4>
      <ul class="coe-option-list">
        <li v-for="opt in standaloneToggles" :key="opt.__key" class="coe-option-row">
          <span class="coe-option-name">{{ opt.name }}</span>
          <span class="coe-deltas">{{ formatDeltas(opt) }}</span>
          <span v-if="opt.default_selected" class="coe-badge">{{ t('components.comboOptions.badgeDefaultOn') }}</span>
          <button v-if="!readonly" type="button" class="icon-btn" :title="t('common.edit')" @click="openOptionEditor(opt, null)">✎</button>
          <button v-if="!readonly" type="button" class="icon-btn icon-btn-danger" :title="t('common.delete')" @click="removeOption(opt)">✕</button>
        </li>
        <li v-if="standaloneToggles.length === 0" class="coe-empty-row">{{ t('components.comboOptions.toggleEmpty') }}</li>
      </ul>
    </div>

    <!-- Options-Editor-Modal -->
    <div v-if="showOptionModal" class="modal-overlay" @click.self="closeOptionModal">
      <div class="modal-dialog coe-modal">
        <h3>{{ editingOption ? t('components.comboOptions.modalEditTitle') : t('components.comboOptions.modalNewTitle') }}</h3>
        <div class="form-group">
          <label>{{ t('components.comboOptions.labelOptionName') }}</label>
          <input v-model="form.name" type="text" class="form-input" :placeholder="t('components.comboOptions.phOptionName')" />
        </div>
        <div class="form-group coe-inline-group">
          <label class="checkbox-label">
            <input v-model="form.default_selected" type="checkbox" />
            {{ form.option_group_key ? t('components.comboOptions.labelDefaultInGroup') : t('components.comboOptions.labelDefaultToggle') }}
          </label>
        </div>

        <div class="form-group">
          <div class="coe-deltas-head">
            <label>{{ t('components.comboOptions.labelDeltaList') }}</label>
            <button type="button" class="btn-outline-small" @click="addDeltaRow">{{ t('components.comboOptions.btnAddDelta') }}</button>
          </div>
          <p class="batch-field-hint">{{ t('components.templateOptions.deltaHint') }}</p>
          <div v-for="(d, idx) in form.deltas" :key="idx" class="coe-delta-row coe-delta-row-template">
            <input v-model="d.name" type="text" class="form-input coe-delta-name" :placeholder="t('components.templateOptions.phComponentName')" />
            <input v-model="d.component_type" type="text" class="form-input coe-delta-type" :placeholder="t('components.templateOptions.phComponentType')" />
            <input v-model.number="d.qty_delta" type="number" class="form-input coe-num" :title="t('components.comboOptions.labelQtyDelta')" />
            <select v-model="d.component_source" class="form-select coe-select-sm">
              <option value="stock">{{ t('components.materialDetail.componentSourceStock') }}</option>
              <option value="self_provided">{{ t('components.materialDetail.componentSourceSelfProvided') }}</option>
            </select>
            <button type="button" class="icon-btn icon-btn-danger" :title="t('common.delete')" @click="form.deltas.splice(idx, 1)">✕</button>
          </div>
          <p v-if="form.deltas.length === 0" class="batch-field-hint">{{ t('components.comboOptions.deltaEmpty') }}</p>
        </div>

        <p v-if="modalError" class="error-text">{{ modalError }}</p>
        <div class="modal-actions">
          <button type="button" class="btn-secondary btn-sm" @click="closeOptionModal">{{ t('common.cancel') }}</button>
          <button type="button" class="btn-primary btn-sm" :disabled="!canSubmit" @click="submitOption">
            {{ t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ComponentSource, OptionSelectionType } from '@/api/templates'

export interface TemplateOptionGroupForm {
  __key: number
  name: string
  selection_type: OptionSelectionType
  min_select: number
  max_select: number | null
}

export interface TemplateOptionDeltaForm {
  name: string
  component_type: string
  qty_delta: number
  tracking: 'serialized' | 'bulk'
  component_source: ComponentSource
  is_generic: boolean
}

export interface TemplateOptionForm {
  __key: number
  name: string
  default_selected: boolean
  /** Verweist auf __key einer Gruppe oder null (Toggle). */
  option_group_key: number | null
  deltas: TemplateOptionDeltaForm[]
}

const props = defineProps<{ readonly?: boolean }>()

const groupsModel = defineModel<TemplateOptionGroupForm[]>('groups', { required: true })
const optionsModel = defineModel<TemplateOptionForm[]>('options', { required: true })

const { t } = useI18n()
const readonly = computed(() => !!props.readonly)
const modalError = ref('')
let keyCounter = Date.now()

const standaloneToggles = computed(() => optionsModel.value.filter((o) => o.option_group_key === null))
function optionsForGroup(groupKey: number) {
  return optionsModel.value.filter((o) => o.option_group_key === groupKey)
}

function formatDeltas(opt: TemplateOptionForm): string {
  if (!opt.deltas || opt.deltas.length === 0) return t('components.comboOptions.noDeltas')
  return opt.deltas
    .map((d) => `${d.qty_delta > 0 ? '+' : ''}${d.qty_delta} ${d.name || d.component_type}${d.component_source === 'self_provided' ? ' *' : ''}`)
    .join(', ')
}

// ── Gruppen ──
function addGroup() {
  groupsModel.value.push({
    __key: ++keyCounter,
    name: t('components.comboOptions.newGroupName'),
    selection_type: 'exclusive',
    min_select: 1,
    max_select: 1,
  })
}

function setGroupMax(group: TemplateOptionGroupForm, e: Event) {
  const raw = (e.target as HTMLInputElement).value
  group.max_select = raw === '' ? null : Math.max(0, Number(raw))
}

function removeGroup(group: TemplateOptionGroupForm) {
  // Optionen der Gruppe lösen (zu Toggles) statt löschen wäre verwirrend → mitlöschen.
  optionsModel.value = optionsModel.value.filter((o) => o.option_group_key !== group.__key)
  groupsModel.value = groupsModel.value.filter((g) => g.__key !== group.__key)
}

// ── Options-Editor ──
interface EditForm {
  name: string
  default_selected: boolean
  option_group_key: number | null
  deltas: TemplateOptionDeltaForm[]
}

const showOptionModal = ref(false)
const editingOption = ref<TemplateOptionForm | null>(null)
const form = reactive<EditForm>({ name: '', default_selected: false, option_group_key: null, deltas: [] })

const canSubmit = computed(
  () => form.name.trim() !== '' && form.deltas.every((d) => d.name.trim() !== '' || d.component_type.trim() !== ''),
)

function openOptionEditor(opt: TemplateOptionForm | null, groupKey: number | null) {
  modalError.value = ''
  editingOption.value = opt
  if (opt) {
    form.name = opt.name
    form.default_selected = opt.default_selected
    form.option_group_key = opt.option_group_key
    form.deltas = opt.deltas.map((d) => ({ ...d }))
  } else {
    form.name = ''
    form.default_selected = false
    form.option_group_key = groupKey
    form.deltas = []
  }
  showOptionModal.value = true
}

function closeOptionModal() {
  showOptionModal.value = false
  editingOption.value = null
}

function addDeltaRow() {
  form.deltas.push({ name: '', component_type: '', qty_delta: 1, tracking: 'bulk', component_source: 'stock', is_generic: true })
}

function submitOption() {
  if (!canSubmit.value) return
  const deltas: TemplateOptionDeltaForm[] = form.deltas.map((d) => ({
    name: d.name.trim() || d.component_type.trim(),
    component_type: d.component_type.trim() || slug(d.name),
    qty_delta: Math.trunc(Number(d.qty_delta) || 0),
    tracking: d.tracking ?? 'bulk',
    component_source: (d.component_source as ComponentSource) ?? 'stock',
    is_generic: d.is_generic !== false,
  }))
  if (editingOption.value) {
    editingOption.value.name = form.name.trim()
    editingOption.value.default_selected = form.default_selected
    editingOption.value.option_group_key = form.option_group_key
    editingOption.value.deltas = deltas
  } else {
    optionsModel.value.push({
      __key: ++keyCounter,
      name: form.name.trim(),
      default_selected: form.default_selected,
      option_group_key: form.option_group_key,
      deltas,
    })
  }
  closeOptionModal()
}

function removeOption(opt: TemplateOptionForm) {
  optionsModel.value = optionsModel.value.filter((o) => o !== opt)
}

function slug(s: string): string {
  return s.toLowerCase().trim().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '')
}
</script>

<style scoped>
.combo-options-editor { display: flex; flex-direction: column; gap: 1rem; }
.coe-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.coe-title { font-size: 1rem; font-weight: 600; margin: 0; }
.coe-intro { font-size: 0.8rem; color: var(--text-muted, #6b7280); margin: 0.25rem 0 0; }
.coe-head-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.coe-group-card { border: 1px solid var(--border-color, #e5e7eb); border-radius: 8px; padding: 0.75rem; background: var(--surface-2, #f9fafb); }
.coe-group-head { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.coe-group-name { max-width: 180px; }
.coe-select-sm { max-width: 150px; }
.coe-num { width: 70px; }
.coe-minmax { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.8rem; }
.coe-option-list { list-style: none; margin: 0.5rem 0 0; padding: 0; display: flex; flex-direction: column; gap: 0.35rem; }
.coe-option-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.5rem; border-radius: 6px; background: var(--surface-1, #fff); border: 1px solid var(--border-color, #eee); }
.coe-option-name { font-weight: 600; font-size: 0.85rem; }
.coe-deltas { font-size: 0.78rem; color: var(--text-muted, #6b7280); flex: 1; }
.coe-badge { font-size: 0.68rem; padding: 0.05rem 0.4rem; border-radius: 999px; background: #e0e7ff; color: #3730a3; }
.coe-empty-row { font-size: 0.78rem; color: var(--text-muted, #9ca3af); padding: 0.25rem 0.5rem; }
.coe-subtitle { font-size: 0.85rem; font-weight: 600; margin: 0.5rem 0 0.25rem; }
.coe-modal { max-width: 620px; width: 100%; }
.coe-deltas-head { display: flex; justify-content: space-between; align-items: center; }
.coe-delta-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.4rem; }
.coe-delta-row-template { flex-wrap: wrap; }
.coe-delta-name { flex: 1; min-width: 120px; }
.coe-delta-type { flex: 1; min-width: 100px; }
.coe-inline-group { margin-top: 0.25rem; }
.icon-btn { background: none; border: none; cursor: pointer; padding: 0.2rem 0.4rem; border-radius: 4px; }
.icon-btn-danger { color: #dc2626; }
</style>
