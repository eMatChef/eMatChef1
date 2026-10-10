<template>
  <section class="verkauf">
    <header class="verkauf__head">
      <div>
        <h3 class="verkauf__title">{{ t('grossanlass.rueckbau.sale.title') }}</h3>
        <p class="verkauf__hint">{{ t('grossanlass.rueckbau.sale.linkHint') }}</p>
      </div>
      <router-link :to="`/${departmentId}/ga/material/resale`" class="verkauf__link">
        <EButton variant="primary">
          <v-icon icon="mdi-tag-multiple-outline" start size="18" /> {{ t('grossanlass.rueckbau.sale.openResale') }}
        </EButton>
      </router-link>
    </header>

    <EEmptyState
      v-if="!fromRueckbau.length"
      variant="generic"
      icon="mdi-tag-outline"
      :title="t('grossanlass.rueckbau.sale.emptyTitle')"
      :description="t('grossanlass.rueckbau.sale.emptyText')"
    />
    <ul v-else class="verkauf__list">
      <li v-for="offer in fromRueckbau" :key="offer.id">
        <span><strong>{{ offer.qty }}× {{ offer.name }}</strong> · {{ offer.origin }}</span>
        <v-chip size="x-small" variant="flat" :color="offer.status === 'published' ? 'success' : 'grey'">
          {{ t(`grossanlass.verkauf.status.${offer.status}`) }}
        </v-chip>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useGaVerkaufMock } from '@/views/grossanlass/weiterverkauf/gaVerkaufMock'

const { t } = useI18n()
const route = useRoute()
const { offers } = useGaVerkaufMock()
const departmentId = computed(() => String(route.params.departmentId || ''))
const fromRueckbau = computed(() => offers.value.filter((offer) => offer.origin.startsWith('Rückbau')))
</script>

<style scoped>
.verkauf {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.verkauf__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}
.verkauf__title {
  margin: 0;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.verkauf__hint {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 0.86rem;
}
.verkauf__link {
  text-decoration: none;
}
.verkauf__list {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.verkauf__list li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  font-size: 0.88rem;
}
</style>
