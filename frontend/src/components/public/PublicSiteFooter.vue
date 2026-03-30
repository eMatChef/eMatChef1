<template>
  <footer class="plt-footer" role="contentinfo">
    <div class="plt-footer-inner">
      <nav class="plt-footer-nav" aria-label="Fußzeile">
        <RouterLink to="/blog">Blog</RouterLink>
        <RouterLink to="/faq">FAQ</RouterLink>
        <RouterLink to="/tos">Nutzung &amp; Datenschutz</RouterLink>
        <RouterLink to="/impressum">Impressum</RouterLink>
      </nav>
      <p class="plt-footer-copy">© {{ year }} eMatChef</p>
    </div>
  </footer>
</template>

<script setup lang="ts">
const year = new Date().getFullYear()
</script>
