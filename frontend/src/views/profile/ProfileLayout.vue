<template>
  <PageShell :title="t('profile.page.title')" max-width="800px">
    <template #actions>
      <EButton variant="secondary" size="small" data-testid="profile-back" @click="goBack">
        {{ t('profile.page.back') }}
      </EButton>
    </template>
    <ProfileContainer mode="page" />
  </PageShell>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { EButton } from '@/components/form/base'
import PageShell from '@/components/layout/PageShell.vue'
import ProfileContainer from '@/components/profile/ProfileContainer.vue'
import { profileFallbackPath, profileFromQuery } from '@/utils/profileReturn'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

function goBack() {
  void router.push(profileFromQuery(route.query) ?? profileFallbackPath(authStore.activeDepartmentId))
}
</script>
