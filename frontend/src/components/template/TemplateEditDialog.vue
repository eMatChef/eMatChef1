<template>
  <div class="modal-overlay">
    <div class="modal-dialog modal-dialog--structured modal-dialog--wide">
      <!-- Header -->
      <div class="modal-header">
        <div class="modal-header-title">
          <h2>{{ props.readonly ? t('components.templateEditDialog.titleView') : (isEditing ? t('components.templateEditDialog.titleEdit') : t('components.templateEditDialog.titleCreate')) }}</h2>
          <span v-if="isSinglePart" class="kind-badge">{{ t('components.templateEditDialog.kindBadge') }}</span>
          <span v-if="props.readonly" class="readonly-badge">{{ t('components.templateEditDialog.readonlyBadge') }}</span>
        </div>
        <button class="modal-close" @click="$emit('close')">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body modal-body--scroll">
        <!-- Tabs -->
        <div class="tabs">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="tab"
            :class="{
              active: activeTab === tab.id,
              'tab-unvisited': !isEditing && !visitedTabs.has(tab.id),
            }"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
            <span v-if="!isEditing && !visitedTabs.has(tab.id)" class="tab-visit-dot" :title="t('components.templateEditDialog.tabNotVisitedYet')" />
            <span v-if="tab.id === 'components' && form.components.length > 0" class="tab-count">
              {{ form.components.length }}
            </span>
            <span v-if="tab.id === 'accessories' && form.accessories.length > 0" class="tab-count">
              {{ form.accessories.length }}
            </span>
          </button>
        </div>

        <!-- Tab: Allgemein -->
        <div v-show="activeTab === 'general'" class="tab-content">
          <div class="form-grid">
            <div class="form-group full">
              <label class="form-label required">{{ t('components.templateEditDialog.nameLabel') }}</label>
              <input v-model="form.name" type="text" class="form-input" :placeholder="t('components.templateEditDialog.namePlaceholder')" :disabled="props.readonly" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('components.templateEditDialog.manufacturerLabel') }}</label>
              <select
                v-model="form.manufacturer_address_id"
                class="form-select"
                :disabled="props.readonly || loadingManufacturerOptions"
                @change="syncManufacturerLabel"
              >
                <option :value="null">{{ t('components.templateEditDialog.manufacturerMixed') }}</option>
                <option v-for="opt in manufacturerOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
              <span class="form-hint">{{ t('components.templateEditDialog.manufacturerHint') }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('components.templateEditDialog.modelLabel') }}</label>
              <input v-model="form.model" type="text" class="form-input" :placeholder="t('components.templateEditDialog.modelPlaceholder')" :disabled="props.readonly" />
            </div>

            <div class="form-group full">
              <label class="form-label">{{ t('components.templateEditDialog.descriptionLabel') }}</label>
              <textarea v-model="form.description" class="form-textarea" rows="2" :placeholder="t('components.templateEditDialog.descriptionPlaceholder')" :disabled="props.readonly"></textarea>
            </div>

            <div v-if="form.template_kind" class="form-group">
              <label class="form-label">{{ t('components.templateEditDialog.templateKindLabel') }}</label>
              <p class="kind-readonly">{{ templateKindDisplay }}</p>
              <span class="form-hint">{{ t('components.templateEditDialog.templateKindFromWizardHint') }}</span>
            </div>
            <div v-if="form.template_domain" class="form-group">
              <label class="form-label">{{ t('components.templateEditDialog.templateDomainLabel') }}</label>
              <p class="kind-readonly">{{ templateDomainDisplay }}</p>
              <span class="form-hint">{{ t('components.templateEditDialog.templateDomainFromWizardHint') }}</span>
            </div>
            <div v-else class="form-group">
              <label class="form-label">{{ t('components.templateEditDialog.materialTypeLabel') }}</label>
              <select v-model="form.material_type" class="form-select" :disabled="props.readonly">
                <option value="physical_combo">{{ t('components.templateEditDialog.materialTypePhysicalCombo') }}</option>
                <option value="virtual_combo">{{ t('components.templateEditDialog.materialTypeVirtualCombo') }}</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('components.templateEditDialog.statusLabel') }}</label>
              <div class="toggle-group">
                <label class="toggle">
                  <input type="checkbox" v-model="form.is_active" :disabled="props.readonly" />
                  <span class="toggle-slider"></span>
                </label>
                <span class="toggle-label">{{ form.is_active ? t('components.templateEditDialog.statusActive') : t('components.templateEditDialog.statusInactive') }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab: Zelt-Details -->
        <div v-show="activeTab === 'tent'" class="tab-content">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">{{ t('components.templateEditDialog.tentTypeLabel') }}</label>
              <select v-model="form.tent_type" class="form-select" :disabled="props.readonly">
                <option :value="null">{{ t('components.templateEditDialog.noneOption') }}</option>
                <option value="gruppenzelt">{{ t('components.templateEditDialog.tentTypeGroupTent') }}</option>
                <option value="sonstiges">{{ t('components.templateEditDialog.tentTypeOther') }}</option>
              </select>
              <span class="form-hint">{{ t('components.templateEditDialog.tentTypeHint') }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('components.templateEditDialog.capacityLabel') }}</label>
              <input v-model.number="form.capacity" type="number" class="form-input" :placeholder="t('components.templateEditDialog.capacityPlaceholder')" min="1" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('components.templateEditDialog.sourceLabel') }}</label>
              <input v-model="form.source" type="text" class="form-input" :placeholder="t('components.templateEditDialog.sourcePlaceholder')" />
            </div>
          </div>
        </div>

        <!-- Tab: Komponenten -->
        <div v-show="activeTab === 'components'" class="tab-content">
          <p v-if="form.material_type === 'virtual_combo'" class="optional-hint">
            {{ t('components.templateEditDialog.optionalNotAccessory') }}
          </p>
          <p v-if="isSinglePart" class="single-part-hint">
            {{ t('components.templateEditDialog.singlePartHint') }}
          </p>

          <div v-if="isSinglePartLocked && form.components.length === 1" class="single-part-tracking-section">
            <label class="form-label">{{ t('components.templateEditDialog.trackingLabel') }}</label>
            <p class="form-hint tracking-domain-hint">{{ singlePartTrackingDefaultHint }}</p>
            <div class="tracking-choice-bar">
              <button
                type="button"
                class="tracking-choice-btn"
                :class="{ active: form.components[0].tracking === 'serialized' }"
                @click="form.components[0].tracking = 'serialized'"
              >
                <strong>{{ t('components.templateEditDialog.trackingSerialized') }}</strong>
                <span>{{ t('components.templateEditDialog.trackingSerializedShortHint') }}</span>
              </button>
              <button
                type="button"
                class="tracking-choice-btn"
                :class="{ active: form.components[0].tracking === 'bulk' }"
                @click="form.components[0].tracking = 'bulk'"
              >
                <strong>{{ t('components.templateEditDialog.trackingBulk') }}</strong>
                <span>{{ t('components.templateEditDialog.trackingBulkShortHint') }}</span>
              </button>
            </div>
          </div>

          <div class="components-header">
            <p class="components-info">
              {{ isSinglePartLocked ? t('components.templateEditDialog.singlePartComponentsInfo') : t('components.templateEditDialog.componentsInfo') }}
            </p>
            <button v-if="!props.readonly && !isSinglePartLocked" class="btn-add-component" @click="addComponent">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              {{ t('components.templateEditDialog.addComponent') }}
            </button>
          </div>

          <div v-if="form.components.length === 0" class="empty-components">
            <p>{{ t('components.templateEditDialog.emptyComponents') }}</p>
          </div>

          <draggable
            v-else
            v-model="form.components"
            item-key="__key"
            handle=".drag-handle"
            ghost-class="dragging"
            class="components-list"
          >
            <template #item="{ element, index }">
              <div class="component-card" :class="{ 'single-part-card': isSinglePartLocked }">
                <div class="component-header">
                  <button v-if="!isSinglePartLocked" class="drag-handle" :title="t('components.templateEditDialog.dragToSortTitle')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/>
                    </svg>
                  </button>
                  <span class="component-number">#{{ index + 1 }}</span>
                  <span class="component-title">{{ element.name || t('components.templateEditDialog.newComponent') }}</span>
                  <div class="component-badges">
                    <span class="comp-badge" :class="element.tracking">{{ element.tracking === 'serialized' ? t('components.templateEditDialog.trackingShortSn') : t('components.templateEditDialog.trackingShortBulk') }}</span>
                    <span v-if="element.is_generic" class="comp-badge generic" :title="t('components.templateEditDialog.genericBadgeTitle')">🌐</span>
                    <span v-if="element.is_optional" class="comp-badge optional">{{ t('components.templateEditDialog.optionalBadge') }}</span>
                    <span v-if="element.component_source === 'self_provided'" class="comp-badge" :title="t('components.templateEditDialog.componentSourceSelfProvided')">{{ t('components.templateEditDialog.componentSourceSelfBadge') }}</span>
                  </div>
                  <button
                    v-if="!isSinglePartLocked"
                    class="component-toggle"
                    :class="{ expanded: expandedComponents.has(index) }"
                    @click="toggleComponent(index)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="6 9 12 15 18 9"/>
                    </svg>
                  </button>
                  <button v-if="!props.readonly && !isSinglePartLocked" class="btn-remove-component" @click="removeComponent(index)" :title="t('components.templateEditDialog.removeTitle')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                  </button>
                </div>

                <transition name="slide">
                  <div v-if="isComponentExpanded(index)" class="component-body">
                    <div class="comp-form-grid">
                      <div class="form-group">
                        <label class="form-label required">{{ t('components.templateEditDialog.nameLabel') }}</label>
                        <input v-model="element.name" type="text" class="form-input" :placeholder="t('components.templateEditDialog.componentNamePlaceholder')" />
                      </div>
                      <div class="form-group">
                        <label class="form-label">{{ t('components.templateEditDialog.typeLabel') }}</label>
                        <input v-model="element.component_type" type="text" class="form-input" :placeholder="t('components.templateEditDialog.componentTypePlaceholder')" />
                      </div>
                      <div class="form-group">
                        <label class="form-label">{{ t('components.templateEditDialog.quantityLabel') }}</label>
                        <input v-model.number="element.required_qty" type="number" class="form-input" min="1" />
                      </div>
                      <div v-if="!isSinglePartLocked" class="form-group">
                        <label class="form-label">{{ t('components.templateEditDialog.trackingLabel') }}</label>
                        <select v-model="element.tracking" class="form-select">
                          <option value="serialized">{{ t('components.templateEditDialog.trackingSerialized') }}</option>
                          <option value="bulk">{{ t('components.templateEditDialog.trackingBulk') }}</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label class="form-label">{{ t('components.templateEditDialog.componentSourceLabel') }}</label>
                        <select v-model="element.component_source" class="form-select">
                          <option value="stock">{{ t('components.templateEditDialog.componentSourceStock') }}</option>
                          <option value="self_provided">{{ t('components.templateEditDialog.componentSourceSelfProvided') }}</option>
                        </select>
                      </div>
                      <div v-if="form.material_type === 'virtual_combo'" class="form-group checkbox-group">
                        <label class="checkbox-label">
                          <input type="checkbox" v-model="element.is_optional" />
                          <span>{{ t('components.templateEditDialog.optionalHint') }}</span>
                        </label>
                      </div>
                      <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                          <input type="checkbox" v-model="element.is_generic" />
                          <span>{{ t('components.templateEditDialog.genericHint') }}</span>
                        </label>
                      </div>
                      <div class="form-group full">
                        <label class="form-label">{{ t('components.templateEditDialog.repairTypesLabel') }}</label>
                        <input v-model="element._repairTypesStr" type="text" class="form-input" :placeholder="t('components.templateEditDialog.repairTypesPlaceholder')" />
                        <span class="form-hint">{{ t('components.templateEditDialog.repairTypesHint') }}</span>
                      </div>
                    </div>
                  </div>
                </transition>
              </div>
            </template>
          </draggable>
        </div>

        <!-- Tab: Verwandtes Zubehör -->
        <div v-show="activeTab === 'accessories'" class="tab-content">
          <div class="components-header">
            <p class="components-info">
              {{ t('components.templateEditDialog.accessoriesInfo') }}
            </p>
            <button v-if="!props.readonly" class="btn-add-component" @click="addAccessory">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              {{ t('components.templateEditDialog.addAccessory') }}
            </button>
          </div>

          <div v-if="form.accessories.length === 0" class="empty-components">
            <p>{{ t('components.templateEditDialog.emptyAccessories') }}</p>
          </div>

          <div v-else class="components-list">
            <div v-for="(acc, index) in form.accessories" :key="acc.__key" class="component-item">
              <div class="component-body" style="padding: 12px 16px;">
                <div class="form-grid">
                  <div class="form-group">
                    <label class="form-label required">{{ t('components.templateEditDialog.nameLabel') }}</label>
                    <input v-model="acc.name" type="text" class="form-input" :placeholder="t('components.templateEditDialog.accessoryNamePlaceholder')" :disabled="props.readonly" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">{{ t('components.templateEditDialog.typeLabel') }}</label>
                    <input v-model="acc.component_type" type="text" class="form-input" :placeholder="t('components.templateEditDialog.componentTypePlaceholder')" :disabled="props.readonly" />
                  </div>
                  <div class="form-group full">
                    <label class="checkbox-row">
                      <input type="checkbox" v-model="acc.is_generic" :disabled="props.readonly" />
                      <span>{{ t('components.templateEditDialog.genericHint') }}</span>
                    </label>
                  </div>
                </div>
                <button v-if="!props.readonly" class="btn-remove-component" @click="removeAccessory(index)" :title="t('components.templateEditDialog.removeTitle')">
                  {{ t('components.templateEditDialog.removeTitle') }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab: Konfigurator (nur virtuelle Kombo) -->
        <div v-if="form.material_type === 'virtual_combo'" v-show="activeTab === 'configurator'" class="tab-content">
          <TemplateOptionsEditor
            v-model:groups="form.optionGroups"
            v-model:options="form.options"
            :readonly="props.readonly"
          />
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer modal-footer--plain">
        <div class="footer-left">
          <div v-if="saveError" class="save-error">{{ saveError }}</div>
          <p v-if="!isEditing && !props.readonly && missingTabs.length" class="tabs-todo-hint">
            {{ t('components.templateEditDialog.visitAllTabsHint', { tabs: missingTabLabels }) }}
          </p>
        </div>
        <div class="footer-actions">
        <button class="btn-secondary" @click="$emit('close')">{{ props.readonly ? t('components.templateEditDialog.close') : t('common.cancel') }}</button>
        <button
          v-if="!props.readonly"
          class="btn-primary"
          @click="save"
          :disabled="isSaving || !canSubmit"
          :title="!canSubmit && !isEditing ? t('components.templateEditDialog.visitAllTabsHint', { tabs: missingTabLabels }) : undefined"
        >
          {{ isSaving ? t('components.templateEditDialog.saving') : (isEditing ? t('common.save') : t('components.templateEditDialog.create')) }}
        </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive, watch } from 'vue'
