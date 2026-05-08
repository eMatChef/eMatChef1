<template>
  <div class="permissions-settings">
    <h2 class="settings-title">{{ t('settings.permissions.title') }}</h2>
    <p class="settings-description">{{ t('settings.permissions.description') }}</p>
    
    <div class="settings-section">
      <h3>{{ t('settings.permissions.manageTitle') }}</h3>
      <p>{{ t('settings.permissions.comingSoon') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<style scoped>
.permissions-settings {
  padding: 0;
}

.settings-title {
  font-size: 24px;
  font-weight: 600;
  margin-bottom: 8px;
  color: #1e293b;
}

.settings-description {
  color: #64748b;
  margin-bottom: 32px;
}

.settings-section {
  margin-bottom: 32px;
}

.settings-section h3 {
  font-size: 18px;
  font-weight: 500;
  margin-bottom: 16px;
  color: #334155;
}
</style>
