<template>
  <div data-onboarding="profile-security">
    <ProfileSecurityTotpSection :open="true" class="!mt-0 !border-t-0 !pt-0" />
    <ProfileSecuritySessionsSection :open="true" />
    <ProfileSecurityActivitySection :open="true" />
  </div>
</template>

<script setup lang="ts">
import ProfileSecurityTotpSection from '@/components/layout/ProfileSecurityTotpSection.vue'
import ProfileSecuritySessionsSection from '@/components/layout/ProfileSecuritySessionsSection.vue'
import ProfileSecurityActivitySection from '@/components/layout/ProfileSecurityActivitySection.vue'
</script>
