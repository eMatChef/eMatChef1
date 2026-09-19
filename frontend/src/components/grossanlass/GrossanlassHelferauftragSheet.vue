<template>
  <article class="helfer-sheet">
    <header class="helfer-sheet__head">
      <h1>{{ title }}</h1>
      <p v-if="windowLabel" class="helfer-sheet__window">{{ windowLabel }}</p>
      <p v-if="place" class="helfer-sheet__code">{{ place.public_code }}</p>
    </header>

    <div class="helfer-sheet__grid">
      <div v-if="map?.image_url" class="helfer-sheet__map">
        <img :src="map.image_url" :alt="map.name" />
        <span
          v-if="pinStyle"
          class="helfer-sheet__pin"
          :style="pinStyle"
          aria-hidden="true"
        />
      </div>
      <div v-else class="helfer-sheet__map helfer-sheet__map--empty">
        {{ t('grossanlass.planung.ressorts.printNoMap') }}
      </div>
      <div v-if="place?.qr_url" class="helfer-sheet__qr">
        <PublicQrTag
          :url="place.qr_url"
          :code="place.public_code"
          :size="160"
          :image-label="place.name"
          :image-entity-id="place.id"
        />
      </div>
    </div>

    <section>
      <h2>{{ t('grossanlass.planung.ressorts.tasksHeading') }}</h2>
      <ol v-if="tasks.length" class="helfer-sheet__list">
        <li v-for="task in tasks" :key="task.id">{{ task.title }}</li>
      </ol>
      <p v-else class="muted">{{ t('grossanlass.planung.ressorts.tasksEmpty') }}</p>
    </section>

    <section>
      <h2>{{ t('grossanlass.planung.ressorts.materialHeading') }}</h2>
      <ul v-if="material.length" class="helfer-sheet__list">
        <li v-for="line in material" :key="line.id">
          {{ line.quantity }}× {{ line.label }}
        </li>
      </ul>
      <p v-else class="muted">{{ t('grossanlass.planung.ressorts.materialEmpty') }}</p>
    </section>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import type { GaBauprojektBriefing } from '@/api/grossanlassBauprojekt'
import { formatBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'

const props = defineProps<{
  briefing: GaBauprojektBriefing
}>()

const { t } = useI18n()

const title = computed(() => props.briefing.group?.name || props.briefing.place?.name || '')
const place = computed(() => props.briefing.place)
const map = computed(() => props.briefing.map)
const tasks = computed(() => props.briefing.tasks)
const material = computed(() => props.briefing.material)
const windowLabel = computed(() =>
  formatBauprojektWindow(props.briefing.window_start, props.briefing.window_end),
)

const pinStyle = computed(() => {
  const p = place.value
  if (p?.map_x == null || p.map_y == null) return null
  return {
    left: `${p.map_x * 100}%`,
    top: `${p.map_y * 100}%`,
  }
})
</script>

<style scoped>
.helfer-sheet {
  background: #fff;
  color: #111;
  padding: 16px;
  max-width: 720px;
}
.helfer-sheet__head h1 {
  margin: 0 0 4px;
  font-size: 1.35rem;
}
.helfer-sheet__window,
.helfer-sheet__code,
.muted {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0 0 8px;
}
.helfer-sheet__grid {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 16px;
  align-items: start;
  margin: 12px 0 20px;
}
.helfer-sheet__map {
  position: relative;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  min-height: 160px;
}
.helfer-sheet__map img {
  display: block;
  width: 100%;
  height: auto;
}
.helfer-sheet__map--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  color: #64748b;
}
.helfer-sheet__pin {
  position: absolute;
  width: 14px;
  height: 14px;
  margin: -7px 0 0 -7px;
  border-radius: 50%;
  background: #dc2626;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #991b1b;
}
.helfer-sheet h2 {
  font-size: 1rem;
  margin: 16px 0 8px;
}
.helfer-sheet__list {
  margin: 0;
  padding-left: 1.2rem;
}
.helfer-sheet__list li {
  margin: 4px 0;
}
@media print {
  .helfer-sheet {
    max-width: none;
    padding: 0;
  }
}
</style>
