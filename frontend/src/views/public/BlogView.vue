<template>
  <div class="public-page public-page--legal blog-public">
    <article class="public-card public-card--legal max-w-content">
      <h1>{{ title }}</h1>
      <div v-if="introHtml" class="blog-intro" v-html="sanitizePublicHtml(introHtml)" />
      <p v-else-if="introPlain" class="intro">{{ introPlain }}</p>

      <template v-if="posts.length">
        <article v-for="p in posts" :key="p.id" class="blog-post">
          <h2 class="blog-post-title">{{ p.title || t('public.blog.untitledPost') }}</h2>
          <time v-if="p.createdAt" class="blog-post-date" :datetime="p.createdAt">{{ formatDate(p.createdAt) }}</time>
          <div class="blog-post-body plt-prose-public" v-html="sanitizePublicHtml(p.bodyHtml)" />
        </article>
      </template>
      <p v-else class="muted">{{ t('public.blog.noPostsYet') }}</p>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSiteContentStore } from '@/stores/siteContent'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'

const { t, locale } = useI18n()
const site = useSiteContentStore()
onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('blog'))
type BlogLocale = 'de' | 'en' | 'fr'

function preferredBlogLocale(): BlogLocale {
  const lc = String(locale.value || 'de').toLowerCase()
  if (lc.startsWith('en')) return 'en'
  if (lc.startsWith('fr')) return 'fr'
  return 'de'
}

function localizedBlogContent(raw: Record<string, unknown>): Record<string, unknown> {
  const localesRaw = raw.locales
  if (!localesRaw || typeof localesRaw !== 'object') return raw
  const locales = localesRaw as Record<string, unknown>
  const order: BlogLocale[] = [preferredBlogLocale(), 'de', 'en', 'fr']
  for (const loc of order) {
    const entry = locales[loc]
    if (entry && typeof entry === 'object') {
      return entry as Record<string, unknown>
    }
  }
  return raw
}

const localized = computed(() => localizedBlogContent(c.value))
const title = computed(() => String(localized.value.title ?? c.value.title ?? t('public.blog.titleFallback')))

const introHtml = computed(() => {
  const raw = localized.value.introHtml ?? c.value.introHtml
  return typeof raw === 'string' && raw.trim() ? raw : ''
})

const introPlain = computed(() => {
  if (introHtml.value) return ''
  const raw = localized.value.intro ?? c.value.intro
  return typeof raw === 'string' ? raw : ''
})

function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

interface PublicPost {
  id: string
  title: string
  bodyHtml: string
  createdAt: string
}

function newId(i: number): string {
  return `legacy-${i}`
}

const posts = computed((): PublicPost[] => {
  const raw = localized.value.posts ?? c.value.posts
  if (!Array.isArray(raw)) return []
  return raw.map((p, i) => {
    if (typeof p !== 'object' || !p) {
      return { id: newId(i), title: '', bodyHtml: '<p></p>', createdAt: '' }
    }
    const o = p as Record<string, unknown>
    const id = typeof o.id === 'string' && o.id ? o.id : newId(i)
    const title = String(o.title ?? '')
    let bodyHtml = typeof o.bodyHtml === 'string' ? o.bodyHtml : ''
    if (!bodyHtml && typeof o.excerpt === 'string' && o.excerpt.trim()) {
      bodyHtml = `<p>${escapeHtml(o.excerpt)}</p>`
    }
    if (!bodyHtml) bodyHtml = '<p></p>'
    const createdAt = typeof o.createdAt === 'string' ? o.createdAt : ''
    return { id, title, bodyHtml, createdAt }
  })
})

function formatDate(iso: string): string {
  if (!iso) return ''
  try {
    const dateLocale = locale.value ? locale.value.replace('_', '-') : 'de-CH'
    return new Intl.DateTimeFormat(dateLocale, { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso))
  } catch {
    return iso
  }
}
</script>

<style scoped>
.max-w-content {
  max-width: 42rem;
  margin: 0 auto;
}

.intro {
  margin: 1rem 0 1.5rem;
  color: #475569;
  line-height: 1.6;
}

.blog-intro {
  margin: 1rem 0 2rem;
  line-height: 1.65;
  color: #334155;
}

.blog-intro :deep(p) {
  margin: 0 0 0.75rem;
}

.muted {
  color: #94a3b8;
}

.blog-post {
  padding: 1.5rem 0;
  border-bottom: 1px solid #e2e8f0;
}

.blog-post:last-child {
  border-bottom: none;
}

.blog-post-title {
  font-size: 1.2rem;
  margin: 0 0 0.35rem;
  color: #0f172a;
}

.blog-post-date {
  display: block;
  font-size: 0.85rem;
  color: #64748b;
  margin-bottom: 0.75rem;
}

.blog-post-body {
  line-height: 1.65;
  color: #334155;
}

.blog-post-body :deep(p) {
  margin: 0 0 0.65rem;
}

.blog-post-body :deep(ul),
.blog-post-body :deep(ol) {
  margin: 0 0 0.65rem;
  padding-left: 1.25rem;
}

.blog-post-body :deep(a) {
  color: #059669;
}
</style>
