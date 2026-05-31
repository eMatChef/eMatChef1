<template>
  <footer class="plt-footer" role="contentinfo">
    <p class="plt-footer-copy">© {{ copyrightYear }}</p>
  </footer>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const copyrightYear = computed(() => new Date().getFullYear())
</script>
