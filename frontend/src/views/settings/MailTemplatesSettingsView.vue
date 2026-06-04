<template>
  <div class="mail-templates-view">
    <div class="header">
      <h2 class="section-head">{{ t('mail.templates.title') }}</h2>
      <p class="subtitle">{{ t('mail.templates.subtitle') }}</p>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="card"
      :message="t('mail.templates.loading')"
    />
    <div v-else-if="error" class="error-block">
      <v-alert type="error" variant="tonal" :text="error" />
    </div>

    <template v-else>
      <v-alert v-if="inlineSaveError" type="error" variant="tonal" class="mb-3" :text="inlineSaveError" />
      <v-tabs v-model="locale" class="locale-tabs" color="primary">
        <v-tab v-for="loc in MAIL_TEMPLATE_LOCALES" :key="loc" :value="loc">
          {{ localeTabLabel(loc) }}
        </v-tab>
      </v-tabs>

      <div class="editor-toolbar">
        <EButton variant="primary" :loading="saving" @click="saveMessages">
          {{ saving ? t('mail.templates.editor.saving') : t('common.save') }}
        </EButton>
        <EButton variant="secondary" :disabled="saving" @click="loadAll">
          {{ t('mail.templates.editor.reload') }}
        </EButton>
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
            <ETextField v-model="messages[tpl.key].subject" hide-details="auto" spellcheck="false" />
          </div>

          <div class="template-block">
            <p class="block-label">{{ t('mail.templates.editor.textBody') }}</p>
            <ETextarea v-model="messages[tpl.key].text_body" rows="10" hide-details="auto" spellcheck="false" />
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
import { onMounted, ref, computed, watch } from 'vue'
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
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ETextField, ETextarea } from '@/components/form/base'

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

watch(locale, async (loc, prev) => {
  if (prev !== undefined && loc !== prev) {
    await loadAll()
  }
})

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

.error-block {
  margin-bottom: 8px;
}

.locale-tabs {
  margin-bottom: 12px;
}

.editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
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
</style>
