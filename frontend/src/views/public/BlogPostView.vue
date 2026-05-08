<template>
  <div class="public-page public-page--legal blog-post-public">
    <article class="public-card public-card--legal max-w-content">
      <RouterLink to="/blog" class="back-link">← {{ t('public.blog.backToList') }}</RouterLink>

      <template v-if="post">
        <time v-if="post.createdAt" class="post-date" :datetime="post.createdAt">{{ formatDate(post.createdAt) }}</time>
        <h1>{{ post.title || t('public.blog.untitledPost') }}</h1>
        <img v-if="post.coverImage" :src="post.coverImage" class="post-cover" alt="" />
        <div class="post-body plt-prose-public" v-html="sanitizePublicHtml(post.bodyHtml)" />
      </template>

      <template v-else>
        <h1>{{ t('public.blog.notFoundTitle') }}</h1>
        <p class="muted">{{ t('public.blog.notFoundBody') }}</p>
      </template>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useSiteContentStore } from '@/stores/siteContent'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'
import { localizedBlogContent, normalizePublicPosts } from '@/utils/publicBlog'

const route = useRoute()
const { t, locale } = useI18n()
const site = useSiteContentStore()

onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('blog'))
const localized = computed(() => localizedBlogContent(c.value, String(locale.value)))
const posts = computed(() => normalizePublicPosts(localized.value, c.value, t('public.blog.untitledPost')))
const slug = computed(() => String(route.params.slug || '').trim())

const post = computed(() => posts.value.find((p) => p.slug === slug.value) ?? null)

function formatDate(iso: string): string {
  if (!iso) return ''
  try {
    const dateLocale = locale.value ? locale.value.replace('_', '-') : 'de-CH'
    return new Intl.DateTimeFormat(dateLocale, { dateStyle: 'long' }).format(new Date(iso))
  } catch {
    return iso
  }
}
</script>

<style scoped>
.max-w-content {
  max-width: 52rem;
  margin: 0 auto;
}

.back-link {
  display: inline-block;
  margin-bottom: 1.1rem;
  color: #0f766e;
  text-decoration: none;
  font-weight: 600;
}

.back-link:hover {
  text-decoration: underline;
}

.post-date {
  display: block;
  color: #64748b;
  font-size: 0.95rem;
  margin-bottom: 0.75rem;
  letter-spacing: 0.01em;
}

h1 {
  margin: 0 0 1.4rem;
  font-size: clamp(2rem, 4vw, 2.7rem);
  line-height: 1.2;
  color: #0f172a;
}

.post-body {
  color: #334155;
  line-height: 1.8;
  font-size: 1.05rem;
}

.post-cover {
  width: 100%;
  max-height: 30rem;
  object-fit: cover;
  border-radius: 14px;
  margin: 0 0 1.2rem;
}

.post-body :deep(p) {
  margin: 0 0 1.15rem;
}

.post-body :deep(h2) {
  margin: 2rem 0 0.8rem;
  font-size: 1.5rem;
  line-height: 1.3;
  color: #0f172a;
}

.post-body :deep(h3) {
  margin: 1.4rem 0 0.7rem;
  font-size: 1.2rem;
  line-height: 1.35;
  color: #0f172a;
}

.post-body :deep(ul),
.post-body :deep(ol) {
  margin: 0.2rem 0 1.15rem;
  padding-left: 1.35rem;
}

.post-body :deep(li) {
  margin-bottom: 0.35rem;
}

.post-body :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 12px;
  margin: 1.1rem 0 1.25rem;
}

.muted {
  color: #64748b;
}
</style>

