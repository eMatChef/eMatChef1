<template>
  <ul class="tour-list">
    <li v-for="tour in ONBOARDING_TOURS" :key="tour.id" class="tour-item">
      <span class="tour-icon" aria-hidden="true">
        <v-icon :icon="tour.mdiIcon" size="22" color="#0284c7" />
      </span>
      <div class="tour-body">
        <span class="tour-title">{{ t(tour.titleKey) }}</span>
        <p class="tour-desc">{{ t(tour.descriptionKey) }}</p>
        <span v-if="isTourDone(tour.id)" class="tour-status tour-status--done">
          {{ t('onboarding.tours.statusDone') }}
        </span>
      </div>
      <EButton variant="secondary" size="small" @click="startTour(tour.id)">
        {{ isTourDone(tour.id) ? t('onboarding.tours.restart') : t('onboarding.tours.start') }}
      </EButton>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { ONBOARDING_TOURS, type OnboardingTourId } from '@/config/onboardingTours'
import { useOnboardingTour } from '@/composables/useOnboardingTour'
import { useDepartmentOnboardingAccess } from '@/composables/useDepartmentOnboardingAccess'
import { isOnboardingTourCompleted } from '@/utils/onboardingTourProgress'

const { t } = useI18n()
const route = useRoute()
const { startTour: launchTour } = useOnboardingTour()
const { departmentId, profileId } = useDepartmentOnboardingAccess()

const progressTick = ref(0)
watch(() => route.fullPath, () => {
  progressTick.value += 1
})

const completedTourIds = computed(() => {
  progressTick.value
  const depId = departmentId.value
  const profId = profileId.value
  if (!depId || !profId) return new Set<OnboardingTourId>()
  return new Set(
    ONBOARDING_TOURS.filter((tour) => isOnboardingTourCompleted(profId, depId, tour.id)).map(
      (tour) => tour.id
    )
  )
})

function isTourDone(tourId: OnboardingTourId): boolean {
  return completedTourIds.value.has(tourId)
}

function startTour(tourId: OnboardingTourId) {
  const depId = departmentId.value
  if (!depId) return
  launchTour(tourId, depId)
}
</script>

<style scoped>
.tour-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tour-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #fff;
}

.tour-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

.tour-body {
  flex: 1;
  min-width: 0;
}

.tour-title {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.35;
}

.tour-desc {
  margin: 4px 0 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.45;
}

.tour-status {
  display: inline-block;
  margin-top: 6px;
  font-size: 12px;
}

.tour-status--done {
  color: #15803d;
}
</style>
