<template>
  <ProfileSecurityExternalIdentitiesSection :open="true" class="!mt-0 !border-t-0 !pt-0" />
</template>

<script setup lang="ts">
import { nextTick, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { takeExternalIdentityLinkResult } from '@/api/profileSecurity'
import { PROFILE_SECURITY_RETURN_PARAM } from '@/utils/oauthReturnParams'
import { PROFILE_FROM_PARAM, takeRememberedProfileFrom } from '@/utils/profileReturn'
import ProfileSecurityExternalIdentitiesSection from '@/components/layout/ProfileSecurityExternalIdentitiesSection.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

/**
 * Return from the OAuth link flow: ?profile_security=1&oauth=linked|error&provider=…&reason=…
 * The URL is only a hint. The outcome is fetched once from the server (bound to the session), shown, and the
 * hint parameters are removed. Without a server-side result (prepared URL, reload, normal sign-in) nothing happens.
 */
async function handleLinkReturn() {
  const query = route.query
  if (query[PROFILE_SECURITY_RETURN_PARAM] !== '1') return
  const {
    [PROFILE_SECURITY_RETURN_PARAM]: _hint,
    oauth: _oauth,
    provider: _provider,
    reason: _reason,
    ...rest
  } = query
  void _hint
  void _oauth
  void _provider
  void _reason
  // The provider redirect drops the query: restore the parked return path.
  const from = takeRememberedProfileFrom()
  const nextQuery = from && !rest[PROFILE_FROM_PARAM] ? { ...rest, [PROFILE_FROM_PARAM]: from } : rest
  await router.replace({ path: route.path, query: nextQuery, hash: route.hash })

  // After the return the app reloads: ask only once the profile is loaded.
  if (!authStore.profile) {
    await new Promise<void>((resolve) => {
      const stop = watch(
        () => authStore.profile,
        (profile) => {
          if (profile) {
            stop()
            resolve()
          }
        },
        { immediate: true },
      )
    })
  }
  const profileId = authStore.profileId || authStore.profile?.id || ''
  const result = await (profileId ? takeExternalIdentityLinkResult(profileId) : Promise.resolve(null)).catch(() => null)
  if (!result) return
  await nextTick()
  window.dispatchEvent(
    new CustomEvent('emc-profile-security-link-result', {
      detail: { status: result.status, reason: result.reason, provider: result.provider },
    }),
  )
  if (result.status === 'linked') toast.success(t('layout.profileModal.externalIdentities.linkedToast'))
}

onMounted(() => void handleLinkReturn())
</script>
