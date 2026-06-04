<template>
  <Teleport to="body">
    <div class="global-toast-container" aria-live="polite">
      <TransitionGroup name="global-toast">
        <v-alert
          v-for="toast in items"
          :key="toast.id"
          :type="toast.type"
          variant="flat"
          density="compact"
          closable
          :class="['global-toast-alert', `global-toast-alert--${toast.type}`]"
          role="status"
          @click:close="toastStore.remove(toast.id)"
        >
          {{ toast.message }}
        </v-alert>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()
const { items } = storeToRefs(toastStore)
</script>
