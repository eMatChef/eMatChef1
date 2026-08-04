<template>
  <div class="tour-columns">
    <v-expansion-panels
      v-for="category in visibleCategories"
      :key="category"
      v-model="expandedByCategory[category]"
      multiple
      variant="accordion"
      class="tour-column-accordion"
    >
      <v-expansion-panel :value="category">
        <v-expansion-panel-title>
          <span class="tour-column-title">{{ t(ONBOARDING_TOUR_CATEGORY_LABEL_KEYS[category]) }}</span>
          <v-chip
            v-if="categoryDoneCount(category) > 0"
            size="x-small"
            color="success"
            variant="tonal"
            class="tour-column-badge"
          >
            {{ t('onboarding.tours.categoryDone', categoryDoneChip(category)) }}
          </v-chip>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <ul class="tour-list">
            <li v-for="tour in groupedTours[category]" :key="tour.id" class="tour-item">
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
              <EButton variant="secondary" size="small" class="tour-start-btn" @click="startTour(tour.id)">
                {{ isTourDone(tour.id) ? t('onboarding.tours.restart') : t('onboarding.tours.start') }}
              </EButton>
            </li>
          </ul>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import {
  filterOnboardingToursForRole,
  groupOnboardingToursByCategory,
  ONBOARDING_TOUR_CATEGORY_LABEL_KEYS,
  ONBOARDING_TOUR_CATEGORY_ORDER,
  type OnboardingTourCategory,
  type OnboardingTourId,
} from '@/config/onboardingTours'
import { useOnboardingTour } from '@/composables/useOnboardingTour'
import { useDepartmentOnboardingAccess } from '@/composables/useDepartmentOnboardingAccess'
import { useActivityGroupMemberScope } from '@/composables/useActivityGroupMemberScope'
import { isOnboardingTourCompleted } from '@/utils/onboardingTourProgress'

const { t } = useI18n()
const route = useRoute()
const { startTour: launchTour } = useOnboardingTour()
const { departmentId, profileId, departmentRole } = useDepartmentOnboardingAccess()
const { canCreateCampAndEvent, loadGroupsForDepartment } = useActivityGroupMemberScope()

onMounted(() => {
  const depId = departmentId.value
  if (depId) void loadGroupsForDepartment(depId)
})

const visibleTours = computed(() =>
  filterOnboardingToursForRole(departmentRole.value, {
    canCreateCamp: canCreateCampAndEvent.value,
  })
)

const groupedTours = computed(() => groupOnboardingToursByCategory(visibleTours.value))

const visibleCategories = computed(() =>
  ONBOARDING_TOUR_CATEGORY_ORDER.filter((category) => groupedTours.value[category].length > 0)
)

const expandedByCategory = reactive<Record<OnboardingTourCategory, OnboardingTourCategory[]>>({
  material: ['material'],
  activities: ['activities'],
  settings: ['settings'],
})

const progressTick = ref(0)
watch(
  () => route.fullPath,
  () => {
    progressTick.value += 1
  }
)

const completedTourIds = computed(() => {
  progressTick.value
  const depId = departmentId.value
  const profId = profileId.value
  if (!depId || !profId) return new Set<OnboardingTourId>()
  return new Set(
    visibleTours.value
      .filter((tour) => isOnboardingTourCompleted(profId, depId, tour.id))
      .map((tour) => tour.id)
  )
})

function isTourDone(tourId: OnboardingTourId): boolean {
  return completedTourIds.value.has(tourId)
}

function categoryDoneCount(category: OnboardingTourCategory): number {
  return groupedTours.value[category].filter((tour) => isTourDone(tour.id)).length
}

function categoryDoneChip(category: OnboardingTourCategory) {
  return {
    done: categoryDoneCount(category),
    total: groupedTours.value[category].length,
  }
}

function startTour(tourId: OnboardingTourId) {
  const depId = departmentId.value
  if (!depId) return
  launchTour(tourId, depId)
}
</script>

<style scoped>
.tour-columns {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  align-items: start;
}

@media (min-width: 600px) {
  .tour-columns {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
}

@media (min-width: 1024px) {
  .tour-columns {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.tour-column-accordion :deep(.v-expansion-panel) {
  border: 1px solid #e2e8f0;
  border-radius: 12px !important;
  overflow: hidden;
}

.tour-column-accordion :deep(.v-expansion-panel-title) {
  font-weight: 600;
  color: #0f172a;
  min-height: 48px;
  padding-inline: 14px;
}

.tour-column-accordion :deep(.v-expansion-panel-text__wrapper) {
  padding-inline: 0;
}

.tour-column-title {
  font-size: 0.95rem;
}

.tour-column-badge {
  margin-left: auto;
  margin-right: 4px;
}

.tour-list {
  list-style: none;
  margin: 0;
  padding: 0 12px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tour-item {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #fff;
}

@media (min-width: 600px) {
  .tour-item {
    min-height: 100%;
  }
}

.tour-icon {
  flex-shrink: 0;
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

.tour-start-btn {
  align-self: flex-start;
}
</style>
