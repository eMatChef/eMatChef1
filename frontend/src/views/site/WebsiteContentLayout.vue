<template>
  <div class="site-editor-layout">
    <aside
      class="site-editor-nav"
      :class="{ 'site-editor-nav--collapsed': !navExpanded }"
      aria-label="Seiten"
      @mouseenter="openNav"
      @mouseleave="closeNav"
    >
      <div class="site-editor-nav-header">
        <span v-show="navExpanded" class="site-editor-nav-title">{{ t('siteEditor.navTitle') }}</span>
      </div>
      <RouterLink
        :to="{ name: 'SitePageEditor', params: { slug: 'landing' } }"
        class="site-editor-link"
        active-class="site-editor-link--active"
        :title="!navExpanded ? t('siteEditor.navLanding') : undefined"
        @click="onNavClick"
      >
        <span class="site-editor-link-short" aria-hidden="true">S</span>
        <span v-show="navExpanded" class="site-editor-link-full">{{ t('siteEditor.navLanding') }}</span>
      </RouterLink>
      <RouterLink
        :to="{ name: 'SitePageEditor', params: { slug: 'blog' } }"
        class="site-editor-link"
        active-class="site-editor-link--active"
        :title="!navExpanded ? t('siteEditor.navBlog') : undefined"
        @click="onNavClick"
      >
        <span class="site-editor-link-short" aria-hidden="true">B</span>
        <span v-show="navExpanded" class="site-editor-link-full">{{ t('siteEditor.navBlog') }}</span>
      </RouterLink>
      <RouterLink
        :to="{ name: 'SiteGeneralEditor', params: { tab: 'faq' } }"
        class="site-editor-link"
        :class="{ 'site-editor-link--active': isAllgemeinActive }"
        :title="!navExpanded ? t('siteEditor.navGeneral') : undefined"
        @click="onNavClick"
      >
        <span class="site-editor-link-short" aria-hidden="true">A</span>
        <span v-show="navExpanded" class="site-editor-link-full">{{ t('siteEditor.navGeneral') }}</span>
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
import { useI18n } from 'vue-i18n'
import { useHoverSubnav } from '@/composables/useHoverSubnav'

const route = useRoute()
const { t } = useI18n()
const { expanded: navExpanded, open: openNav, close: closeNav, onNavClick } = useHoverSubnav()
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
  padding: 0.75rem 0 1rem;
  background: #f8fafc;
  transition: width 0.2s ease;
}

.site-editor-nav--collapsed {
  width: 3.25rem;
}

.site-editor-nav-header {
  padding: 0 0.5rem 0.5rem;
  margin-bottom: 0.25rem;
  border-bottom: 1px solid #e2e8f0;
}

.site-editor-nav--collapsed .site-editor-nav-header {
  padding: 0 0.35rem 0.5rem;
}

.site-editor-nav-title {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  flex: 1;
}

.site-editor-link {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.5rem 1rem;
  color: #334155;
  text-decoration: none;
  font-size: 0.95rem;
}

.site-editor-nav--collapsed .site-editor-link {
  justify-content: center;
  padding: 0.5rem 0.35rem;
}

.site-editor-link-short {
  display: none;
  width: 1.75rem;
  height: 1.75rem;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  background: #e2e8f0;
  color: #334155;
}

.site-editor-nav--collapsed .site-editor-link-short {
  display: inline-flex;
}

.site-editor-nav--collapsed .site-editor-link-full {
  display: none;
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
