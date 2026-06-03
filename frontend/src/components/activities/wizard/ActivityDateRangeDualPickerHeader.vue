<template>
  <div class="activity-date-range-dual-picker-header v-date-picker-controls">
    <VBtn
      icon
      variant="text"
      :aria-label="t('$vuetify.datePicker.ariaLabel.previousMonth')"
      @click="emit('prev')"
    >
      <VIcon>mdi-chevron-left</VIcon>
    </VBtn>

    <div class="activity-date-range-dual-picker-header__center">
      <VBtn
        variant="text"
        rounded
        class="activity-date-range-dual-picker-header__month"
        :aria-label="t('$vuetify.datePicker.ariaLabel.selectMonth')"
        @click="emit('open-left-month')"
      >
        {{ leftMonthText }}
      </VBtn>
      <VBtn
        variant="text"
        rounded
        class="activity-date-range-dual-picker-header__year"
        :aria-label="t('$vuetify.datePicker.ariaLabel.selectYear')"
        @click="emit('open-year')"
      >
        {{ yearText }}
      </VBtn>
      <VBtn
        variant="text"
        rounded
        class="activity-date-range-dual-picker-header__month"
        :aria-label="t('$vuetify.datePicker.ariaLabel.selectMonth')"
        @click="emit('open-right-month')"
      >
        {{ rightMonthText }}
      </VBtn>
    </div>

    <VBtn
      icon
      variant="text"
      :aria-label="t('$vuetify.datePicker.ariaLabel.nextMonth')"
      @click="emit('next')"
    >
      <VIcon>mdi-chevron-right</VIcon>
    </VBtn>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { VBtn, VIcon } from 'vuetify/components'

defineProps<{
  leftMonthText: string
  rightMonthText: string
  yearText: string
}>()

const emit = defineEmits<{
  prev: []
  next: []
  'open-left-month': []
  'open-right-month': []
  'open-year': []
}>()

const { t } = useI18n()
</script>
