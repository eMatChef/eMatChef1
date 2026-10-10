<template>
  <PageShell
    :title="t('gaHelp.page.title')"
    :subtitle="t('gaHelp.page.subtitle')"
    max-width="820px"
  >
    <nav class="ga-help-toc" :aria-label="t('gaHelp.page.tocAria')">
      <div v-for="group in groups" :key="group.id" class="ga-help-toc__group">
        <h2 class="ga-help-toc__heading">{{ t(`gaHelp.groups.${group.id}`) }}</h2>
        <ul class="ga-help-toc__list">
          <li v-for="topic in group.topics" :key="topic.id">
            <RouterLink
              class="ga-help-toc__link"
              :to="helpPageLocation(topic.id)"
            >
              {{ t(gaHelpKey.title(topic.id)) }}
            </RouterLink>
          </li>
        </ul>
      </div>
    </nav>

    <template v-for="group in groups" :key="group.id">
      <section
        v-for="topic in group.topics"
        :id="chapterDomId(topic.id)"
        :key="topic.id"
        class="ga-help-chapter"
        :class="{ 'ga-help-chapter--active': topic.id === activeTopic }"
      >
        <h2 class="ga-help-chapter__title">{{ t(gaHelpKey.title(topic.id)) }}</h2>
        <p class="ga-help-chapter__summary">{{ t(gaHelpKey.summary(topic.id)) }}</p>
        <RouterLink class="ga-help-chapter__open" :to="pageLocation(topic)">
          <v-icon icon="mdi-arrow-right" size="16" aria-hidden="true" />
          {{ t('gaHelp.page.openPage') }}
        </RouterLink>
        <GaHelpFaq :topic-id="topic.id" />
      </section>
    </template>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import PageShell from '@/components/layout/PageShell.vue'
import GaHelpFaq from '@/components/grossanlass/GaHelpFaq.vue'
import { useGaHelp } from '@/composables/useGaHelp'
import {
  GA_HELP_GROUPS,
  gaHelpKey,
  gaHelpTopic,
  gaHelpTopicsByGroup,
  type GaHelpTopic,
} from '@/config/gaHelp'

defineOptions({ name: 'GrossanlassHilfeView' })

const { t } = useI18n()
const route = useRoute()
const { departmentId, helpPageLocation } = useGaHelp()

const groups = GA_HELP_GROUPS.map((id) => ({ id, topics: gaHelpTopicsByGroup(id) })).filter(
  (group) => group.topics.length > 0,
)

const activeTopic = computed(() => {
  const value = route.params.topic
  const id = typeof value === 'string' ? value : ''
  return gaHelpTopic(id) ? id : ''
})

function chapterDomId(id: string): string {
  return `ga-help-${id}`
}

function pageLocation(topic: GaHelpTopic) {
  return { name: topic.routeNames[0], params: { departmentId: departmentId.value } }
}

async function scrollToActive() {
  if (!activeTopic.value) return
  await nextTick()
  document.getElementById(chapterDomId(activeTopic.value))?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(scrollToActive)
watch(activeTopic, scrollToActive)
</script>

<style scoped>
.ga-help-toc {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px 24px;
  margin-bottom: 32px;
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
}

.ga-help-toc__heading {
  margin: 0 0 6px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}

.ga-help-toc__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ga-help-toc__link {
  font-size: 14px;
  color: #0284c7;
  text-decoration: none;
}

.ga-help-toc__link:hover {
  text-decoration: underline;
}

.ga-help-chapter {
  margin-bottom: 32px;
  scroll-margin-top: 80px;
}

.ga-help-chapter--active .ga-help-chapter__title {
  color: #0284c7;
}

.ga-help-chapter__title {
  margin: 0 0 6px;
  font-size: 1.15rem;
  font-weight: 600;
  color: #0f172a;
}

.ga-help-chapter__summary {
  margin: 0 0 8px;
  font-size: 14px;
  color: #475569;
  line-height: 1.55;
}

.ga-help-chapter__open {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 12px;
  font-size: 13px;
  font-weight: 600;
  color: #0284c7;
  text-decoration: none;
}

.ga-help-chapter__open:hover {
  text-decoration: underline;
}
</style>
