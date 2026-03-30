<template>
  <div class="dept-page accounting-shell">
    <div class="page-header header-content">
      <div class="header-left">
        <h1>Buchhaltung</h1>
        <span class="subtitle">Kostenstellen, Buchungen, Budget und Materialkosten – unterstützend zum Vereins-Finanztool</span>
      </div>
    </div>

    <div class="filter-bar accounting-shell-tabs">
      <div class="filter-tabs">
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingOverview', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">Übersicht</a>
        </router-link>
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingCostCenters', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">Kostenstellen</a>
        </router-link>
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingBookings', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">Buchungen</a>
        </router-link>
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingMaterialCosts', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">Materialkosten</a>
        </router-link>
        <router-link
          v-slot="{ isExactActive, navigate, href }"
          :to="{ name: 'AccountingBudget', params: { departmentId } }"
          custom
        >
          <a :href="href" class="filter-tab" :class="{ active: isExactActive }" @click="navigate($event)">Budget</a>
        </router-link>
      </div>
    </div>

    <router-view />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const departmentId = computed(() => String(route.params.departmentId || ''))
</script>

<style scoped>
@import '@/styles/accounting-view.css';
</style>
