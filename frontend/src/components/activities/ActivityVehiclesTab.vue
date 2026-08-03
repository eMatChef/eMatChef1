<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  assignActivityVehicle,
  createAndAssignActivityVehicle,
  getActivityVehicles,
  removeActivityVehicle,
  updateActivityVehicle,
  type ActivityVehicleAssignment,
} from '@/api/activityVehicles'
import { getAddresses, type Address } from '@/api/addresses'
import type { DepartmentVehicle } from '@/api/departmentVehicles'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import ActivityTabPanelShell from '@/components/activities/ActivityTabPanelShell.vue'
import ActivityVehicleAssignPicker from '@/components/activities/ActivityVehicleAssignPicker.vue'
import DepartmentAddressAutocomplete from '@/components/addresses/DepartmentAddressAutocomplete.vue'
import EButton from '@/components/form/base/EButton.vue'
import { useToast } from '@/composables/useToast'
import { useActivityTabLoad } from '@/composables/useActivityTabLoad'

defineOptions({ name: 'ActivityVehiclesTab' })

const props = defineProps<{
  activityId: string
  departmentId: string
  canManage: boolean
  reloadToken?: number
}>()

const emit = defineEmits<{
  assignmentsChanged: []
}>()

const { t } = useI18n()
const toast = useToast()
const { showFullLoading, isRefreshing, resetTabLoad, withTabLoad } = useActivityTabLoad()

const saving = ref(false)
const assignments = ref<ActivityVehicleAssignment[]>([])
const addresses = ref<Address[]>([])
const createOpen = ref(false)
const editId = ref<string | null>(null)

const createForm = ref({
  name: '',
  plate: '',
  load_length_cm: null as number | null,
  load_width_cm: null as number | null,
  load_height_cm: null as number | null,
  max_payload_kg: null as number | null,
  notes: '',
  owner_address_id: null as string | null,
  assignmentNotes: '',
})

const editForm = ref({
  name: '',
  plate: '',
  load_length_cm: null as number | null,
  load_width_cm: null as number | null,
  load_height_cm: null as number | null,
  max_payload_kg: null as number | null,
  notes: '',
  owner_address_id: null as string | null,
  assignmentNotes: '',
})

const assignedVehicleIds = computed(() => assignments.value.map((a) => a.vehicle_id))

async function loadAssignments(opts?: { forceFull?: boolean }): Promise<void> {
  await withTabLoad(async () => {
    try {
      assignments.value = await getActivityVehicles(props.activityId)
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
    }
  }, opts)
}

async function loadAddresses(): Promise<void> {
  try {
    const res = await getAddresses(props.departmentId)
    addresses.value = res.addresses.filter((a) => !a.is_deleted)
  } catch {
    addresses.value = []
  }
}

watch(
  () => [props.activityId, props.reloadToken] as const,
  (curr, prev) => {
    const [activityId] = curr
    const prevActivityId = prev?.[0]
    if (prevActivityId != null && activityId !== prevActivityId) {
      resetTabLoad()
    }
    void loadAssignments()
  },
  { immediate: true },
)

watch(
  () => props.departmentId,
  () => {
    void loadAddresses()
  },
  { immediate: true },
)

function metersToCm(m: number | null | undefined): number | null {
  if (m == null) return null
  return Math.round(m * 100)
}

function cmToMeters(cm: number | null | undefined): number | null {
  if (cm == null || !Number.isFinite(cm) || cm <= 0) return null
  return Math.round(cm) / 100
}

function loadDimsFromVehicle(v: DepartmentVehicle): {
  load_length_cm: number | null
  load_width_cm: number | null
  load_height_cm: number | null
} {
  return {
    load_length_cm: metersToCm(v.length_m),
    load_width_cm: metersToCm(v.width_m),
    load_height_cm: metersToCm(v.height_m),
  }
}

