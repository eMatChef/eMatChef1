<template>
  <DepartmentPrintSettingsPanel :department-id="departmentId" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import DepartmentPrintSettingsPanel from '@/components/print/DepartmentPrintSettingsPanel.vue'

const route = useRoute()
const authStore = useAuthStore()

const departmentId = computed(
  () => String(route.params.departmentId || authStore.activeDepartmentId || ''),
)
</script>
