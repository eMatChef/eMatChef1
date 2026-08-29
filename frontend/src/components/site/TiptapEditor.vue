<template>
  <div v-if="!editor" class="tiptap-loading">{{ t('tiptap.loading') }}</div>
  <div v-else class="tiptap-wrap" :class="{ 'tiptap-wrap--disabled': disabled }">
    <div class="tiptap-toolbar" role="toolbar" :aria-label="t('tiptap.toolbarAria')">
      <button
        type="button"
        class="tiptap-tb-btn"
        :class="{ 'is-active': editor.isActive('bold') }"
        :title="t('tiptap.bold')"
        @click="editor.chain().focus().toggleBold().run()"
      >
        B
      </button>
      <button
        type="button"
        class="tiptap-tb-btn"
        :class="{ 'is-active': editor.isActive('italic') }"
        :title="t('tiptap.italic')"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        <em>I</em>
      </button>
      <span class="tiptap-tb-sep" />
      <select
        class="tiptap-tb-select tiptap-tb-select--size"
        :aria-label="t('tiptap.fontSize')"
        :title="t('tiptap.fontSize')"
        :value="currentFontSize"
        @change="onFontSize"
      >
        <option value="10pt">{{ t('tiptap.fontSizeSmall') }}</option>
        <option value="">{{ t('tiptap.fontSizeNormal') }}</option>
        <option value="14pt">{{ t('tiptap.fontSizeLarge') }}</option>
        <option value="18pt">{{ t('tiptap.fontSizeHuge') }}</option>
      </select>
      <span class="tiptap-tb-sep" />
      <button
        type="button"
        class="tiptap-tb-btn"
        :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }"
        :title="t('tiptap.heading2')"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
      >
        H2
      </button>
      <button
        type="button"
        class="tiptap-tb-btn"
        :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }"
        :title="t('tiptap.heading3')"
        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
      >
        H3
      </button>
      <span class="tiptap-tb-sep" />
      <button
        type="button"
        class="tiptap-tb-btn"
        :class="{ 'is-active': editor.isActive('bulletList') }"
        :title="t('tiptap.bulletList')"
        @click="editor.chain().focus().toggleBulletList().run()"
      >
        •
      </button>
      <button
        type="button"
        class="tiptap-tb-btn"
        :class="{ 'is-active': editor.isActive('orderedList') }"
        :title="t('tiptap.orderedList')"
        @click="editor.chain().focus().toggleOrderedList().run()"
      >
        1.
      </button>
      <span class="tiptap-tb-sep" />
      <button
        type="button"
        class="tiptap-tb-btn"
        :class="{ 'is-active': editor.isActive('link') }"
        :title="t('tiptap.link')"
        @click="setLink"
      >
        Link
      </button>
      <button
        type="button"
        class="tiptap-tb-btn"
        :title="t('tiptap.image')"
        @click="openImagePicker"
      >
        {{ t('tiptap.image') }}
      </button>
      <span class="tiptap-tb-sep" />
      <button
        type="button"
        class="tiptap-tb-btn"
        :title="t('tiptap.undo')"
        @click="editor.chain().focus().undo().run()"
      >
        ↶
      </button>
      <button
        type="button"
        class="tiptap-tb-btn"
        :title="t('tiptap.redo')"
        @click="editor.chain().focus().redo().run()"
      >
        ↷
      </button>
      <template v-if="insertTokens.length">
        <span class="tiptap-tb-sep" />
        <select
          class="tiptap-tb-select"
          :aria-label="t('tiptap.placeholderMenu')"
          :value="''"
          @change="onInsertToken"
        >
          <option value="" disabled>{{ t('tiptap.placeholderMenu') }}</option>
          <option v-for="item in insertTokens" :key="item.token" :value="item.token">
            {{ item.label }}
          </option>
        </select>
        <button
          v-if="allowCustomTokens"
          type="button"
          class="tiptap-tb-btn"
          :title="t('tiptap.placeholderAdd')"
          @click="emit('addCustomToken')"
        >
          {{ t('tiptap.placeholderAdd') }}
        </button>
      </template>
    </div>
    <input
      ref="imageInput"
      type="file"
      class="tiptap-file-input"
      accept="image/*"
      @change="onImageSelected"
    />
    <EditorContent :editor="editor" class="tiptap-content" />
    <div v-if="isDraggingImage" class="tiptap-drop-overlay">
      {{ t('tiptap.dropImageHint') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import { FontSize, TextStyle } from '@tiptap/extension-text-style'
import { useI18n } from 'vue-i18n'
import type { Editor } from '@tiptap/core'

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    disabled?: boolean
    insertTokens?: { token: string; label: string }[]
    allowCustomTokens?: boolean
  }>(),
  { placeholder: undefined, disabled: false, insertTokens: () => [], allowCustomTokens: false }
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  addCustomToken: []
}>()

