<template>
  <div class="general-editor">
    <nav class="general-tabs" aria-label="Allgemein">
      <RouterLink
        v-for="tab in SITE_GENERAL_TABS"
        :key="tab"
        :to="{ name: 'SiteGeneralEditor', params: { tab } }"
        class="general-tab"
        active-class="general-tab--active"
      >
        {{ SITE_GENERAL_TAB_LABELS[tab] }}
      </RouterLink>
    </nav>

    <div class="general-panel">
      <FaqPageEditor v-if="activeTab === 'faq'" ref="faqRef" />
      <TosPageEditor v-else-if="activeTab === 'tos'" ref="tosRef" />
      <ImpressumPageEditor v-else-if="activeTab === 'impressum'" ref="impRef" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FaqPageEditor from '@/components/site/FaqPageEditor.vue'
import TosPageEditor from '@/components/site/TosPageEditor.vue'
import ImpressumPageEditor from '@/components/site/ImpressumPageEditor.vue'
import {
  SITE_GENERAL_TABS,
  SITE_GENERAL_TAB_LABELS,
  isSiteGeneralTab,
} from '@/config/sitePageEditorFields'

const route = useRoute()
const router = useRouter()

const activeTab = computed(() => {
  const t = String(route.params.tab || '')
  return isSiteGeneralTab(t) ? t : 'faq'
})

watch(
  () => route.params.tab,
  (t) => {
    const s = String(t || '')
    if (s && !isSiteGeneralTab(s)) {
      void router.replace({ name: 'SiteGeneralEditor', params: { tab: 'faq' } })
    }
  },
  { immediate: true }
)
</script>

<style scoped>
.general-editor {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-height: 100%;
}

.general-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  padding: 0 0 1rem;
  margin-bottom: 0.25rem;
  border-bottom: 1px solid #e2e8f0;
}

.general-tab {
  padding: 0.5rem 0.9rem;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  color: #475569;
  text-decoration: none;
  border: 1px solid transparent;
}

.general-tab:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.general-tab--active {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #065f46;
}

.general-panel {
  flex: 1;
  min-height: 0;
}
</style>