import { useToast } from '@/composables/useToast'
import { useI18n } from 'vue-i18n'
import draggable from 'vuedraggable'
import TemplateOptionsEditor, {
  type TemplateOptionGroupForm,
  type TemplateOptionForm,
} from './TemplateOptionsEditor.vue'
import {
  getTemplate,
  getTemplateManufacturerOptions,
  createTemplate,
  updateTemplate,
  type Template,
  type TemplateWizardResult,
  type TemplateKind,
  type TemplateDomain,
  type TemplateManufacturerOption,
  type CreateTemplateRequest,
  type UpdateTemplateRequest,
  type CreateTemplateComponentRequest,
  type CreateTemplateRelatedAccessoryRequest,
  type UpsertTemplateOptionGroupRequest,
  type UpsertTemplateOptionRequest,
  type ComponentSource
} from '@/api/templates'

interface ComponentForm {
  __key: number
  component_type: string
  name: string
  required_qty: number
  is_optional: boolean
  is_generic: boolean
  tracking: 'serialized' | 'bulk'
  component_source: ComponentSource
  repair_types: string[] | null
  _repairTypesStr: string
  sort_order: number
}

interface AccessoryForm {
  __key: number
  name: string
  component_type: string
  is_generic: boolean
  sort_order: number
}

const props = withDefaults(defineProps<{
  departmentId?: string
  templateScope?: 'global' | 'department'
  template: Template | null
  initialWizard?: TemplateWizardResult | null
  readonly?: boolean
}>(), {
  templateScope: 'department',
  initialWizard: null,
})

