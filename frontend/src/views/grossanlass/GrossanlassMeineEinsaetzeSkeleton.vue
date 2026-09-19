<template>
  <div class="meine-einsaetze-content meine-einsaetze-skeleton" aria-hidden="true">
    <section class="ga-trips meine-einsaetze-skeleton__trips">
      <div class="ga-trips__head">
        <span class="meine-einsaetze-skeleton__line meine-einsaetze-skeleton__line--section" />
        <span class="meine-einsaetze-skeleton__count" />
      </div>
      <ul class="ga-trips__list">
        <li v-for="rowIndex in 2" :key="`trip-${rowIndex}`" class="ga-trips__item">
          <div class="meine-einsaetze-skeleton__trip">
            <span class="meine-einsaetze-skeleton__line meine-einsaetze-skeleton__line--title" />
            <span class="meine-einsaetze-skeleton__line meine-einsaetze-skeleton__line--meta" />
            <span class="meine-einsaetze-skeleton__line meine-einsaetze-skeleton__line--meta-short" />
            <span class="meine-einsaetze-skeleton__pill" />
          </div>
        </li>
      </ul>
    </section>

    <section class="meine-einsaetze-section">
      <span class="meine-einsaetze-skeleton__line meine-einsaetze-skeleton__line--section" />
      <ul class="meine-einsaetze-list">
        <li v-for="rowIndex in 2" :key="`einsatz-${rowIndex}`">
          <div class="meine-einsaetze-skeleton__row">
            <span class="meine-einsaetze-skeleton__line meine-einsaetze-skeleton__line--title" />
            <span class="meine-einsaetze-skeleton__line meine-einsaetze-skeleton__line--meta" />
            <span class="meine-einsaetze-skeleton__line meine-einsaetze-skeleton__line--status" />
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'GrossanlassMeineEinsaetzeSkeleton' })
</script>

<style scoped>
.meine-einsaetze-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.meine-einsaetze-skeleton__trips {
  margin: 0;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  padding: 14px 16px;
}

.meine-einsaetze-skeleton__line,
.meine-einsaetze-skeleton__count,
.meine-einsaetze-skeleton__pill {
  display: block;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: meine-einsaetze-skeleton-shimmer 1.2s ease-in-out infinite;
  border-radius: 6px;
}

.meine-einsaetze-skeleton__trip,
.meine-einsaetze-skeleton__row {
  display: block;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
}

.meine-einsaetze-skeleton__line--section {
  height: 16px;
  width: 120px;
}

.meine-einsaetze-skeleton__count {
  width: 20px;
  height: 14px;
}

.meine-einsaetze-skeleton__line--title {
  height: 15px;
  width: 46%;
  max-width: 240px;
  margin-bottom: 6px;
}

.meine-einsaetze-skeleton__line--meta {
  height: 12px;
  width: 64%;
  max-width: 340px;
  margin-bottom: 6px;
}

.meine-einsaetze-skeleton__line--meta-short {
  height: 12px;
  width: 36%;
  max-width: 180px;
  margin-bottom: 8px;
}

.meine-einsaetze-skeleton__line--status {
  height: 12px;
  width: 22%;
  max-width: 110px;
}

.meine-einsaetze-skeleton__pill {
  width: 72px;
  height: 28px;
  border-radius: 999px;
}

.ga-trips__head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.ga-trips__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}

.ga-trips__item {
  padding: 0;
  border: 0;
}

.meine-einsaetze-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.meine-einsaetze-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}

@keyframes meine-einsaetze-skeleton-shimmer {
  0% {
    background-position: 200% 0;
  }

  100% {
    background-position: -200% 0;
  }
}
</style>
