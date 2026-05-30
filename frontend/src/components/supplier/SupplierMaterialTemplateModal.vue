<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <header class="modal-header">
        <h3>
          {{
            template
              ? t('supplierTemplates.modal.editTitle')
              : t('supplierTemplates.modal.createTitle')
          }}
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">
          {{ t('common.cancel') }}
        </button>
      </header>

      <div v-if="loadingDetail" class="modal-body modal-loading">{{ t('common.loading') }}</div>

      <form v-else class="modal-body" @submit.prevent="submit">
        <section class="section">
          <h4>{{ t('supplierTemplates.sections.header') }}</h4>
          <label class="field">
            <span>{{ t('supplierTemplates.fields.name') }}</span>
            <input v-model.trim="form.name" type="text" required maxlength="160" />
          </label>

          <div class="field-row">
            <label class="field">
              <span>{{ t('supplierTemplates.fields.materialType') }}</span>
              <select v-model="form.material_type">
                <option value="physical_combo">{{ t('supplierTemplates.materialType.physicalCombo') }}</option>
                <option value="virtual_combo">{{ t('supplierTemplates.materialType.virtualCombo') }}</option>
              </select>
            </label>
            <label class="field">
              <span>{{ t('supplierTemplates.fields.manufacturer') }}</span>
              <input v-model.trim="form.manufacturer" type="text" maxlength="255" />
            </label>
          </div>

          <div class="field-row">
            <label class="field">
              <span>{{ t('supplierTemplates.fields.model') }}</span>
              <input v-model.trim="form.model" type="text" maxlength="100" />
            </label>
            <label class="field field-narrow">
              <span>{{ t('supplierTemplates.fields.capacity') }}</span>
              <input v-model.trim="form.capacity" type="number" min="1" step="1" />
            </label>
          </div>

          <div class="field-row">
            <label class="field">
              <span>{{ t('supplierTemplates.fields.unitPrice') }}</span>
              <input v-model.trim="form.unit_price" type="number" min="0" step="0.01" />
            </label>
            <label class="field field-narrow">
              <span>{{ t('supplierTemplates.fields.currency') }}</span>
              <input v-model.trim="form.currency" type="text" maxlength="3" />
            </label>
          </div>

          <label class="field">
            <span>{{ t('supplierTemplates.fields.categoryHint') }}</span>
            <input v-model.trim="form.category_hint" type="text" maxlength="255" />
          </label>

          <label class="field">
            <span>{{ t('supplierTemplates.fields.description') }}</span>
            <textarea v-model.trim="form.description" rows="2" maxlength="5000" />
          </label>

          <div class="field-row">
            <label class="field">
              <span>{{ t('supplierTemplates.fields.visibility') }}</span>
              <select v-model="form.visibility">
                <option value="private">{{ t('supplierTemplates.visibility.private') }}</option>
                <option value="departments">{{ t('supplierTemplates.visibility.departments') }}</option>
                <option value="global">{{ t('supplierTemplates.visibility.global') }}</option>
              </select>
            </label>
            <label class="field">
              <span>{{ t('supplierTemplates.fields.status') }}</span>
              <select v-model="form.status">
                <option value="draft">{{ t('supplierTemplates.status.draft') }}</option>
                <option value="published">{{ t('supplierTemplates.status.published') }}</option>
                <option value="pending_review">{{ t('supplierTemplates.status.pendingReview') }}</option>
              </select>
            </label>
          </div>

          <p v-if="form.visibility === 'global'" class="hint">{{ t('supplierTemplates.globalReviewHint') }}</p>

          <label class="checkbox-field">
            <input v-model="form.is_active" type="checkbox" />
            <span>{{ t('supplierTemplates.fields.isActive') }}</span>
          </label>
        </section>

        <section class="section">
          <div class="section-header">
            <h4>{{ t('supplierTemplates.sections.components') }}</h4>
            <button type="button" class="btn btn-secondary btn-sm" @click="addComponent">
              {{ t('supplierTemplates.addComponent') }}
            </button>
          </div>
          <p v-if="form.components.length === 0" class="hint">{{ t('supplierTemplates.componentsEmpty') }}</p>
          <div v-for="(comp, index) in form.components" :key="index" class="nested-card">
            <div class="field-row">
              <label class="field">
                <span>{{ t('supplierTemplates.fields.componentType') }}</span>
                <input v-model.trim="comp.component_type" type="text" required maxlength="60" />
              </label>
              <label class="field">
                <span>{{ t('supplierTemplates.fields.componentName') }}</span>
                <input v-model.trim="comp.name" type="text" required maxlength="160" />
              </label>
            </div>
            <div class="field-row">
              <label class="field field-narrow">
                <span>{{ t('supplierTemplates.fields.requiredQty') }}</span>
                <input v-model.number="comp.required_qty" type="number" min="1" step="1" />
              </label>
              <label class="field">
                <span>{{ t('supplierTemplates.fields.tracking') }}</span>
                <select v-model="comp.tracking">
                  <option value="bulk">{{ t('supplierTemplates.tracking.bulk') }}</option>
                  <option value="serialized">{{ t('supplierTemplates.tracking.serialized') }}</option>
                </select>
              </label>
              <label class="field">
                <span>{{ t('supplierTemplates.fields.componentSource') }}</span>
                <select v-model="comp.component_source">
                  <option value="stock">{{ t('supplierTemplates.componentSource.stock') }}</option>
                  <option value="self_provided">{{ t('supplierTemplates.componentSource.selfProvided') }}</option>
                </select>
              </label>
            </div>
            <label class="checkbox-field">
              <input v-model="comp.is_optional" type="checkbox" />
              <span>{{ t('supplierTemplates.fields.isOptionalToggle') }}</span>
            </label>
            <button type="button" class="btn btn-danger btn-sm" @click="removeComponent(index)">
              {{ t('supplierTemplates.removeComponent') }}
            </button>
          </div>
        </section>

        <section class="section">
          <div class="section-header">
            <h4>{{ t('supplierTemplates.sections.standaloneOptions') }}</h4>
            <button type="button" class="btn btn-secondary btn-sm" @click="addStandaloneOption">
              {{ t('supplierTemplates.addOption') }}
            </button>
          </div>
          <p class="hint">{{ t('supplierTemplates.standaloneOptionsHint') }}</p>
          <div v-for="(opt, optIndex) in form.standalone_options" :key="'s' + optIndex" class="nested-card">
            <OptionEditor
              :option="opt"
              @remove="removeStandaloneOption(optIndex)"
              @add-delta="addDelta(opt)"
              @remove-delta="(di) => removeDelta(opt, di)"
            />
          </div>
        </section>

        <section class="section">
          <div class="section-header">
            <h4>{{ t('supplierTemplates.sections.optionGroups') }}</h4>
            <button type="button" class="btn btn-secondary btn-sm" @click="addOptionGroup">
              {{ t('supplierTemplates.addOptionGroup') }}
            </button>
          </div>
          <p class="hint">{{ t('supplierTemplates.optionGroupsHint') }}</p>
          <div v-for="(group, groupIndex) in form.option_groups" :key="'g' + groupIndex" class="nested-card group-card">
            <div class="field-row">
              <label class="field">
                <span>{{ t('supplierTemplates.fields.groupName') }}</span>
                <input v-model.trim="group.name" type="text" required maxlength="120" />
              </label>
              <label class="field">
                <span>{{ t('supplierTemplates.fields.selectionType') }}</span>
                <select v-model="group.selection_type">
                  <option value="exclusive">{{ t('supplierTemplates.selectionType.exclusive') }}</option>
                  <option value="multi">{{ t('supplierTemplates.selectionType.multi') }}</option>
                  <option value="quantity">{{ t('supplierTemplates.selectionType.quantity') }}</option>
                </select>
              </label>
            </div>
            <div class="field-row">
              <label class="field field-narrow">
                <span>{{ t('supplierTemplates.fields.minSelect') }}</span>
                <input v-model.number="group.min_select" type="number" min="0" step="1" />
              </label>
              <label class="field field-narrow">
                <span>{{ t('supplierTemplates.fields.maxSelect') }}</span>
                <input
                  :value="group.max_select ?? ''"
                  type="number"
                  min="0"
                  step="1"
                  @input="group.max_select = parseMaxSelect(($event.target as HTMLInputElement).value)"
                />
              </label>
            </div>
            <div class="group-options">
              <div class="section-header">
                <strong>{{ t('supplierTemplates.groupOptionsTitle') }}</strong>
                <button type="button" class="btn btn-secondary btn-sm" @click="addGroupOption(group)">
                  {{ t('supplierTemplates.addOption') }}
                </button>
              </div>
              <div v-for="(opt, optIndex) in group.options" :key="optIndex" class="nested-card nested-card--inner">
                <OptionEditor
                  :option="opt"
                  force-display-mode="group"
                  @remove="removeGroupOption(group, optIndex)"
                  @add-delta="addDelta(opt)"
                  @remove-delta="(di) => removeDelta(opt, di)"
                />
              </div>
            </div>
            <button type="button" class="btn btn-danger btn-sm" @click="removeOptionGroup(groupIndex)">
              {{ t('supplierTemplates.removeOptionGroup') }}
            </button>
          </div>
        </section>

        <p v-if="error" class="error">{{ error }}</p>

        <footer class="modal-footer">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import OptionEditor from '@/components/supplier/SupplierTemplateOptionEditor.vue'
