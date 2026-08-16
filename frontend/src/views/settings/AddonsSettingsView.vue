<template>
  <div class="addons-settings">
    <div class="header-section">
      <div>
        <h1>{{ t('settings.addons.title') }}</h1>
        <p class="description">{{ t('settings.addons.description') }}</p>
      </div>
    </div>

    <div v-if="!canManage" class="info-card">
      <p class="muted">{{ t('settings.addons.noPermission') }}</p>
    </div>

    <div v-else class="info-card">
      <p class="muted">{{ t('settings.addons.empty') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentSettingsManagerAccess } from '@/composables/useDepartmentSettingsManagerAccess'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()

const selectedDepartmentId = computed(
  () => String(route.params.departmentId || authStore.activeDepartmentId || '').trim() || null,
)

const { canManageDepartmentSensitiveSettings: canManage } =
  useDepartmentSettingsManagerAccess(selectedDepartmentId)
</script>

<style scoped>
.addons-settings {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.header-section h1 {
  margin: 0;
  font-size: 24px;
}

.description {
  margin: 4px 0 0;
  color: #6b7280;
}

.info-card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}

.muted {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}
</style>
