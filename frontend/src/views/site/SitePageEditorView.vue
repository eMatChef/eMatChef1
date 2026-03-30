<template>
  <BlogPageEditor v-if="slug === 'blog'" />
  <div v-else class="site-page-editor">
    <header class="editor-head">
      <h1>{{ label }}</h1>
      <p v-if="updatedAt" class="meta">Zuletzt gespeichert: {{ updatedAtDisplay }}</p>
    </header>
    <p v-if="error" class="error">{{ error }}</p>
    <textarea v-model="jsonText" class="json-area" spellcheck="false" rows="22" :disabled="saving" />
    <div class="actions">
      <button type="button" class="btn btn-primary" :disabled="saving" @click="save">Speichern</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import BlogPageEditor from '@/components/site/BlogPageEditor.vue'
import { SITE_PAGE_LABELS, type SitePageSlug } from '@/config/sitePageEditorFields'
import { getAdminSitePage, putAdminSitePage } from '@/api/sitePages'

const route = useRoute()
const slug = computed(() => String(route.params.slug || ''))

const label = computed(() => {
  const s = slug.value as SitePageSlug
  return SITE_PAGE_LABELS[s] ?? s
})

const jsonText = ref('{}')
const updatedAt = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)

const updatedAtDisplay = computed(() => {
  const u = updatedAt.value
  if (!u) return ''
  try {
    return new Intl.DateTimeFormat('de-CH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(u))
  } catch {
    return u
  }
})

async function load() {
  error.value = null
  const s = slug.value
  if (!s || s === 'blog') return
  try {
    const data = await getAdminSitePage(s)
    jsonText.value = JSON.stringify(data.content, null, 2)
    updatedAt.value = data.updatedAt
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Laden fehlgeschlagen'
  }
}

watch(
  () => route.params.slug,
  () => {
    void load()
  },
  { immediate: true }
)

async function save() {
  error.value = null
  saving.value = true
  try {
    const parsed = JSON.parse(jsonText.value) as Record<string, unknown>
    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
      throw new Error('Inhalt muss ein JSON-Objekt sein.')
    }
    const data = await putAdminSitePage(slug.value, parsed)
    jsonText.value = JSON.stringify(data.content, null, 2)
    updatedAt.value = data.updatedAt
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Speichern fehlgeschlagen'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.editor-head {
  margin-bottom: 1rem;
}

.editor-head h1 {
  font-size: 1.35rem;
  margin: 0 0 0.25rem;
}

.meta {
  font-size: 0.85rem;
  color: #64748b;
  margin: 0;
}

.error {
  color: #b91c1c;
  margin-bottom: 0.75rem;
}

.json-area {
  width: 100%;
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
  padding: 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
}

.actions {
  margin-top: 1rem;
}
</style>