import type {
  SupplierMaterialTemplate,
  SupplierMaterialTemplatePayload,
  SupplierMaterialType,
  SupplierTemplateComponent,
  SupplierTemplateOption,
  SupplierTemplateOptionDelta,
  SupplierTemplateOptionGroup,
  SupplierTemplateStatus,
  SupplierTemplateVisibility,
} from '@/api/supplierMaterialTemplates'

const props = defineProps<{
  template: SupplierMaterialTemplate | null
  defaultManufacturer?: string | null
  loadingDetail?: boolean
}>()

const emit = defineEmits<{
  close: []
  save: [payload: SupplierMaterialTemplatePayload]
}>()

const { t } = useI18n()
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive({
  name: '',
  description: '',
  manufacturer: '',
  model: '',
  material_type: 'physical_combo' as SupplierMaterialType,
  capacity: '',
  category_hint: '',
  unit_price: '',
  currency: 'CHF',
  visibility: 'private' as SupplierTemplateVisibility,
  status: 'draft' as SupplierTemplateStatus,
  is_active: true,
  components: [] as SupplierTemplateComponent[],
  standalone_options: [] as SupplierTemplateOption[],
  option_groups: [] as SupplierTemplateOptionGroup[],
})

function emptyComponent(): SupplierTemplateComponent {
  return {
    component_type: '',
    name: '',
    required_qty: 1,
    is_optional: false,
    tracking: 'bulk',
    component_source: 'stock',
    is_generic: false,
    sort_order: form.components.length,
  }
}

