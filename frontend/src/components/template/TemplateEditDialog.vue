<template>
  <div class="modal-overlay">
    <div class="edit-dialog">
      <!-- Header -->
      <div class="dialog-header">
        <h2>{{ props.readonly ? 'Vorlage ansehen' : (isEditing ? 'Vorlage bearbeiten' : 'Neue Vorlage') }}</h2>
        <span v-if="props.readonly" class="readonly-badge">Nur Lesen</span>
        <button class="close-btn" @click="$emit('close')">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="dialog-body">
        <!-- Tabs -->
        <div class="tabs">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            class="tab"
            :class="{ active: activeTab === tab.id }"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
            <span v-if="tab.id === 'components' && form.components.length > 0" class="tab-count">
              {{ form.components.length }}
            </span>
          </button>
        </div>

        <!-- Tab: Allgemein -->
        <div v-show="activeTab === 'general'" class="tab-content">
          <div class="form-grid">
            <div class="form-group full">
              <label class="form-label required">Name</label>
              <input v-model="form.name" type="text" class="form-input" placeholder="z.B. Spatz Zelt 6P" :disabled="props.readonly" />
            </div>

            <div class="form-group">
              <label class="form-label">Hersteller</label>
              <input v-model="form.manufacturer" type="text" class="form-input" placeholder="z.B. Spatz, hajk" :disabled="props.readonly" />
            </div>

            <div class="form-group">
              <label class="form-label">Modell</label>
              <input v-model="form.model" type="text" class="form-input" placeholder="z.B. Group 6" :disabled="props.readonly" />
            </div>

            <div class="form-group full">
              <label class="form-label">Beschreibung</label>
              <textarea v-model="form.description" class="form-textarea" rows="2" placeholder="Optionale Beschreibung" :disabled="props.readonly"></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Material-Typ</label>
              <select v-model="form.material_type" class="form-select" :disabled="props.readonly">
                <option value="physical_combo">Physische Combo (Feste Einheit)</option>
                <option value="virtual_combo">Virtuelle Combo (Flexibel)</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Status</label>
              <div class="toggle-group">
                <label class="toggle">
                  <input type="checkbox" v-model="form.is_active" :disabled="props.readonly" />
                  <span class="toggle-slider"></span>
                </label>
                <span class="toggle-label">{{ form.is_active ? 'Aktiv' : 'Inaktiv' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab: Zelt-Details -->
        <div v-show="activeTab === 'tent'" class="tab-content">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Zelttyp</label>
              <select v-model="form.tent_type" class="form-select">
                <option :value="null">– Keiner –</option>
                <option value="gruppenzelt">Gruppenzelt</option>
                <option value="sonstiges">Sonstiges</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Kapazität (Personen)</label>
              <input v-model.number="form.capacity" type="number" class="form-input" placeholder="z.B. 6" min="1" />
            </div>

            <div class="form-group">
              <label class="form-label">Reservierungsmodus</label>
              <select v-model="form.reservation_mode" class="form-select">
                <option :value="null">– Standard –</option>
                <option value="complete_only">Nur komplett</option>
                <option value="individual">Einzeln</option>
                <option value="flexible">Flexibel</option>
              </select>
              <span class="form-hint">
                <template v-if="form.reservation_mode === 'complete_only'">Das Zelt kann nur als Ganzes reserviert werden.</template>
                <template v-else-if="form.reservation_mode === 'individual'">Einzelkomponenten können separat reserviert werden.</template>
                <template v-else-if="form.reservation_mode === 'flexible'">Beides möglich – komplett oder einzeln.</template>
              </span>
            </div>

            <div class="form-group">
              <label class="form-label">Quelle</label>
              <input v-model="form.source" type="text" class="form-input" placeholder="z.B. Hersteller, Intern" />
            </div>
          </div>
        </div>

        <!-- Tab: Komponenten -->
        <div v-show="activeTab === 'components'" class="tab-content">
          <div class="components-header">
            <p class="components-info">
              Definieren Sie die Komponenten, die zu dieser Vorlage gehören.
            </p>
            <button v-if="!props.readonly" class="btn-add-component" @click="addComponent">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              Komponente hinzufügen
            </button>
          </div>

          <div v-if="form.components.length === 0" class="empty-components">
            <p>Noch keine Komponenten definiert.</p>
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
              <div class="component-card">
                <div class="component-header">
                  <button class="drag-handle" title="Ziehen zum Sortieren">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/>
                    </svg>
                  </button>
                  <span class="component-number">#{{ index + 1 }}</span>
                  <span class="component-title">{{ element.name || 'Neue Komponente' }}</span>
                  <div class="component-badges">
                    <span class="comp-badge" :class="element.tracking">{{ element.tracking === 'serialized' ? 'SN' : 'Bulk' }}</span>
                    <span v-if="element.is_generic" class="comp-badge generic" title="Übergreifendes Material – Name bleibt generisch">🌐</span>
                    <span v-if="element.is_optional" class="comp-badge optional">Optional</span>
                  </div>
                  <button class="component-toggle" :class="{ expanded: expandedComponents.has(index) }" @click="toggleComponent(index)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="6 9 12 15 18 9"/>
                    </svg>
                  </button>
                  <button v-if="!props.readonly" class="btn-remove-component" @click="removeComponent(index)" title="Entfernen">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                  </button>
                </div>

                <transition name="slide">
                  <div v-if="expandedComponents.has(index)" class="component-body">
                    <div class="comp-form-grid">
                      <div class="form-group">
                        <label class="form-label required">Name</label>
                        <input v-model="element.name" type="text" class="form-input" placeholder="z.B. Aussenzelt" />
                      </div>
                      <div class="form-group">
                        <label class="form-label">Typ</label>
                        <input v-model="element.component_type" type="text" class="form-input" placeholder="z.B. aussenzelt, innenzelt, heringe" />
                      </div>
                      <div class="form-group">
                        <label class="form-label">Anzahl</label>
                        <input v-model.number="element.required_qty" type="number" class="form-input" min="1" />
                      </div>
                      <div class="form-group">
                        <label class="form-label">Tracking</label>
                        <select v-model="element.tracking" class="form-select">
                          <option value="serialized">Serialisiert (Seriennummer)</option>
                          <option value="bulk">Bulk (Mengenverwaltung)</option>
                        </select>
                      </div>
                      <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                          <input type="checkbox" v-model="element.is_optional" />
                          <span>Optional (nicht zwingend erforderlich)</span>
                        </label>
                      </div>
                      <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                          <input type="checkbox" v-model="element.is_generic" />
                          <span>Übergreifendes Material (Name bleibt generisch, z.B. "Heringe" statt "Heringe Phoenix")</span>
                        </label>
                      </div>
                      <div class="form-group full">
                        <label class="form-label">Reparaturtypen</label>
                        <input v-model="element._repairTypesStr" type="text" class="form-input" placeholder="z.B. loch, riss, abspannung (kommagetrennt)" />
                        <span class="form-hint">Kommagetrennte Liste möglicher Reparaturtypen</span>
                      </div>
                    </div>
                  </div>
                </transition>
              </div>
            </template>
          </draggable>
        </div>
      </div>

      <!-- Footer -->
      <div class="dialog-footer">
        <div v-if="saveError" class="save-error">{{ saveError }}</div>
        <button class="btn-secondary" @click="$emit('close')">{{ props.readonly ? 'Schliessen' : 'Abbrechen' }}</button>
        <button v-if="!props.readonly" class="btn-primary" @click="save" :disabled="isSaving || !isValid">
          {{ isSaving ? 'Wird gespeichert...' : (isEditing ? 'Speichern' : 'Erstellen') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useToast } from '@/composables/useToast'
import draggable from 'vuedraggable'
import {
  getTemplate,
  createTemplate,
  updateTemplate,
  type Template,
  type CreateTemplateRequest,
  type UpdateTemplateRequest,
  type CreateTemplateComponentRequest
} from '@/api/templates'

interface ComponentForm {
  __key: number
  component_type: string
  name: string
  required_qty: number
  is_optional: boolean
  is_generic: boolean
  tracking: 'serialized' | 'bulk'
  repair_types: string[] | null
  _repairTypesStr: string
  sort_order: number
}

const props = defineProps<{
  departmentId: string
  template: Template | null
  readonly?: boolean
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const toast = useToast()
const isEditing = computed(() => !!props.template && !!props.template.id)
const activeTab = ref('general')
const isSaving = ref(false)
const saveError = ref('')
const expandedComponents = ref(new Set<number>())
let keyCounter = 0

const tabs = [
  { id: 'general', label: 'Allgemein' },
  { id: 'tent', label: 'Zelt-Details' },
  { id: 'components', label: 'Komponenten' },
]

// Form-Daten
const form = reactive({
  name: '',
  description: null as string | null,
  manufacturer: null as string | null,
  model: null as string | null,
  material_type: 'physical_combo' as 'physical_combo' | 'virtual_combo',
  tent_type: null as string | null,
  capacity: null as number | null,
  reservation_mode: null as string | null,
  is_active: true,
  source: null as string | null,
  components: [] as ComponentForm[],
})

const isValid = computed(() => {
  return form.name.trim().length > 0
})

// Komponenten-Verwaltung
function addComponent() {
  const newComp: ComponentForm = {
    __key: ++keyCounter,
    component_type: '',
    name: '',
    required_qty: 1,
    is_optional: false,
    is_generic: false,
    tracking: 'serialized',
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

// Speichern
async function save() {
  if (!isValid.value) return
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
      repair_types: c._repairTypesStr
        ? c._repairTypesStr.split(',').map(s => s.trim()).filter(Boolean)
        : undefined,
      sort_order: i,
    }))

    if (isEditing.value && props.template) {
      const data: UpdateTemplateRequest = {
        name: form.name,
        description: form.description,
        manufacturer: form.manufacturer,
        model: form.model,
        material_type: form.material_type,
        tent_type: form.tent_type,
        capacity: form.capacity,
        reservation_mode: form.reservation_mode,
        is_active: form.is_active,
        source: form.source,
        components,
      }
      await updateTemplate(props.template.id, data)
    } else {
      const data: CreateTemplateRequest = {
        department_id: props.departmentId,
        name: form.name,
        description: form.description,
        manufacturer: form.manufacturer,
        model: form.model,
        material_type: form.material_type,
        tent_type: form.tent_type,
        capacity: form.capacity,
        reservation_mode: form.reservation_mode,
        is_active: form.is_active,
        source: form.source,
        components,
      }
      await createTemplate(data)
    }

    emit('saved')
  } catch (err: any) {
    const msg = err.response?.data?.error || err.message || 'Fehler beim Speichern'
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
  form.model = props.template.model
  form.material_type = props.template.material_type
  form.tent_type = props.template.tent_type
  form.capacity = props.template.capacity
  form.reservation_mode = props.template.reservation_mode
  form.is_active = props.template.is_active
  form.source = props.template.source

  // Wenn ID vorhanden: Vollständige Daten inkl. Komponenten vom Server laden
  if (props.template.id) {
    try {
      const full = await getTemplate(props.template.id)
      if (full.components) {
        form.components = full.components.map(c => ({
          __key: ++keyCounter,
          component_type: c.component_type,
          name: c.name,
          required_qty: c.required_qty,
          is_optional: c.is_optional,
          is_generic: c.is_generic ?? false,
          tracking: c.tracking,
          repair_types: c.repair_types,
          _repairTypesStr: c.repair_types ? c.repair_types.join(', ') : '',
          sort_order: c.sort_order,
        }))
      }
    } catch (err) {
      console.error('Fehler beim Laden der Vorlage:', err)
    }
  }
}

onMounted(() => {
  loadTemplate()
})
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
}

.edit-dialog {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 720px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

/* Header */
.dialog-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.dialog-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.readonly-badge {
  padding: 4px 12px;
  background: #fef3c7;
  color: #92400e;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

.close-btn {
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
}

.close-btn:hover {
  background: #f3f4f6;
  color: #374151;
}

/* Body */
.dialog-body {
  flex: 1;
  overflow-y: auto;
  padding: 0;
}

/* Tabs */
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
  color: #7c3aed;
  border-bottom-color: #7c3aed;
}

.tab-count {
  background: #ede9fe;
  color: #7c3aed;
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

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group.full {
  grid-column: 1 / -1;
}

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.form-label.required::after {
  content: ' *';
  color: #dc2626;
}

.form-input,
.form-select,
.form-textarea {
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  color: #111827;
  transition: all 0.2s;
  background: white;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #8b5cf6;
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 60px;
}

.form-hint {
  font-size: 12px;
  color: #9ca3af;
  min-height: 16px;
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
  background: #7c3aed;
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
  accent-color: #7c3aed;
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
  background: #ede9fe;
  color: #7c3aed;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-add-component:hover {
  background: #ddd6fe;
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

/* Footer */
.dialog-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.save-error {
  flex: 1;
  font-size: 13px;
  color: #dc2626;
}

.btn-primary {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #7c3aed 0%, #9333ea 100%);
  box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-secondary {
  padding: 10px 18px;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background: #f9fafb;
  border-color: #9ca3af;
}
</style>
