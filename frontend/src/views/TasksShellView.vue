<template>
  <div class="dept-page tasks-shell">
    <div class="page-header header-content">
      <div class="header-left">
        <h1>Aufgaben</h1>
        <span class="subtitle">Allgemeine Übersicht und Druckaufträge</span>
      </div>
    </div>

    <div class="filter-bar tasks-shell-tabs">
      <div class="filter-tabs">
        <router-link
          :to="{ name: 'TasksGeneral', params: { departmentId } }"
          class="filter-tab"
          active-class="active"
        >
          Allgemein
        </router-link>
        <router-link
          :to="{ name: 'TasksPrint', params: { departmentId } }"
          class="filter-tab"
          active-class="active"
        >
          Drucken
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
.tasks-shell-tabs {
  margin-bottom: 20px;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 0;
}
</style>
