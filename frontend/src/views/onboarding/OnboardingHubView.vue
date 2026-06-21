<template>
  <PageShell
    :title="t('onboarding.hub.title')"
    :subtitle="t('onboarding.hub.subtitle')"
    max-width="720px"
  >
    <div v-if="isLoading" class="hub-muted">{{ t('common.loading') }}</div>

    <template v-else>
      <v-expansion-panels
        v-model="expandedPanels"
        multiple
        variant="accordion"
        class="hub-accordion"
      >
        <v-expansion-panel value="tours" readonly>
          <v-expansion-panel-title>
            <span class="hub-panel-title">{{ t('onboarding.hub.toursTitle') }}</span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="hub-muted hub-panel-lead">{{ t('onboarding.hub.toursLead') }}</p>
            <OnboardingTourList />
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="setup">
          <v-expansion-panel-title>
            <span class="hub-panel-title">{{ t('onboarding.hub.setupTitle') }}</span>
            <v-chip
              v-if="!isFullyDone && !expandedPanels.includes('setup')"
              size="x-small"
              color="primary"
              variant="tonal"
              class="hub-open-badge"
            >
              {{ t('onboarding.hub.openCountBadge', { count: openCount }) }}
            </v-chip>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <ECard class="hub-progress-card" variant="outlined">
              <div class="hub-progress-header">
                <span class="hub-progress-label">
                  {{ t('onboarding.hub.progressLabel', { done: doneCount, total: totalItems }) }}
                </span>
                <span class="hub-progress-percent">{{ progressPercent }}%</span>
              </div>
              <div class="hub-progress-track">
                <div class="hub-progress-fill" :style="{ width: `${progressPercent}%` }"></div>
              </div>
            </ECard>

            <ul class="checklist">
              <li
                v-for="item in checklistItems"
                :key="item.key"
                class="checklist-item"
                :class="{
                  'checklist-item--done': item.done,
                  'checklist-item--skipped': item.skipped,
                }"
              >
                <span class="checklist-icon" aria-hidden="true">
                  <v-icon
                    :icon="checklistIcon(item)"
                    size="20"
                    :color="checklistIconColor(item)"
                  />
                </span>
                <div class="checklist-body">
                  <span class="checklist-label">{{ t(item.labelKey) }}</span>
                  <p class="checklist-desc">{{ t(item.descriptionKey) }}</p>
                  <span v-if="item.tier !== 'required'" class="checklist-tier">
                    {{ tierLabel(item.tier) }}
                  </span>
                  <p v-if="item.skipped" class="checklist-hint">{{ t('onboarding.hub.skippedHint') }}</p>
                  <p v-else-if="item.alwaysResolved" class="checklist-hint checklist-hint--done">
                    {{ t('onboarding.hub.configureHint') }}
                  </p>
                  <p v-else-if="item.done" class="checklist-hint checklist-hint--done">
                    {{ t('onboarding.hub.detectedDoneHint') }}
                  </p>
                </div>
                <div class="checklist-actions">
                  <EButton
                    variant="secondary"
                    size="small"
                    @click="openChecklistItem(item)"
                  >
                    {{ item.resolved ? t('onboarding.hub.reviewItem') : t('onboarding.hub.openItem') }}
                  </EButton>
                  <EButton
                    v-if="canSkipItem(item)"
                    variant="text"
                    size="small"
                    @click="skipChecklistItem(item)"
                  >
                    {{ t('onboarding.hub.skipItem') }}
                  </EButton>
                </div>
              </li>
            </ul>

            <div v-if="!isFullyDone" class="hub-setup-actions">
              <EButton variant="primary" size="small" @click="continueSetup">
                {{ t('onboarding.hub.continueSetup') }}
              </EButton>
            </div>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </template>
  </PageShell>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import PageShell from '@/components/layout/PageShell.vue'
import OnboardingTourList from '@/components/onboarding/OnboardingTourList.vue'
import { EButton, ECard } from '@/components/form/base'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useOnboardingChecklist, type OnboardingChecklistRow } from '@/composables/useOnboardingChecklist'
import { resolveChecklistItemRoute } from '@/utils/onboardingChecklist'

defineOptions({ name: 'OnboardingHubView' })

const { t } = useI18n()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()

const {
  departmentId,
  doneCount,
  openCount,
  totalItems,
  progressPercent,
  checklistItems,
  isLoading,
  isFullyDone,
  markItemSkipped,
} = useOnboardingChecklist()

