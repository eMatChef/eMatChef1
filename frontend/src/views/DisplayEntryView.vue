<template>
  <div class="display-entry">
    <header class="display-entry-header">
      <EmcLogoMark size="sm" />
      <div>
        <h1 class="display-entry-title">{{ t('display.entry.title') }}</h1>
        <p class="display-entry-subtitle">{{ t('display.entry.subtitle') }}</p>
      </div>
    </header>

    <section class="display-entry-panel">
      <form class="display-entry-form" @submit.prevent="submitPublicId">
        <label class="display-entry-label" for="display-public-id">{{ t('display.entry.idLabel') }}</label>
        <input
          id="display-public-id"
          v-model="publicIdInput"
          type="text"
          class="display-entry-input"
          :placeholder="t('display.entry.idPlaceholder')"
          autocomplete="off"
          autocapitalize="off"
          spellcheck="false"
          maxlength="12"
          :disabled="submitting"
          @input="onPublicIdInput"
        />
        <p class="display-entry-hint muted">{{ t('display.entry.idHint') }}</p>
        <p v-if="idError" class="display-entry-error">{{ idError }}</p>
        <button type="submit" class="display-entry-submit" :disabled="submitting || publicIdInput.length < 4">
          {{ submitting ? t('display.entry.submitting') : t('display.entry.continue') }}
        </button>
      </form>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
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

function onPublicIdInput() {
  publicIdInput.value = normalizePublicId(publicIdInput.value)
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
  padding: 28px 32px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 8px 32px rgba(15, 23, 42, 0.08);
}

.display-entry-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.display-entry-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: #334155;
}

.display-entry-input {
  font-size: 1.25rem;
  letter-spacing: 0.12em;
  text-align: center;
  padding: 14px 16px;
  border: 2px solid #cbd5e1;
  border-radius: 10px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-weight: 600;
}

.display-entry-input:focus {
  outline: none;
  border-color: #3b82f6;
}

.display-entry-hint {
  margin: 0;
  font-size: 0.85rem;
  line-height: 1.45;
}

.muted {
  color: #64748b;
}

.display-entry-error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}

.display-entry-submit {
  margin-top: 8px;
  padding: 12px 16px;
  border: none;
  border-radius: 10px;
  background: #2563eb;
  color: #fff;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.display-entry-submit:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