const { t } = useI18n()
const imageInput = ref<HTMLInputElement | null>(null)
const MAX_IMAGE_BYTES = 5 * 1024 * 1024
const isDraggingImage = ref(false)

function placeholderText(): string {
  const p = props.placeholder?.trim()
  return p ? p : t('tiptap.placeholderDefault')
}

const editor = useEditor({
  content: props.modelValue || '<p></p>',
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3] },
      link: false,
    }),
    Link.configure({
      openOnClick: false,
      HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
    }),
    TextStyle,
    FontSize,
    Placeholder.configure({ placeholder: placeholderText() }),
  ],
  editorProps: {
    attributes: {
      class: 'tiptap-prose',
    },
  },
  onUpdate: ({ editor: ed }) => {
    emit('update:modelValue', ed.getHTML())
  },
})

watch(
  () => props.modelValue,
  (html) => {
    const ed = editor.value
    if (!ed) return
    const next = html || '<p></p>'
    if (next === ed.getHTML()) return
    ed.commands.setContent(next, { emitUpdate: false })
  }
)

watch(
  () => props.disabled,
  (d) => {
    editor.value?.setEditable(!d)
  },
  { immediate: true }
)

const FONT_SIZE_VALUES = ['10pt', '14pt', '18pt'] as const

const currentFontSize = computed(() => {
  const ed = editor.value
  if (!ed) return ''
  const size = String(ed.getAttributes('textStyle').fontSize || '').trim()
  if (FONT_SIZE_VALUES.includes(size as (typeof FONT_SIZE_VALUES)[number])) return size
  return ''
})

function onFontSize(event: Event) {
  const ed = editor.value
  const select = event.target as HTMLSelectElement
  const size = select.value.trim()
  if (!ed) return
  if (!size) {
    ed.chain().focus().unsetFontSize().run()
    return
  }
  ed.chain().focus().setFontSize(size).run()
}

function onInsertToken(event: Event) {
  const select = event.target as HTMLSelectElement
  const token = select.value.trim()
  select.value = ''
  insertToken(token)
}

function insertToken(token: string) {
  const ed = editor.value
  const name = token.trim()
  if (!ed || !name) return
  ed.chain().focus().insertContent('{{' + name + '}}').run()
}

defineExpose({ insertToken })

function setLink() {
  const ed = editor.value
  if (!ed) return
  const prev = ed.getAttributes('link').href
  const url = window.prompt(t('tiptap.linkPrompt'), prev || t('tiptap.linkDefault'))
  if (url === null) return
  if (url === '') {
    ed.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }
  ed.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

function openImagePicker() {
  imageInput.value?.click()
}

async function onImageSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  const dataUrl = await fileToDataUrl(file)
  if (!dataUrl) return
  const ed = editor.value
  if (!ed) return
  ed.chain().focus().insertContent(`<p><img src="${dataUrl}" alt="" /></p>`).run()
}

async function insertImageFromFile(file: File): Promise<void> {
  const dataUrl = await fileToDataUrl(file)
  if (!dataUrl) return
  const ed = editor.value
  if (!ed) return
  ed.chain().focus().insertContent(`<p><img src="${dataUrl}" alt="" /></p>`).run()
}

async function fileToDataUrl(file: File): Promise<string | null> {
  if (!file.type.startsWith('image/')) {
    window.alert(t('tiptap.imageUploadError'))
    return null
  }
  if (file.size > MAX_IMAGE_BYTES) {
    window.alert(t('tiptap.imageTooLarge'))
    return null
  }
  return readFileAsDataUrl(file)
}

function readFileAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result || ''))
    reader.onerror = () => reject(reader.error ?? new Error('file-read-failed'))
    reader.readAsDataURL(file)
  })
}

let editorDom: HTMLElement | null = null

function getEditorDom(ed: Editor | null | undefined): HTMLElement | null {
  if (!ed) return null
  try {
    const dom = ed.view?.dom
    return dom instanceof HTMLElement ? dom : null
  } catch {
    return null
  }
}

function attachEditorDomListeners(dom: HTMLElement) {
  dom.addEventListener('dragenter', onDragEnter)
  dom.addEventListener('dragover', onDragOver)
  dom.addEventListener('dragleave', onDragLeave)
  dom.addEventListener('drop', onDropEvent)
  dom.addEventListener('paste', onPasteEvent)
}

function detachEditorDomListeners(dom: HTMLElement | null) {
  if (!dom) return
  dom.removeEventListener('dragenter', onDragEnter)
  dom.removeEventListener('dragover', onDragOver)
  dom.removeEventListener('dragleave', onDragLeave)
  dom.removeEventListener('drop', onDropEvent)
  dom.removeEventListener('paste', onPasteEvent)
}