const emit = defineEmits<{
  close: []
  saved: []
}>()

const toast = useToast()
const { t } = useI18n()
const isEditing = computed(() => !!props.template && !!props.template.id)
const activeTab = ref('general')
const visitedTabs = ref<Set<string>>(new Set(['general']))
const isSaving = ref(false)
const saveError = ref('')
const expandedComponents = ref(new Set<number>())
let keyCounter = 0

const manufacturerOptions = ref<TemplateManufacturerOption[]>([])
const loadingManufacturerOptions = ref(false)

// Form-Daten
const form = reactive({
  name: '',
  description: null as string | null,
  manufacturer: null as string | null,
  manufacturer_address_id: null as string | null,
  template_kind: null as TemplateKind | null,
  template_domain: null as TemplateDomain | null,
  model: null as string | null,
  material_type: 'physical_combo' as 'physical_combo' | 'virtual_combo',
  tent_type: null as string | null,
  capacity: null as number | null,
  is_active: true,
  source: null as string | null,
  components: [] as ComponentForm[],
  accessories: [] as AccessoryForm[],
  optionGroups: [] as TemplateOptionGroupForm[],
  options: [] as TemplateOptionForm[],
})

const isSinglePart = computed(
  () => form.template_kind === 'single_part' || form.components.length === 1,
)
const isSinglePartLocked = computed(() => form.template_kind === 'single_part')

