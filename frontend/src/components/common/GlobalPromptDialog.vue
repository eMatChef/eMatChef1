<template>
  <EDialog
    v-model="dialogOpen"
    max-width="420"
    card-variant="outlined"
    card-class="global-prompt-dialog-card"
  >
    <div v-if="promptStore.options" class="global-prompt-dialog">
      <div class="global-prompt-dialog__icon" aria-hidden="true">
        <v-icon icon="mdi-alert" size="28" />
      </div>
      <h3 class="global-prompt-dialog__title">{{ promptStore.options.title }}</h3>
      <p v-if="promptStore.options.message" class="global-prompt-dialog__message">
        {{ promptStore.options.message }}
      </p>
      <ETextField
        ref="inputRef"
        v-model="promptStore.inputValue"
        class="global-prompt-dialog__input"
        :label="promptStore.options.placeholder || undefined"
        :placeholder="promptStore.options.placeholder"
        hide-details
        autofocus
        @keydown.enter.prevent="promptStore.confirm()"
      />
    </div>

    <template v-if="promptStore.options" #actions>
      <v-spacer />
      <EButton variant="secondary" @click="promptStore.cancel()">
        {{ promptStore.options.cancelText }}
      </EButton>
      <EButton
        :disabled="promptStore.options.required && !promptStore.inputValue.trim()"
        @click="promptStore.confirm()"
      >
        {{ promptStore.options.confirmText }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { EButton, EDialog, ETextField } from '@/components/form/base'
import { usePromptStore } from '@/stores/prompt'

const promptStore = usePromptStore()
const inputRef = ref<InstanceType<typeof ETextField> | null>(null)

const dialogOpen = computed({
  get: () => promptStore.isOpen,
  set: (open: boolean) => {
    if (!open) {
      promptStore.cancel()
    }
  },
})

watch(
  () => promptStore.isOpen,
  async (open) => {
    if (!open) return
    await nextTick()
    const el = inputRef.value?.$el?.querySelector('input') as HTMLInputElement | null
    el?.focus()
    el?.select()
  },
)
</script>
