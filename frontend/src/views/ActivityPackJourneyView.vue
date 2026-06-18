<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ActivityMaterialJourneyView from '@/components/activities/ActivityMaterialJourneyView.vue'

const route = useRoute()

const departmentId = computed(() => String(route.params.departmentId ?? ''))
const activityId = computed(() => String(route.params.activityId ?? ''))
</script>

<template>
  <div class="activity-pack-journey-view">
    <ActivityMaterialJourneyView
      v-if="departmentId && activityId"
      :department-id="departmentId"
      :activity-id="activityId"
    />
  </div>
</template>

<style scoped>
.activity-pack-journey-view {
  width: 100%;
  max-width: none;
  box-sizing: border-box;
  padding: 16px 12px 32px;
}

@media (min-width: 768px) {
  .activity-pack-journey-view {
    padding: 24px 24px 32px;
  }
}
</style>
