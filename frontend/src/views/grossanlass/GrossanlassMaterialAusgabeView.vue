<template>
  <div class="ausgabe">
    <p class="ausgabe__intro">
      {{ t('grossanlass.ausgabe.intro') }}
      <v-chip size="x-small" variant="tonal" color="warning" class="ml-2">{{ t('grossanlass.ausgabe.prototype') }}</v-chip>
    </p>
    <p class="ausgabe__note">{{ t('grossanlass.ausgabe.notPacking') }}</p>

    <v-tabs v-model="tab" class="materials-view-tabs" color="primary" show-arrows>
      <v-tab value="issue"><v-icon icon="mdi-barcode-scan" start size="18" />{{ t('grossanlass.ausgabe.tab.issue') }}</v-tab>
      <v-tab value="open">
        <v-icon icon="mdi-account-clock-outline" start size="18" />{{ t('grossanlass.ausgabe.tab.open') }}
        <v-chip v-if="overdue" size="x-small" variant="flat" color="error" class="ml-2">{{ overdue }}</v-chip>
      </v-tab>
      <v-tab value="history"><v-icon icon="mdi-history" start size="18" />{{ t('grossanlass.ausgabe.tab.history') }}</v-tab>
    </v-tabs>

    <GrossanlassAusgabeSchalter v-if="tab === 'issue'" />
    <GrossanlassAusgabeOffen v-else-if="tab === 'open'" />
    <GrossanlassAusgabeVerlauf v-else />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import GrossanlassAusgabeOffen from '@/views/grossanlass/ausgabe/GrossanlassAusgabeOffen.vue'
import GrossanlassAusgabeSchalter from '@/views/grossanlass/ausgabe/GrossanlassAusgabeSchalter.vue'
import GrossanlassAusgabeVerlauf from '@/views/grossanlass/ausgabe/GrossanlassAusgabeVerlauf.vue'
import { isOverdue, useGaAusgabeMock } from '@/views/grossanlass/ausgabe/gaAusgabeMock'
import '@/styles/views/materials-view-tabs.css'

const { t } = useI18n()
const { loans } = useGaAusgabeMock()
const tab = ref<'issue' | 'open' | 'history'>('issue')
const overdue = computed(() => loans.value.filter((loan) => isOverdue(loan)).length)
</script>

<style scoped>
.ausgabe {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 4px 0 24px;
}
.ausgabe__intro {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
}
.ausgabe__note {
  margin: 0;
  padding: 8px 12px;
  border-radius: 10px;
  background: #f1f5f9;
  color: #475569;
  font-size: 0.84rem;
}
</style>
