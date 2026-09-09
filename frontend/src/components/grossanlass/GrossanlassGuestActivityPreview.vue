<template>
  <section class="ga-guest-preview">
    <div class="ga-guest-preview__intro">
      <h3>{{ t('grossanlass.planung.freigabe.guestPreviewTitle') }}</h3>
      <p>{{ t('grossanlass.planung.freigabe.guestPreviewHint') }}</p>
    </div>

    <div class="ga-guest-preview__frame" role="region" :aria-label="t('grossanlass.planung.freigabe.guestPreviewTitle')">
      <div class="ga-guest-preview__chrome">
        <span class="ga-guest-preview__chrome-dept">{{ guestDeptName }}</span>
        <span class="ga-guest-preview__chrome-badge">{{ t('grossanlass.planung.freigabe.guestPreviewBadge') }}</span>
      </div>

      <template v-if="pane === 'list'">
        <div class="ga-guest-preview__list-head">
          <div>
            <h4>{{ t('activities.title') }}</h4>
            <p>{{ t('activities.subtitle') }}</p>
          </div>
          <span class="ga-guest-preview__fake-btn">{{ t('activities.create') }}</span>
        </div>
        <div class="ga-guest-preview__tabs">
          <span class="is-on">{{ t('activities.tabs.open') }}</span>
          <span>{{ t('activities.tabs.upcoming') }}</span>
          <span>{{ t('activities.tabs.all') }}</span>
        </div>
        <button type="button" class="ga-guest-preview__row" @click="pane = 'detail'">
          <span class="status-dot draft" />
          <span class="ga-guest-preview__row-body">
            <strong>{{ activityName }}</strong>
            <span class="activity-list-type-badges activity-list-type-badges--inline">
              <span class="type-badge" :class="guestType">{{ typeLabel }}</span>
            </span>
            <span class="ga-guest-preview__row-meta">
              {{ periodLabel }}
              ·
              <span class="status-label draft">{{ t('activities.status.draft') }}</span>
            </span>
          </span>
          <v-icon icon="mdi-chevron-right" size="22" />
        </button>
      </template>

      <template v-else>
        <header class="ga-guest-preview__detail-head">
          <EButton variant="secondary" size="small" @click="pane = 'list'">
            <v-icon icon="mdi-arrow-left" start size="18" />
            {{ t('activities.detail.backToList') }}
          </EButton>
          <div class="ga-guest-preview__detail-title">
            <span class="type-badge" :class="guestType">{{ typeLabel }}</span>
            <h4>{{ activityName }}</h4>
            <span class="status-label draft">{{ t('activities.status.draft') }}</span>
          </div>
        </header>

        <div class="ga-guest-preview__detail-tabs" role="tablist">
          <button
            v-for="tab in detailTabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="detailTab === tab.id"
            :class="{ 'is-on': detailTab === tab.id }"
            @click="detailTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </div>

        <v-alert
          v-if="detailTab === 'overview'"
          type="warning"
          variant="tonal"
          density="compact"
          class="ga-guest-preview__draft"
          icon="mdi-information-outline"
        >
          <strong>{{ t('activities.detail.draftLabel') }}</strong>
          {{ t('activities.detail.draftBannerWithGroup') }}
          {{ t('activities.detail.draftBannerSubmitCampEvent') }}
        </v-alert>

        <div v-if="detailTab === 'overview'" class="ga-guest-preview__cards">
          <div class="ga-guest-preview__card">
            <h5>{{ t('activities.draftOverview.sectionBasics') }}</h5>
            <dl>
              <div>
                <dt>{{ t('activities.detail.labelDepartment') }}</dt>
                <dd>{{ guestDeptName }}</dd>
              </div>
              <div>
                <dt>{{ t('common.name') }}</dt>
                <dd>{{ activityName }}</dd>
              </div>
              <div>
                <dt>{{ t('activities.jsMaterial.includeToggle') }}</dt>
                <dd>{{ t('grossanlass.planung.freigabe.guestPreviewJsOff') }}</dd>
              </div>
              <div v-if="locationText">
                <dt>{{ t('activities.wizard.form.venueLabel') }}</dt>
                <dd>{{ locationText }}</dd>
              </div>
            </dl>
          </div>
          <div class="ga-guest-preview__card">
            <h5>{{ t('activities.detail.sectionPeriod') }}</h5>
            <dl>
              <div>
                <dt>{{ t('activities.detail.labelUsage') }}</dt>
                <dd>{{ periodLabel }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <p v-else-if="detailTab === 'material'" class="ga-guest-preview__empty">
          {{ t('activities.materialLinesTable.defaultEmpty') }}
        </p>
        <p v-else class="ga-guest-preview__empty">
          {{ t('grossanlass.planung.freigabe.guestPreviewHistoryEmpty') }}
        </p>
      </template>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import type { GrossanlassGuestActivityType, GrossanlassPlanungOverview } from '@/api/grossanlassPlanung'
import '@/styles/ui/activity-type-badges.css'
import '@/styles/activity-status.css'

defineOptions({ name: 'GrossanlassGuestActivityPreview' })

const props = defineProps<{
  pack: GrossanlassPlanungOverview
}>()

const { t } = useI18n()

const pane = ref<'list' | 'detail'>('list')
const detailTab = ref<'overview' | 'material' | 'history'>('overview')

const guestType = computed<GrossanlassGuestActivityType>(
  () => (props.pack.config.guest_activity_type === 'event' ? 'event' : 'camp'),
)

const typeLabel = computed(() =>
  guestType.value === 'event' ? t('activities.types.event') : t('activities.types.camp'),
)

const activityName = computed(() => props.pack.department_name || t('activities.detail.fallbackTitle'))

const guestDeptName = computed(() => {
  const first = props.pack.participants[0]
  return first?.name || t('grossanlass.planung.freigabe.guestPreviewExampleDept')
})

const locationText = computed(() => (props.pack.config.location_text || '').trim())

const periodLabel = computed(() => {
  const start = props.pack.config.planned_event_start
  if (!start) return t('activities.detail.usageNotSet')
  const from = new Date(start)
  const endRaw = props.pack.config.planned_event_end
  const fmt = (d: Date) =>
    d.toLocaleString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  if (!endRaw) return fmt(from)
  return `${fmt(from)} – ${fmt(new Date(endRaw))}`
})

const detailTabs = computed(() => [
  { id: 'overview' as const, label: t('activities.detail.tabOverview') },
  { id: 'material' as const, label: t('common.material') },
  { id: 'history' as const, label: t('activities.detail.tabHistory') },
])
</script>

<style scoped>
.ga-guest-preview {
  margin-top: 22px;
  max-width: 720px;
}
.ga-guest-preview__intro h3 {
  margin: 0 0 6px;
  font-size: 0.95rem;
}
.ga-guest-preview__intro p {
  margin: 0 0 12px;
  color: #64748b;
  font-size: 0.88rem;
}
.ga-guest-preview__frame {
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  background: #f8fafc;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}
.ga-guest-preview__chrome {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #1e293b;
  color: #e2e8f0;
  font-size: 0.75rem;
}
.ga-guest-preview__chrome-badge {
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  font-size: 0.68rem;
  color: #94a3b8;
}
.ga-guest-preview__list-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  padding: 14px 16px 8px;
  background: #fff;
}
.ga-guest-preview__list-head h4 {
  margin: 0;
  font-size: 1.15rem;
}
.ga-guest-preview__list-head p {
  margin: 2px 0 0;
  color: #64748b;
  font-size: 0.82rem;
}
.ga-guest-preview__fake-btn {
  flex-shrink: 0;
  padding: 6px 10px;
  border-radius: 8px;
  background: var(--color-primary, #059669);
  color: #fff;
  font-size: 0.78rem;
  font-weight: 600;
  opacity: 0.55;
}
.ga-guest-preview__tabs {
  display: flex;
  gap: 4px;
  padding: 0 12px 10px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
}
.ga-guest-preview__tabs span {
  padding: 6px 10px;
  border-radius: 8px;
  font-size: 0.8rem;
  color: #64748b;
}
.ga-guest-preview__tabs span.is-on {
  background: #ecfdf5;
  color: #047857;
  font-weight: 600;
}
.ga-guest-preview__row {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  text-align: left;
  padding: 12px 16px;
  background: #fff;
  border: 0;
  cursor: pointer;
}
.ga-guest-preview__row:hover {
  background: #f8fafc;
}
.ga-guest-preview__row-body {
  display: grid;
  gap: 4px;
  min-width: 0;
  flex: 1;
}
.ga-guest-preview__row-body strong {
  font-size: 0.95rem;
}
.ga-guest-preview__row-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  color: #64748b;
  font-size: 0.8rem;
}
.ga-guest-preview__detail-head {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 16px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
}
.ga-guest-preview__detail-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.ga-guest-preview__detail-title h4 {
  margin: 0;
  font-size: 1.1rem;
}
.ga-guest-preview__detail-tabs {
  display: flex;
  gap: 2px;
  padding: 0 8px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
}
.ga-guest-preview__detail-tabs button {
  border: 0;
  background: transparent;
  padding: 10px 12px;
  font-size: 0.85rem;
  color: #64748b;
  cursor: pointer;
  border-bottom: 2px solid transparent;
}
.ga-guest-preview__detail-tabs button.is-on {
  color: var(--color-primary, #059669);
  font-weight: 600;
  border-bottom-color: var(--color-primary, #059669);
}
.ga-guest-preview__draft {
  margin: 12px 16px 0;
}
.ga-guest-preview__cards {
  display: grid;
  gap: 12px;
  padding: 12px 16px 16px;
}
.ga-guest-preview__card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
}
.ga-guest-preview__card h5 {
  margin: 0 0 10px;
  font-size: 0.88rem;
}
.ga-guest-preview__card dl {
  margin: 0;
  display: grid;
  gap: 10px;
}
.ga-guest-preview__card dt {
  font-size: 0.72rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.ga-guest-preview__card dd {
  margin: 2px 0 0;
  font-size: 0.9rem;
}
.ga-guest-preview__empty {
  margin: 0;
  padding: 24px 16px;
  color: #64748b;
  font-size: 0.88rem;
}
</style>
