<template>
  <div class="mein-ressort-content mein-ressort-skeleton" aria-hidden="true">
    <section v-if="helperView" class="helper-scan-panel">
      <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--scan-label" />
      <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--scan-input" />
    </section>

    <div v-for="cardIndex in ressortCards" :key="`ressort-${cardIndex}`" class="ressort-card">
      <div class="ressort-card__head">
        <span class="mein-ressort-skeleton__icon" />
        <div class="mein-ressort-skeleton__head-text">
          <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--title" />
          <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--badge" />
        </div>
      </div>
      <div v-if="!helperView" class="wish-mini-list">
        <div v-for="rowIndex in 2" :key="`wish-${cardIndex}-${rowIndex}`" class="wish-mini-row">
          <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--wish" />
          <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--meta" />
        </div>
      </div>
    </div>

    <section v-if="helperView" class="assignments-panel">
      <div class="assignments-panel__head">
        <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--section" />
        <span class="mein-ressort-skeleton__pill" />
      </div>
      <ul class="assignments-list">
        <li v-for="rowIndex in 2" :key="`trip-${rowIndex}`">
          <div class="mein-ressort-skeleton__assignment">
            <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--assignment-title" />
            <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--assignment-meta" />
            <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--assignment-status" />
          </div>
        </li>
      </ul>
    </section>

    <section v-if="helperView" class="assignments-panel">
      <div class="assignments-panel__head">
        <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--section" />
      </div>
      <ul class="assignments-list">
        <li v-for="rowIndex in 2" :key="`einsatz-${rowIndex}`">
          <div class="mein-ressort-skeleton__assignment">
            <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--assignment-title" />
            <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--assignment-meta" />
            <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--assignment-status" />
          </div>
        </li>
      </ul>
    </section>

    <div v-if="!helperView" class="kosten-panel">
      <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--section" />
      <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--kosten" />
      <div v-for="rowIndex in 2" :key="`cost-${rowIndex}`" class="wish-mini-row">
        <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--wish" />
        <span class="mein-ressort-skeleton__line mein-ressort-skeleton__line--meta" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  helperView?: boolean
}>()

const ressortCards = computed(() => (props.helperView ? 1 : 2))
</script>

<style scoped>
.mein-ressort-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ressort-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
}

.ressort-card__head {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 10px;
}

.wish-mini-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.wish-mini-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  background: #f9fafb;
  border-radius: 6px;
}

.assignments-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
}

.assignments-panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.assignments-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}

.assignments-list li {
  margin: 0;
  padding: 0;
}

.helper-scan-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.mein-ressort-skeleton__line--scan-label {
  height: 14px;
  width: 72px;
}

.mein-ressort-skeleton__line--scan-input {
  height: 40px;
  width: 100%;
}

.kosten-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px;
  background: #fff;
}

.mein-ressort-skeleton__line,
.mein-ressort-skeleton__icon,
.mein-ressort-skeleton__pill {
  display: block;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: mein-ressort-skeleton-shimmer 1.2s ease-in-out infinite;
  border-radius: 6px;
}

.mein-ressort-skeleton__assignment {
  display: block;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
}

.mein-ressort-skeleton__icon {
  width: 20px;
  height: 20px;
  border-radius: 999px;
  flex-shrink: 0;
}

.mein-ressort-skeleton__head-text {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.mein-ressort-skeleton__line--title {
  height: 16px;
  width: 42%;
  max-width: 220px;
}

.mein-ressort-skeleton__line--badge {
  height: 12px;
  width: 28%;
  max-width: 140px;
}

.mein-ressort-skeleton__line--wish {
  height: 14px;
  width: 55%;
  max-width: 280px;
}

.mein-ressort-skeleton__line--meta {
  height: 12px;
  width: 38%;
  max-width: 200px;
}

.mein-ressort-skeleton__line--section {
  height: 15px;
  width: 120px;
}

.mein-ressort-skeleton__line--kosten {
  height: 12px;
  width: 70%;
  max-width: 360px;
  margin: 6px 0 10px;
}

.mein-ressort-skeleton__pill {
  width: 88px;
  height: 28px;
  border-radius: 999px;
}

.mein-ressort-skeleton__line--assignment-title {
  height: 15px;
  width: 48%;
  max-width: 240px;
  margin-bottom: 6px;
}

.mein-ressort-skeleton__line--assignment-meta {
  height: 12px;
  width: 62%;
  max-width: 320px;
  margin-bottom: 6px;
}

.mein-ressort-skeleton__line--assignment-status {
  height: 12px;
  width: 24%;
  max-width: 120px;
}

@keyframes mein-ressort-skeleton-shimmer {
  0% {
    background-position: 200% 0;
  }

  100% {
    background-position: -200% 0;
  }
}
</style>
