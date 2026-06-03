<template>
  <v-app class="emc-app">
    <DevEnvironmentBanner />
    <router-view />
    <GlobalToastContainer />
    <GlobalConfirmDialog />
    <PhysicalComboContainerWarningModal />
    <GlobalPromptDialog />
  </v-app>
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

<style src="@/components/form/base/e-form-field.css"></style>
<style src="@/components/form/base/e-button.css"></style>
<style src="@/components/form/base/e-card.css"></style>

<style scoped>
.emc-app {
  height: 100dvh;
  overflow: hidden;
  background-color: #f5f5f5;
}

.emc-app :deep(.v-application__wrap) {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}

/* Öffentliche Marketing-Seiten: eigener Scroll in .plt-shell (nicht overflow:hidden der App) */
.emc-app :deep(.v-application__wrap > .plt-shell) {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}
</style>