function loadDimsToMeters(form: {
  load_length_cm: number | null
  load_width_cm: number | null
  load_height_cm: number | null
}): { length_m: number | null; width_m: number | null; height_m: number | null } {
  return {
    length_m: cmToMeters(form.load_length_cm),
    width_m: cmToMeters(form.load_width_cm),
    height_m: cmToMeters(form.load_height_cm),
  }
}

function resetCreateForm(): void {
  createForm.value = {
    name: '',
    plate: '',
    load_length_cm: null,
    load_width_cm: null,
    load_height_cm: null,
    max_payload_kg: null,
    notes: '',
    owner_address_id: null,
    assignmentNotes: '',
  }
}

async function onAssignExisting(vehicle: DepartmentVehicle): Promise<void> {
  if (!props.canManage) return
  saving.value = true
  try {
    const created = await assignActivityVehicle(props.activityId, { vehicle_id: vehicle.id })
    assignments.value = [...assignments.value, created]
    toast.success(t('activities.vehicles.assigned'))
    emit('assignmentsChanged')
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    saving.value = false
  }
}

async function onCreateAndAssign(): Promise<void> {
  const name = createForm.value.name.trim()
  if (!name || !props.canManage) return
  saving.value = true
  try {
    const dims = loadDimsToMeters(createForm.value)
    const created = await createAndAssignActivityVehicle(props.activityId, {
      vehicle: {
        name,
        plate: createForm.value.plate.trim() || undefined,
        length_m: dims.length_m ?? undefined,
        width_m: dims.width_m ?? undefined,
        height_m: dims.height_m ?? undefined,
        max_payload_kg: createForm.value.max_payload_kg ?? undefined,
        notes: createForm.value.notes.trim() || undefined,
        owner_address_id: createForm.value.owner_address_id ?? undefined,
      },
      notes: createForm.value.assignmentNotes.trim() || undefined,
    })
    assignments.value = [...assignments.value, created]
    createOpen.value = false
    resetCreateForm()
    toast.success(t('activities.vehicles.created'))
    emit('assignmentsChanged')
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    saving.value = false
  }
}

function startEdit(row: ActivityVehicleAssignment): void {
  editId.value = row.id
  editForm.value = {
    name: row.vehicle.name,
    plate: row.vehicle.plate ?? '',
    ...loadDimsFromVehicle(row.vehicle),
    max_payload_kg: row.vehicle.max_payload_kg,
    notes: row.vehicle.notes ?? '',
    owner_address_id: row.vehicle.owner_address_id ?? null,
    assignmentNotes: row.notes ?? '',
  }
}

function cancelEdit(): void {
  editId.value = null
}

async function onSaveEdit(row: ActivityVehicleAssignment): Promise<void> {
  if (!props.canManage) return
  saving.value = true
  try {
    const dims = loadDimsToMeters(editForm.value)
    const updated = await updateActivityVehicle(props.activityId, row.id, {
      notes: editForm.value.assignmentNotes.trim() || undefined,
      vehicle: {
        name: editForm.value.name.trim(),
        plate: editForm.value.plate.trim() || null,
        length_m: dims.length_m,
        width_m: dims.width_m,
        height_m: dims.height_m,
        max_payload_kg: editForm.value.max_payload_kg,
        notes: editForm.value.notes.trim() || null,
        owner_address_id: editForm.value.owner_address_id ?? undefined,
      },
    })
    assignments.value = assignments.value.map((a) => (a.id === updated.id ? updated : a))
    editId.value = null
    toast.success(t('activities.vehicles.saved'))
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    saving.value = false
  }
}

async function onRemove(row: ActivityVehicleAssignment): Promise<void> {
  if (!props.canManage) return
  saving.value = true
  try {
    await removeActivityVehicle(props.activityId, row.id)
    assignments.value = assignments.value.filter((a) => a.id !== row.id)
    if (editId.value === row.id) editId.value = null
    toast.success(t('activities.vehicles.removed'))
    emit('assignmentsChanged')
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    saving.value = false
  }
}

