<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityVehicleAssignment } from '@/api/activityVehicles'
import EButton from '@/components/form/base/EButton.vue'

const props = defineProps<{
  open: boolean
  vehicles: ActivityVehicleAssignment[]
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  selectVehicle: [vehicleId: string]
  selectSimple: []
}>()

const { t } = useI18n()
const selectedVehicleId = ref('')

const canConfirmVehicle = computed(
  () => selectedVehicleId.value.trim().length > 0 && !props.loading,
)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) selectedVehicleId.value = ''
    else if (props.vehicles.length === 1) {
      selectedVehicleId.value = props.vehicles[0]!.vehicle_id
    }
  },
)

function close(): void {
  emit('update:open', false)
}

function onConfirmVehicle(): void {
  if (!canConfirmVehicle.value) return
  emit('selectVehicle', selectedVehicleId.value)
}

function onSimpleTour(): void {
  emit('selectSimple')
}
</script>

<template>
  <v-dialog
    :model-value="open"
    max-width="440"
    @update:model-value="emit('update:open', $event)"
  >
    <div class="material-journey-choose-tour-modal section-card">
      <h2 class="material-journey-choose-tour-modal__title">
        {{ t('activities.materialJourney.transportTours.chooseTourTitle') }}
      </h2>
      <p class="material-journey-choose-tour-modal__hint text-muted">
        {{ t('activities.materialJourney.transportTours.chooseTourHint') }}
      </p>

      <label v-if="vehicles.length > 0" class="material-journey-choose-tour-modal__field">
        <span class="material-journey-choose-tour-modal__label">
          {{ t('activities.materialJourney.transportTours.vehicleLabel') }}
        </span>
        <select v-model="selectedVehicleId" class="material-journey-choose-tour-modal__select">
          <option value="">
            {{ t('activities.materialJourney.transportTours.chooseVehicle') }}
          </option>
          <option
            v-for="assignment in vehicles"
            :key="assignment.id"
            :value="assignment.vehicle_id"
          >
            {{ assignment.vehicle.name }}
            <template v-if="assignment.vehicle.plate">
              ({{ assignment.vehicle.plate }})
            </template>
          </option>
        </select>
      </label>

      <p v-else class="text-muted">
        {{ t('activities.materialJourney.transportTours.noActivityVehicles') }}
      </p>

      <div class="material-journey-choose-tour-modal__actions">
        <EButton variant="secondary" size="small" :disabled="loading" @click="close">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          variant="secondary"
          size="small"
          :disabled="loading"
          @click="onSimpleTour"
        >
          {{ t('activities.materialJourney.transportTours.simpleTour') }}
        </EButton>
        <EButton
          v-if="vehicles.length > 0"
          variant="primary"
          size="small"
          :loading="loading"
          :disabled="!canConfirmVehicle"
          @click="onConfirmVehicle"
        >
          {{ t('activities.materialJourney.transportTours.chooseTourConfirm') }}
        </EButton>
      </div>
    </div>
  </v-dialog>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
