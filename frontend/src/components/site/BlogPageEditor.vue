<template>
  <div class="blog-editor">
    <header class="blog-editor-head">
      <h1>Blog</h1>
      <p v-if="updatedAt" class="meta">Zuletzt gespeichert: {{ formatDe(updatedAt) }}</p>
    </header>

    <p v-if="error" class="error">{{ error }}</p>

    <section class="blog-block">
      <label class="lbl" for="blog-page-title">Seitentitel</label>
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
      <span class="lbl">Einleitung</span>
      <TiptapEditor v-model="introHtml" placeholder="Kurzer Einleitungstext…" :disabled="saving" />
    </section>

    <section class="blog-block">
      <div class="blog-posts-head">
        <h2 class="h2">Beiträge</h2>
        <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="addPost">
          Neuer Beitrag
        </button>
      </div>

      <p v-if="!sortedPosts.length" class="hint">Noch keine Beiträge. „Neuer Beitrag“ legt einen an.</p>

      <ul v-else class="post-index" aria-label="Beiträge nach Datum">
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
        <label class="lbl" :for="'post-title-' + editingPost.id">Überschrift</label>
        <input
          :id="'post-title-' + editingPost.id"
          v-model="editingPost.title"
          type="text"
          class="inp"
          :disabled="saving"
        />
        <span class="lbl">Inhalt</span>
        <TiptapEditor
          :key="editingPost.id"
          v-model="editingPost.bodyHtml"
          placeholder="Beitragstext…"
          :disabled="saving"
        />
      </div>
    </section>

    <div class="actions">
      <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import TiptapEditor from '@/components/site/TiptapEditor.vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { getAdminSitePage, putAdminSitePage } from '@/api/sitePages'

export interface BlogPostRow {
  id: string
  title: string
  bodyHtml: string
  createdAt: string
}

function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function newId(): string {
  return typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `p-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

function normalizeContent(raw: Record<string, unknown>): { title: string; introHtml: string; posts: BlogPostRow[] } {
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

function formatDe(iso: string): string {
  try {
    return new Intl.DateTimeFormat('de-CH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
  } catch {
    return iso
  }
}

const route = useRoute()
const siteContent = useSiteContentStore()

const pageTitle = ref('')
const introHtml = ref('<p></p>')
const posts = ref<BlogPostRow[]>([])
const updatedAt = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)
const editingId = ref<string | null>(null)

const sortedPosts = computed(() =>
  [...posts.value].sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
)

const editingPost = computed(() => posts.value.find((p) => p.id === editingId.value) ?? null)

async function load() {
  error.value = null
  try {
    const data = await getAdminSitePage('blog')
    const n = normalizeContent(data.content as Record<string, unknown>)
    pageTitle.value = n.title
    introHtml.value = n.introHtml
    posts.value = n.posts
    updatedAt.value = data.updatedAt
    editingId.value = null
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Laden fehlgeschlagen'
  }
}

async function save() {
  error.value = null
  saving.value = true
  try {
    const content = {
      title: pageTitle.value,
      introHtml: introHtml.value,
      posts: posts.value.map((p) => ({
        id: p.id,
        title: p.title,
        bodyHtml: p.bodyHtml,
        createdAt: p.createdAt,
      })),
    }
    const data = await putAdminSitePage('blog', content)
    updatedAt.value = data.updatedAt
    void siteContent.refresh()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Speichern fehlgeschlagen'
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
  if (!confirm('Beitrag wirklich löschen?')) return
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
