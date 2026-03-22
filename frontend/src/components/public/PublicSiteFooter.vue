<template>
  <footer class="public-footer" role="contentinfo">
    <div class="public-footer-inner">
      <p class="public-footer-copy">&copy; {{ year }} eMatChef</p>
      <nav class="public-footer-nav" aria-label="Rechtliches">
        <RouterLink to="/impressum" class="public-footer-link">Impressum</RouterLink>
        <span class="public-footer-sep" aria-hidden="true">·</span>
        <RouterLink to="/datenschutz" class="public-footer-link">Datenschutz</RouterLink>
      </nav>
    </div>
  </footer>
</template>

<script setup lang="ts">
const year = new Date().getFullYear()
</script>

<style scoped>
.public-footer {
  flex-shrink: 0;
  margin-top: auto;
  padding: 16px 20px 24px;
  border-top: 1px solid var(--public-header-border);
  background: var(--public-header-bg);
}

.public-footer-inner {
  max-width: 720px;
  margin: 0 auto;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 8px 16px;
  font-size: 0.85rem;
  color: var(--public-footer-muted);
}

.public-footer-copy {
  margin: 0;
}

.public-footer-nav {
  display: flex;
  align-items: center;
  gap: 8px;
}

.public-footer-sep {
  user-select: none;
  opacity: 0.6;
}

.public-footer-link {
  color: var(--public-brand-title-hover);
  text-decoration: none;
  font-weight: 500;
}

.public-footer-link:hover {
  text-decoration: underline;
}
</style>