const templateKindDisplay = computed(() => {
  switch (form.template_kind) {
    case 'single_part':
      return t('components.templateStartWizard.kindSinglePart')
    case 'combo':
      return t('components.templateStartWizard.kindCombo')
    case 'configurator':
      return t('components.templateStartWizard.kindConfigurator')
    default:
      return ''
  }
})

const templateDomainDisplay = computed(() => {
  switch (form.template_domain) {
    case 'tent':
      return t('components.templateStartWizard.domainTent')
    case 'kitchen':
      return t('components.templateStartWizard.domainKitchen')
    case 'workshop':
      return t('components.templateStartWizard.domainWorkshop')
    case 'first_aid':
      return t('components.templateStartWizard.domainFirstAid')
    case 'general':
      return t('components.templateStartWizard.domainGeneral')
    default:
      return ''
  }
})

const singlePartTrackingDefaultHint = computed(() => {
  if (form.template_domain === 'tent') {
    return t('components.templateEditDialog.singlePartTrackingDefaultSerialized')
  }
  return t('components.templateEditDialog.singlePartTrackingDefaultBulk')
})

function syncMaterialTypeFromKind() {
  if (form.template_kind === 'configurator') {
    form.material_type = 'virtual_combo'
  } else if (form.template_kind === 'single_part' || form.template_kind === 'combo') {
    form.material_type = 'physical_combo'
  }
}

