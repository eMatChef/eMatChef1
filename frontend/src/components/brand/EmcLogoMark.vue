<!--
  Einziges EMC-Badge (grün / weiß) – gleiche Darstellung wie in der Kopfzeile der öffentlichen Seiten.
  Überall wiederverwenden: Sidebar, Login, /i/…, Impressum, …
-->
<template>
  <span
    class="emc-logo-mark"
    :class="`emc-logo-mark--${size}`"
    aria-hidden="true"
  >EMC</span>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    /** sm = 40px (Header/Sidebar), lg = 68px (Login-Hero) */
    size?: 'sm' | 'lg'
  }>(),
  { size: 'sm' }
)
</script>

<style scoped>
.emc-logo-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-sizing: border-box;
  background: var(--emc-logo-bg);
  color: var(--emc-logo-fg);
  font-weight: var(--emc-logo-font-weight);
  letter-spacing: var(--emc-logo-letter-spacing);
  line-height: 1;
  border-radius: var(--emc-mark-radius, var(--emc-logo-radius));
  width: var(--emc-mark-w);
  height: var(--emc-mark-h);
  font-size: var(--emc-mark-fs);
  box-shadow: var(--emc-mark-shadow, none);
}

.emc-logo-mark--sm {
  --emc-mark-w: 40px;
  --emc-mark-h: 40px;
  --emc-mark-fs: 0.68rem;
  --emc-mark-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);
}

.emc-logo-mark--lg {
  --emc-mark-w: 68px;
  --emc-mark-h: 68px;
  --emc-mark-fs: 1.1rem;
  --emc-mark-radius: 16px;
  --emc-mark-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
}
</style>
