<template>
  <div class="page-ed">
    <header class="page-ed-head">
      <h1>{{ t('components.siteEditors.faq.title') }}</h1>
      <p v-if="updatedAt" class="meta">{{ t('components.siteEditors.lastSaved') }}: {{ formatDe(updatedAt) }}</p>
    </header>
    <p v-if="error" class="error">{{ error }}</p>

    <div class="locale-tabs" role="tablist" :aria-label="t('publicNav.language')">
      <button
        v-for="loc in LOCALES"
        :key="loc"
        type="button"
        class="locale-tab"
        :class="{ active: activeLocale === loc }"
        :aria-selected="activeLocale === loc"
        @click="activeLocale = loc"
      >
        {{ loc.toUpperCase() }}
      </button>
    </div>

    <label class="lbl" for="faq-title">{{ t('components.siteEditors.pageTitleLabel') }}</label>
    <input id="faq-title" v-model="title" type="text" class="inp" :disabled="saving" />

    <div class="block-head">
      <h2 class="h2">{{ t('components.siteEditors.faq.itemsTitle') }}</h2>
      <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="addItem">{{ t('components.siteEditors.faq.addItem') }}</button>
    </div>

    <div v-for="(it, idx) in items" :key="idx" class="faq-item">
      <label class="lbl" :for="'faq-q-' + idx">{{ t('components.siteEditors.faq.questionLabel') }}</label>
      <input :id="'faq-q-' + idx" v-model="it.q" type="text" class="inp" :disabled="saving" />
      <span class="lbl">{{ t('components.siteEditors.faq.answerLabel') }}</span>
      <TiptapEditor v-model="it.aHtml" :placeholder="t('components.siteEditors.faq.answerPlaceholder')" :disabled="saving" />
      <button type="button" class="btn-remove" :disabled="saving" @click="removeItem(idx)">{{ t('components.siteEditors.faq.removeItem') }}</button>
    </div>

    <div class="actions">
      <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
        {{ saving ? t('components.siteEditors.saving') : t('common.save') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import TiptapEditor from '@/components/site/TiptapEditor.vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { getAdminSitePage, putAdminSitePage } from '@/api/sitePages'
import { plainToP } from '@/utils/siteHtmlMigrate'

interface FaqItem {
  q: string
  aHtml: string
}
type PageLocale = 'de' | 'en' | 'fr'
interface FaqLocaleContent {
  title: string
  items: FaqItem[]
}
const LOCALES: PageLocale[] = ['de', 'en', 'fr']

const siteContent = useSiteContentStore()
const { t } = useI18n()
const activeLocale = ref<PageLocale>('de')
const localeContent = ref<Record<PageLocale, FaqLocaleContent>>({
  de: { title: 'FAQ', items: [{ q: '', aHtml: '<p></p>' }] },
  en: { title: 'FAQ', items: [{ q: '', aHtml: '<p></p>' }] },
  fr: { title: 'FAQ', items: [{ q: '', aHtml: '<p></p>' }] },
})
const updatedAt = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)

function normalizeLocale(raw: Record<string, unknown>): FaqLocaleContent {
  const t = String(raw.title ?? 'FAQ')
  const rawItems = Array.isArray(raw.items) ? raw.items : []
  const list: FaqItem[] = rawItems.map((row) => {
    if (typeof row !== 'object' || !row) return { q: '', aHtml: '<p></p>' }
    const o = row as Record<string, unknown>
    const q = String(o.q ?? '')
    let aHtml = typeof o.aHtml === 'string' ? o.aHtml : ''
    if (!aHtml && typeof o.a === 'string') aHtml = plainToP(o.a)
    if (!aHtml) aHtml = '<p></p>'
    return { q, aHtml }
  })
  return { title: t, items: list.length ? list : [{ q: '', aHtml: '<p></p>' }] }
}

function normalize(raw: Record<string, unknown>): Record<PageLocale, FaqLocaleContent> {
  const legacy = normalizeLocale(raw)
  const out: Record<PageLocale, FaqLocaleContent> = {
    de: legacy,
    en: { title: 'FAQ', items: [{ q: '', aHtml: '<p></p>' }] },
    fr: { title: 'FAQ', items: [{ q: '', aHtml: '<p></p>' }] },
  }
  const localesRaw = raw.locales
  if (!localesRaw || typeof localesRaw !== 'object') return out
  const localesObj = localesRaw as Record<string, unknown>
  for (const loc of LOCALES) {
    const entry = localesObj[loc]
    if (entry && typeof entry === 'object') {
      out[loc] = normalizeLocale(entry as Record<string, unknown>)
    }
  }
  return out
}

function formatDe(iso: string): string {
  try {
    return new Intl.DateTimeFormat('de-CH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
  } catch {
    return iso
  }
}

async function load() {
  error.value = null
  try {
    const data = await getAdminSitePage('faq')
    localeContent.value = normalize(data.content as Record<string, unknown>)
    updatedAt.value = data.updatedAt
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('components.siteEditors.loadFailed')
  }
}

async function save() {
  error.value = null
  saving.value = true
  try {
    const de = localeContent.value.de
    const content = {
      // Legacy fallback remains DE at top-level.
      title: de.title,
      items: de.items.map((it) => ({ q: it.q, aHtml: it.aHtml })),
      locales: Object.fromEntries(
        LOCALES.map((loc) => [
          loc,
          {
            title: localeContent.value[loc].title,
            items: localeContent.value[loc].items.map((it) => ({ q: it.q, aHtml: it.aHtml })),
          },
        ])
      ),
    }
    const data = await putAdminSitePage('faq', content)
    updatedAt.value = data.updatedAt
    void siteContent.refresh()
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('components.siteEditors.saveFailed')
  } finally {
    saving.value = false
  }
}

function addItem() {
  localeContent.value[activeLocale.value].items.push({ q: '', aHtml: '<p></p>' })
}

function removeItem(idx: number) {
  const list = localeContent.value[activeLocale.value].items
  if (list.length <= 1) {
    localeContent.value[activeLocale.value].items = [{ q: '', aHtml: '<p></p>' }]
    return
  }
  list.splice(idx, 1)
}

const title = computed({
  get: () => localeContent.value[activeLocale.value].title,
  set: (v: string) => {
    localeContent.value[activeLocale.value].title = v
  },
})

const items = computed({
  get: () => localeContent.value[activeLocale.value].items,
  set: (v: FaqItem[]) => {
    localeContent.value[activeLocale.value].items = v
  },
})

onMounted(() => {
  void load()
})
</script>

<style scoped>
.page-ed {
  max-width: 52rem;
}

.page-ed-head h1 {
  font-size: 1.35rem;
  margin: 0 0 0.25rem;
}

.meta {
  font-size: 0.85rem;
  color: #64748b;
  margin: 0 0 1rem;
}

.error {
  color: #b91c1c;
  margin-bottom: 0.75rem;
}

.locale-tabs {
  display: inline-flex;
  gap: 0.35rem;
  padding: 0.2rem;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #f8fafc;
  margin-bottom: 0.75rem;
}

.locale-tab {
  border: none;
  border-radius: 999px;
  background: transparent;
  color: #475569;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  padding: 0.28rem 0.62rem;
  cursor: pointer;
}

.locale-tab.active {
  background: #0f172a;
  color: #fff;
}

.lbl {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
  margin: 0.75rem 0 0.35rem;
}

.lbl:first-of-type {
  margin-top: 0;
}

.inp {
  width: 100%;
  max-width: 40rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
}

.block-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin: 1.5rem 0 0.75rem;
}

.h2 {
  font-size: 1.1rem;
  margin: 0;
}

.btn-sm {
  padding: 0.4rem 0.75rem;
  font-size: 0.85rem;
}

.faq-item {
  margin-bottom: 1.5rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fafafa;
}

.btn-remove {
  margin-top: 0.75rem;
  padding: 0.35rem 0.65rem;
  font-size: 0.85rem;
  border: none;
  background: transparent;
  color: #b91c1c;
  cursor: pointer;
  font-weight: 600;
}

.btn-remove:hover {
  text-decoration: underline;
}

.actions {
  margin-top: 1.25rem;
}
</style>
