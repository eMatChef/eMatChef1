<template>
  <div class="page-ed">
    <header class="page-ed-head">
      <h1>{{ t('components.siteEditors.impressum.title') }}</h1>
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

    <label class="lbl" for="imp-title">{{ t('components.siteEditors.pageTitleLabel') }}</label>
    <input id="imp-title" v-model="title" type="text" class="inp" :disabled="saving" />

    <div class="block-head">
      <h2 class="h2">{{ t('components.siteEditors.impressum.sectionsTitle') }}</h2>
      <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="addSection">{{ t('components.siteEditors.impressum.addSection') }}</button>
    </div>

    <div v-for="(sec, idx) in sections" :key="idx" class="sec-item">
      <label class="lbl" :for="'imp-h-' + idx">{{ t('components.siteEditors.impressum.headingLabel') }}</label>
      <input :id="'imp-h-' + idx" v-model="sec.heading" type="text" class="inp" :disabled="saving" />
      <span class="lbl">{{ t('components.siteEditors.impressum.contentLabel') }}</span>
      <TiptapEditor v-model="sec.bodyHtml" :placeholder="t('components.siteEditors.impressum.contentPlaceholder')" :disabled="saving" />
      <button type="button" class="btn-remove" :disabled="saving" @click="removeSection(idx)">{{ t('components.siteEditors.impressum.removeSection') }}</button>
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
import { escapeHtml, plainToP } from '@/utils/siteHtmlMigrate'

interface ImpSection {
  heading: string
  bodyHtml: string
}
type PageLocale = 'de' | 'en' | 'fr'
interface ImpressumLocaleContent {
  title: string
  sections: ImpSection[]
}
const LOCALES: PageLocale[] = ['de', 'en', 'fr']

const siteContent = useSiteContentStore()
const { t } = useI18n()
const activeLocale = ref<PageLocale>('de')
const localeContent = ref<Record<PageLocale, ImpressumLocaleContent>>({
  de: { title: 'Impressum', sections: [{ heading: 'Angaben gemäß TMG / Schweiz: Anbieterkennzeichnung', bodyHtml: '<p></p>' }] },
  en: { title: 'Imprint', sections: [{ heading: 'Provider information', bodyHtml: '<p></p>' }] },
  fr: { title: 'Mentions légales', sections: [{ heading: "Informations de l'éditeur", bodyHtml: '<p></p>' }] },
})
const updatedAt = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)

function legacyToSections(raw: Record<string, unknown>): ImpSection[] {
  const company = String(raw.company ?? '')
  const address = String(raw.address ?? '')
  const contact = String(raw.contact ?? '')
  const rep = String(raw.representative ?? '')
  const liability = String(raw.liability ?? '')
  const blocks: ImpSection[] = []
  if (company || address || contact) {
    const pre = [company, address, contact].filter(Boolean).join('\n')
    blocks.push({
      heading: 'Angaben gemäß TMG / Schweiz: Anbieterkennzeichnung',
      bodyHtml: `<p>${escapeHtml(pre).replace(/\n/g, '<br />')}</p>`,
    })
  }
  if (rep) {
    blocks.push({
      heading: 'Vertretungsberechtigt',
      bodyHtml: plainToP(rep),
    })
  }
  if (liability) {
    blocks.push({
      heading: 'Haftung für Inhalte',
      bodyHtml: plainToP(liability),
    })
  }
  return blocks.length ? blocks : [{ heading: 'Angaben gemäß TMG / Schweiz: Anbieterkennzeichnung', bodyHtml: '<p></p>' }]
}

function normalizeLocale(raw: Record<string, unknown>): ImpressumLocaleContent {
  const t = String(raw.title ?? 'Impressum')
  const rawSec = Array.isArray(raw.sections) ? raw.sections : []
  if (rawSec.length) {
    const list: ImpSection[] = rawSec.map((row) => {
      if (typeof row !== 'object' || !row) return { heading: '', bodyHtml: '<p></p>' }
      const o = row as Record<string, unknown>
      const heading = String(o.heading ?? '')
      let bodyHtml = typeof o.bodyHtml === 'string' ? o.bodyHtml : ''
      if (!bodyHtml && typeof o.body === 'string') bodyHtml = plainToP(o.body)
      if (!bodyHtml) bodyHtml = '<p></p>'
      return { heading, bodyHtml }
    })
    return { title: t, sections: list }
  }
  return { title: t, sections: legacyToSections(raw) }
}

function normalize(raw: Record<string, unknown>): Record<PageLocale, ImpressumLocaleContent> {
  const legacy = normalizeLocale(raw)
  const out: Record<PageLocale, ImpressumLocaleContent> = {
    de: legacy,
    en: { title: 'Imprint', sections: [{ heading: 'Provider information', bodyHtml: '<p></p>' }] },
    fr: { title: 'Mentions légales', sections: [{ heading: "Informations de l'éditeur", bodyHtml: '<p></p>' }] },
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
    const data = await getAdminSitePage('impressum')
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
      sections: de.sections.map((s) => ({
        heading: s.heading,
        bodyHtml: s.bodyHtml,
      })),
      locales: Object.fromEntries(
        LOCALES.map((loc) => [
          loc,
          {
            title: localeContent.value[loc].title,
            sections: localeContent.value[loc].sections.map((s) => ({
              heading: s.heading,
              bodyHtml: s.bodyHtml,
            })),
          },
        ])
      ),
    }
    const data = await putAdminSitePage('impressum', content)
    updatedAt.value = data.updatedAt
    void siteContent.refresh()
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('components.siteEditors.saveFailed')
  } finally {
    saving.value = false
  }
}

function addSection() {
  localeContent.value[activeLocale.value].sections.push({ heading: '', bodyHtml: '<p></p>' })
}

function removeSection(idx: number) {
  const list = localeContent.value[activeLocale.value].sections
  if (list.length <= 1) {
    localeContent.value[activeLocale.value].sections = [{ heading: '', bodyHtml: '<p></p>' }]
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

const sections = computed({
  get: () => localeContent.value[activeLocale.value].sections,
  set: (v: ImpSection[]) => {
    localeContent.value[activeLocale.value].sections = v
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

.sec-item {
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

.actions {
  margin-top: 1.25rem;
}
</style>
