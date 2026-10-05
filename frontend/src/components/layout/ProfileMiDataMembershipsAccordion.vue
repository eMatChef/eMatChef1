<template>
  <details v-show="available" class="profile-accordion">
    <summary class="profile-accordion__summary">{{ t('layout.profileModal.midataSection') }}</summary>
    <div class="profile-accordion__body">
      <MiDataMembershipOffers v-if="open" @availability="available = $event" />
    </div>
  </details>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import MiDataMembershipOffers from '@/components/auth/MiDataMembershipOffers.vue'

/** Weitere verifizierte MiData-Zugehörigkeiten für User, die schon eine Membership haben. */
defineProps<{ open?: boolean }>()

const { t } = useI18n()
const available = ref(false)
</script>