function emptyDelta(): SupplierTemplateOptionDelta {
  return {
    component_type: '',
    name: '',
    qty_delta: 1,
    tracking: 'bulk',
    component_source: 'stock',
    is_generic: false,
    sort_order: 0,
  }
}

function emptyOption(displayMode: 'toggle' | 'group' = 'toggle'): SupplierTemplateOption {
  return {
    name: '',
    display_mode: displayMode,
    default_selected: false,
    sort_order: 0,
    deltas: [],
  }
}

function emptyOptionGroup(): SupplierTemplateOptionGroup {
  return {
    name: '',
    selection_type: 'exclusive',
    min_select: 0,
    max_select: null,
    sort_order: form.option_groups.length,
    options: [],
  }
}

function parseMaxSelect(value: string): number | null {
  const trimmed = value.trim()
  if (trimmed === '') return null
  const n = Number(trimmed)
  return Number.isFinite(n) ? n : null
}

function addComponent() {
  form.components.push(emptyComponent())
}

function removeComponent(index: number) {
  form.components.splice(index, 1)
}

function addStandaloneOption() {
  form.standalone_options.push(emptyOption('toggle'))
}

function removeStandaloneOption(index: number) {
  form.standalone_options.splice(index, 1)
}

function addOptionGroup() {
  form.option_groups.push(emptyOptionGroup())
}

function removeOptionGroup(index: number) {
  form.option_groups.splice(index, 1)
}

function addGroupOption(group: SupplierTemplateOptionGroup) {
  group.options.push(emptyOption('group'))
}

function removeGroupOption(group: SupplierTemplateOptionGroup, index: number) {
  group.options.splice(index, 1)
}

function addDelta(option: SupplierTemplateOption) {
  option.deltas.push(emptyDelta())
}

function removeDelta(option: SupplierTemplateOption, index: number) {
  option.deltas.splice(index, 1)
}

function resetForm() {
  form.name = ''
  form.description = ''
  form.manufacturer = props.defaultManufacturer || ''
  form.model = ''
  form.material_type = 'physical_combo'
  form.capacity = ''
  form.category_hint = ''
  form.unit_price = ''
  form.currency = 'CHF'
  form.visibility = 'private'
  form.status = 'draft'
  form.is_active = true
  form.components = []
  form.standalone_options = []
  form.option_groups = []
}

