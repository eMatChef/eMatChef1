<template>
  <div class="page-ed">
    <header class="page-ed-head">
      <h1>FAQ</h1>
      <p v-if="updatedAt" class="meta">Zuletzt gespeichert: {{ formatDe(updatedAt) }}</p>
    </header>
    <p v-if="error" class="error">{{ error }}</p>

    <label class="lbl" for="faq-title">Seitentitel</label>
    <input id="faq-title" v-model="title" type="text" class="inp" :disabled="saving" />

    <div class="block-head">
      <h2 class="h2">Fragen &amp; Antworten</h2>
      <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="addItem">Eintrag hinzufügen</button>
    </div>

    <div v-for="(it, idx) in items" :key="idx" class="faq-item">
      <label class="lbl" :for="'faq-q-' + idx">Frage</label>
      <input :id="'faq-q-' + idx" v-model="it.q" type="text" class="inp" :disabled="saving" />
      <span class="lbl">Antwort</span>
      <TiptapEditor v-model="it.aHtml" placeholder="Antwort…" :disabled="saving" />
      <button type="button" class="btn-remove" :disabled="saving" @click="removeItem(idx)">Eintrag entfernen</button>
    </div>

    <div class="actions">
      <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import TiptapEditor from '@/components/site/TiptapEditor.vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { getAdminSitePage, putAdminSitePage } from '@/api/sitePages'
import { plainToP } from '@/utils/siteHtmlMigrate'

interface FaqItem {
  q: string
  aHtml: string
}

const siteContent = useSiteContentStore()
const title = ref('FAQ')
const items = ref<FaqItem[]>([])
const updatedAt = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)

function normalize(raw: Record<string, unknown>): { title: string; items: FaqItem[] } {
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
  return { title: t, items: list }
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
    const n = normalize(data.content as Record<string, unknown>)
    title.value = n.title
    items.value = n.items.length ? n.items : [{ q: '', aHtml: '<p></p>' }]
    updatedAt.value = data.updatedAt
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Laden fehlgeschlagen'
  }
}

async function save() {
  error.value = null
  saving.value = true
  try {
    const content = {
      title: title.value,
      items: items.value.map((it) => ({ q: it.q, aHtml: it.aHtml })),
    }
    const data = await putAdminSitePage('faq', content)
    updatedAt.value = data.updatedAt
    void siteContent.refresh()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Speichern fehlgeschlagen'
  } finally {
    saving.value = false
  }
}

function addItem() {
  items.value.push({ q: '', aHtml: '<p></p>' })
}

function removeItem(idx: number) {
  if (items.value.length <= 1) {
    items.value = [{ q: '', aHtml: '<p></p>' }]
    return
  }
  items.value.splice(idx, 1)
}

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
