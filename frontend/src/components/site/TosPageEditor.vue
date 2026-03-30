<template>
  <div class="page-ed">
    <header class="page-ed-head">
      <h1>Nutzung &amp; Datenschutz</h1>
      <p v-if="updatedAt" class="meta">Zuletzt gespeichert: {{ formatDe(updatedAt) }}</p>
    </header>
    <p v-if="error" class="error">{{ error }}</p>

    <label class="lbl" for="tos-title">Seitentitel</label>
    <input id="tos-title" v-model="title" type="text" class="inp" :disabled="saving" />

    <div class="block-head">
      <h2 class="h2">Abschnitte</h2>
      <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="addSection">Abschnitt hinzufügen</button>
    </div>

    <div v-for="(sec, idx) in sections" :key="idx" class="sec-item">
      <label class="lbl" :for="'tos-id-' + idx">Anker-ID (optional, z. B. datenschutz)</label>
      <input :id="'tos-id-' + idx" v-model="sec.id" type="text" class="inp inp-mono" :disabled="saving" />
      <label class="lbl" :for="'tos-h-' + idx">Überschrift</label>
      <input :id="'tos-h-' + idx" v-model="sec.heading" type="text" class="inp" :disabled="saving" />
      <span class="lbl">Inhalt</span>
      <TiptapEditor v-model="sec.bodyHtml" placeholder="Text…" :disabled="saving" />
      <button type="button" class="btn-remove" :disabled="saving" @click="removeSection(idx)">Abschnitt entfernen</button>
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

interface TosSection {
  id: string
  heading: string
  bodyHtml: string
}

const siteContent = useSiteContentStore()
const title = ref('')
const sections = ref<TosSection[]>([])
const updatedAt = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)

function normalize(raw: Record<string, unknown>): { title: string; sections: TosSection[] } {
  const t = String(raw.title ?? 'Nutzungsbedingungen & Datenschutz')
  const rawSec = Array.isArray(raw.sections) ? raw.sections : []
  const list: TosSection[] = rawSec.map((row) => {
    if (typeof row !== 'object' || !row) return { id: '', heading: '', bodyHtml: '<p></p>' }
    const o = row as Record<string, unknown>
    const id = o.id != null ? String(o.id) : ''
    const heading = String(o.heading ?? '')
    let bodyHtml = typeof o.bodyHtml === 'string' ? o.bodyHtml : ''
    if (!bodyHtml && typeof o.body === 'string') bodyHtml = plainToP(o.body)
    if (!bodyHtml) bodyHtml = '<p></p>'
    return { id, heading, bodyHtml }
  })
  return { title: t, sections: list }
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
    const data = await getAdminSitePage('tos')
    const n = normalize(data.content as Record<string, unknown>)
    title.value = n.title
    sections.value = n.sections.length
      ? n.sections
      : [{ id: 'nutzung', heading: 'Nutzung', bodyHtml: '<p></p>' }]
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
      sections: sections.value.map((s) => {
        const row: { heading: string; bodyHtml: string; id?: string } = {
          heading: s.heading,
          bodyHtml: s.bodyHtml,
        }
        const id = s.id.trim()
        if (id) row.id = id
        return row
      }),
    }
    const data = await putAdminSitePage('tos', content)
    updatedAt.value = data.updatedAt
    void siteContent.refresh()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Speichern fehlgeschlagen'
  } finally {
    saving.value = false
  }
}

function addSection() {
  sections.value.push({ id: '', heading: '', bodyHtml: '<p></p>' })
}

function removeSection(idx: number) {
  if (sections.value.length <= 1) {
    sections.value = [{ id: '', heading: '', bodyHtml: '<p></p>' }]
    return
  }
  sections.value.splice(idx, 1)
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

.inp {
  width: 100%;
  max-width: 40rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
}

.inp-mono {
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
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
