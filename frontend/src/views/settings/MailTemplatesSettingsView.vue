<template>
  <div class="mail-templates-view">
    <div class="header">
      <h2 class="section-head">{{ t('mail.templates.title') }}</h2>
      <p class="subtitle">{{ t('mail.templates.subtitle') }}</p>
    </div>

    <div v-if="isLoading" class="state-box">{{ t('mail.templates.loading') }}</div>
    <div v-else-if="error" class="state-box error">{{ error }}</div>

    <template v-else>
      <div v-if="inlineSaveError" class="state-box error inline-alert">{{ inlineSaveError }}</div>
      <div class="locale-tabs" role="tablist" :aria-label="t('mail.templates.title')">
        <button
          v-for="loc in MAIL_TEMPLATE_LOCALES"
          :key="loc"
          type="button"
          role="tab"
          class="locale-tab"
          :class="{ 'is-active': locale === loc }"
          :aria-selected="locale === loc"
          @click="setLocale(loc)"
        >
          {{ localeTabLabel(loc) }}
        </button>
      </div>

      <div class="editor-toolbar">
        <button type="button" class="btn primary" :disabled="saving" @click="saveMessages">
          {{ saving ? t('mail.templates.editor.saving') : t('common.save') }}
        </button>
        <button type="button" class="btn ghost" :disabled="saving" @click="loadAll">
          {{ t('mail.templates.editor.reload') }}
        </button>
        <span v-if="saveNotice" class="save-notice">{{ saveNotice }}</span>
      </div>

      <p class="hint">{{ t('mail.templates.editor.placeholderHint') }}</p>

      <div class="template-list">
        <article v-for="tpl in templates" :key="tpl.key" class="template-card">
          <div class="template-head">
            <h2>{{ tpl.title }}</h2>
            <span class="template-key">{{ tpl.key }}</span>
          </div>
          <p class="template-description">{{ tpl.description }}</p>

          <div class="template-block">
            <p class="block-label">{{ t('mail.templates.subject') }}</p>
            <input v-model="messages[tpl.key].subject" type="text" class="input-text" spellcheck="false" />
          </div>

          <div class="template-block">
            <p class="block-label">{{ t('mail.templates.editor.textBody') }}</p>
            <textarea v-model="messages[tpl.key].text_body" class="input-area" rows="10" spellcheck="false" />
          </div>

          <div v-if="htmlKeys(tpl.key).length" class="template-block html-block">
            <p class="block-label">{{ t('mail.templates.editor.htmlBlock') }}</p>
            <div v-for="hk in htmlKeys(tpl.key)" :key="hk" class="html-field">
              <p class="field-label">{{ t('mail.templates.editor.htmlFieldLabel') }}: {{ hk }}</p>
              <TiptapEditor v-model="messages[tpl.key].html![hk]" :placeholder="hk" />
            </div>
          </div>
        </article>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getMailTemplates,
  getMailTemplateMessages,
  putMailTemplateMessages,
  MAIL_TEMPLATE_LOCALES,
  type MailTemplateDefinition,
  type MailTemplateLocale,
  type MailTemplateMessages,
} from '@/api/mailTemplates'
import TiptapEditor from '@/components/site/TiptapEditor.vue'

const route = useRoute()
const { t } = useI18n()
const templates = ref<MailTemplateDefinition[]>([])
const messages = ref<MailTemplateMessages>({})
const locale = ref<MailTemplateLocale>('de')
const isLoading = ref(true)
const saving = ref(false)
const error = ref('')
const saveNotice = ref('')
const inlineSaveError = ref('')

const departmentId = computed(() => {
  const raw = route.params.departmentId
  return typeof raw === 'string' && raw.trim() ? raw : undefined
})

function cloneMessages(src: MailTemplateMessages): MailTemplateMessages {
  return JSON.parse(JSON.stringify(src)) as MailTemplateMessages
}

function ensureMessageShape(keys: string[], raw: MailTemplateMessages): MailTemplateMessages {
  const out: MailTemplateMessages = {}
  for (const key of keys) {
    const src = raw[key] ?? {}
    out[key] = {
      subject: typeof src.subject === 'string' ? src.subject : '',
      text_body: typeof src.text_body === 'string' ? src.text_body : '',
      html: src.html && typeof src.html === 'object' ? { ...src.html } : {},
    }
    for (const hk of Object.keys(out[key].html ?? {})) {
      const v = out[key].html![hk]
      if (typeof v !== 'string') {
        delete out[key].html![hk]
      }
    }
  }
  return out
}