function payloadLabel(v: DepartmentVehicle): string {
  if (v.max_payload_kg != null) {
    return t('activities.vehicles.payloadKg', { kg: v.max_payload_kg })
  }
  return t('activities.vehicles.payloadUnknown')
}

function loadAreaLabel(v: DepartmentVehicle): string | null {
  const length = metersToCm(v.length_m)
  const width = metersToCm(v.width_m)
  const height = metersToCm(v.height_m)
  if (length == null && width == null && height == null) return null
  return t('activities.vehicles.loadAreaCm', {
    length: length ?? '–',
    width: width ?? '–',
    height: height ?? '–',
  })
}
</script>

<template>
  <div class="activity-vehicles-tab">
    <ActivityTabHeader :title="t('activities.vehicles.title')">
      <p class="text-muted activity-vehicles-tab__intro">
        {{ t('activities.vehicles.intro') }}
      </p>
    </ActivityTabHeader>

    <ActivityTabPanelShell :loading="showFullLoading" :refreshing="isRefreshing">
      <section class="activity-vehicles-tab__assigned">
      <div class="activity-vehicles-tab__section-head">
        <h3 class="activity-vehicles-tab__section-title">
          {{ t('activities.vehicles.assignedTitle', { count: assignments.length }) }}
        </h3>
        <EButton
          v-if="canManage"
          variant="primary"
          size="small"
          @click="createOpen = true"
        >
          {{ t('activities.vehicles.createNew') }}
        </EButton>
      </div>

      <p v-if="assignments.length === 0" class="text-muted">
        {{ t('activities.vehicles.emptyAssigned') }}
      </p>

      <ul v-else class="activity-vehicles-tab__list">
        <li v-for="row in assignments" :key="row.id" class="activity-vehicles-tab__card">
          <template v-if="editId === row.id">
            <div class="activity-vehicles-tab__form-grid">
              <label>
                <span>{{ t('activities.vehicles.fieldName') }}</span>
                <input v-model="editForm.name" type="text" class="activity-vehicles-tab__input" />
              </label>
              <label>
                <span>{{ t('activities.vehicles.fieldPlate') }}</span>
                <input v-model="editForm.plate" type="text" class="activity-vehicles-tab__input" />
              </label>
              <label>
                <span>{{ t('activities.vehicles.fieldPayload') }}</span>
                <input
                  v-model.number="editForm.max_payload_kg"
                  type="number"
                  min="0"
                  class="activity-vehicles-tab__input"
                />
                <span class="activity-vehicles-tab__field-hint text-muted">
                  {{ t('activities.vehicles.fieldPayloadHint') }}
                </span>
              </label>
              <div class="activity-vehicles-tab__load-area">
                <span class="activity-vehicles-tab__load-area-label">{{ t('activities.vehicles.fieldLoadArea') }}</span>
                <div class="activity-vehicles-tab__load-area-fields">
                  <label>
                    <span>{{ t('activities.vehicles.fieldLoadLength') }}</span>
                    <input
                      v-model.number="editForm.load_length_cm"
                      type="number"
                      min="0"
                      class="activity-vehicles-tab__input"
                    />
                  </label>
                  <label>
                    <span>{{ t('activities.vehicles.fieldLoadWidth') }}</span>
                    <input
                      v-model.number="editForm.load_width_cm"
                      type="number"
                      min="0"
                      class="activity-vehicles-tab__input"
                    />
                  </label>
                  <label>
                    <span>{{ t('activities.vehicles.fieldLoadHeight') }}</span>
                    <input
                      v-model.number="editForm.load_height_cm"
                      type="number"
                      min="0"
                      class="activity-vehicles-tab__input"
                    />
                  </label>
                </div>
              </div>
              <label class="activity-vehicles-tab__field-wide">
                <span>{{ t('activities.vehicles.fieldOwner') }}</span>
                <DepartmentAddressAutocomplete
                  :addresses="addresses"
                  :selected-id="editForm.owner_address_id"
                  primary-type="supplier"
                  :placeholder="t('activities.vehicles.ownerPlaceholder')"
                  @update:selected-id="editForm.owner_address_id = $event"
                />
              </label>
              <label class="activity-vehicles-tab__field-wide">
                <span>{{ t('activities.vehicles.fieldNotes') }}</span>
                <textarea v-model="editForm.notes" rows="2" class="activity-vehicles-tab__input" />
              </label>
              <label class="activity-vehicles-tab__field-wide">
                <span>{{ t('activities.vehicles.fieldAssignmentNotes') }}</span>
                <textarea v-model="editForm.assignmentNotes" rows="2" class="activity-vehicles-tab__input" />
              </label>
            </div>
            <div class="activity-vehicles-tab__card-actions">
              <EButton variant="secondary" size="small" @click="cancelEdit">{{ t('common.cancel') }}</EButton>
              <EButton variant="primary" size="small" :loading="saving" @click="onSaveEdit(row)">
                {{ t('common.save') }}
              </EButton>
            </div>
          </template>
          <template v-else>
            <div class="activity-vehicles-tab__card-main">
              <strong>{{ row.vehicle.name }}</strong>
              <span v-if="row.vehicle.plate" class="text-muted">{{ row.vehicle.plate }}</span>
              <span class="text-muted">{{ payloadLabel(row.vehicle) }}</span>
              <span v-if="loadAreaLabel(row.vehicle)" class="text-muted">{{ loadAreaLabel(row.vehicle) }}</span>
              <span v-if="row.vehicle.owner_label" class="activity-vehicles-tab__owner">
                {{ t('activities.vehicles.ownerLine', { label: row.vehicle.owner_label }) }}
              </span>
              <p v-if="row.notes" class="text-muted activity-vehicles-tab__assignment-note">
                {{ row.notes }}
              </p>
            </div>
            <div v-if="canManage" class="activity-vehicles-tab__card-actions">
              <EButton variant="secondary" size="small" @click="startEdit(row)">{{ t('common.edit') }}</EButton>
              <EButton variant="secondary" size="small" :loading="saving" @click="onRemove(row)">
                {{ t('activities.vehicles.remove') }}
              </EButton>
            </div>
          </template>
        </li>
      </ul>
    </section>

    <section v-if="canManage" class="section-card activity-vehicles-tab__search">
      <h3 class="activity-vehicles-tab__section-title">{{ t('activities.vehicles.searchTitle') }}</h3>
      <p class="text-muted activity-vehicles-tab__search-hint">{{ t('activities.vehicles.searchHint') }}</p>
      <ActivityVehicleAssignPicker
        :department-id="departmentId"
        :activity-id="activityId"
        :excluded-vehicle-ids="assignedVehicleIds"
        :disabled="saving"
        :reload-token="reloadToken"
        @select="onAssignExisting"
      />
    </section>
    </ActivityTabPanelShell>

    <v-dialog v-model="createOpen" max-width="560">
      <div class="section-card activity-vehicles-tab__dialog">
        <h3 class="activity-vehicles-tab__section-title">{{ t('activities.vehicles.createTitle') }}</h3>
        <div class="activity-vehicles-tab__form-grid">
          <label>
            <span>{{ t('activities.vehicles.fieldName') }}</span>
            <input v-model="createForm.name" type="text" class="activity-vehicles-tab__input" />
          </label>
          <label>
            <span>{{ t('activities.vehicles.fieldPlate') }}</span>
            <input v-model="createForm.plate" type="text" class="activity-vehicles-tab__input" />
          </label>
          <label>
            <span>{{ t('activities.vehicles.fieldPayload') }}</span>
            <input
              v-model.number="createForm.max_payload_kg"
              type="number"
              min="0"
              class="activity-vehicles-tab__input"
            />
            <span class="activity-vehicles-tab__field-hint text-muted">
              {{ t('activities.vehicles.fieldPayloadHint') }}
            </span>
          </label>
          <div class="activity-vehicles-tab__load-area">
            <span class="activity-vehicles-tab__load-area-label">{{ t('activities.vehicles.fieldLoadArea') }}</span>
            <div class="activity-vehicles-tab__load-area-fields">
              <label>
                <span>{{ t('activities.vehicles.fieldLoadLength') }}</span>
                <input
                  v-model.number="createForm.load_length_cm"
                  type="number"
                  min="0"
                  class="activity-vehicles-tab__input"
                />
              </label>
              <label>
                <span>{{ t('activities.vehicles.fieldLoadWidth') }}</span>
                <input
                  v-model.number="createForm.load_width_cm"
                  type="number"
                  min="0"
                  class="activity-vehicles-tab__input"
                />
              </label>
              <label>
                <span>{{ t('activities.vehicles.fieldLoadHeight') }}</span>
                <input
                  v-model.number="createForm.load_height_cm"
                  type="number"
                  min="0"
                  class="activity-vehicles-tab__input"
                />
              </label>
            </div>
          </div>
          <label class="activity-vehicles-tab__field-wide">
            <span>{{ t('activities.vehicles.fieldOwner') }}</span>
            <DepartmentAddressAutocomplete
              :addresses="addresses"
              :selected-id="createForm.owner_address_id"
              primary-type="supplier"
              :placeholder="t('activities.vehicles.ownerPlaceholder')"
              @update:selected-id="createForm.owner_address_id = $event"
            />
          </label>
          <label class="activity-vehicles-tab__field-wide">
            <span>{{ t('activities.vehicles.fieldNotes') }}</span>
            <textarea v-model="createForm.notes" rows="2" class="activity-vehicles-tab__input" />
          </label>
          <label class="activity-vehicles-tab__field-wide">
            <span>{{ t('activities.vehicles.fieldAssignmentNotes') }}</span>
            <textarea v-model="createForm.assignmentNotes" rows="2" class="activity-vehicles-tab__input" />
          </label>
        </div>
        <div class="activity-vehicles-tab__card-actions">
          <EButton variant="secondary" @click="createOpen = false">{{ t('common.cancel') }}</EButton>
          <EButton
            variant="primary"
            :disabled="!createForm.name.trim()"
            :loading="saving"
            @click="onCreateAndAssign"
          >
            {{ t('activities.vehicles.createConfirm') }}
          </EButton>
        </div>
      </div>
    </v-dialog>
  </div>
