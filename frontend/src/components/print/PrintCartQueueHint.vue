<template>
  <p v-if="queueHint" class="print-cart-hint">{{ queueHint }}</p>
</template>

<script setup lang="ts">
import { usePrintCart } from '@/composables/usePrintCart'

const { queueHint } = usePrintCart()
</script>

<style scoped>
.print-cart-hint {
  margin: 0;
  font-size: 12px;
  color: #4b5563;
  line-height: 1.4;
}
</style>
