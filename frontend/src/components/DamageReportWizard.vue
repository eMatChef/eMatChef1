<template>
  <Teleport to="body">
    <div v-if="isOpen" class="damage-wizard-overlay">
      <div class="damage-wizard-modal">
        <div class="wizard-header">
          <h2>Schaden melden</h2>
          <button class="close-btn" @click="close" title="Schliessen">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <div class="wizard-body">
          <!-- Step 1: Aktivität wählen -->
          <div v-if="step === 1" class="wizard-step">
            <h3>1. Aktivität wählen</h3>
            <p class="step-hint">Wähle eine Aktivität von dir oder deiner Gruppe</p>
            <button class="no-activity-btn" @click="startWithoutActivity">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
              </svg>
              Ohne Aktivitätsbezug melden
            </button>
            <div v-if="isLoadingActivities" class="loading-row">
              <div class="spinner"></div>
              <span>Aktivitäten werden geladen...</span>
            </div>
            <div v-else-if="selectableActivities.length === 0" class="empty-state">
              <p>Keine Aktivitäten im Status «Ausgegeben» oder «Retour» gefunden.</p>
              <p class="hint">Schaden kann nur bei ausgegebenen Aktivitäten gemeldet werden.</p>
            </div>
            <div v-else class="activity-list">
              <button
                v-for="a in selectableActivities"
                :key="a.id"
                class="activity-option"
                :class="{ selected: selectedActivity?.id === a.id }"
                @click="selectActivity(a)"
              >
                <span class="activity-name">{{ a.name }}</span>
                <span class="activity-meta">{{ a.group_name || a.customer_name || '–' }} · {{ formatDateShort(a.usage_start) }}</span>
              </button>
            </div>
          </div>

          <!-- Step 2a: Mit Aktivität – Material + Details -->
          <div v-else-if="step === 2 && mode === 'with_activity' && selectedActivity" class="wizard-step">
            <h3>2. Schaden erfassen</h3>
            <p class="step-hint">Aktivität: {{ selectedActivity.name }}</p>
            <div v-if="isLoadingPackItems" class="loading-row">
              <div class="spinner"></div>
              <span>Material wird geladen...</span>
            </div>
            <div v-else class="form-fields">
              <div class="form-group">
                <label>Material <span class="required">*</span></label>
                <select v-model="form.materialItemId" class="form-select" required>
                  <option value="">– Material wählen –</option>
                  <option v-for="pi in packItems" :key="pi.material_item_id" :value="pi.material_item_id">
                    {{ pi.material_name }} ({{ pi.quantity_issued ?? pi.quantity_packed }} Stk.)
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Art <span class="required">*</span></label>
                <select v-model="form.type" class="form-select">
                  <option value="damage">Schaden</option>
                  <option value="repair">Reparatur</option>
                  <option value="loss">Verlust</option>
                </select>
              </div>
              <div class="form-group">
                <label>Menge</label>
                <input v-model.number="form.quantity" type="number" min="1" class="form-input" />
              </div>
              <div class="form-group">
                <label>Beschreibung</label>
                <textarea v-model="form.description" rows="3" class="form-input" placeholder="Was ist passiert?"></textarea>
              </div>
            </div>
          </div>

          <!-- Step 2b: Ohne Aktivität – Material + Titel -->
          <div v-else-if="step === 2 && mode === 'no_activity'" class="wizard-step">
            <h3>Schaden erfassen (ohne Aktivität)</h3>
            <p class="step-hint">Werkstatt-Ticket wird direkt erstellt</p>
            <div class="form-fields">
              <div class="form-group">
                <label>Material <span class="required">*</span></label>
                <div class="mat-search-wrap">
                  <div v-if="selectedMaterial" class="mat-selected">
                    <span>{{ selectedMaterial.name }}</span>
                    <button type="button" class="mat-clear" @click="selectedMaterial = null">×</button>
                  </div>
                  <div v-else>
                    <input
                      v-model="formNoActivity.matSearch"
                      type="text"
                      class="form-input"
                      placeholder="Material suchen (min. 2 Zeichen)..."
                      @input="onMatSearchInput"
                    />
                    <div v-if="formNoActivity.matSearch.length >= 2 && matSearchResults.length > 0" class="mat-dropdown">
                      <button
                        v-for="m in matSearchResults"
                        :key="m.id"
                        type="button"
                        class="mat-dropdown-item"
                        @mousedown.prevent="selectMaterial(m)"
                      >
                        {{ m.name }}
                        <span v-if="m.category" class="mat-cat">{{ m.category.name }}</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label>Titel / Kurzbeschreibung <span class="required">*</span></label>
                <input v-model="formNoActivity.title" type="text" class="form-input" placeholder="z.B. Riss im Zeltdach" />
              </div>
              <div class="form-group">
                <label>Typ</label>
                <select v-model="formNoActivity.type" class="form-select">
                  <option value="repair">Reparatur</option>
                  <option value="inspection">Inspektion</option>
                  <option value="writeoff">Abschreibung</option>
                  <option value="cleaning">Reinigung</option>
                </select>
              </div>
              <div class="form-group">
                <label>Beschreibung</label>
                <textarea v-model="formNoActivity.description" rows="3" class="form-input" placeholder="Details zum Problem..."></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="wizard-footer">
          <button v-if="step === 2" class="btn btn-outline" @click="goBack">Zurück</button>
          <div class="spacer"></div>
          <button v-if="step === 1" class="btn btn-primary" :disabled="!selectedActivity" @click="advanceToStep2">
            Weiter
          </button>
          <button v-else-if="mode === 'no_activity'" class="btn btn-primary" :disabled="!canSubmitNoActivity || isSubmitting" @click="submitNoActivity">
            {{ isSubmitting ? 'Wird gesendet...' : 'Ticket erstellen' }}
          </button>
          <button v-else class="btn btn-primary" :disabled="!canSubmit || isSubmitting" @click="submit">
            {{ isSubmitting ? 'Wird gesendet...' : 'Schaden melden' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import apiClient from '@/api/apiClient'
import { getGroups, type Group } from '@/api/groups'
import { getMaterials, type Material } from '@/api/materials'
import { createWorkshopTicket } from '@/api/workshop'

interface ActivityOption {
  id: string
  name: string
  status?: string
  group_id?: string | null
  group_name?: string | null
  customer_name?: string | null
  usage_start?: string | null
  created_by_user_id?: string | null
}

interface PackItem {
  material_item_id: string
  material_name: string
  quantity_packed: number
  quantity_issued?: number
}

const props = defineProps<{
  isOpen: boolean
  departmentId: string
}>()

const emit = defineEmits<{
  close: []
  success: []
}>()

const authStore = useAuthStore()
const toast = useToast()
const userId = computed(() => authStore.userId)

const step = ref(1)
const mode = ref<'with_activity' | 'no_activity'>('with_activity')
const activities = ref<ActivityOption[]>([])
const groups = ref<Group[]>([])
const isLoadingActivities = ref(false)
const selectedActivity = ref<ActivityOption | null>(null)
const packItems = ref<PackItem[]>([])
const isLoadingPackItems = ref(false)
const isSubmitting = ref(false)

const selectedMaterial = ref<Material | null>(null)
const matSearchResults = ref<Material[]>([])
let matSearchTimer: ReturnType<typeof setTimeout> | null = null

const form = ref({
  materialItemId: '',
  type: 'damage' as 'damage' | 'repair' | 'loss',
  quantity: 1,
  description: ''
})

const formNoActivity = ref({
  matSearch: '',
  title: '',
  type: 'repair' as 'repair' | 'inspection' | 'writeoff' | 'cleaning',
  description: ''
})

const userGroupIds = computed(() => {
  const uid = userId.value
  if (!uid) return []
  const ids: string[] = []
  for (const g of groups.value) {
    if (g.members?.some((m: any) => m.user_id === uid)) ids.push(g.id)
  }
  return ids
})

const selectableActivities = computed(() => {
  const uid = userId.value
  const myGroupIds = userGroupIds.value
  return activities.value.filter(a => {
    if (!['issued', 'returned'].includes(a.status)) return false
    if (a.created_by_user_id === uid) return true
    if (a.group_id && myGroupIds.includes(a.group_id)) return true
    return false
  })
})

const canSubmit = computed(() => {
  return !!form.value.materialItemId && form.value.quantity >= 1
})

const canSubmitNoActivity = computed(() => {
  return !!selectedMaterial.value && !!formNoActivity.value.title.trim()
})

function formatDateShort(iso?: string | null): string {
  if (!iso) return '–'
  return new Date(iso).toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

async function loadActivities() {
  if (!props.departmentId) return
  isLoadingActivities.value = true
  try {
    const [actRes, grpRes] = await Promise.all([
      apiClient.get<ActivityOption[]>('/api/activities', {
        params: { department_id: props.departmentId, status: 'issued,returned' }
      }),
      getGroups(props.departmentId)
    ])
    activities.value = (actRes.data || []).map((a: any) => ({
      ...a,
      status: a.status
    }))
    groups.value = grpRes
  } catch (err) {
    console.error('Aktivitäten laden fehlgeschlagen:', err)
    activities.value = []
  } finally {
    isLoadingActivities.value = false
  }
}

async function loadPackItems() {
  if (!selectedActivity.value) return
  isLoadingPackItems.value = true
  try {
    const res = await apiClient.get(`/api/activities/${selectedActivity.value.id}/pack-items`)
    packItems.value = (res.data || []).map((pi: any) => ({
      material_item_id: pi.material_item_id,
      material_name: pi.material_name,
      quantity_packed: pi.quantity_packed,
      quantity_issued: pi.quantity_issued
    }))
  } catch (err) {
    console.error('Packliste laden fehlgeschlagen:', err)
    packItems.value = []
  } finally {
    isLoadingPackItems.value = false
  }
}

function selectActivity(a: ActivityOption) {
  selectedActivity.value = a
}

function startWithoutActivity() {
  mode.value = 'no_activity'
  step.value = 2
}

function goBack() {
  if (mode.value === 'no_activity') {
    mode.value = 'with_activity'
    selectedMaterial.value = null
    formNoActivity.value = { matSearch: '', title: '', type: 'repair', description: '' }
  }
  step.value = 1
}

function onMatSearchInput() {
  if (matSearchTimer) clearTimeout(matSearchTimer)
  const q = formNoActivity.value.matSearch.trim()
  if (q.length < 2) {
    matSearchResults.value = []
    return
  }
  matSearchTimer = setTimeout(async () => {
    try {
      const mats = await getMaterials(props.departmentId, { search: q })
      matSearchResults.value = mats.slice(0, 15)
    } catch {
      matSearchResults.value = []
    }
  }, 300)
}

function selectMaterial(m: Material) {
  selectedMaterial.value = m
  formNoActivity.value.matSearch = ''
  matSearchResults.value = []
}

function advanceToStep2() {
  if (selectedActivity.value) {
    mode.value = 'with_activity'
    step.value = 2
    loadPackItems()
  }
}

async function submitNoActivity() {
  if (!selectedMaterial.value || !formNoActivity.value.title.trim() || isSubmitting.value) return
  isSubmitting.value = true
  try {
    await createWorkshopTicket({
      department_id: props.departmentId,
      material_item_id: selectedMaterial.value.id,
      title: formNoActivity.value.title.trim(),
      type: formNoActivity.value.type,
      description: formNoActivity.value.description || undefined
    })
    emit('success')
    close()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  } finally {
    isSubmitting.value = false
  }
}

async function submit() {
  if (!selectedActivity.value || !form.value.materialItemId || isSubmitting.value) return
  isSubmitting.value = true
  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/issues`, {
      material_item_id: form.value.materialItemId,
      type: form.value.type,
      quantity: form.value.quantity,
      description: form.value.description || null
    })
    emit('success')
    close()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  } finally {
    isSubmitting.value = false
  }
}

function close() {
  emit('close')
}

function reset() {
  step.value = 1
  mode.value = 'with_activity'
  selectedActivity.value = null
  selectedMaterial.value = null
  packItems.value = []
  matSearchResults.value = []
  form.value = { materialItemId: '', type: 'damage', quantity: 1, description: '' }
  formNoActivity.value = { matSearch: '', title: '', type: 'repair', description: '' }
}

watch(() => props.isOpen, (open) => {
  if (open) {
    reset()
    loadActivities()
  }
})
</script>

<style scoped>
.damage-wizard-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.damage-wizard-modal {
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  max-width: 480px;
  width: 90%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.wizard-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.wizard-header h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
}

.close-btn {
  background: none;
  border: none;
  padding: 4px;
  cursor: pointer;
  color: #6b7280;
}

.close-btn:hover {
  color: #1f2937;
}

.wizard-body {
  padding: 24px;
  overflow-y: auto;
}

.wizard-step h3 {
  margin: 0 0 8px 0;
  font-size: 1rem;
}

.step-hint {
  color: #6b7280;
  font-size: 0.9rem;
  margin: 0 0 16px 0;
}

.no-activity-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 12px 16px;
  margin-bottom: 16px;
  background: #f0fdf4;
  border: 2px dashed #10b981;
  border-radius: 8px;
  color: #059669;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.2s;
}

.no-activity-btn:hover {
  background: #dcfce7;
  border-color: #059669;
}

.mat-search-wrap {
  position: relative;
}

.mat-selected {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  background: #f0fdf4;
  border: 1px solid #10b981;
  border-radius: 8px;
}

.mat-clear {
  background: none;
  border: none;
  font-size: 1.2rem;
  color: #6b7280;
  cursor: pointer;
  padding: 0 4px;
}

.mat-clear:hover {
  color: #ef4444;
}

.mat-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 4px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  max-height: 200px;
  overflow-y: auto;
  z-index: 10;
}

.mat-dropdown-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  padding: 10px 12px;
  background: none;
  border: none;
  text-align: left;
  cursor: pointer;
  font-size: 0.95rem;
}

.mat-dropdown-item:hover {
  background: #f9fafb;
}

.mat-cat {
  font-size: 0.8rem;
  color: #6b7280;
  margin-top: 2px;
}

.loading-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 24px;
  color: #6b7280;
}

.empty-state {
  padding: 24px;
  text-align: center;
  color: #6b7280;
}

.empty-state .hint {
  font-size: 0.85rem;
  margin-top: 8px;
}

.activity-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 280px;
  overflow-y: auto;
}

.activity-option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 12px 16px;
  background: #f9fafb;
  border: 2px solid transparent;
  border-radius: 8px;
  cursor: pointer;
  text-align: left;
  transition: all 0.2s;
}

.activity-option:hover {
  background: #f3f4f6;
}

.activity-option.selected {
  border-color: #10b981;
  background: #ecfdf5;
}

.activity-name {
  font-weight: 500;
  color: #1f2937;
}

.activity-meta {
  font-size: 0.8rem;
  color: #6b7280;
  margin-top: 2px;
}

.form-fields {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group label {
  display: block;
  font-weight: 500;
  font-size: 0.9rem;
  margin-bottom: 6px;
  color: #374151;
}

.required {
  color: #ef4444;
}

.form-select,
.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.95rem;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}

textarea.form-input {
  resize: vertical;
  min-height: 80px;
}

.wizard-footer {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
}

.spacer {
  flex: 1;
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  border: none;
}

.btn-outline {
  background: white;
  border: 1px solid #d1d5db;
  color: #374151;
}

.btn-outline:hover {
  background: #f9fafb;
}

.btn-primary {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
