<template>
  <EDialog v-model="open" :max-width="640" :title="t('grossanlass.planung.transportDraftTitle')">
    <div v-if="need" class="ga-need-dialog">
      <p class="ga-need-dialog__group">{{ need.group_name }}</p>
      <p :class="committed ? 'ga-need-dialog__chip ga-need-dialog__chip--on' : 'ga-need-dialog__chip'">
        {{ committed ? t('grossanlass.fahrzeuge.covered') : t('grossanlass.fahrzeuge.openVehicle') }}
      </p>
      <ETextField
        v-model="form.task"
        :label="t('grossanlass.planung.ressorts.vehicleTask')"
        :disabled="!canEdit"
        hide-details
      />
      <GrossanlassVehicleCategoryField
        v-model="form.category"
        :department-id="departmentId"
        :disabled="!canEdit"
      />
      <ETextField
        v-model="form.vehicle"
        :label="t('grossanlass.planung.ressorts.vehicleWish')"
        :placeholder="t('grossanlass.planung.ressorts.materialVehiclePlaceholder')"
        :disabled="!canEdit"
        hide-details
      />
      <EDateField
        v-model="form.date"
        :label="t('grossanlass.planung.ressorts.taskBlockDate')"
        :department-id="departmentId"
        :disabled="!canEdit"
        allow-past
      />
      <div class="ga-need-dialog__times">
        <ETimeField
          v-model="form.time"
          :label="t('grossanlass.planung.ressorts.taskBlockTimeStart')"
          :disabled="!canEdit"
          hide-details
        />
        <ETimeField
          v-model="form.end"
          :label="t('grossanlass.planung.ressorts.taskBlockTimeEnd')"
          :disabled="!canEdit"
          hide-details
        />
      </div>
    </div>
    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton v-if="canEdit" variant="primary" :loading="saving" :disabled="!canSave" @click="save">
        {{ t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, EDateField, EDialog, ETextField, ETimeField } from '@/components/form/base'
import GrossanlassVehicleCategoryField from '@/components/grossanlass/GrossanlassVehicleCategoryField.vue'
import {
  updateGrossanlassBauprojektVehicle,
  type GaBauprojektVehicleNeed,
} from '@/api/grossanlassBauprojekt'

const open = defineModel<boolean>({ default: false })
const props = defineProps<{
  departmentId: string
  need: (GaBauprojektVehicleNeed & { group_name: string }) | null
  committed: boolean
  canEdit: boolean
}>()
const emit = defineEmits<{
  saved: [row: GaBauprojektVehicleNeed]
}>()

const { t } = useI18n()
const toast = useToast()
const saving = ref(false)
const form = ref({
  task: '',
  vehicle: '',
  category: '',
  date: '',
  time: '08:00',
  end: '08:15',
})

const canSave = computed(() =>
  !saving.value && (!!form.value.task.trim() || !!form.value.vehicle.trim()),
)

function clockMinutes(value: string): number | null {
  const match = /^(\d{1,2}):(\d{2})$/.exec(value.trim())
  if (!match) return null
  const hours = Number(match[1])
  const minutes = Number(match[2])
  if (hours > 23 || minutes > 59) return null
  return hours * 60 + minutes
}

function clockFromMinutes(total: number): string {
  const minutes = ((total % (24 * 60)) + 24 * 60) % (24 * 60)
  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`
}

function fill(need: GaBauprojektVehicleNeed) {
  const start = need.starts_at ? new Date(need.starts_at) : null
  const valid = !!start && !Number.isNaN(start.getTime())
  const time = valid
    ? `${String(start.getHours()).padStart(2, '0')}:${String(start.getMinutes()).padStart(2, '0')}`
    : '08:00'
  const startMin = clockMinutes(time)
  form.value = {
    task: need.task_label || '',
    vehicle: need.vehicle_label || '',
    category: need.category_label || '',
    date: valid
      ? `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}`
      : '',
    time,
    end: need.duration_minutes && startMin != null ? clockFromMinutes(startMin + need.duration_minutes) : time,
  }
}

watch(() => props.need, (need) => {
  if (need) fill(need)
})

watch(() => form.value.time, (time) => {
  const start = clockMinutes(time)
  const end = clockMinutes(form.value.end)
  if (start == null) return
  if (end == null || ((end - start + 24 * 60) % (24 * 60)) < 15) {
    form.value.end = clockFromMinutes(start + 15)
  }
})

async function save() {
  const need = props.need
  if (!need || !canSave.value || !props.canEdit) return
  const start = clockMinutes(form.value.time)
  const end = clockMinutes(form.value.end)
  let duration: number | null = null
  if (start != null && end != null) {
    duration = end - start
    if (duration < 0) duration += 24 * 60
    if (duration < 15) duration = 15
  }
  saving.value = true
  try {
    const saved = await updateGrossanlassBauprojektVehicle(props.departmentId, need.group_id, need.id, {
      task_label: form.value.task.trim(),
      vehicle_label: form.value.vehicle.trim(),
      category_label: form.value.category.trim() || null,
      starts_at: form.value.date ? `${form.value.date}T${form.value.time || '08:00'}:00` : null,
      duration_minutes: duration,
    })
    emit('saved', saved)
    toast.success(t('grossanlass.planung.transportDraftSaved'))
    open.value = false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.ga-need-dialog {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ga-need-dialog__group {
  margin: 0;
  font-weight: 600;
}
.ga-need-dialog__chip {
  align-self: flex-start;
  margin: 0;
  padding: 0 8px;
  border-radius: 999px;
  background: #ffedd5;
  color: #9a3412;
  font-size: 0.75rem;
}
.ga-need-dialog__chip--on {
  background: #dcfce7;
  color: #166534;
}
.ga-need-dialog__times {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
</style>
