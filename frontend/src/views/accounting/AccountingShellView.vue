<template>
  <div class="dept-page accounting-shell">
    <div class="page-header header-content">
      <div class="header-left">
        <h1>{{ t('accounting.shell.title') }}</h1>
        <span class="subtitle">{{ t('accounting.shell.subtitle') }}</span>
      </div>
    </div>

    <div class="filter-bar accounting-shell-tabs">
      <div class="filter-tabs">
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingOverview', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">{{ t('accounting.shell.tabOverview') }}</a>
        </router-link>
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingCostCenters', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">{{ t('accounting.shell.tabCostCenters') }}</a>
        </router-link>
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingBookings', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">{{ t('accounting.shell.tabBookings') }}</a>
        </router-link>
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingMaterialCosts', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">{{ t('accounting.shell.tabMaterialCosts') }}</a>
        </router-link>
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingBudget', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">{{ t('accounting.shell.tabBudget') }}</a>
        </router-link>
      </div>
    </div>

    <router-view />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const departmentId = computed(() => String(route.params.departmentId || ''))
</script>

<style scoped>
@import '@/styles/accounting-view.css';
</style>
