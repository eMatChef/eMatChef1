<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="920"
    :title="template ? t('supplierTemplates.modal.editTitle') : t('supplierTemplates.modal.createTitle')"
    scrollable
    persistent
  >
    <ELoadingState
      v-if="loadingDetail"
      variant="inline"
      :message="t('common.loading')"
    />

    <form v-else id="supplier-template-form" @submit.prevent="submit">
      <section class="section">
        <h4>{{ t('supplierTemplates.sections.header') }}</h4>
        <ETextField
          v-model="form.name"
          :label="t('supplierTemplates.fields.name')"
          maxlength="160"
          hide-details="auto"
          class="mb-3"
        />

        <div class="field-row">
          <ESelect
            v-model="form.material_type"
            :items="materialTypeItems"
            :label="t('supplierTemplates.fields.materialType')"
            hide-details="auto"
            class="field-grow"
          />
          <ETextField
            v-model="form.manufacturer"
            :label="t('supplierTemplates.fields.manufacturer')"
            maxlength="255"
            hide-details="auto"
            class="field-grow"
          />
        </div>

        <div class="field-row">
          <ETextField
            v-model="form.model"
            :label="t('supplierTemplates.fields.model')"
            maxlength="100"
            hide-details="auto"
            class="field-grow"
          />
          <ETextField
            v-model="form.capacity"
            type="number"
            :label="t('supplierTemplates.fields.capacity')"
            hide-details="auto"
            class="field-narrow"
          />
        </div>

        <div class="field-row">
          <ETextField
            v-model="form.unit_price"
            type="number"
            :label="t('supplierTemplates.fields.unitPrice')"
            hide-details="auto"
            class="field-grow"
          />
          <ETextField
            v-model="form.currency"
            :label="t('supplierTemplates.fields.currency')"
            maxlength="3"
            hide-details="auto"
            class="field-narrow"
          />
        </div>

        <ETextField
          v-model="form.category_hint"
          :label="t('supplierTemplates.fields.categoryHint')"
          maxlength="255"
          hide-details="auto"
          class="mb-3"
        />

        <ETextarea
          v-model="form.description"
          :label="t('supplierTemplates.fields.description')"
          rows="2"
          maxlength="5000"
          hide-details="auto"
          class="mb-3"
        />

        <div class="field-row">
          <ESelect
            v-model="form.visibility"
            :items="visibilityItems"
            :label="t('supplierTemplates.fields.visibility')"
            hide-details="auto"
            class="field-grow"
          />
          <ESelect
            v-model="form.status"
            :items="statusItems"
            :label="t('supplierTemplates.fields.status')"
            hide-details="auto"
            class="field-grow"
          />
        </div>

        <p v-if="form.visibility === 'global'" class="hint">{{ t('supplierTemplates.globalReviewHint') }}</p>

        <ECheckbox
          v-model="form.is_active"
          :label="t('supplierTemplates.fields.isActive')"
          hide-details
          class="mb-2"
        />
      </section>

      <section class="section">
        <div class="section-header">
          <h4>{{ t('supplierTemplates.sections.components') }}</h4>
          <EButton variant="secondary" size="small" @click="addComponent">
            {{ t('supplierTemplates.addComponent') }}
          </EButton>
        </div>
        <p v-if="form.components.length === 0" class="hint">{{ t('supplierTemplates.componentsEmpty') }}</p>
        <div v-for="(comp, index) in form.components" :key="index" class="nested-card">
          <div class="field-row">
            <ETextField
              v-model="comp.component_type"
              :label="t('supplierTemplates.fields.componentType')"
              maxlength="60"
              hide-details="auto"
              class="field-grow"
            />
            <ETextField
              v-model="comp.name"
              :label="t('supplierTemplates.fields.componentName')"
              maxlength="160"
              hide-details="auto"
              class="field-grow"
            />
          </div>
          <div class="field-row">
            <ETextField
              v-model.number="comp.required_qty"
              type="number"
              :label="t('supplierTemplates.fields.requiredQty')"
              hide-details="auto"
              class="field-narrow"
            />
            <ESelect
              v-model="comp.tracking"
              :items="trackingItems"
              :label="t('supplierTemplates.fields.tracking')"
              hide-details="auto"
              class="field-grow"
            />
            <ESelect
              v-model="comp.component_source"
              :items="componentSourceItems"
              :label="t('supplierTemplates.fields.componentSource')"
              hide-details="auto"
              class="field-grow"
            />
          </div>
          <ECheckbox
            v-model="comp.is_optional"
            :label="t('supplierTemplates.fields.isOptionalToggle')"
            hide-details
            class="mb-2"
          />
          <EButton variant="danger" size="small" @click="removeComponent(index)">
            {{ t('supplierTemplates.removeComponent') }}
          </EButton>
        </div>
      </section>

      <section class="section">
        <div class="section-header">
          <h4>{{ t('supplierTemplates.sections.standaloneOptions') }}</h4>
          <EButton variant="secondary" size="small" @click="addStandaloneOption">
            {{ t('supplierTemplates.addOption') }}
          </EButton>
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
          <EButton variant="secondary" size="small" @click="addOptionGroup">
            {{ t('supplierTemplates.addOptionGroup') }}
          </EButton>
        </div>
        <p class="hint">{{ t('supplierTemplates.optionGroupsHint') }}</p>
        <div v-for="(group, groupIndex) in form.option_groups" :key="'g' + groupIndex" class="nested-card group-card">
          <div class="field-row">
            <ETextField
              v-model="group.name"
              :label="t('supplierTemplates.fields.groupName')"
              maxlength="120"
              hide-details="auto"
              class="field-grow"
            />
            <ESelect
              v-model="group.selection_type"
              :items="selectionTypeItems"
              :label="t('supplierTemplates.fields.selectionType')"
              hide-details="auto"
              class="field-grow"
            />
          </div>
          <div class="field-row">
            <ETextField
              v-model.number="group.min_select"
              type="number"
              :label="t('supplierTemplates.fields.minSelect')"
              hide-details="auto"
              class="field-narrow"
            />
            <ETextField
              :model-value="group.max_select ?? ''"
              type="number"
              :label="t('supplierTemplates.fields.maxSelect')"
              hide-details="auto"
              class="field-narrow"
              @update:model-value="group.max_select = parseMaxSelect(String($event ?? ''))"
            />
          </div>
          <div class="group-options">
            <div class="section-header">
              <strong>{{ t('supplierTemplates.groupOptionsTitle') }}</strong>
              <EButton variant="secondary" size="small" @click="addGroupOption(group)">
                {{ t('supplierTemplates.addOption') }}
              </EButton>
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
          <EButton variant="danger" size="small" @click="removeOptionGroup(groupIndex)">
            {{ t('supplierTemplates.removeOptionGroup') }}
          </EButton>
        </div>
      </section>

      <v-alert v-if="error" type="error" variant="tonal" :text="error" />
    </form>

    <template #actions>
      <EButton variant="secondary" size="small" @click="close">{{ t('common.cancel') }}</EButton>
      <EButton
        v-if="!loadingDetail"
        variant="primary"
        size="small"
        type="submit"
        form="supplier-template-form"
        :disabled="saving"
        :loading="saving"
      >
        {{ saving ? t('common.saving') : t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
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
import { EButton, ECheckbox, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'

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
const dialogOpen = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const materialTypeItems = computed(() => [
  { title: t('supplierTemplates.materialType.physicalCombo'), value: 'physical_combo' as const },
  { title: t('supplierTemplates.materialType.virtualCombo'), value: 'virtual_combo' as const },
])

const visibilityItems = computed(() => [
  { title: t('supplierTemplates.visibility.private'), value: 'private' as const },
  { title: t('supplierTemplates.visibility.departments'), value: 'departments' as const },
  { title: t('supplierTemplates.visibility.global'), value: 'global' as const },
])

const statusItems = computed(() => [
  { title: t('supplierTemplates.status.draft'), value: 'draft' as const },
  { title: t('supplierTemplates.status.published'), value: 'published' as const },
  { title: t('supplierTemplates.status.pendingReview'), value: 'pending_review' as const },
])

const trackingItems = computed(() => [
  { title: t('supplierTemplates.tracking.bulk'), value: 'bulk' as const },
  { title: t('supplierTemplates.tracking.serialized'), value: 'serialized' as const },
])

const componentSourceItems = computed(() => [
  { title: t('supplierTemplates.componentSource.stock'), value: 'stock' as const },
  { title: t('supplierTemplates.componentSource.selfProvided'), value: 'self_provided' as const },
])

const selectionTypeItems = computed(() => [
  { title: t('supplierTemplates.selectionType.exclusive'), value: 'exclusive' as const },
  { title: t('supplierTemplates.selectionType.multi'), value: 'multi' as const },
  { title: t('supplierTemplates.selectionType.quantity'), value: 'quantity' as const },
])

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

watch(dialogOpen, (open) => {
  if (!open) emit('close')
})

function close() {
  dialogOpen.value = false
}

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

.field-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.field-grow {
  flex: 1 1 180px;
}

.field-narrow {
  flex: 0 1 120px;
  max-width: 140px;
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
</style>
