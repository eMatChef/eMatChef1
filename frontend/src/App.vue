<template>
  <div>
    <router-view />
    <GlobalToastContainer />
    <GlobalConfirmDialog />
    <GlobalPromptDialog />
  </div>
</template>

<script setup lang="ts">
import { useAutoLogout } from '@/composables/useAutoLogout'
import GlobalToastContainer from '@/components/common/GlobalToastContainer.vue'
import GlobalConfirmDialog from '@/components/common/GlobalConfirmDialog.vue'
import GlobalPromptDialog from '@/components/common/GlobalPromptDialog.vue'

// Auto-Logout aktivieren
useAutoLogout()
</script>
