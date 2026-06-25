<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  mapTourItemsForPatch,
  updateActivityTransportTour,
  type ActivityTransportTour,
} from '@/api/activityTransportTours'
import AutoSaveField from '@/components/common/autoSave/AutoSaveField.vue'
import type { AutoSaveFieldValue } from '@/components/common/autoSave/types'

const props = defineProps<{
  activityId: string
  tour: ActivityTransportTour
  itemId: string
  baselineKg: number | null
  disabled?: boolean
}>()

const emit = defineEmits<{
  saved: [tour: ActivityTransportTour]
}>()

const { t } = useI18n()

const modelValue = ref<AutoSaveFieldValue>(
  props.baselineKg != null ? String(props.baselineKg) : '',
)

watch(
  () => props.baselineKg,
  (kg) => {
    modelValue.value = kg != null ? String(kg) : ''
  },
)

function parseKg(value: AutoSaveFieldValue): number | null {
  const raw = value == null ? '' : String(value).trim().replace(',', '.')
  if (raw === '') return null
  const n = Number(raw)
  return Number.isFinite(n) && n > 0 ? n : null
}

async function save(value: AutoSaveFieldValue): Promise<void> {
  const parsed = parseKg(value)
  const items = mapTourItemsForPatch(
    props.tour.items.map((item) =>
      item.id === props.itemId ? { ...item, measured_weight_kg: parsed } : item,
    ),
  )
  const updated = await updateActivityTransportTour(props.activityId, props.tour.id, { items })
  emit('saved', updated)
}
</script>

<template>
  <AutoSaveField
    v-model="modelValue"
    :baseline="baselineKg != null ? String(baselineKg) : null"
    :label="t('activities.materialJourney.transportTours.weighLabel')"
    :placeholder="t('activities.materialJourney.transportTours.weighPlaceholder')"
    suffix="kg"
    span-class="material-journey-transport-tours__autosave-weight"
    :disabled="disabled"
    :save="save"
  />
</template>

<style scoped>
.material-journey-transport-tours__autosave-weight {
  margin-left: auto;
  min-width: 0;
  flex: 0 1 auto;
}

.material-journey-transport-tours__autosave-weight :deep(.autosave-control) {
  min-width: 0;
}

.material-journey-transport-tours__autosave-weight :deep(.e-text-field) {
  max-width: 120px;
}

.material-journey-transport-tours__autosave-weight :deep(.autosave-label) {
  font-size: 12px;
  color: #64748b;
}
</style>
