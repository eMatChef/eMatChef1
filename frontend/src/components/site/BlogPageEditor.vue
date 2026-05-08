<template>
  <div class="blog-editor">
    <header class="blog-editor-head">
      <div class="blog-editor-head-row">
        <h1>{{ t('components.siteEditors.blog.title') }}</h1>
        <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="addPost">
          {{ t('components.siteEditors.blog.newPost') }}
        </button>
      </div>
      <div class="blog-editor-head-row">
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
        <p v-if="updatedAt" class="meta">{{ t('components.siteEditors.lastSaved') }}: {{ formatDe(updatedAt) }}</p>
      </div>
    </header>

    <p v-if="error" class="error">{{ error }}</p>

    <section class="blog-block">
      <button type="button" class="accordion-toggle" @click="showPageContent = !showPageContent">
        <span>{{ t('components.siteEditors.blog.pageAccordionTitle') }}</span>
        <span>{{ showPageContent ? '−' : '+' }}</span>
      </button>
      <div v-show="showPageContent" class="accordion-content">
        <label class="lbl" for="blog-page-title">{{ t('components.siteEditors.pageTitleLabel') }}</label>
        <input
          id="blog-page-title"
          v-model="pageTitle"
          type="text"
          class="inp"
          :disabled="saving"
          autocomplete="off"
        />
        <span class="lbl">{{ t('components.siteEditors.blog.introLabel') }}</span>
        <TiptapEditor v-model="introHtml" :placeholder="t('components.siteEditors.blog.introPlaceholder')" :disabled="saving" />
      </div>
    </section>

    <section class="blog-block">
      <button type="button" class="accordion-toggle" @click="showPostEditor = !showPostEditor">
        <span>{{ t('components.siteEditors.blog.postAccordionTitle') }}</span>
        <span>{{ showPostEditor ? '−' : '+' }}</span>
      </button>
      <div v-show="showPostEditor" class="accordion-content">
        <div class="blog-posts-head">
          <div>
            <h2 class="h2">{{ t('components.siteEditors.blog.postsTitle') }}</h2>
            <p class="hint">{{ t('components.siteEditors.blog.workflowHint') }}</p>
          </div>
        </div>

        <p v-if="!sortedPosts.length" class="hint">{{ t('components.siteEditors.blog.noPostsHint') }}</p>

        <ul v-else class="post-index" :aria-label="t('components.siteEditors.blog.postsAria')">
          <li v-for="p in sortedPosts" :key="p.id" class="post-index-row" :class="{ active: editingId === p.id }" @click="editPost(p.id)">
            <div class="post-index-main">
              <span class="post-index-title">{{ p.title || t('components.siteEditors.blog.untitledPost') }}</span>
              <time class="post-index-date" :datetime="p.createdAt">
                {{ t('components.siteEditors.blog.createdLabel') }}: {{ formatDe(p.createdAt) }}
              </time>
            </div>
            <div class="post-index-actions">
              <button type="button" class="btn-ghost" @click.stop="editPost(p.id)">
                {{ editingId === p.id ? t('components.siteEditors.blog.collapse') : t('components.siteEditors.blog.edit') }}
              </button>
              <button type="button" class="btn-ghost danger" :disabled="saving" @click.stop="removePost(p.id)">
                {{ t('components.siteEditors.blog.delete') }}
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
        <div v-else class="post-edit-empty">{{ t('components.siteEditors.blog.editorEmptyHint') }}</div>
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
const showPageContent = ref(true)
const showPostEditor = ref(true)

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

function ensureEditingPost() {
  if (!posts.value.length) {
    editingId.value = null
    return
  }
  const exists = posts.value.some((p) => p.id === editingId.value)
  if (!exists) {
    editingId.value = sortedPosts.value[0]?.id ?? posts.value[0].id
  }
}

async function load() {
  error.value = null
  try {
    const data = await getAdminSitePage('blog')
    localeContent.value = normalizeContent(data.content as Record<string, unknown>)
    updatedAt.value = data.updatedAt
    ensureEditingPost()
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
  showPostEditor.value = true
}

function removePost(id: string) {
  if (!confirm(t('components.siteEditors.blog.confirmDeletePost'))) return
  posts.value = posts.value.filter((p) => p.id !== id)
  if (editingId.value === id) ensureEditingPost()
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

watch(posts, () => {
  ensureEditingPost()
})
</script>

<style scoped>
.blog-editor {
  max-width: 52rem;
}

.blog-editor-head h1 {
  font-size: 1.35rem;
  margin: 0 0 0.25rem;
}

.blog-editor-head-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
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

.accordion-toggle {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  color: #0f172a;
  font-size: 0.95rem;
  font-weight: 600;
  padding: 0.7rem 0.9rem;
  cursor: pointer;
}

.accordion-content {
  margin-top: 0.75rem;
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
  margin: 0.2rem 0 0;
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
  cursor: pointer;
}

.post-index-row:last-child {
  border-bottom: none;
}

.post-index-row.active {
  background: #f0fdf4;
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

.post-edit-empty {
  padding: 0.9rem 1rem;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  background: #f8fafc;
  color: #64748b;
  font-size: 0.9rem;
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