const expandedPanels = ref<Array<'tours' | 'setup'>>(['tours', 'setup'])

watch(
  [isFullyDone, isLoading],
  ([done, loading]) => {
    if (loading) return
    const panels = new Set(expandedPanels.value)
    panels.add('tours')
    if (!done) {
      panels.add('setup')
    } else {
      panels.delete('setup')
    }
    expandedPanels.value = [...panels]
  },
  { immediate: true },
)

function checklistIcon(item: OnboardingChecklistRow): string {
  if (item.skipped) return 'mdi-minus-circle-outline'
  if (item.done) return 'mdi-check-circle'
  return 'mdi-circle-outline'
}

function checklistIconColor(item: OnboardingChecklistRow): string {
  if (item.skipped) return '#94a3b8'
  if (item.done) return '#15803d'
  return '#94a3b8'
}

function tierLabel(tier: OnboardingChecklistRow['tier']): string {
  if (tier === 'optional') return t('onboarding.hub.tierOptional')
  return t('onboarding.hub.tierRecommended')
}

function canSkipItem(item: OnboardingChecklistRow): boolean {
  return !item.alwaysResolved && !item.resolved && item.tier !== 'required'
}

function continueSetup() {
  const depId = departmentId.value
  if (!depId) return
  const firstOpen = checklistItems.value.find((item) => !item.resolved)
  if (!firstOpen) return
  openChecklistItem(firstOpen)
}

function openChecklistItem(item: OnboardingChecklistRow) {
  const depId = departmentId.value
  if (!depId) return
  router.push(resolveChecklistItemRoute(depId, item))
}

async function skipChecklistItem(item: OnboardingChecklistRow) {
  if (item.tier === 'required') return
  const ok = await confirm.confirm({
    title: t('onboarding.hub.skipConfirmTitle'),
    message: t('onboarding.hub.skipConfirmMessage', { item: t(item.labelKey) }),
    confirmText: t('onboarding.hub.skipItem'),
    cancelText: t('common.cancel'),
    variant: 'warning',
  })
  if (!ok) return
  markItemSkipped(item.key)
  toast.success(t('onboarding.hub.skipSuccess'))
}
</script>

<style scoped>
.hub-accordion {
  margin-bottom: 24px;
}

.hub-accordion :deep(.v-expansion-panel) {
  border: 1px solid #e2e8f0;
  border-radius: 12px !important;
  overflow: hidden;
  margin-bottom: 10px;
}

.hub-accordion :deep(.v-expansion-panel-title) {
  font-weight: 600;
  color: #0f172a;
  min-height: 52px;
}

.hub-panel-title {
  font-size: 1.02rem;
}

.hub-panel-lead {
  margin: 0 0 12px;
}

.hub-open-badge {
  margin-right: 4px;
}

.hub-progress-card {
  padding: 16px;
  margin-bottom: 16px;
  border-radius: 12px;
}

.hub-progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.hub-progress-label {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.hub-progress-percent {
  font-size: 14px;
  color: #64748b;
}

.hub-progress-track {
  height: 8px;
  border-radius: 999px;
  background: #e2e8f0;
  overflow: hidden;
}

.hub-progress-fill {
  height: 100%;
  background: #0284c7;
  transition: width 0.2s ease;
}

.hub-muted {
  margin: 0;
  font-size: 14px;
  color: #64748b;
  line-height: 1.5;
}

.checklist {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.checklist-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #fff;
}

.checklist-item--done {
  background: #f8fafc;
}

.checklist-icon {
  flex-shrink: 0;
  display: flex;
}

.checklist-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.checklist-label {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.4;
}

.checklist-desc {
  margin: 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.45;
}

.checklist-tier {
  font-size: 12px;
  color: #64748b;
  line-height: 1.3;
}

.checklist-hint {
  margin: 0;
  font-size: 12px;
  color: #94a3b8;
  line-height: 1.35;
}

.checklist-hint--done {
  color: #64748b;
}

.checklist-item--done .checklist-label,
.checklist-item--skipped .checklist-label {
  color: #64748b;
}

.checklist-actions {
  display: flex;
  flex-shrink: 0;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: flex-end;
}

.hub-setup-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

@media (min-width: 600px) {
  .checklist-item {
    padding: 12px 14px;
  }
}
</style>
