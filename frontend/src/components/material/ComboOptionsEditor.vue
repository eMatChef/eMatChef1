<template>
  <div class="combo-options-editor">
    <div class="coe-head">
      <div>
        <h3 class="coe-title">{{ t('components.comboOptions.title') }}</h3>
        <p class="coe-intro">{{ t('components.comboOptions.intro') }}</p>
      </div>
      <div class="coe-head-actions">
        <button type="button" class="btn-outline-small" @click="openOptionEditor(null, null)">
          {{ t('components.comboOptions.btnAddToggle') }}
        </button>
        <button type="button" class="btn-outline-small" @click="addGroup">
          {{ t('components.comboOptions.btnAddGroup') }}
        </button>
      </div>
    </div>

    <p v-if="error" class="error-text">{{ error }}</p>

    <!-- Options-Gruppen -->
    <div v-for="group in sortedGroups" :key="group.id" class="coe-group-card">
      <div class="coe-group-head">
        <input
          v-model="group.name"
          type="text"
          class="form-input coe-group-name"
          :placeholder="t('components.comboOptions.phGroupName')"
          @change="saveGroup(group)"
        />
        <select v-model="group.selection_type" class="form-select coe-select-sm" @change="saveGroup(group)">
          <option value="exclusive">{{ t('components.comboOptions.selExclusive') }}</option>
          <option value="multi">{{ t('components.comboOptions.selMulti') }}</option>
          <option value="quantity">{{ t('components.comboOptions.selQuantity') }}</option>
        </select>
        <label class="coe-minmax">
          {{ t('components.comboOptions.labelMin') }}
          <input v-model.number="group.min_select" type="number" min="0" class="form-input coe-num" @change="saveGroup(group)" />
        </label>
        <label class="coe-minmax">
          {{ t('components.comboOptions.labelMax') }}
          <input
            :value="group.max_select ?? ''"
            type="number"
            min="0"
            class="form-input coe-num"
            :placeholder="t('components.comboOptions.phUnlimited')"
            @change="(e) => saveGroupMax(group, e)"
          />
        </label>
        <button type="button" class="btn-outline-small" @click="openOptionEditor(null, group.id)">
          {{ t('components.comboOptions.btnAddOptionToGroup') }}
        </button>
        <TableIconButton icon="mdi-delete-outline" danger :title="t('components.comboOptions.btnDeleteGroup')" @click="removeGroup(group)" />
      </div>
      <ul class="coe-option-list">
        <li v-for="opt in optionsForGroup(group.id)" :key="opt.id" class="coe-option-row">
          <span class="coe-option-name">{{ opt.name }}</span>
          <span class="coe-deltas">{{ formatDeltas(opt) }}</span>
          <span v-if="opt.default_selected" class="coe-badge">{{ t('components.comboOptions.badgeDefault') }}</span>
          <TableIconButton icon="mdi-pencil" :title="t('common.edit')" @click="openOptionEditor(opt, group.id)" />
          <TableIconButton icon="mdi-delete-outline" danger :title="t('common.delete')" @click="removeOption(opt)" />
        </li>
        <li v-if="optionsForGroup(group.id).length === 0" class="coe-empty-row">{{ t('components.comboOptions.groupEmpty') }}</li>
      </ul>
    </div>

    <!-- Eigenständige Toggle-Optionen -->
    <div class="coe-toggle-section">
      <h4 class="coe-subtitle">{{ t('components.comboOptions.toggleSectionTitle') }}</h4>
      <ul class="coe-option-list">
        <li v-for="opt in standaloneToggles" :key="opt.id" class="coe-option-row">
          <span class="coe-option-name">{{ opt.name }}</span>
          <span class="coe-deltas">{{ formatDeltas(opt) }}</span>
          <span v-if="opt.default_selected" class="coe-badge">{{ t('components.comboOptions.badgeDefaultOn') }}</span>
          <TableIconButton icon="mdi-pencil" :title="t('common.edit')" @click="openOptionEditor(opt, null)" />
          <TableIconButton icon="mdi-delete-outline" danger :title="t('common.delete')" @click="removeOption(opt)" />
        </li>
        <li v-if="standaloneToggles.length === 0" class="coe-empty-row">{{ t('components.comboOptions.toggleEmpty') }}</li>
      </ul>
    </div>

    <EDialog
      v-model="showOptionModal"
      :max-width="720"
      :title="editingOption ? t('components.comboOptions.modalEditTitle') : t('components.comboOptions.modalNewTitle')"
      scrollable
      persistent
      card-class="coe-modal"
    >
        <div class="form-group">
          <label>{{ t('components.comboOptions.labelOptionName') }}</label>
          <input v-model="form.name" type="text" class="form-input" :placeholder="t('components.comboOptions.phOptionName')" />
        </div>
        <div class="form-group coe-inline-group">
          <label class="checkbox-label">
            <input v-model="form.default_selected" type="checkbox" />
            {{ form.option_group_id ? t('components.comboOptions.labelDefaultInGroup') : t('components.comboOptions.labelDefaultToggle') }}
          </label>
        </div>

        <div class="form-group">
          <div class="coe-deltas-head">
            <label>{{ t('components.comboOptions.labelDeltaList') }}</label>
            <button type="button" class="btn-outline-small" @click="addDeltaRow">{{ t('components.comboOptions.btnAddDelta') }}</button>
          </div>
          <p class="batch-field-hint">{{ t('components.comboOptions.deltaHint') }}</p>
          <div v-for="(d, idx) in form.deltas" :key="idx" class="coe-delta-row">
            <div class="coe-delta-picker">
              <MaterialLookupInput
                v-if="!d.component_material_id"
                v-model="d._search"
                :fetcher="materialFetcher"
                :min-chars="1"
                :max-suggestions="8"
                :placeholder="t('components.comboOptions.phPickMaterial')"
                :loading-text="t('components.materialDetail.lookupLoadingEllipsis')"
                :empty-text="t('components.materialDetail.lookupEmptyHits', { query: d._search || '' })"
                :get-result-label="(it: Record<string, unknown>) => String(it?.name ?? '')"
                :get-result-secondary="formatSecondary"
                @select="(it: Record<string, unknown>) => pickDeltaMaterial(idx, it)"
              />
              <span v-else class="coe-delta-matname">{{ d._name }}</span>
            </div>
            <input v-model.number="d.qty_delta" type="number" class="form-input coe-num" :title="t('components.comboOptions.labelQtyDelta')" />
            <select v-model="d.component_source" class="form-select coe-select-sm">
              <option value="stock">{{ t('components.materialDetail.componentSourceStock') }}</option>
              <option value="self_provided">{{ t('components.materialDetail.componentSourceSelfProvided') }}</option>
            </select>
            <TableIconButton icon="mdi-delete-outline" danger :title="t('common.delete')" @click="form.deltas.splice(idx, 1)" />
          </div>
          <p v-if="form.deltas.length === 0" class="batch-field-hint">{{ t('components.comboOptions.deltaEmpty') }}</p>
        </div>

        <p v-if="modalError" class="error-text">{{ modalError }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeOptionModal">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="modalSubmitting || !canSubmit" :loading="modalSubmitting" @click="submitOption">
          {{ modalSubmitting ? t('common.saving') : t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import TableIconButton from '@/components/common/TableIconButton.vue'
import { EButton, EDialog } from '@/components/form/base'
import { createBasicMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import {
  addComboOption,
  addComboOptionGroup,
  deleteComboOption,
  deleteComboOptionGroup,
  updateComboOption,
  updateComboOptionGroup,
  type ComboOption,
  type ComboOptionGroup,
  type ComponentSource,
  type UpsertOptionDeltaRequest,
} from '@/api/materials'

interface Props {
  materialId: string
  departmentId: string
  options: ComboOption[]
  groups: ComboOptionGroup[]
  /** Optional guard before BOM/options mutations (e.g. open-activity warning). */
  beforeMutate?: () => Promise<boolean>
}
const props = defineProps<Props>()
const emit = defineEmits<{ (e: 'reload'): void }>()

const { t } = useI18n()

const error = ref('')
const modalError = ref('')

async function guardMutate(): Promise<boolean> {
  if (!props.beforeMutate) return true
  return props.beforeMutate()
}

// Lokale Kopien für inline editierbare Gruppen.
const sortedGroups = computed(() => [...props.groups].sort((a, b) => a.sort_order - b.sort_order))
const sortedOptions = computed(() => [...props.options].sort((a, b) => a.sort_order - b.sort_order))

const standaloneToggles = computed(() => sortedOptions.value.filter((o) => o.option_group_id === null))
function optionsForGroup(groupId: string) {
  return sortedOptions.value.filter((o) => o.option_group_id === groupId)
}

function formatDeltas(opt: ComboOption): string {
  if (!opt.deltas || opt.deltas.length === 0) return t('components.comboOptions.noDeltas')
  return opt.deltas
    .map((d) => `${d.qty_delta > 0 ? '+' : ''}${d.qty_delta} ${d.component_material.name}${d.component_source === 'self_provided' ? ' *' : ''}`)
    .join(', ')
}

function formatSecondary(item: Record<string, unknown>) {
  const tt = item?.tracking_type
  const mt = item?.material_type
  return [tt, mt].filter(Boolean).join(' · ')
}

const materialFetcher = async (query: string) => {
  const fetcher = createBasicMaterialLookupFetcher(() => props.departmentId)
  const items = await fetcher(query)
  return items.filter((m) => m.id !== props.materialId)
}

// ── Gruppen ──
async function addGroup() {
  if (!(await guardMutate())) return
  error.value = ''
  try {
    const maxSort = props.groups.reduce((acc, g) => Math.max(acc, g.sort_order), -1)
    await addComboOptionGroup(props.materialId, {
      name: t('components.comboOptions.newGroupName'),
      selection_type: 'exclusive',
      min_select: 1,
      max_select: 1,
      sort_order: maxSort + 1,
    })
    emit('reload')
  } catch (e: unknown) {
    error.value = extractError(e)
  }
}

async function saveGroup(group: ComboOptionGroup) {
  if (!(await guardMutate())) return
  error.value = ''
  try {
    await updateComboOptionGroup(props.materialId, group.id, {
      name: group.name,
      selection_type: group.selection_type,
      min_select: group.min_select,
      max_select: group.max_select,
    })
    emit('reload')
  } catch (e: unknown) {
    error.value = extractError(e)
  }
}

function saveGroupMax(group: ComboOptionGroup, e: Event) {
  const raw = (e.target as HTMLInputElement).value
  group.max_select = raw === '' ? null : Math.max(0, Number(raw))
  void saveGroup(group)
}

async function removeGroup(group: ComboOptionGroup) {
  if (!(await guardMutate())) return
  error.value = ''
  try {
    await deleteComboOptionGroup(props.materialId, group.id)
    emit('reload')
  } catch (e: unknown) {
    error.value = extractError(e)
  }
}

// ── Options-Editor ──
interface DeltaForm extends UpsertOptionDeltaRequest {
  _search?: string
  _name?: string
}
interface OptionForm {
  name: string
  default_selected: boolean
  option_group_id: string | null
  deltas: DeltaForm[]
}

const showOptionModal = ref(false)
const editingOption = ref<ComboOption | null>(null)
const modalSubmitting = ref(false)
const form = reactive<OptionForm>({ name: '', default_selected: false, option_group_id: null, deltas: [] })

const canSubmit = computed(() => form.name.trim() !== '' && form.deltas.every((d) => d.component_material_id))

function openOptionEditor(opt: ComboOption | null, groupId: string | null) {
  modalError.value = ''
  editingOption.value = opt
  if (opt) {
    form.name = opt.name
    form.default_selected = opt.default_selected
    form.option_group_id = opt.option_group_id
    form.deltas = opt.deltas.map((d) => ({
      component_material_id: d.component_material.id,
      qty_delta: d.qty_delta,
      component_source: d.component_source,
      assignment_mode: d.assignment_mode,
      tracking: d.tracking,
      _name: d.component_material.name,
      _search: '',
    }))
  } else {
    form.name = ''
    form.default_selected = false
    form.option_group_id = groupId
    form.deltas = []
  }
  showOptionModal.value = true
}

function closeOptionModal() {
  showOptionModal.value = false
  editingOption.value = null
}

function addDeltaRow() {
  form.deltas.push({ component_material_id: '', qty_delta: 1, component_source: 'stock', _search: '', _name: '' })
}

function pickDeltaMaterial(idx: number, item: Record<string, unknown>) {
  const d = form.deltas[idx]
  d.component_material_id = String(item.id)
  d._name = String(item.name ?? '')
  d.tracking = (item.tracking_type as string) ?? null
  d.assignment_mode = (item.tracking_type === 'serialized') ? 'on_issue' : 'bulk'
}

async function submitOption() {
  if (!canSubmit.value) return
  if (!(await guardMutate())) return
  modalSubmitting.value = true
  modalError.value = ''
  try {
    const displayMode = form.option_group_id ? 'group' : 'toggle'
    const deltas: UpsertOptionDeltaRequest[] = form.deltas.map((d, i) => ({
      component_material_id: d.component_material_id,
      qty_delta: Math.trunc(Number(d.qty_delta) || 0),
      component_source: (d.component_source as ComponentSource) ?? 'stock',
      assignment_mode: d.assignment_mode,
      tracking: d.tracking ?? null,
      sort_order: i,
    }))
    const payload = {
      name: form.name.trim(),
      display_mode: displayMode as 'toggle' | 'group',
      default_selected: form.default_selected,
      option_group_id: form.option_group_id,
      deltas,
    }
    if (editingOption.value) {
      await updateComboOption(props.materialId, editingOption.value.id, payload)
    } else {
      const maxSort = props.options.reduce((acc, o) => Math.max(acc, o.sort_order), -1)
      await addComboOption(props.materialId, { ...payload, sort_order: maxSort + 1 })
    }
    showOptionModal.value = false
    editingOption.value = null
    emit('reload')
  } catch (e: unknown) {
    modalError.value = extractError(e)
  } finally {
    modalSubmitting.value = false
  }
}

async function removeOption(opt: ComboOption) {
  if (!(await guardMutate())) return
  error.value = ''
  try {
    await deleteComboOption(props.materialId, opt.id)
    emit('reload')
  } catch (e: unknown) {
    error.value = extractError(e)
  }
}

function extractError(e: unknown): string {
  const err = e as { response?: { data?: { error?: string } } }
  return err?.response?.data?.error ?? t('components.comboOptions.genericError')
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
.coe-modal { max-width: 560px; width: 100%; }
.coe-deltas-head { display: flex; justify-content: space-between; align-items: center; }
.coe-delta-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.4rem; }
.coe-delta-picker { flex: 1; min-width: 0; }
.coe-delta-matname { font-size: 0.85rem; font-weight: 500; }
.coe-inline-group { margin-top: 0.25rem; }
.icon-btn { background: none; border: none; cursor: pointer; padding: 0.2rem 0.4rem; border-radius: 4px; }
.icon-btn-danger { color: #dc2626; }
</style>
