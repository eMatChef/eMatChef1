<template>
  <div class="plt-legal-page" :class="pageClasses">
    <header v-if="$slots.hero" class="plt-subpage-hero">
      <div class="plt-container plt-legal-page__inner">
        <slot name="hero" />
      </div>
    </header>
    <div class="plt-container plt-legal-page__inner plt-subpage-main">
      <ECard variant="outlined" class="plt-legal-card">
        <slot />
      </ECard>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import ECard from '@/components/form/base/ECard.vue'

const props = withDefaults(
  defineProps<{
    wide?: boolean
    /** content: FAQ/TOS — Hero + Karten; blog: Karten-Grid */
    variant?: 'default' | 'content' | 'blog' | 'faq'
  }>(),
  { wide: false, variant: 'default' },
)

const pageClasses = computed(() => ({
  'plt-legal-page--wide': props.wide,
  'plt-legal-page--content': props.variant === 'content' || props.variant === 'faq',
  'plt-legal-page--blog': props.variant === 'blog',
}))
</script>