onBeforeUnmount(() => {
  detachEditorDomListeners(editorDom)
  editorDom = null
  try {
    editor.value?.destroy()
  } catch {
    // Ignore teardown races when view is already gone.
  }
})

watch(
  () => editor.value,
  (ed) => {
    detachEditorDomListeners(editorDom)
    editorDom = getEditorDom(ed)
    if (!editorDom) return
    attachEditorDomListeners(editorDom)
  }
)

function onDragEnter(event: Event) {
  const e = event as DragEvent
  if (!e.dataTransfer?.types?.includes('Files')) return
  isDraggingImage.value = true
}

function onDragOver(event: Event) {
  const e = event as DragEvent
  if (!e.dataTransfer?.types?.includes('Files')) return
  e.preventDefault()
  isDraggingImage.value = true
}

function onDragLeave(event: Event) {
  const e = event as DragEvent
  const target = e.currentTarget as HTMLElement | null
  if (!target) {
    isDraggingImage.value = false
    return
  }
  const related = e.relatedTarget as Node | null
  if (!related || !target.contains(related)) {
    isDraggingImage.value = false
  }
}

function onDragEnd() {
  isDraggingImage.value = false
}

function onDropEvent(event: Event) {
  const e = event as DragEvent
  onDragEnd()
  const files = e.dataTransfer?.files
  if (!files || files.length === 0) return
  const file = files[0]
  if (!file.type.startsWith('image/')) return
  e.preventDefault()
  void insertImageFromFile(file)
}

function onPasteEvent(event: Event) {
  const e = event as ClipboardEvent
  const files = e.clipboardData?.files
  if (!files || files.length === 0) return
  const file = files[0]
  if (!file.type.startsWith('image/')) return
  e.preventDefault()
  void insertImageFromFile(file)
}
</script>

<style scoped>
.tiptap-loading {
  padding: 1rem;
  color: #64748b;
  font-size: 0.9rem;
}

.tiptap-wrap {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  overflow: hidden;
}

.tiptap-wrap--disabled {
  opacity: 0.7;
  pointer-events: none;
}

.tiptap-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  padding: 8px 10px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.tiptap-tb-btn {
  min-width: 1.75rem;
  height: 1.75rem;
  padding: 0 6px;
  border: 1px solid transparent;
  border-radius: 6px;
  background: transparent;
  font-size: 0.75rem;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
}

.tiptap-tb-btn:hover {
  background: #e2e8f0;
}

.tiptap-tb-btn.is-active {
  background: #d1fae5;
  border-color: #6ee7b7;
  color: #065f46;
}

.tiptap-tb-sep {
  width: 1px;
  height: 1.25rem;
  background: #cbd5e1;
  margin: 0 4px;
}

.tiptap-tb-select {
  max-width: 11rem;
  height: 1.75rem;
  padding: 0 6px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
  font-size: 0.75rem;
  font-weight: 600;
  color: #334155;
}

.tiptap-tb-select--size {
  max-width: 7.5rem;
}

.tiptap-content :deep(.tiptap) {
  min-height: 12rem;
  padding: 12px 14px;
  outline: none;
}

.tiptap-content :deep(.tiptap p.is-editor-empty:first-child::before) {
  color: #94a3b8;
  content: attr(data-placeholder);
  float: left;
  height: 0;
  pointer-events: none;
}

.tiptap-content :deep(.tiptap-prose) {
  font-size: 0.95rem;
  line-height: 1.6;
  color: #0f172a;
}

.tiptap-content :deep(.tiptap-prose p) {
  margin: 0 0 0.5rem;
}

.tiptap-content :deep(.tiptap-prose ul),
.tiptap-content :deep(.tiptap-prose ol) {
  margin: 0 0 0.5rem;
  padding-left: 1.35rem;
}

.tiptap-content :deep(.tiptap-prose h2) {
  font-size: 1.15rem;
  margin: 0.75rem 0 0.35rem;
}

.tiptap-content :deep(.tiptap-prose h3) {
  font-size: 1.05rem;
  margin: 0.65rem 0 0.3rem;
}

.tiptap-content :deep(.tiptap-prose a) {
  color: #059669;
  text-decoration: underline;
}

.tiptap-content :deep(.tiptap-prose img) {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
}

.tiptap-file-input {
  display: none;
}

.tiptap-drop-overlay {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(15, 23, 42, 0.08);
  border: 2px dashed #0f766e;
  color: #0f766e;
  font-weight: 700;
  pointer-events: none;
}

.tiptap-wrap {
  position: relative;
}
</style>
