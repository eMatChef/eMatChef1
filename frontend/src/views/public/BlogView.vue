<template>
  <PublicContentPage variant="blog" wide>
    <template #hero>
      <p class="plt-subpage-kicker">{{ t('public.blog.kicker') }}</p>
      <h1 class="plt-subpage-title plt-subpage-title--center">{{ title }}</h1>
      <div
        v-if="introHtml"
        class="plt-subpage-lead plt-subpage-lead--center"
        v-html="sanitizePublicHtml(introHtml)"
      />
      <p v-else-if="introPlain" class="plt-subpage-lead plt-subpage-lead--center">{{ introPlain }}</p>
    </template>

    <div v-if="posts.length" class="plt-blog-grid">
      <article
        v-for="p in posts"
        :key="p.id"
        class="plt-blog-card-link"
        role="button"
        tabindex="0"
        @click="openPost(p.slug)"
        @keydown.enter.prevent="openPost(p.slug)"
        @keydown.space.prevent="openPost(p.slug)"
      >
        <ECard variant="outlined" class="plt-blog-card">
          <img v-if="p.coverImage" :src="p.coverImage" class="plt-blog-card-cover" alt="" />
          <div class="plt-blog-card-body">
            <time v-if="p.createdAt" class="plt-blog-date-chip" :datetime="p.createdAt">
              {{ formatPublicBlogDate(p.createdAt, String(locale)) }}
            </time>
            <h2 class="plt-blog-card-title">
              {{ p.title || t('public.blog.untitledPost') }}
            </h2>
            <p v-if="p.excerpt" class="plt-blog-card-excerpt">{{ p.excerpt }}</p>
            <span class="plt-blog-read-more">
              {{ t('public.blog.readMore') }}
              <v-icon icon="mdi-arrow-right" size="18" />
            </span>
          </div>
        </ECard>
      </article>
    </div>
    <EEmptyState
      v-else
      :title="t('public.blog.noPostsYet')"
      :description="t('public.blog.noPostsHint')"
    />

    <EDialog
      v-model="postDialogOpen"
      :max-width="720"
      scrollable
      card-class="plt-blog-dialog"
      @update:model-value="onDialogToggle"
    >
      <template #title>
        <div class="plt-blog-dialog-header">
          <span class="plt-blog-dialog-heading">
            {{ activePost?.title || t('public.blog.untitledPost') }}
          </span>
          <button
            type="button"
            class="plt-dialog-close"
            :aria-label="t('common.close')"
            @click="closePost"
          >
            <v-icon icon="mdi-close" size="18" />
          </button>
        </div>
      </template>
      <template v-if="activePost">
        <time
          v-if="activePost.createdAt"
          class="plt-blog-date-chip plt-blog-date-chip--dialog"
          :datetime="activePost.createdAt"
        >
          {{ formatPublicBlogDate(activePost.createdAt, String(locale)) }}
        </time>
        <img
          v-if="activePost.coverImage"
          :src="activePost.coverImage"
          class="plt-blog-dialog-cover"
          alt=""
        />
        <div
          class="plt-legal-prose plt-blog-dialog-body"
          v-html="sanitizePublicHtml(activePost.bodyHtml)"
        />
      </template>
      <template v-else>
        <p class="plt-muted">{{ t('public.blog.notFoundBody') }}</p>
      </template>
      <template v-if="dialogCanScroll && dialogScrolled" #actions>
        <div class="plt-blog-dialog-footer is-visible">
          <button
            type="button"
            class="plt-dialog-close plt-dialog-close--footer"
            :aria-label="t('common.close')"
            @click="closePost"
          >
            <v-icon icon="mdi-close" size="18" />
          </button>
        </div>
      </template>
    </EDialog>
  </PublicContentPage>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PublicContentPage from '@/components/layout/PublicContentPage.vue'
import ECard from '@/components/form/base/ECard.vue'
import EDialog from '@/components/form/base/EDialog.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { usePageHeadStore } from '@/stores/pageHead'
import { syncDocumentHead } from '@/composables/usePageHead'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'
import {
  formatPublicBlogDate,
  localizedBlogContent,
  normalizePublicPosts,
  type PublicBlogPost,
} from '@/utils/publicBlog'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const site = useSiteContentStore()
const pageHead = usePageHeadStore()

const postDialogOpen = ref(false)
const dialogCanScroll = ref(false)
const dialogScrolled = ref(false)

let dialogScrollEl: HTMLElement | null = null
let dialogResizeObserver: ResizeObserver | null = null
const DIALOG_SCROLL_THRESHOLD = 32

function onDialogBodyScroll() {
  if (!dialogScrollEl) return
  dialogCanScroll.value = dialogScrollEl.scrollHeight > dialogScrollEl.clientHeight + 8
  dialogScrolled.value = dialogScrollEl.scrollTop > DIALOG_SCROLL_THRESHOLD
}

function bindDialogScrollListener() {
  unbindDialogScrollListener()
  const text = document.querySelector('.plt-blog-dialog .v-card-text.e-dialog__body') as HTMLElement | null
  const card = document.querySelector('.plt-blog-dialog.v-card') as HTMLElement | null
  if (text && text.scrollHeight > text.clientHeight + 8) {
    dialogScrollEl = text
  } else if (card && card.scrollHeight > card.clientHeight + 8) {
    dialogScrollEl = card
  } else {
    dialogScrollEl = text ?? card
  }
  if (!dialogScrollEl) return
  dialogScrollEl.addEventListener('scroll', onDialogBodyScroll, { passive: true })
  dialogResizeObserver?.disconnect()
  dialogResizeObserver = new ResizeObserver(() => onDialogBodyScroll())
  dialogResizeObserver.observe(dialogScrollEl)
  onDialogBodyScroll()
}

function unbindDialogScrollListener() {
  dialogResizeObserver?.disconnect()
  dialogResizeObserver = null
  if (dialogScrollEl) {
    dialogScrollEl.removeEventListener('scroll', onDialogBodyScroll)
    dialogScrollEl = null
  }
  dialogCanScroll.value = false
  dialogScrolled.value = false
}

onMounted(() => {
  void site.ensureLoaded()
})

onBeforeUnmount(() => {
  unbindDialogScrollListener()
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
  normalizePublicPosts(localized.value, c.value, t('public.blog.untitledPost')),
)

const routeSlug = computed(() => String(route.params.slug || '').trim())

const activePost = computed((): PublicBlogPost | null => {
  const slug = routeSlug.value
  if (!slug) return null
  return posts.value.find((p) => p.slug === slug) ?? null
})

watch(activePost, (post) => {
  if (post) {
    pageHead.setDynamic(`${post.title} · Blog · eMatChef`, post.excerpt || post.title)
  } else {
    pageHead.clearDynamic()
  }
  syncDocumentHead(route)
}, { immediate: true })

watch(
  [routeSlug, posts],
  () => {
    postDialogOpen.value = Boolean(routeSlug.value)
  },
  { immediate: true },
)

watch(postDialogOpen, async (open) => {
  if (open) {
    await nextTick()
    bindDialogScrollListener()
  } else {
    unbindDialogScrollListener()
  }
})

watch(activePost, async () => {
  if (!postDialogOpen.value) return
  await nextTick()
  bindDialogScrollListener()
})

function openPost(slug: string) {
  router.push({ name: 'BlogPost', params: { slug } })
}

function closePost() {
  router.push({ name: 'Blog' })
}

function onDialogToggle(open: boolean) {
  if (!open) closePost()
}
</script>
