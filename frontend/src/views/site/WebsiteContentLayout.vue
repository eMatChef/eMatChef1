<template>
  <div class="site-editor-layout">
    <aside class="site-editor-nav" aria-label="Seiten">
      <RouterLink
        :to="{ name: 'SitePageEditor', params: { slug: 'landing' } }"
        class="site-editor-link"
        active-class="site-editor-link--active"
      >
        Startseite
      </RouterLink>
      <RouterLink
        :to="{ name: 'SitePageEditor', params: { slug: 'blog' } }"
        class="site-editor-link"
        active-class="site-editor-link--active"
      >
        Blog
      </RouterLink>
      <RouterLink
        :to="{ name: 'SiteGeneralEditor', params: { tab: 'faq' } }"
        class="site-editor-link"
        :class="{ 'site-editor-link--active': isAllgemeinActive }"
      >
        Allgemein
      </RouterLink>
    </aside>
    <div class="site-editor-body">
      <RouterView />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const isAllgemeinActive = computed(() => route.name === 'SiteGeneralEditor')
</script>

<style scoped>
.site-editor-layout {
  display: flex;
  min-height: calc(100vh - 120px);
  gap: 0;
}

.site-editor-nav {
  width: 14rem;
  flex-shrink: 0;
  border-right: 1px solid #e2e8f0;
  padding: 1rem 0;
  background: #f8fafc;
}

.site-editor-link {
  display: block;
  padding: 0.5rem 1rem;
  color: #334155;
  text-decoration: none;
  font-size: 0.95rem;
}

.site-editor-link:hover {
  background: #f1f5f9;
}

.site-editor-link--active {
  background: #e2e8f0;
  font-weight: 600;
  color: #0f172a;
}

.site-editor-body {
  flex: 1;
  padding: 1rem 1.25rem 2rem;
  overflow: auto;
}
</style>
