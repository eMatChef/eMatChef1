<template>
  <div class="display-entry">
    <header class="display-entry-header">
      <EmcLogoMark size="sm" />
      <div>
        <h1 class="display-entry-title">{{ t('display.entry.title') }}</h1>
        <p class="display-entry-subtitle">{{ t('display.entry.subtitle') }}</p>
      </div>
    </header>

    <ECard variant="outlined" class="display-entry-panel">
      <form class="display-entry-form" @submit.prevent="submitPublicId">
        <ETextField
          id="display-public-id"
          v-model="publicIdInput"
          :label="t('display.entry.idLabel')"
          :placeholder="t('display.entry.idPlaceholder')"
          :disabled="submitting"
          :error-messages="idError ? [idError] : undefined"
          autocomplete="off"
          autocapitalize="off"
          spellcheck="false"
          maxlength="12"
          hide-details="auto"
          class="display-entry-field"
          @update:model-value="onPublicIdInput"
        />
        <p class="display-entry-hint muted">{{ t('display.entry.idHint') }}</p>
        <EButton
          type="submit"
          variant="primary"
          block
          :disabled="submitting || publicIdInput.length < 4"
          :loading="submitting"
        >
          {{ submitting ? t('display.entry.submitting') : t('display.entry.continue') }}
        </EButton>
      </form>
    </ECard>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import EButton from '@/components/form/base/EButton.vue'
import ECard from '@/components/form/base/ECard.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import { lookupPublicDisplay } from '@/api/displayScreens'

const ID_CHARSET = /[^a-z0-9]/gi

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const publicIdInput = ref('')
const idError = ref<string | null>(null)
const submitting = ref(false)

function normalizePublicId(raw: string): string {
  return raw.trim().toLowerCase().replace(ID_CHARSET, '').slice(0, 12)
}

function onPublicIdInput(value?: string) {
  publicIdInput.value = normalizePublicId(value ?? publicIdInput.value)
  idError.value = null
}

async function submitPublicId() {
  const id = normalizePublicId(publicIdInput.value)
  if (id.length < 4) {
    idError.value = t('display.entry.idInvalid')
    return
  }

  submitting.value = true
  idError.value = null
  try {
    const valid = await lookupPublicDisplay(id)
    if (!valid) {
      idError.value = t('display.entry.idNotFound')
      return
    }
    await router.push({ name: 'PublicDepartmentDisplay', params: { publicId: id } })
  } catch {
    idError.value = t('display.entry.idNotFound')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  const fromQuery = normalizePublicId(String(route.query.id || route.query.screen || ''))
  if (fromQuery) {
    publicIdInput.value = fromQuery
  }
})
</script>

<style scoped>
.display-entry {
  min-height: 100vh;
  padding: 24px 28px 32px;
  background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
  color: #0f172a;
}

.display-entry-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 32px;
}

.display-entry-title {
  margin: 0;
  font-size: clamp(1.35rem, 2.5vw, 2rem);
  font-weight: 800;
  line-height: 1.2;
}

.display-entry-subtitle {
  margin: 4px 0 0;
  font-size: 0.95rem;
  color: #64748b;
}

.display-entry-panel {
  max-width: 440px;
  margin: 0 auto;
  padding: 28px 32px !important;
}

.display-entry-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.display-entry-field :deep(input) {
  font-size: 1.25rem;
  letter-spacing: 0.12em;
  text-align: center;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-weight: 600;
}

.display-entry-hint {
  margin: 0;
  font-size: 0.85rem;
  line-height: 1.45;
}

.muted {
  color: #64748b;
}
</style>
