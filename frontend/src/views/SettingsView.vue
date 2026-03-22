<template>
  <div class="settings-view">
    <div class="settings-container">
      <!-- Linkes Menü -->
      <aside class="settings-menu">
        <nav class="settings-nav">
          <router-link
            v-for="item in allMenuItems"
            :key="item.id"
            :to="getSettingsLink(`/${item.id}`)"
            class="settings-nav-item"
            :class="{ active: isSettingsItemActive(item.id) }"
          >
            <component :is="item.icon" class="nav-icon" />
            <span class="nav-label">{{ item.label }}</span>
          </router-link>
        </nav>
      </aside>

      <!-- Rechter Content-Bereich -->
      <main class="settings-content">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, markRaw } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import IconSettings from '@/components/icons/IconSettings.vue'
import IconContacts from '@/components/icons/IconContacts.vue'
import IconEmployees from '@/components/icons/IconEmployees.vue'
import IconDashboard from '@/components/icons/IconDashboard.vue'
import IconActivities from '@/components/icons/IconActivities.vue'
import IconMaterials from '@/components/icons/IconMaterials.vue'

const route = useRoute()
const authStore = useAuthStore()

// Department-ID aus Route oder Store
const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

// Helper: /…/settings/<segment>
function getSettingsLink(path: string): string {
  if (!departmentId.value) return '#'
  return `/${departmentId.value}/settings${path}`
}

function isSettingsItemActive(itemId: string): boolean {
  const base = departmentId.value ? `/${departmentId.value}/settings`.replace(/\/$/, '') : ''
  const p = (route.path || '').replace(/\/$/, '') || '/'
  if (itemId === 'my-department') {
    return p === base || p === `${base}/my-department`
  }
  if (itemId === 'zeit') {
    return p === `${base}/zeit`
  }
  return p === `${base}/${itemId}` || p.startsWith(`${base}/${itemId}/`)
}

// Components mit markRaw markieren, um Vue-Warnungen zu vermeiden
const allMenuItems = ref([
  {
    id: 'my-department',
    label: 'Mein Department',
    icon: markRaw(IconDashboard)
  },
  {
    id: 'zeit',
    label: 'Zeit/Ort',
    icon: markRaw(IconSettings)
  },
  {
    id: 'categories',
    label: 'Kategorien',
    icon: markRaw(IconDashboard)
  },
  {
    id: 'users',
    label: 'Benutzer',
    icon: markRaw(IconEmployees)
  },
  {
    id: 'groups',
    label: 'Gruppen',
    icon: markRaw(IconContacts)
  },
  {
    id: 'activities',
    label: 'Aktivitäten',
    icon: markRaw(IconActivities)
  },
  {
    id: 'storage',
    label: 'Regale & Fächer',
    icon: markRaw(IconMaterials)
  },
  {
    id: 'templates',
    label: 'Vorlagen',
    icon: markRaw(IconSettings)
  }
])

</script>

<style scoped>
.settings-view {
  padding: 24px;
  height: 100%;
}

.settings-container {
  display: flex;
  gap: 24px;
  height: 100%;
  max-width: 1400px;
  margin: 0 auto;
}

.settings-menu {
  width: 240px;
  flex-shrink: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 16px 0;
  height: fit-content;
}

.settings-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.settings-nav-item {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: #64748b;
  text-decoration: none;
  transition: all 0.2s;
  border-left: 3px solid transparent;
}

.settings-nav-item:hover {
  background-color: #f1f5f9;
  color: #334155;
}

.settings-nav-item.active {
  background-color: #eff6ff;
  color: #2563eb;
  border-left-color: #2563eb;
  font-weight: 500;
}

.settings-nav-item .nav-icon {
  width: 20px;
  height: 20px;
  margin-right: 12px;
  flex-shrink: 0;
}

.settings-nav-item .nav-label {
  font-size: 14px;
}

.settings-content {
  flex: 1;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 24px;
  min-height: 600px;
}

/* Fade Transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
