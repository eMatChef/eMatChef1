<template>
  <div class="public-page public-page--legal blog-public">
    <article class="public-card public-card--legal max-w-content">
      <h1 class="blog-page-title">{{ title }}</h1>
      <div v-if="introHtml" class="blog-intro" v-html="sanitizePublicHtml(introHtml)" />
      <p v-else-if="introPlain" class="intro">{{ introPlain }}</p>

      <template v-if="posts.length">
        <article v-for="p in posts" :key="p.id" class="blog-post">
          <time v-if="p.createdAt" class="blog-post-date" :datetime="p.createdAt">{{ formatDate(p.createdAt) }}</time>
          <h2 class="blog-post-title">
            <RouterLink class="blog-post-title-link" :to="`/blog/${p.slug}`">
              {{ p.title || t('public.blog.untitledPost') }}
            </RouterLink>
          </h2>
          <img v-if="p.coverImage" :src="p.coverImage" class="blog-post-cover" alt="" />
          <p class="blog-post-excerpt">{{ p.excerpt }}</p>
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
import { localizedBlogContent, normalizePublicPosts } from '@/utils/publicBlog'

const { t, locale } = useI18n()
const site = useSiteContentStore()
onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('blog'))
const localized = computed(() => localizedBlogContent(c.value, String(locale.value)))
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

const posts = computed(() =>
  normalizePublicPosts(localized.value, c.value, t('public.blog.untitledPost'))
)

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

.blog-page-title {
  text-align: center;
  font-size: clamp(2.2rem, 5vw, 3.2rem);
  margin: 0 0 1.1rem;
  line-height: 1.15;
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
  padding: 1.75rem 0;
  border-bottom: 1px solid #e2e8f0;
}

.blog-post:last-child {
  border-bottom: none;
}

.blog-post-title {
  font-size: 1.25rem;
  margin: 0 0 0.5rem;
  color: #0f172a;
  line-height: 1.35;
}

.blog-post-title-link {
  color: var(--plt-text);
  text-decoration: none;
  border-bottom: 2px solid transparent;
  transition: color 0.15s ease, border-color 0.15s ease;
}

.blog-post-title-link:hover {
  color: var(--plt-accent);
  border-bottom-color: var(--plt-accent-soft);
}

.blog-post-date {
  display: block;
  font-size: 0.85rem;
  color: #64748b;
  margin-bottom: 0.75rem;
}

.blog-post-excerpt {
  margin: 0 0 0.75rem;
  line-height: 1.72;
  color: #334155;
  font-size: 1.02rem;
}

.blog-post-cover {
  width: 100%;
  max-height: 22rem;
  object-fit: cover;
  border-radius: 12px;
  margin: 0.25rem 0 0.9rem;
}

</style>
