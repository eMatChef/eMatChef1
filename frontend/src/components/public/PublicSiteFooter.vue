<template>
  <footer class="plt-footer" role="contentinfo">
    <div class="plt-footer-inner" :class="{ 'is-compact': compact }">
      <nav v-if="!compact" class="plt-footer-nav" :aria-label="t('publicNav.footerAria')">
        <RouterLink to="/blog">{{ t('publicNav.blog') }}</RouterLink>
        <RouterLink to="/faq">{{ t('publicNav.faq') }}</RouterLink>
        <RouterLink to="/tos">{{ t('publicNav.tos') }}</RouterLink>
        <RouterLink to="/impressum">{{ t('publicNav.impressum') }}</RouterLink>
      </nav>
      <p class="plt-footer-copy">© 2026 eMatChef</p>
    </div>
  </footer>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

withDefaults(defineProps<{
  compact?: boolean
}>(), {
  compact: false,
})

const { t } = useI18n()
</script>
