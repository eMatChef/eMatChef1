<template>
  <div class="tasks-general-panel">
    <div class="general-card">
      <h2 class="panel-heading">{{ t('tasksGeneral.heading') }}</h2>
      <p class="panel-text">
        {{ t('tasksGeneral.intro') }}
      </p>
      <p v-if="canManagePrintTasks" class="panel-text panel-text-muted">
        {{ t('tasksGeneral.printHint') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const authStore = useAuthStore()

const departmentRole = computed(() => String(authStore.currentDepartmentRole || 'u').toLowerCase().trim())
const canManagePrintTasks = computed(() => !['u', 'user'].includes(departmentRole.value))
</script>

<style scoped>
.tasks-general-panel {
  max-width: 720px;
}

.general-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px 22px;
}

.panel-heading {
  margin: 0 0 12px;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.panel-text {
  margin: 0 0 10px;
  font-size: 14px;
  line-height: 1.5;
  color: #374151;
}

.panel-text:last-child {
  margin-bottom: 0;
}

.panel-text-muted {
  color: #6b7280;
  font-size: 13px;
}
</style>
