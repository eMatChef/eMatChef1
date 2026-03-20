<template>
  <div class="admin-dashboard">
    <header class="dashboard-header">
      <div class="header-content">
        <h1>Admin-Übersicht</h1>
        <p class="welcome-text">
          Willkommen, {{ authStore.userDisplayName }} – {{ formatDate(new Date()) }}
        </p>
      </div>
    </header>

    <div v-if="isLoading" class="dashboard-loading">
      <div class="spinner"></div>
      <p>Dashboard wird geladen...</p>
    </div>

    <div v-else class="dashboard-content">
      <!-- Supportanfragen -->
      <section class="dashboard-section">
        <h2 class="section-title">
          <router-link to="/admin-dashboard/support-requests" class="section-title-link">
            Supportanfragen
          </router-link>
        </h2>
        <div class="stat-cards">
          <router-link to="/admin-dashboard/support-requests" class="stat-card submitted join-request-stat-link">
            <span class="stat-value">{{ pendingAdminCount }}</span>
            <span class="stat-label">Offene Admin-Anfragen</span>
          </router-link>
        </div>
        <router-link to="/admin-dashboard/support-requests" class="section-link">Zu Supportanfragen →</router-link>
      </section>

      <!-- Admin-Konfiguration -->
      <section class="dashboard-section">
        <h2 class="section-title">Konfiguration</h2>
        <div class="config-links">
          <router-link to="/admin-dashboard/settings/organisations" class="config-card">
            <span class="config-label">Organisationen</span>
            <span class="config-desc">Organisationen verwalten</span>
          </router-link>
          <router-link to="/admin-dashboard/settings/departments" class="config-card">
            <span class="config-label">Departments</span>
            <span class="config-desc">Departments verwalten</span>
          </router-link>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { getPendingAdminJoinRequests } from '@/api/joinRequests'

const authStore = useAuthStore()
const isLoading = ref(true)
const pendingAdminCount = ref(0)

function formatDate(d: Date): string {
  return d.toLocaleDateString('de-CH', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}

async function load() {
  isLoading.value = true
  try {
    const requests = await getPendingAdminJoinRequests('')
    pendingAdminCount.value = requests.length
  } catch (err) {
    console.error('Admin-Dashboard laden fehlgeschlagen:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => load())
</script>

<style scoped>
.admin-dashboard {
  padding: 24px;
  max-width: 1200px;
}

.dashboard-header {
  margin-bottom: 24px;
}

.dashboard-header h1 {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.welcome-text {
  color: #6b7280;
  margin: 0;
}

.dashboard-loading {
  text-align: center;
  padding: 60px 24px;
  color: #6b7280;
}

.dashboard-section {
  margin-bottom: 32px;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #374151;
  margin: 0 0 12px 0;
}

.section-title-link {
  color: inherit;
  text-decoration: none;
}

.section-title-link:hover {
  text-decoration: underline;
}

.section-link {
  display: inline-block;
  margin-top: 8px;
  color: #4f46e5;
  font-size: 14px;
  text-decoration: none;
}

.section-link:hover {
  text-decoration: underline;
}

.stat-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.stat-card {
  padding: 16px 20px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #fff;
  min-width: 140px;
  text-decoration: none;
  color: inherit;
  transition: all 0.15s;
}

.stat-card:hover {
  border-color: #c7d2fe;
  background: #eef2ff;
}

.stat-card.submitted {
  background: #dbeafe;
  border-color: #93c5fd;
}

.stat-card.submitted .stat-value {
  color: #2563eb;
}

.stat-value {
  display: block;
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
}

.stat-label {
  font-size: 13px;
  color: #6b7280;
}

.config-links {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.config-card {
  display: flex;
  flex-direction: column;
  padding: 16px 20px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #fff;
  min-width: 180px;
  text-decoration: none;
  color: inherit;
  transition: all 0.15s;
}

.config-card:hover {
  border-color: #c7d2fe;
  background: #f8fafc;
}

.config-label {
  font-weight: 600;
  color: #1f2937;
}

.config-desc {
  font-size: 13px;
  color: #6b7280;
  margin-top: 4px;
}
</style>
