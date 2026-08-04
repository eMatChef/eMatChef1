<template>
  <PageShell
    :title="t('help.dokumentation.title')"
    :subtitle="pageSubtitle"
    max-width="720px"
  >
    <section class="help-doc-section">
      <h2 class="help-doc-heading">{{ t('help.dokumentation.happyPathTitle') }}</h2>
      <p class="help-doc-lead">{{ pageLead }}</p>
      <ul class="help-doc-links">
        <li v-for="item in happyPathItems" :key="item.id">
          <RouterLink class="help-doc-link" :to="item.to">
            <v-icon :icon="item.icon" size="20" class="help-doc-link__icon" />
            <span class="help-doc-link__body">
              <span class="help-doc-link__title">{{ item.title }}</span>
              <span class="help-doc-link__desc">{{ item.description }}</span>
            </span>
            <v-icon icon="mdi-chevron-right" size="20" class="help-doc-link__chevron" />
          </RouterLink>
        </li>
      </ul>
    </section>

    <section class="help-doc-section">
      <h2 class="help-doc-heading">{{ t('help.dokumentation.faqTitle') }}</h2>
      <v-expansion-panels variant="accordion" class="help-doc-faq">
        <v-expansion-panel v-for="item in faqItems" :key="item.id" :value="item.id">
          <v-expansion-panel-title>{{ item.question }}</v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="help-doc-faq-answer">{{ item.answer }}</p>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </section>
  </PageShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import PageShell from '@/components/layout/PageShell.vue'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentOnboardingAccess } from '@/composables/useDepartmentOnboardingAccess'

defineOptions({ name: 'HelpDokumentationView' })

const { t } = useI18n()
const route = useRoute()
const authStore = useAuthStore()
const { canUseHelpEinrichtung, canUseSetupChecklist } = useDepartmentOnboardingAccess()

/** MW/DC: volle Doku; User/L1–L3: nur Aktivität (wie Touren). */
const isMwDocs = canUseSetupChecklist

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

function deptPath(suffix: string) {
  const id = departmentId.value
  return id ? `/${id}${suffix}` : '#'
}

const pageSubtitle = computed(() =>
  isMwDocs.value
    ? t('help.dokumentation.subtitle')
    : t('help.dokumentation.subtitleMember')
)

const pageLead = computed(() =>
  isMwDocs.value
    ? t('help.dokumentation.happyPathLead')
    : t('help.dokumentation.happyPathLeadMember')
)

const happyPathItems = computed(() => {
  const items: Array<{
    id: string
    icon: string
    title: string
    description: string
    to: string
  }> = []

  if (canUseHelpEinrichtung.value) {
    items.push({
      id: 'einrichtung',
      icon: 'mdi-compass-outline',
      title: t('help.dokumentation.links.setupTitle'),
      description: isMwDocs.value
        ? t('help.dokumentation.links.setupDescMw')
        : t('help.dokumentation.links.setupDescMember'),
      to: deptPath('/help/einrichtung'),
    })
  }

  items.push({
    id: 'activity',
    icon: 'mdi-calendar-plus',
    title: t('help.dokumentation.links.activityTitle'),
    description: isMwDocs.value
      ? t('help.dokumentation.links.activityDesc')
      : t('help.dokumentation.links.activityDescMember'),
    to: deptPath('/activities'),
  })

  if (!isMwDocs.value) return items

  items.push(
    {
      id: 'pack',
      icon: 'mdi-package-variant-closed',
      title: t('help.dokumentation.links.packTitle'),
      description: t('help.dokumentation.links.packDesc'),
      to: deptPath('/activities'),
    },
    {
      id: 'material',
      icon: 'mdi-package-variant-plus',
      title: t('help.dokumentation.links.materialTitle'),
      description: t('help.dokumentation.links.materialDesc'),
      to: deptPath('/materials'),
    },
    {
      id: 'costs',
      icon: 'mdi-cash-multiple',
      title: t('help.dokumentation.links.costsTitle'),
      description: t('help.dokumentation.links.costsDesc'),
      to: deptPath('/accounting'),
    },
  )

  return items
})

const faqItems = computed(() => {
  if (!isMwDocs.value) {
    return [
      {
        id: 'find-help',
        question: t('help.dokumentation.faq.findHelpMember.q'),
        answer: t('help.dokumentation.faq.findHelpMember.a'),
      },
      {
        id: 'create-activity',
        question: t('help.dokumentation.faq.createActivity.q'),
        answer: t('help.dokumentation.faq.createActivity.a'),
      },
    ]
  }

  return [
    {
      id: 'find-help',
      question: t('help.dokumentation.faq.findHelp.q'),
      answer: t('help.dokumentation.faq.findHelp.a'),
    },
    {
      id: 'create-activity',
      question: t('help.dokumentation.faq.createActivity.q'),
      answer: t('help.dokumentation.faq.createActivity.a'),
    },
    {
      id: 'packing',
      question: t('help.dokumentation.faq.packing.q'),
      answer: t('help.dokumentation.faq.packing.a'),
    },
    {
      id: 'costs',
      question: t('help.dokumentation.faq.costs.q'),
      answer: t('help.dokumentation.faq.costs.a'),
    },
    {
      id: 'invite',
      question: t('help.dokumentation.faq.invite.q'),
      answer: t('help.dokumentation.faq.invite.a'),
    },
    {
      id: 'roles',
      question: t('help.dokumentation.faq.roles.q'),
      answer: t('help.dokumentation.faq.roles.a'),
    },
  ]
})
</script>

<style scoped>
.help-doc-section {
  margin-bottom: 28px;
}

.help-doc-heading {
  margin: 0 0 6px;
  font-size: 1.05rem;
  font-weight: 600;
  color: #0f172a;
}

.help-doc-lead {
  margin: 0 0 14px;
  font-size: 14px;
  color: #64748b;
  line-height: 1.5;
}

.help-doc-links {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.help-doc-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.help-doc-link:hover {
  border-color: #bae6fd;
  background: #f8fafc;
}

.help-doc-link__icon {
  flex-shrink: 0;
  color: #0284c7;
}

.help-doc-link__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.help-doc-link__title {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.help-doc-link__desc {
  font-size: 13px;
  color: #64748b;
  line-height: 1.4;
}

.help-doc-link__chevron {
  flex-shrink: 0;
  color: #94a3b8;
}

.help-doc-faq :deep(.v-expansion-panel) {
  border: 1px solid #e2e8f0;
  border-radius: 12px !important;
  overflow: hidden;
  margin-bottom: 8px;
}

.help-doc-faq-answer {
  margin: 0;
  font-size: 14px;
  color: #475569;
  line-height: 1.5;
}
</style>