watch(() => form.template_kind, () => syncMaterialTypeFromKind())

const tabs = computed(() => {
  const ids: string[] = ['general']
  if (form.template_domain === 'tent') ids.push('tent')
  ids.push('components', 'accessories')
  if (form.material_type === 'virtual_combo') ids.push('configurator')
  return ids.map((id) => ({
    id,
    label: t(`components.templateEditDialog.tab.${id}`),
  }))
})

function syncManufacturerLabel() {
  const selected = manufacturerOptions.value.find((o) => o.id === form.manufacturer_address_id)
  form.manufacturer = selected?.label ?? null
}

async function loadManufacturerOptions() {
  loadingManufacturerOptions.value = true
  try {
    manufacturerOptions.value = await getTemplateManufacturerOptions(
      props.templateScope,
      props.templateScope === 'department' ? props.departmentId : undefined,
    )
    syncManufacturerLabel()
  } catch (err) {
    console.error(t('components.templateEditDialog.errorLoadManufacturerOptions'), err)
  } finally {
    loadingManufacturerOptions.value = false
  }
}

function applyInitialWizard() {
  const w = props.initialWizard
  if (!w) return

  form.template_kind = w.template_kind
  form.template_domain = w.template_domain
  form.manufacturer_address_id = w.manufacturer_address_id
  form.manufacturer = w.manufacturer
  form.material_type = w.material_type
  syncMaterialTypeFromKind()
  syncManufacturerLabel()

  if (w.template_kind === 'single_part' && form.components.length === 0) {
    addComponent()
  }

  if (!w.manufacturer_address_id) {
    for (const c of form.components) {
      c.is_generic = true
    }
  }
}

watch(activeTab, (tab) => {
  visitedTabs.value = new Set([...visitedTabs.value, tab])
})

const missingTabs = computed(() => tabs.value.filter((tab) => !visitedTabs.value.has(tab.id)))

const missingTabLabels = computed(() => missingTabs.value.map((tab) => tab.label).join(', '))

const isValid = computed(() => form.name.trim().length > 0)

const canSubmit = computed(() => {
  if (!isValid.value || props.readonly) return false
  if (isEditing.value) return true
  return missingTabs.value.length === 0
})

// Komponenten-Verwaltung
function defaultComponentTracking(): 'serialized' | 'bulk' {
  // Einzelteil: Zelte oft SN, Küche/Werkstatt/Allgemein typischerweise Bulk (Menge)
  if (form.template_kind === 'single_part') {
    return form.template_domain === 'tent' ? 'serialized' : 'bulk'
  }
  return 'serialized'
}

function isComponentExpanded(index: number): boolean {
  return isSinglePartLocked.value || expandedComponents.value.has(index)
}

function addComponent() {
  const newComp: ComponentForm = {
    __key: ++keyCounter,
    component_type: '',
    name: '',
    required_qty: 1,
    is_optional: false,
    is_generic: false,
    tracking: defaultComponentTracking(),
    component_source: 'stock',
    repair_types: null,
    _repairTypesStr: '',
    sort_order: form.components.length,
  }
  form.components.push(newComp)
  expandedComponents.value.add(form.components.length - 1)
}

function removeComponent(index: number) {
  form.components.splice(index, 1)
  // Expand-Set aktualisieren
  const newSet = new Set<number>()
  expandedComponents.value.forEach(i => {
    if (i < index) newSet.add(i)
    else if (i > index) newSet.add(i - 1)
  })
  expandedComponents.value = newSet
}

function toggleComponent(index: number) {
  if (expandedComponents.value.has(index)) {
    expandedComponents.value.delete(index)
  } else {
    expandedComponents.value.add(index)
  }
}

