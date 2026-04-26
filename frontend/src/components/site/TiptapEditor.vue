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
    </div>
    <EditorContent :editor="editor" class="tiptap-content" />
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import { useI18n } from 'vue-i18n'

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    disabled?: boolean
  }>(),
  { placeholder: undefined, disabled: false }
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const { t } = useI18n()

function placeholderText(): string {
  const p = props.placeholder?.trim()
  return p ? p : t('tiptap.placeholderDefault')
}

const editor = useEditor({
  content: props.modelValue || '<p></p>',
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3] },
    }),
    Link.configure({
      openOnClick: false,
      HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
    }),
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

onBeforeUnmount(() => {
  editor.value?.destroy()
})
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
  min-width: 2rem;
  height: 2rem;
  padding: 0 8px;
  border: 1px solid transparent;
  border-radius: 6px;
  background: transparent;
  font-size: 0.8rem;
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
</style>
