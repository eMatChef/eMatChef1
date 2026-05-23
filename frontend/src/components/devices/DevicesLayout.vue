<template>
  <div class="devices-layout" :class="layoutClass">
    <header class="devices-header">
      <div class="devices-header-main">
        <h1 class="devices-brand">{{ t('devices.brand') }}</h1>
        <p v-if="departmentName" class="devices-dept">{{ departmentName }}</p>
      </div>
      <div class="devices-header-actions">
        <span v-if="userLabel" class="devices-user">{{ userLabel }}</span>
        <button type="button" class="btn-text" @click="onLogout">{{ t('devices.logout') }}</button>
      </div>
    </header>
    <main class="devices-main">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useDevicesUiMode } from '@/composables/useDevicesUiMode'
import { getPostLogoutPath } from '@/utils/appLoginUrl'

const props = defineProps<{
  departmentId: string
  departmentName?: string
}>()

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()
const { mode } = useDevicesUiMode()

const layoutClass = computed(() => `devices-layout--${mode.value}`)
const userLabel = computed(() => authStore.userDisplayName || '')

async function onLogout() {
  await authStore.logout()
  const path = getPostLogoutPath()
  if (path.startsWith('http')) {
    window.location.assign(path)
    return
  }
  await router.push(path)
}
</script>

<style scoped>
.devices-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f1f5f9;
  color: #0f172a;
}

.devices-header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  padding: 12px 16px;
  background: #0f172a;
  color: #f8fafc;
}

.devices-brand {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
}

.devices-dept {
  margin: 2px 0 0;
  font-size: 13px;
  opacity: 0.85;
}

.devices-header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
}

.devices-user {
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.btn-text {
  border: none;
  background: transparent;
  color: #93c5fd;
  cursor: pointer;
  font: inherit;
  padding: 4px 0;
}

.devices-main {
  flex: 1;
  padding: 12px 16px 24px;
  max-width: 1200px;
  width: 100%;
  margin: 0 auto;
  box-sizing: border-box;
}

.devices-layout--handheld .devices-main {
  padding: 10px 12px 20px;
}

.devices-layout--desktop .devices-main {
  padding: 16px 20px 32px;
}
</style>