// Verwandtes Zubehör (Empfehlung, kein Stücklisten-Teil)
function addAccessory() {
  form.accessories.push({
    __key: ++keyCounter,
    name: '',
    component_type: '',
    is_generic: false,
    sort_order: form.accessories.length,
  })
}

function removeAccessory(index: number) {
  form.accessories.splice(index, 1)
}

// Speichern
async function save() {
  if (!canSubmit.value) return
  isSaving.value = true
  saveError.value = ''

  try {
    const components: CreateTemplateComponentRequest[] = form.components.map((c, i) => ({
      component_type: c.component_type || c.name.toLowerCase().replace(/\s+/g, '_'),
      name: c.name,
      required_qty: c.required_qty,
      is_optional: c.is_optional,
      is_generic: c.is_generic,
      tracking: c.tracking,
      component_source: c.component_source,
      repair_types: c._repairTypesStr
        ? c._repairTypesStr.split(',').map(s => s.trim()).filter(Boolean)
        : undefined,
      sort_order: i,
    }))

    const related_accessories: CreateTemplateRelatedAccessoryRequest[] = form.accessories
      .filter((a) => a.name.trim().length > 0)
      .map((a, i) => ({
        name: a.name.trim(),
        component_type: a.component_type.trim() || null,
        is_generic: a.is_generic,
        sort_order: i,
      }))

    // Konfigurator-Optionen (generisch, nur bei virtueller Kombo). __key dient als temp-Bezug.
    const isCombo = form.material_type === 'virtual_combo'
    const option_groups: UpsertTemplateOptionGroupRequest[] | undefined = isCombo
      ? form.optionGroups.map((g, i) => ({
          id: `tmp-${g.__key}`,
          name: g.name.trim() || `Gruppe ${i + 1}`,
          selection_type: g.selection_type,
          min_select: g.min_select,
          max_select: g.max_select,
          sort_order: i,
        }))
      : undefined
    const options: UpsertTemplateOptionRequest[] | undefined = isCombo
      ? form.options.map((o, i) => ({
          name: o.name.trim() || `Option ${i + 1}`,
          display_mode: o.option_group_key === null ? 'toggle' : 'group',
          default_selected: o.default_selected,
          option_group_id: o.option_group_key === null ? null : `tmp-${o.option_group_key}`,
          sort_order: i,
          deltas: o.deltas.map((d, j) => ({
            component_type: d.component_type,
            name: d.name,
            qty_delta: d.qty_delta,
            tracking: d.tracking,
            component_source: d.component_source,
            is_generic: d.is_generic,
            sort_order: j,
          })),
        }))
      : undefined

    if (isEditing.value && props.template) {
      const data: UpdateTemplateRequest = {
        name: form.name,
        description: form.description,
        manufacturer: form.manufacturer,
        manufacturer_address_id: form.manufacturer_address_id,
        template_kind: form.template_kind,
        template_domain: form.template_domain,
        model: form.model,
        material_type: form.material_type,
        tent_type: form.tent_type,
        capacity: form.capacity,
        is_active: form.is_active,
        source: form.source,
        components,
        related_accessories,
        option_groups,
        options,
      }
      await updateTemplate(props.template.id, data)
    } else {
      const data: CreateTemplateRequest = {
        name: form.name,
        description: form.description,
        manufacturer: form.manufacturer,
        manufacturer_address_id: form.manufacturer_address_id,
        template_kind: form.template_kind,
        template_domain: form.template_domain,
        model: form.model,
        material_type: form.material_type,
        tent_type: form.tent_type,
        capacity: form.capacity,
        is_active: form.is_active,
        source: form.source,
        components,
        related_accessories,
        option_groups,
        options,
      }
      if (props.templateScope === 'global') {
        data.scope = 'global'
      } else {
        data.department_id = props.departmentId
      }
      await createTemplate(data)
    }

    emit('saved')
  } catch (err: any) {
    const msg = err.response?.data?.error || err.message || t('components.templateEditDialog.errorSave')
    saveError.value = msg
    toast.error(msg)
  } finally {
    isSaving.value = false
  }
}