function htmlKeys(templateKey: string): string[] {
  const h = messages.value[templateKey]?.html
  if (!h) return []
  return Object.keys(h).sort()
}

function localeTabLabel(loc: MailTemplateLocale): string {
  if (loc === 'de') return t('mail.templates.editor.tabDe')
  if (loc === 'en') return t('mail.templates.editor.tabEn')
  if (loc === 'fr') return t('mail.templates.editor.tabFr')
  return t('mail.templates.editor.tabIt')
}

async function loadAll() {
  isLoading.value = true
  error.value = ''
  saveNotice.value = ''
  inlineSaveError.value = ''
  try {
    const [cat, pack] = await Promise.all([
      getMailTemplates(departmentId.value, locale.value),
      getMailTemplateMessages(locale.value),
    ])
    templates.value = cat
    const keys = cat.map((c) => c.key)
    messages.value = ensureMessageShape(keys, pack.messages)
  } catch (err: unknown) {
    const ax = err as { response?: { data?: { error?: string } } }
    error.value = ax.response?.data?.error || t('mail.templates.loadError')
    templates.value = []
    messages.value = {}
  } finally {
    isLoading.value = false
  }
}

async function setLocale(loc: MailTemplateLocale) {
  if (locale.value === loc) return
  locale.value = loc
  await loadAll()
}

async function saveMessages() {
  saving.value = true
  saveNotice.value = ''
  inlineSaveError.value = ''
  try {
    await putMailTemplateMessages(locale.value, cloneMessages(messages.value))
    saveNotice.value = t('mail.templates.editor.saved')
    await loadAll()
  } catch (err: unknown) {
    const ax = err as { response?: { data?: { error?: string } } }
    inlineSaveError.value = ax.response?.data?.error || t('mail.templates.editor.saveError')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadAll()
})
</script>

<style scoped>
.mail-templates-view {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-head {
  margin: 0 0 6px 0;
  font-size: 18px;
  font-weight: 600;
  color: #0f172a;
}

.subtitle {
  margin: 0;
  color: #6b7280;
  font-size: 14px;
}

.state-box {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px;
  background: #f9fafb;
}

.state-box.error {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.inline-alert {
  margin-bottom: 0;
}

.locale-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.locale-tab {
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 14px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
}

.locale-tab:hover {
  border-color: #cbd5e1;
  color: #0f172a;
}

.locale-tab.is-active {
  border-color: #16a34a;
  background: #ecfdf5;
  color: #166534;
}

.editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.btn {
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn.primary {
  background: #16a34a;
  color: #fff;
  border-color: #15803d;
}

.btn.primary:hover:not(:disabled) {
  background: #15803d;
}

.btn.ghost {
  background: #fff;
  border-color: #e2e8f0;
  color: #334155;
}

.btn.ghost:hover:not(:disabled) {
  background: #f8fafc;
}

.save-notice {
  font-size: 13px;
  color: #166534;
}

.hint {
  margin: 0;
  font-size: 13px;
  color: #64748b;
}

.template-list {
  display: grid;
  gap: 12px;
}

.template-card {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  padding: 14px;
}

.template-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.template-head h2 {
  margin: 0;
  font-size: 16px;
}

.template-key {
  font-family: Consolas, Monaco, 'Courier New', monospace;
  font-size: 12px;
  color: #4b5563;
  background: #f3f4f6;
  border-radius: 6px;
  padding: 3px 8px;
}

.template-description {
  margin: 8px 0 10px 0;
  color: #6b7280;
  font-size: 13px;
}

.template-block {
  margin-top: 10px;
}

.html-field {
  margin-top: 12px;
}

.field-label {
  margin: 0 0 6px 0;
  font-size: 12px;
  color: #64748b;
}

.block-label {
  margin: 0 0 6px 0;
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
}

.input-text {
  width: 100%;
  box-sizing: border-box;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 14px;
  font-family: inherit;
}

.input-area {
  width: 100%;
  box-sizing: border-box;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  font-size: 13px;
  font-family: ui-monospace, monospace;
  line-height: 1.45;
  resize: vertical;
}
</style>
