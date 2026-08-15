<template>
  <div class="module-settings">
    <div class="header-section">
      <h1>{{ t('settings.moduleSettings.title') }}</h1>
      <p class="description">{{ t('settings.moduleSettings.subtitle') }}</p>
    </div>

    <div class="module-settings__accordions">
      <details
        class="info-card settings-accordion"
        :open="openAccordion === 'activities'"
      >
        <summary
          class="settings-accordion__summary"
          @click="onAccordionSummaryClick('activities', $event)"
        >
          <span class="settings-accordion__title">
            <v-icon icon="mdi-calendar" size="20" class="settings-accordion__icon" />
            {{ t('settings.nav.activities') }}
          </span>
          <span class="settings-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="settings-accordion__body">
          <ActivitySettingsView v-if="mounted.activities" embedded />
        </div>
      </details>

      <details
        class="info-card settings-accordion"
        :open="openAccordion === 'workshop'"
      >
        <summary
          class="settings-accordion__summary"
          @click="onAccordionSummaryClick('workshop', $event)"
        >
          <span class="settings-accordion__title">
            <v-icon icon="mdi-wrench" size="20" class="settings-accordion__icon" />
            {{ t('settings.nav.workshop') }}
          </span>
          <span class="settings-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="settings-accordion__body">
          <WorkshopSettingsView v-if="mounted.workshop" embedded />
        </div>
      </details>

      <details
        class="info-card settings-accordion"
        :open="openAccordion === 'accounting'"
      >
        <summary
          class="settings-accordion__summary"
          @click="onAccordionSummaryClick('accounting', $event)"
        >
          <span class="settings-accordion__title">
            <v-icon icon="mdi-cash-register" size="20" class="settings-accordion__icon" />
            {{ t('settings.nav.accounting') }}
          </span>
          <span class="settings-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="settings-accordion__body">
          <AccountingSettingsView v-if="mounted.accounting" embedded />
        </div>
      </details>

      <details
        class="info-card settings-accordion"
        :open="openAccordion === 'zeit'"
      >
        <summary
          class="settings-accordion__summary"
          @click="onAccordionSummaryClick('zeit', $event)"
        >
          <span class="settings-accordion__title">
            <v-icon icon="mdi-clock-outline" size="20" class="settings-accordion__icon" />
            {{ t('settings.nav.timeLocation') }}
          </span>
          <span class="settings-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="settings-accordion__body">
          <GeneralSettingsView v-if="mounted.zeit" embedded />
        </div>
      </details>
    </div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ActivitySettingsView from '@/views/settings/ActivitySettingsView.vue'
import WorkshopSettingsView from '@/views/settings/WorkshopSettingsView.vue'
import AccountingSettingsView from '@/views/settings/AccountingSettingsView.vue'
import GeneralSettingsView from '@/views/settings/GeneralSettingsView.vue'

type ModuleAccordionId = 'activities' | 'workshop' | 'accounting' | 'zeit'

const { t } = useI18n()

const openAccordion = ref<ModuleAccordionId | null>(null)
const accordionScrollEnabled = ref(false)
const mounted = reactive({
  activities: false,
  workshop: false,
  accounting: false,
  zeit: false,
})

onMounted(() => {
  nextTick(() => {
    accordionScrollEnabled.value = true
  })
})

function mountPanel(id: ModuleAccordionId) {
  mounted[id] = true
}

function scrollAccordionIntoView(el: HTMLElement) {
  if (!accordionScrollEnabled.value) return
  nextTick(() => {
    requestAnimationFrame(() => {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
  })
}

function onAccordionSummaryClick(id: ModuleAccordionId, event: MouseEvent) {
  event.preventDefault()
  const el = (event.currentTarget as HTMLElement).closest('details') as HTMLDetailsElement | null
  if (!el) return

  if (openAccordion.value === id) {
    openAccordion.value = null
    return
  }

  openAccordion.value = id
  mountPanel(id)
  scrollAccordionIntoView(el)
}
</script>

<style scoped>
.module-settings {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.header-section h1 {
  margin: 0 0 4px;
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
}

.description {
  margin: 0;
  color: #6b7280;
  font-size: 14px;
}

.module-settings__accordions {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.settings-accordion {
  padding: 0;
  overflow: hidden;
  scroll-margin-top: 24px;
}

.settings-accordion__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  cursor: pointer;
  list-style: none;
  user-select: none;
}

.settings-accordion__summary::-webkit-details-marker {
  display: none;
}

.settings-accordion__title {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
}

.settings-accordion__icon {
  color: #3b82f6;
}

.settings-accordion__chevron {
  color: #9ca3af;
  font-size: 14px;
  transition: transform 0.15s ease;
}

.settings-accordion[open] .settings-accordion__chevron {
  transform: rotate(180deg);
}

.settings-accordion__body {
  padding: 0 16px 16px;
  border-top: 1px solid #e5e7eb;
}
</style>