</template>

<style scoped>
.activity-vehicles-tab {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.activity-vehicles-tab__intro {
  margin: 8px 0 0;
}

.activity-vehicles-tab__section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.activity-vehicles-tab__section-title {
  margin: 0;
  font-size: 1rem;
}

.activity-vehicles-tab__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.activity-vehicles-tab__card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px;
  background: #fff;
}

.activity-vehicles-tab__card-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.activity-vehicles-tab__owner {
  font-size: 13px;
  color: #475569;
}

.activity-vehicles-tab__assignment-note {
  margin: 4px 0 0;
  font-size: 13px;
}

.activity-vehicles-tab__card-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 12px;
}

.activity-vehicles-tab__form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.activity-vehicles-tab__field-wide {
  grid-column: 1 / -1;
}

.activity-vehicles-tab__load-area {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.activity-vehicles-tab__load-area-label {
  font-size: 13px;
  font-weight: 600;
}

.activity-vehicles-tab__load-area-fields {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
}

.activity-vehicles-tab__load-area-fields label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
}

.activity-vehicles-tab__form-grid label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
}

.activity-vehicles-tab__input {
  min-height: 40px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font: inherit;
  font-weight: 400;
}

.activity-vehicles-tab__field-hint {
  font-size: 12px;
  font-weight: 400;
  line-height: 1.35;
}

textarea.activity-vehicles-tab__input {
  min-height: 72px;
  resize: vertical;
}

.activity-vehicles-tab__search-hint {
  margin: 0 0 12px;
}

.activity-vehicles-tab__dialog {
  padding: 16px;
}
</style>
