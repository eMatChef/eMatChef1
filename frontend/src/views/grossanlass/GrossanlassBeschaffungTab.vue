<template>
  <div class="grossanlass-beschaffung-tab">
    <EEmptyState
      variant="create"
      :icon="tabMeta.icon"
      :title="t(tabMeta.titleKey)"
      :description="t(tabMeta.descriptionKey)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'

const TAB_META: Record<
  string,
  { icon: string; titleKey: string; descriptionKey: string }
> = {
  uebersicht: {
    icon: 'mdi-chart-box-outline',
    titleKey: 'grossanlass.beschaffung.uebersicht.emptyTitle',
    descriptionKey: 'grossanlass.beschaffung.uebersicht.emptyDescription',
  },
  bedarf: {
    icon: 'mdi-clipboard-list-outline',
    titleKey: 'grossanlass.beschaffung.bedarf.emptyTitle',
    descriptionKey: 'grossanlass.beschaffung.bedarf.emptyDescription',
  },
  offerten: {
    icon: 'mdi-file-document-outline',
    titleKey: 'grossanlass.beschaffung.offerten.emptyTitle',
    descriptionKey: 'grossanlass.beschaffung.offerten.emptyDescription',
  },
  bestellungen: {
    icon: 'mdi-cart-outline',
    titleKey: 'grossanlass.beschaffung.bestellungen.emptyTitle',
    descriptionKey: 'grossanlass.beschaffung.bestellungen.emptyDescription',
  },
  erhalten: {
    icon: 'mdi-package-check',
    titleKey: 'grossanlass.beschaffung.erhalten.emptyTitle',
    descriptionKey: 'grossanlass.beschaffung.erhalten.emptyDescription',
  },
}

const route = useRoute()
const { t } = useI18n()

const tabMeta = computed(() => {
  const tabId = (route.meta.beschaffungTab as string) || 'uebersicht'
  return TAB_META[tabId] ?? TAB_META.uebersicht
})
</script>

<style scoped>
.grossanlass-beschaffung-tab {
  padding: 8px 0 24px;
}
</style>
