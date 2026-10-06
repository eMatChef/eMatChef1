<template>
  <div v-if="blocked" class="totp-required-notice" role="alert">
    {{ t('layout.totpRequiredNotice') }}
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getTotpStatus } from '@/api/profileTotp'

const { t } = useI18n()
const authStore = useAuthStore()
const blocked = ref(false)

const GLOBAL_ADMIN_ROLES = ['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF']

// Nur Hinweis: Login und normale Nutzung bleiben möglich; die Sperre der Adminfunktionen folgt serverseitig.
watch(
  () => [authStore.isLoggedIn, authStore.profileId, authStore.userRoles.join(',')] as const,
  async ([loggedIn, profileId]) => {
    blocked.value = false
    if (!loggedIn || !profileId || !authStore.userRoles.some((r) => GLOBAL_ADMIN_ROLES.includes(r))) return
    try {
      blocked.value = (await getTotpStatus(profileId)).admin_blocked
    } catch {
      blocked.value = false
    }
  },
  { immediate: true },
)
</script>

<style scoped>
.totp-required-notice {
  flex: 0 0 auto;
  width: 100%;
  box-sizing: border-box;
  padding: 0.4rem 0.75rem;
  background: #fef3c7;
  color: #78350f;
  border-bottom: 1px solid #f59e0b;
  font-size: 0.8125rem;
  font-weight: 600;
  text-align: center;
}
</style>
