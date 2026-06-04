<template>
  <div class="activity-date-picker-controls-bar v-date-picker-controls">
    <VBtn
      icon
      variant="text"
      :disabled="disabled.includes('prev-month')"
      :aria-label="t('$vuetify.datePicker.ariaLabel.previousMonth')"
      @click="prevMonth"
    >
      <VIcon>mdi-chevron-left</VIcon>
    </VBtn>

    <div class="activity-date-picker-controls-bar__center">
      <VBtn
        variant="text"
        rounded
        class="activity-date-picker-controls-bar__month"
        :disabled="disabled.includes('text')"
        :aria-label="t('$vuetify.datePicker.ariaLabel.selectMonth')"
        @click="openMonths"
      >
        {{ monthText }}
      </VBtn>
      <VBtn
        variant="text"
        rounded
        class="activity-date-picker-controls-bar__year"
        :disabled="disabled.includes('mode')"
        :aria-label="t('$vuetify.datePicker.ariaLabel.selectYear')"
        @click="openYears"
      >
        {{ yearText }}
      </VBtn>
    </div>

    <VBtn
      icon
      variant="text"
      :disabled="disabled.includes('next-month')"
      :aria-label="t('$vuetify.datePicker.ariaLabel.nextMonth')"
      @click="nextMonth"
    >
      <VIcon>mdi-chevron-right</VIcon>
    </VBtn>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { VBtn, VIcon } from 'vuetify/components'

defineProps<{
  viewMode: string
  disabled: string[]
  monthYearText: string
  monthText: string
  yearText: string
  openMonths: () => void
  openYears: () => void
  prevMonth: () => void
  nextMonth: () => void
  prevYear: () => void
  nextYear: () => void
}>()

const { t } = useI18n()
</script>