// Template-Daten laden (bei Bearbeitung)
async function loadTemplate() {
  if (!props.template) return

  // Basis-Daten aus der Liste oder dem Duplikat
  form.name = props.template.name
  form.description = props.template.description
  form.manufacturer = props.template.manufacturer
  form.manufacturer_address_id = props.template.manufacturer_address_id
  form.template_kind = props.template.template_kind
  form.template_domain = props.template.template_domain
  form.model = props.template.model
  form.material_type = props.template.material_type
  syncMaterialTypeFromKind()
  form.tent_type = props.template.tent_type
  form.capacity = props.template.capacity
  form.is_active = props.template.is_active
  form.source = props.template.source

  // Wenn ID vorhanden: Vollständige Daten inkl. Komponenten vom Server laden
  if (props.template.id) {
    try {
      const full = await getTemplate(props.template.id)
      form.manufacturer = full.manufacturer
      form.manufacturer_address_id = full.manufacturer_address_id
      form.template_kind = full.template_kind
      form.template_domain = full.template_domain
      syncMaterialTypeFromKind()
      if (full.components) {
        form.components = full.components.map(c => ({
          __key: ++keyCounter,
          component_type: c.component_type,
          name: c.name,
          required_qty: c.required_qty,
          is_optional: c.is_optional,
          is_generic: c.is_generic ?? false,
          tracking: c.tracking,
          component_source: c.component_source ?? 'stock',
          repair_types: c.repair_types,
          _repairTypesStr: c.repair_types ? c.repair_types.join(', ') : '',
          sort_order: c.sort_order,
        }))
      }
      if (full.related_accessories) {
        form.accessories = full.related_accessories.map((a) => ({
          __key: ++keyCounter,
          name: a.name,
          component_type: a.component_type ?? '',
          is_generic: a.is_generic ?? false,
          sort_order: a.sort_order,
        }))
      }
      // Konfigurator-Optionen laden: reale Gruppen-IDs auf lokale __key abbilden.
      if (full.option_groups && full.option_groups.length > 0) {
        const groupKeyById = new Map<string, number>()
        form.optionGroups = [...full.option_groups]
          .sort((a, b) => a.sort_order - b.sort_order)
          .map((g) => {
            const key = ++keyCounter
            groupKeyById.set(g.id, key)
            return {
              __key: key,
              name: g.name,
              selection_type: g.selection_type,
              min_select: g.min_select,
              max_select: g.max_select,
            }
          })
        form.options = (full.options ?? [])
          .slice()
          .sort((a, b) => a.sort_order - b.sort_order)
          .map((o) => ({
            __key: ++keyCounter,
            name: o.name,
            default_selected: o.default_selected,
            option_group_key: o.option_group_id ? (groupKeyById.get(o.option_group_id) ?? null) : null,
            deltas: (o.deltas ?? []).map((d) => ({
              name: d.name,
              component_type: d.component_type,
              qty_delta: d.qty_delta,
              tracking: d.tracking,
              component_source: d.component_source ?? 'stock',
              is_generic: d.is_generic ?? true,
            })),
          }))
      } else if (full.options && full.options.length > 0) {
        // Nur Toggle-Optionen ohne Gruppen.
        form.options = full.options
          .slice()
          .sort((a, b) => a.sort_order - b.sort_order)
          .map((o) => ({
            __key: ++keyCounter,
            name: o.name,
            default_selected: o.default_selected,
            option_group_key: null,
            deltas: (o.deltas ?? []).map((d) => ({
              name: d.name,
              component_type: d.component_type,
              qty_delta: d.qty_delta,
              tracking: d.tracking,
              component_source: d.component_source ?? 'stock',
              is_generic: d.is_generic ?? true,
            })),
          }))
      }
    } catch (err) {
      console.error(t('components.templateEditDialog.errorLoadConsole'), err)
    }
  }
}

onMounted(async () => {
  await loadManufacturerOptions()
  if (props.initialWizard && !props.template) {
    applyInitialWizard()
  } else {
    await loadTemplate()
  }
})
</script>

<style scoped>
/* Modal overlay + structured layout: shared ui/modals.css */

/* Header badges */
.kind-badge {
  padding: 4px 10px;
  background: var(--color-primary-muted-bg);
  color: var(--color-primary-dark);
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

.kind-readonly {
  margin: 0;
  padding: 10px 12px;
  background: var(--color-primary-muted-bg);
  border: 1px solid var(--color-primary-muted-border);
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-primary-dark);
}

.readonly-badge {
  padding: 4px 12px;
  background: #fef3c7;
  color: #92400e;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

/* Body tabs */
.tabs {
  display: flex;
  border-bottom: 1px solid #e5e7eb;
  padding: 0 24px;
  gap: 0;
  flex-shrink: 0;
}

.tab {
  padding: 14px 20px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}

.tab:hover {
  color: #374151;
}

.tab.active {
  color: var(--color-primary);
  border-bottom-color: var(--color-primary);
}

.tab-unvisited:not(.active) {
  color: #9ca3af;
}

.tab-visit-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #f59e0b;
  flex-shrink: 0;
}

.tab-count {
  background: var(--color-primary-muted-bg);
  color: var(--color-primary);
  padding: 1px 8px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
}

/* Tab Content */
.tab-content {
  padding: 24px;
}

