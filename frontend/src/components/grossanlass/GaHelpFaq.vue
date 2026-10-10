<template>
  <v-expansion-panels v-if="topic && topic.faq.length" variant="accordion" class="ga-help-faq">
    <v-expansion-panel v-for="faqId in topic.faq" :key="faqId" :value="faqId">
      <v-expansion-panel-title>{{ t(gaHelpKey.question(topic.id, faqId)) }}</v-expansion-panel-title>
      <v-expansion-panel-text>
        <p class="ga-help-faq__answer">{{ t(gaHelpKey.answer(topic.id, faqId)) }}</p>
      </v-expansion-panel-text>
    </v-expansion-panel>
  </v-expansion-panels>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { gaHelpKey, gaHelpTopic } from '@/config/gaHelp'

defineOptions({ name: 'GaHelpFaq' })

const props = defineProps<{ topicId: string }>()

const { t } = useI18n()
const topic = computed(() => gaHelpTopic(props.topicId))
</script>

<style scoped>
.ga-help-faq :deep(.v-expansion-panel) {
  border: 1px solid #e2e8f0;
  border-radius: 12px !important;
  overflow: hidden;
  margin-bottom: 8px;
}

.ga-help-faq__answer {
  margin: 0;
  font-size: 14px;
  color: #475569;
  line-height: 1.5;
}
</style>
