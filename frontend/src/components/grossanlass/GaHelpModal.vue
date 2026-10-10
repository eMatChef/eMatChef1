<template>
  <EDialog
    v-if="topicId"
    :model-value="modalOpen"
    :max-width="640"
    :title="t(gaHelpKey.title(topicId))"
    @update:model-value="onUpdate"
  >
    <p class="ga-help-modal__eyebrow">{{ t('gaHelp.modal.eyebrow') }}</p>
    <p class="ga-help-modal__summary">{{ t(gaHelpKey.summary(topicId)) }}</p>

    <template v-if="hasFaq">
      <h3 class="ga-help-modal__heading">{{ t('gaHelp.modal.faqTitle') }}</h3>
      <GaHelpFaq :topic-id="topicId" />
    </template>

    <template #actions>
      <!-- Immer neuer Tab: offene Formulare und ungespeicherte Eingaben der Seite bleiben unberührt. -->
      <a
        class="ga-help-modal__more"
        :href="moreHref"
        target="_blank"
        rel="noopener noreferrer"
      >
        {{ t('gaHelp.modal.more') }}
        <v-icon icon="mdi-open-in-new" size="16" aria-hidden="true" />
      </a>
      <v-spacer />
      <EButton variant="secondary" @click="close">{{ t('gaHelp.modal.close') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { EButton, EDialog } from '@/components/form/base'
import GaHelpFaq from '@/components/grossanlass/GaHelpFaq.vue'
import { useGaHelp } from '@/composables/useGaHelp'
import { gaHelpKey, gaHelpTopic } from '@/config/gaHelp'

defineOptions({ name: 'GaHelpModal' })

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { topicId, modalOpen, helpPageLocation, close } = useGaHelp()

const hasFaq = computed(() => (gaHelpTopic(topicId.value)?.faq.length ?? 0) > 0)
const moreHref = computed(() => router.resolve(helpPageLocation(topicId.value)).href)

function onUpdate(value: boolean) {
  if (!value) close()
}

// Seitenwechsel (z. B. Sidebar bei offenem Modal) schliesst die Hilfe; sie gehört zur Seite davor.
watch(() => route.fullPath, close)
</script>

<style scoped>
.ga-help-modal__eyebrow {
  margin: 0 0 4px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #0284c7;
}

.ga-help-modal__summary {
  margin: 0 0 16px;
  font-size: 14px;
  color: #475569;
  line-height: 1.55;
}

.ga-help-modal__heading {
  margin: 0 0 8px;
  font-size: 0.95rem;
  font-weight: 600;
  color: #0f172a;
}

.ga-help-modal__more {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: #0284c7;
  text-decoration: none;
}

.ga-help-modal__more:hover {
  text-decoration: underline;
}
</style>
