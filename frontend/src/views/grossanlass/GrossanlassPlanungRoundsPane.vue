<template>
  <div class="planung-rounds-pane">
    <GrossanlassKeyDatesPanel v-if="departmentId" :department-id="departmentId" />
    <GrossanlassRoundsTab />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import GrossanlassKeyDatesPanel from '@/components/grossanlass/GrossanlassKeyDatesPanel.vue'
import GrossanlassRoundsTab from '@/views/grossanlass/GrossanlassRoundsTab.vue'

const route = useRoute()
const departmentId = computed(() => String(route.params.departmentId || ''))
</script>
