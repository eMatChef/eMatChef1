<template>
  <div class="blog-editor">
    <header class="blog-editor-head">
      <h1>{{ t('components.siteEditors.blog.title') }}</h1>
      <p v-if="updatedAt" class="meta">{{ t('components.siteEditors.lastSaved') }}: {{ formatDe(updatedAt) }}</p>
    </header>

    <p v-if="error" class="error">{{ error }}</p>

    <section class="blog-block">
      <div class="locale-tabs" role="tablist" :aria-label="t('publicNav.language')">
        <button
          v-for="loc in BLOG_LOCALES"
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
    </section>

    <section class="blog-block">
      <label class="lbl" for="blog-page-title">{{ t('components.siteEditors.pageTitleLabel') }}</label>
      <input
        id="blog-page-title"
        v-model="pageTitle"
        type="text"
        class="inp"
        :disabled="saving"
        autocomplete="off"
      />
    </section>

    <section class="blog-block">
      <span class="lbl">{{ t('components.siteEditors.blog.introLabel') }}</span>
      <TiptapEditor v-model="introHtml" :placeholder="t('components.siteEditors.blog.introPlaceholder')" :disabled="saving" />
    </section>

    <section class="blog-block">
      <div class="blog-posts-head">
        <h2 class="h2">{{ t('components.siteEditors.blog.postsTitle') }}</h2>
        <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="addPost">
          {{ t('components.siteEditors.blog.newPost') }}
        </button>
      </div>

      <p v-if="!sortedPosts.length" class="hint">{{ t('components.siteEditors.blog.noPostsHint') }}</p>

      <ul v-else class="post-index" :aria-label="t('components.siteEditors.blog.postsAria')">
        <li v-for="p in sortedPosts" :key="p.id" class="post-index-row">
          <div class="post-index-main">
            <span class="post-index-title">{{ p.title || '(ohne Titel)' }}</span>
            <time class="post-index-date" :datetime="p.createdAt">Erstellt: {{ formatDe(p.createdAt) }}</time>
          </div>
          <div class="post-index-actions">
            <button type="button" class="btn-ghost" @click="editPost(p.id)">
              {{ editingId === p.id ? 'Zuklappen' : 'Bearbeiten' }}
            </button>
            <button type="button" class="btn-ghost danger" :disabled="saving" @click="removePost(p.id)">
              Löschen
            </button>
          </div>
        </li>
      </ul>

      <div v-if="editingPost" class="post-edit">
        <label class="lbl" :for="'post-title-' + editingPost.id">{{ t('components.siteEditors.blog.postHeadline') }}</label>
        <input
          :id="'post-title-' + editingPost.id"
          v-model="editingPost.title"
          type="text"
          class="inp"
          :disabled="saving"
        />
        <span class="lbl">{{ t('components.siteEditors.blog.contentLabel') }}</span>
        <TiptapEditor
          :key="editingPost.id"
          v-model="editingPost.bodyHtml"
          :placeholder="t('components.siteEditors.blog.postPlaceholder')"
          :disabled="saving"
        />
      </div>
    </section>

    <div class="actions">
      <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
        {{ saving ? t('components.siteEditors.saving') : t('common.save') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import TiptapEditor from '@/components/site/TiptapEditor.vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { getAdminSitePage, putAdminSitePage } from '@/api/sitePages'

export interface BlogPostRow {
  id: string
  title: string
  bodyHtml: string
  createdAt: string
}

type BlogLocale = 'de' | 'en' | 'fr'
const BLOG_LOCALES: BlogLocale[] = ['de', 'en', 'fr']

interface BlogLocaleContent {
  title: string
  introHtml: string
  posts: BlogPostRow[]
}

function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function newId(): string {
  return typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `p-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

function normalizeBlogLocaleContent(raw: Record<string, unknown>): BlogLocaleContent {
  const title = String(raw.title ?? 'Blog')
  let introHtml = ''
  if (typeof raw.introHtml === 'string') introHtml = raw.introHtml
  else if (typeof raw.intro === 'string' && raw.intro.trim()) introHtml = `<p>${escapeHtml(raw.intro)}</p>`
  else introHtml = '<p></p>'

  const rawPosts = Array.isArray(raw.posts) ? raw.posts : []
  const posts: BlogPostRow[] = rawPosts.map((p) => {
    if (typeof p !== 'object' || !p) {
      return { id: newId(), title: '', bodyHtml: '<p></p>', createdAt: new Date().toISOString() }
    }
    const o = p as Record<string, unknown>
    const id = typeof o.id === 'string' && o.id ? o.id : newId()
    const createdAt = typeof o.createdAt === 'string' ? o.createdAt : new Date().toISOString()
    let bodyHtml = typeof o.bodyHtml === 'string' ? o.bodyHtml : ''
    if (!bodyHtml && typeof o.excerpt === 'string' && o.excerpt.trim()) bodyHtml = `<p>${escapeHtml(o.excerpt)}</p>`
    if (!bodyHtml) bodyHtml = '<p></p>'
    return { id, title: String(o.title ?? ''), bodyHtml, createdAt }
  })
  return { title, introHtml, posts }
}

function emptyLocaleContent(): BlogLocaleContent {
  return { title: 'Blog', introHtml: '<p></p>', posts: [] }
}

function normalizeContent(raw: Record<string, unknown>): Record<BlogLocale, BlogLocaleContent> {
  const legacy = normalizeBlogLocaleContent(raw)
  const out: Record<BlogLocale, BlogLocaleContent> = {
    de: legacy,
    en: emptyLocaleContent(),
    fr: emptyLocaleContent(),
  }
  const localesRaw = raw.locales
  if (!localesRaw || typeof localesRaw !== 'object') {
    return out
  }
  const localesObj = localesRaw as Record<string, unknown>
  for (const loc of BLOG_LOCALES) {
    const entry = localesObj[loc]
    if (entry && typeof entry === 'object') {
      out[loc] = normalizeBlogLocaleContent(entry as Record<string, unknown>)
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

const route = useRoute()
const siteContent = useSiteContentStore()
const { t } = useI18n()
const activeLocale = ref<BlogLocale>('de')

const localeContent = ref<Record<BlogLocale, BlogLocaleContent>>({
  de: emptyLocaleContent(),
  en: emptyLocaleContent(),
  fr: emptyLocaleContent(),
})
const updatedAt = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)
const editingId = ref<string | null>(null)

const pageTitle = computed({
  get: () => localeContent.value[activeLocale.value].title,
  set: (v: string) => {
    localeContent.value[activeLocale.value].title = v
  },
})

const introHtml = computed({
  get: () => localeContent.value[activeLocale.value].introHtml,
  set: (v: string) => {
    localeContent.value[activeLocale.value].introHtml = v
  },
})

const posts = computed({
  get: () => localeContent.value[activeLocale.value].posts,
  set: (v: BlogPostRow[]) => {
    localeContent.value[activeLocale.value].posts = v
  },
})

const sortedPosts = computed(() =>
  [...posts.value].sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
)

const editingPost = computed(() => posts.value.find((p) => p.id === editingId.value) ?? null)

async function load() {
  error.value = null
  try {
    const data = await getAdminSitePage('blog')
    localeContent.value = normalizeContent(data.content as Record<string, unknown>)
    updatedAt.value = data.updatedAt
    editingId.value = null
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('components.siteEditors.loadFailed')
  }
}

async function save() {
  error.value = null
  saving.value = true
  try {
    const deContent = localeContent.value.de
    const content = {
      // Legacy fallback shape remains populated from DE.
      title: deContent.title,
      introHtml: deContent.introHtml,
      posts: deContent.posts.map((p) => ({
        id: p.id,
        title: p.title,
        bodyHtml: p.bodyHtml,
        createdAt: p.createdAt,
      })),
      locales: Object.fromEntries(
        BLOG_LOCALES.map((loc) => [
          loc,
          {
            title: localeContent.value[loc].title,
            introHtml: localeContent.value[loc].introHtml,
            posts: localeContent.value[loc].posts.map((p) => ({
              id: p.id,
              title: p.title,
              bodyHtml: p.bodyHtml,
              createdAt: p.createdAt,
            })),
          },
        ])
      ),
    }
    const data = await putAdminSitePage('blog', content)
    updatedAt.value = data.updatedAt
    void siteContent.refresh()
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('components.siteEditors.saveFailed')
  } finally {
    saving.value = false
  }
}

function addPost() {
  const id = newId()
  posts.value.push({
    id,
    title: '',
    bodyHtml: '<p></p>',
    createdAt: new Date().toISOString(),
  })
  editingId.value = id
}

function removePost(id: string) {
  if (!confirm(t('components.siteEditors.blog.confirmDeletePost'))) return
  posts.value = posts.value.filter((p) => p.id !== id)
  if (editingId.value === id) editingId.value = null
}

function editPost(id: string) {
  editingId.value = editingId.value === id ? null : id
}

onMounted(() => {
  void load()
})

watch(
  () => route.params.slug,
  (s) => {
    if (s === 'blog') void load()
  }
)
</script>

<style scoped>
.blog-editor {
  max-width: 52rem;
}

.blog-editor-head h1 {
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

.blog-block {
  margin-bottom: 1.5rem;
}

.locale-tabs {
  display: inline-flex;
  gap: 0.35rem;
  padding: 0.2rem;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #f8fafc;
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
  margin-bottom: 0.35rem;
}

.inp {
  width: 100%;
  max-width: 32rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
}

.blog-posts-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.h2 {
  font-size: 1.1rem;
  margin: 0;
}

.btn-sm {
  padding: 0.4rem 0.75rem;
  font-size: 0.85rem;
}

.hint {
  font-size: 0.9rem;
  color: #64748b;
  margin: 0 0 0.5rem;
}

.post-index {
  list-style: none;
  margin: 0 0 1rem;
  padding: 0;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}

.post-index-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  background: #fff;
}

.post-index-row:last-child {
  border-bottom: none;
}

.post-index-main {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}

.post-index-title {
  font-weight: 600;
  color: #0f172a;
}

.post-index-date {
  font-size: 0.8rem;
  color: #64748b;
}

.post-index-actions {
  display: flex;
  gap: 0.35rem;
  flex-shrink: 0;
}

.btn-ghost {
  padding: 0.35rem 0.6rem;
  border: none;
  background: transparent;
  font-size: 0.85rem;
  font-weight: 600;
  color: #059669;
  cursor: pointer;
  border-radius: 6px;
}

.btn-ghost:hover {
  background: #ecfdf5;
}

.btn-ghost.danger {
  color: #b91c1c;
}

.btn-ghost.danger:hover {
  background: #fef2f2;
}

.post-edit {
  padding: 1rem;
  border: 1px solid #d1fae5;
  border-radius: 10px;
  background: #f8fafc;
}

.post-edit .lbl {
  margin-top: 0.75rem;
}

.post-edit .lbl:first-child {
  margin-top: 0;
}

.actions {
  margin-top: 1.25rem;
}
</style>
