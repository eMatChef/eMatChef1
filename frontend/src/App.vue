<template>
  <div>
    <DevEnvironmentBanner />
    <router-view />
    <GlobalToastContainer />
    <GlobalConfirmDialog />
    <PhysicalComboContainerWarningModal />
    <GlobalPromptDialog />
  </div>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAutoLogout } from '@/composables/useAutoLogout'
import { syncDocumentHead } from '@/composables/usePageHead'
import { usePageHeadStore } from '@/stores/pageHead'
import DevEnvironmentBanner from '@/components/common/DevEnvironmentBanner.vue'
import GlobalToastContainer from '@/components/common/GlobalToastContainer.vue'
import GlobalConfirmDialog from '@/components/common/GlobalConfirmDialog.vue'
import PhysicalComboContainerWarningModal from '@/components/common/PhysicalComboContainerWarningModal.vue'
import GlobalPromptDialog from '@/components/common/GlobalPromptDialog.vue'

// Auto-Logout aktivieren
useAutoLogout()

const route = useRoute()
const pageHeadStore = usePageHeadStore()

/** Wenn Views dynamischen Titel setzen (Material, Public Lookup), Head sofort aktualisieren */
watch(
  () => [pageHeadStore.dynamicTitle, pageHeadStore.dynamicDescription] as const,
  () => {
    syncDocumentHead(route)
  },
  { flush: 'post' }
)
</script>
