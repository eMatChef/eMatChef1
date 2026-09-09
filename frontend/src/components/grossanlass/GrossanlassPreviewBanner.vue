<template>
  <p class="ga-preview-banner" role="status">
    <v-icon icon="mdi-eye-outline" size="18" aria-hidden="true" />
    {{ t('grossanlass.preview.banner') }}
  </p>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<style scoped>
.ga-preview-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 16px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  color: #9a3412;
  font-size: 0.85rem;
  font-weight: 600;
}
</style>
