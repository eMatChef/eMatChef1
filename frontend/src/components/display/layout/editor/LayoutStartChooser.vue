<template>
  <div class="chooser">
    <p class="muted">{{ t('display.layout.editor.chooserHint') }}</p>
    <div class="chooser__grid" role="radiogroup" :aria-label="t('display.create.startWith')">
      <button
        v-for="template in templates"
        :key="template.id"
        type="button"
        class="chooser__option"
        :data-testid="`template-${template.id}`"
        @click="emit('choose', { template_id: template.id })"
      >
        <TemplatePreview :template="template" />
        <strong>{{ t(`display.layout.templates.${template.id}.name`) }}</strong>
        <small>{{ t(`display.layout.templates.${template.id}.description`) }}</small>
      </button>
    </div>
    <ESelect
      v-if="sources.length"
      :model-value="null"
      :items="sources.map((s) => ({ value: s.id, title: s.name }))"
      :label="t('display.create.copyFrom')"
      hide-details
      data-testid="copy-from"
      @update:model-value="$event && emit('choose', { source_screen_id: String($event) })"
    />
    <p class="muted">{{ t('display.create.independentCopy') }}</p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { ESelect } from '@/components/form/base'
import type { LayoutTemplate } from '@/api/displayLayout'
import type { DisplayScreenSettings } from '@/api/displayScreens'
import TemplatePreview from '../TemplatePreview.vue'

/** Startpunkt eines Layouts: leere Anzeige, offizielle Vorlage (unabhängige Kopie) oder Kopie eines anderen Infoscreens. */
defineProps<{ templates: LayoutTemplate[]; sources: DisplayScreenSettings[] }>()
const emit = defineEmits<{ choose: [seed: { template_id?: string; source_screen_id?: string }] }>()
const { t } = useI18n()
</script>

<style scoped>
.chooser { display: flex; flex-direction: column; gap: 12px; }
.chooser__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 12px; }
.chooser__option {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px;
  text-align: left;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
}
.chooser__option:hover,
.chooser__option:focus-visible { border-color: #2563eb; background: #eff6ff; }
.chooser__option small { color: #6b7280; }
.muted { color: #6b7280; margin: 0; }
</style>