/* Form Grid */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

/* Form group/input/select/textarea: shared ui/forms.css */

.form-group.full {
  grid-column: 1 / -1;
}

.form-label.required::after {
  content: ' *';
  color: #dc2626;
}

.form-textarea {
  resize: vertical;
  min-height: 60px;
}

/* Toggle */
.toggle-group {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-top: 4px;
}

.toggle {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
}

.toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: #d1d5db;
  border-radius: 12px;
  transition: all 0.2s;
}

.toggle-slider::before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  left: 3px;
  bottom: 3px;
  background: white;
  border-radius: 50%;
  transition: all 0.2s;
}

.toggle input:checked + .toggle-slider {
  background: var(--color-primary);
}

.toggle input:checked + .toggle-slider::before {
  transform: translateX(20px);
}

.toggle-label {
  font-size: 14px;
  color: #374151;
}

/* Checkbox */
.checkbox-group {
  justify-content: center;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #374151;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--color-primary);
}

.optional-hint,
.single-part-hint {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 12px;
  padding: 10px 12px;
  background: #f9fafb;
  border-radius: 6px;
  border-left: 3px solid var(--color-primary);
}

.single-part-hint {
  border-left-color: var(--color-primary-dark);
}

.single-part-card .component-body {
  display: block;
}

.single-part-card .component-header {
  border-bottom: none;
  padding-bottom: 0;
}

.single-part-tracking-section {
  margin-bottom: 16px;
}

.tracking-domain-hint {
  margin: 4px 0 10px;
}

.tracking-choice-bar {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.tracking-choice-btn {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: white;
  cursor: pointer;
  text-align: left;
}

.tracking-choice-btn strong {
  font-size: 14px;
  color: #111827;
}

.tracking-choice-btn span {
  font-size: 12px;
  color: #6b7280;
  line-height: 1.35;
}

.tracking-choice-btn.active {
  border-color: var(--color-primary);
  background: var(--color-primary-muted-bg);
}

.tracking-choice-btn.active strong {
  color: var(--color-primary-dark);
}

/* Components */
.components-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.components-info {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

.btn-add-component {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: var(--color-primary-muted-bg);
  color: var(--color-primary);
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-add-component:hover {
  background: var(--color-primary-subtle-bg);
}

.empty-components {
  text-align: center;
  padding: 40px 20px;
  color: #9ca3af;
  font-size: 14px;
}

.components-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.component-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: white;
  overflow: hidden;
  transition: box-shadow 0.2s;
}

.component-card:hover {
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.component-card.dragging {
  opacity: 0.5;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.component-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  background: #f9fafb;
}

.drag-handle {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: grab;
  padding: 4px;
  display: flex;
  align-items: center;
}

.drag-handle:active {
  cursor: grabbing;
}

.component-number {
  font-size: 12px;
  font-weight: 600;
  color: #9ca3af;
  min-width: 24px;
}

.component-title {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  flex: 1;
}

.component-badges {
  display: flex;
  gap: 4px;
}

.comp-badge {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 500;
}

.comp-badge.serialized {
  background: #dbeafe;
  color: #1d4ed8;
}

.comp-badge.bulk {
  background: #fef3c7;
  color: #92400e;
}

.comp-badge.optional {
  background: #f3f4f6;
  color: #6b7280;
}

.comp-badge.generic {
  background: #ecfdf5;
  color: #059669;
  font-size: 0.75rem;
  padding: 1px 5px;
  cursor: help;
}

.component-toggle {
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  padding: 4px;
  transition: transform 0.2s;
}

.component-toggle.expanded {
  transform: rotate(180deg);
}

.btn-remove-component {
  background: none;
  border: none;
  color: #d1d5db;
  cursor: pointer;
  padding: 4px;
  transition: color 0.2s;
}

.btn-remove-component:hover {
  color: #dc2626;
}

.component-body {
  padding: 16px;
  border-top: 1px solid #f3f4f6;
}

.comp-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

/* Slide transition */
.slide-enter-active,
.slide-leave-active {
  transition: all 0.2s ease;
  overflow: hidden;
}

.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  max-height: 0;
  padding-top: 0;
  padding-bottom: 0;
}

.slide-enter-to,
.slide-leave-from {
  opacity: 1;
  max-height: 500px;
}

.modal-footer {
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.footer-left {
  flex: 1;
  min-width: 0;
}

.footer-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}

.tabs-todo-hint {
  margin: 4px 0 0;
  font-size: 12px;
  color: #92400e;
  line-height: 1.4;
}

.save-error {
  flex: 1;
  font-size: 13px;
  color: #dc2626;
}

/* Buttons use shared ui/buttons.css */
</style>
