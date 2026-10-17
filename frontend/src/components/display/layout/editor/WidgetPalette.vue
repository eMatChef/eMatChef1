<template>
  <div class="palette">
    <section v-for="group in groups" :key="group.category">
      <h4 class="palette__title">{{ t(`display.layout.category.${group.category}`) }}</h4>
      <ul class="palette__list">
        <li v-for="def in group.items" :key="def.type">
          <button
            type="button"
            class="palette__item"
            draggable="true"
            :data-testid="`palette-${def.type}`"
            @click="emit('add', def)"
            @dragstart="onDragStart($event, def)"
          >
            <span class="palette__name">{{ t(`display.layout.widget.${def.type}`) }}</span>
            <span v-if="def.data_source === 'ga_demo'" class="palette__demo">{{ t('display.layout.demoData') }}</span>
          </button>
        </li>
      </ul>
    </section>
    <p class="palette__hint">{{ t('display.layout.editor.paletteHint') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { WidgetDefinition } from '@/api/displayLayout'

/** Widget-Bibliothek: Klick fügt an der ersten freien Stelle ein, Ziehen legt auf der Rasterfläche ab. */
const props = defineProps<{ widgets: WidgetDefinition[] }>()
const emit = defineEmits<{ add: [def: WidgetDefinition] }>()
const { t } = useI18n()

const groups = computed(() => {
  const order = ['general', 'ga', 'department']
  return order
    .map((category) => ({ category, items: props.widgets.filter((w) => w.category === category) }))
    .filter((g) => g.items.length)
})

function onDragStart(event: DragEvent, def: WidgetDefinition) {
  event.dataTransfer?.setData('application/x-emc-widget', def.type)
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy'
}
</script>

<style scoped>
.palette { display: flex; flex-direction: column; gap: 12px; }
.palette__title { margin: 0 0 4px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
.palette__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.palette__item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 6px;
  width: 100%;
  padding: 6px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  text-align: left;
  cursor: grab;
}
.palette__item:hover { border-color: #2563eb; background: #eff6ff; }
.palette__demo { font-size: 0.7rem; padding: 1px 6px; border-radius: 999px; background: #fef3c7; color: #92400e; }
.palette__hint { margin: 0; font-size: 0.8rem; color: #6b7280; }
</style>
