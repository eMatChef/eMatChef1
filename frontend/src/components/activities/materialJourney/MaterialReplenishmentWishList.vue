<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import type { ActivityReplenishmentWish } from '@/api/activityReplenishmentWishes'

defineProps<{
  wishes: ActivityReplenishmentWish[]
  submitting: boolean
}>()

const emit = defineEmits<{
  fulfill: [wishId: string]
  reject: [wishId: string]
}>()

const { t } = useI18n()

function availabilityText(wish: ActivityReplenishmentWish): string | null {
  const snap = wish.availabilitySnapshot
  if (!snap) return null
  const avail = snap.available_for_period
  if (typeof avail === 'number') {
    return t('activities.materialJourney.replenishmentWish.availabilityAtRequest', { n: avail })
  }
  return null
}
</script>

<template>
  <div v-if="wishes.length" class="material-replenishment-wish-list section-card">
    <h2 class="material-replenishment-wish-list__title">
      {{ t('activities.materialJourney.replenishmentWish.mwQueue', { count: wishes.length }) }}
    </h2>

    <ul class="material-replenishment-wish-list__items">
      <li v-for="wish in wishes" :key="wish.id" class="material-replenishment-wish-list__item">
        <div class="material-replenishment-wish-list__body">
          <span class="material-replenishment-wish-list__material">{{ wish.materialName }}</span>
          <span class="text-muted">
            {{ t('activities.materialJourney.replenishmentWish.qtyBy', { n: wish.quantityRequested }) }}
          </span>
          <span v-if="wish.requestedByName" class="text-muted">
            {{ t('activities.materialJourney.replenishmentWish.requestedBy', { name: wish.requestedByName }) }}
          </span>
          <span v-if="wish.notes" class="material-replenishment-wish-list__notes text-muted">
            {{ wish.notes }}
          </span>
          <span v-if="availabilityText(wish)" class="text-muted">{{ availabilityText(wish) }}</span>
        </div>
        <div class="material-replenishment-wish-list__actions">
          <EButton
            variant="primary"
            size="small"
            :disabled="submitting"
            @click="emit('fulfill', wish.id)"
          >
            {{ t('activities.materialJourney.replenishmentWish.fulfill') }}
          </EButton>
          <EButton
            variant="secondary"
            size="small"
            :disabled="submitting"
            @click="emit('reject', wish.id)"
          >
            {{ t('activities.materialJourney.replenishmentWish.reject') }}
          </EButton>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-replenishment-wish-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px;
}

.material-replenishment-wish-list__title {
  margin: 0;
  font-size: 1rem;
}

.material-replenishment-wish-list__items {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.material-replenishment-wish-list__item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.material-replenishment-wish-list__body {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.material-replenishment-wish-list__material {
  font-weight: 600;
}

.material-replenishment-wish-list__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