function loadFromTemplate(template: SupplierMaterialTemplate) {
  form.name = template.name
  form.description = template.description || ''
  form.manufacturer = template.manufacturer || ''
  form.model = template.model || ''
  form.material_type = template.material_type
  form.capacity = template.capacity != null ? String(template.capacity) : ''
  form.category_hint = template.category_hint || ''
  form.unit_price = template.unit_price != null ? String(template.unit_price) : ''
  form.currency = template.currency
  form.visibility = template.visibility
  form.status = template.status
  form.is_active = template.is_active
  form.components = (template.components || []).map((c, i) => ({ ...c, sort_order: c.sort_order ?? i }))
  form.standalone_options = (template.standalone_options || []).map((o) => ({
    ...o,
    deltas: (o.deltas || []).map((d) => ({ ...d })),
  }))
  form.option_groups = (template.option_groups || []).map((g, gi) => ({
    ...g,
    max_select: g.max_select ?? null,
    sort_order: g.sort_order ?? gi,
    options: (g.options || []).map((o) => ({
      ...o,
      deltas: (o.deltas || []).map((d) => ({ ...d })),
    })),
  }))
}

watch(
  () => props.template,
  (template) => {
    error.value = null
    if (template) {
      loadFromTemplate(template)
    } else {
      resetForm()
    }
  },
  { immediate: true },
)

function buildPayload(): SupplierMaterialTemplatePayload {
  return {
    name: form.name.trim(),
    description: form.description.trim() || null,
    manufacturer: form.manufacturer.trim() || null,
    model: form.model.trim() || null,
    material_type: form.material_type,
    capacity: form.capacity ? Number(form.capacity) : null,
    category_hint: form.category_hint.trim() || null,
    unit_price: form.unit_price ? Number(form.unit_price) : null,
    currency: form.currency.trim() || 'CHF',
    visibility: form.visibility,
    status: form.status,
    is_active: form.is_active,
    components: form.components.map((c, i) => ({
      ...c,
      component_type: c.component_type.trim(),
      name: c.name.trim(),
      sort_order: i,
    })),
    standalone_options: form.standalone_options.map((o, i) => ({
      ...o,
      name: o.name.trim(),
      sort_order: i,
      deltas: o.deltas.map((d, di) => ({
        ...d,
        component_type: d.component_type.trim(),
        name: d.name.trim(),
        sort_order: di,
      })),
    })),
    option_groups: form.option_groups.map((g, gi) => ({
      ...g,
      name: g.name.trim(),
      max_select: g.max_select == null ? null : Number(g.max_select),
      sort_order: gi,
      options: g.options.map((o, oi) => ({
        ...o,
        name: o.name.trim(),
        display_mode: 'group' as const,
        sort_order: oi,
        deltas: o.deltas.map((d, di) => ({
          ...d,
          component_type: d.component_type.trim(),
          name: d.name.trim(),
          sort_order: di,
        })),
      })),
    })),
  }
}

function submit() {
  error.value = null
  if (!form.name.trim()) {
    error.value = t('supplierTemplates.errors.nameRequired')
    return
  }
  saving.value = true
  emit('save', buildPayload())
  saving.value = false
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 24px;
  z-index: 1000;
  overflow-y: auto;
}

.modal-card {
  background: #fff;
  border-radius: 10px;
  width: min(920px, 100%);
  max-height: calc(100vh - 48px);
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  margin: 0;
}

.modal-body {
  padding: 16px 20px;
  overflow-y: auto;
}

.modal-loading {
  padding: 40px;
  text-align: center;
  color: #6b7280;
}

.section {
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid #f3f4f6;
}

.section h4 {
  margin: 0 0 12px;
  font-size: 1rem;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 12px;
  flex: 1;
}

.field-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.field-narrow {
  max-width: 120px;
}

.checkbox-field {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.hint {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0 0 12px;
}

.nested-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 12px;
  background: #fafafa;
}

.nested-card--inner {
  background: #fff;
}

.group-card {
  background: #f3f4f6;
}

.group-options {
  margin: 12px 0;
}

.error {
  color: #b91c1c;
  margin: 8px 0;
}

.modal-footer {
  padding-top: 8px;
  display: flex;
  justify-content: flex-end;
}
</style>
