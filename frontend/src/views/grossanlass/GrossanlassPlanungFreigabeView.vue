<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.planung.freigabe.intro') }}</p>

    <section class="ga-preview-card">
      <h3>{{ t('grossanlass.planung.freigabe.checklistTitle') }}</h3>
      <ul class="ga-preview-checks">
        <li>
          <v-icon icon="mdi-checkbox-marked-outline" size="20" />
          {{ t('grossanlass.planung.freigabe.checkPeriod') }}
        </li>
        <li>
          <v-icon icon="mdi-checkbox-marked-outline" size="20" />
          {{ t('grossanlass.planung.freigabe.checkParticipants') }}
        </li>
        <li>
          <v-icon icon="mdi-checkbox-marked-outline" size="20" />
          {{ t('grossanlass.planung.freigabe.checkRessorts') }}
        </li>
      </ul>
    </section>

    <div class="ga-preview-actions">
      <EButton variant="primary" :disabled="published" @click="onPublish">
        {{ published ? t('grossanlass.chain.publishedBadge') : t('grossanlass.planung.freigabe.publish') }}
      </EButton>
      <EButton variant="secondary" :disabled="!published" @click="goGuest">
        {{ t('grossanlass.chain.openGuestView') }}
      </EButton>
    </div>
    <p class="ga-preview-hint">{{ published ? t('grossanlass.chain.publishedHint') : t('grossanlass.planung.freigabe.publishHint') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { EButton } from '@/components/form/base'
import { isGrossanlassPublished, publishGrossanlassPreview } from '@/views/grossanlass/grossanlassChainPreviewStore'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const published = computed(() => isGrossanlassPublished())

function onPublish() {
  publishGrossanlassPreview()
  toast.success(t('grossanlass.chain.publishedToast'))
}

function goGuest() {
  const id = String(route.params.departmentId || '')
  if (id) void router.push(`/${id}/gast-vorschau`)
}
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.ga-preview-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
  margin-bottom: 16px;
  max-width: 520px;
}
.ga-preview-card h3 { margin: 0 0 12px; font-size: 0.95rem; }
.ga-preview-checks { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.ga-preview-checks li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: #166534;
}
.ga-preview-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.ga-preview-hint { margin: 10px 0 0; font-size: 0.82rem; color: #94a3b8; max-width: 520px; }
</style>
