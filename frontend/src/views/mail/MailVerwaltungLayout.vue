<template>
  <div class="mail-verwaltung">
    <header class="mail-verwaltung-head">
      <h1 class="mail-title">E-Mail</h1>
      <p class="mail-sub">
        Vorlagen, Absender und Versandprotokoll — Versand über SMTP in der JSON-Datei, <code>MAILER_DSN</code> oder bei
        fehlendem SMTP als Datei-Spool.
      </p>
      <nav class="mail-subnav" aria-label="Mail-Untermenü">
        <router-link
          v-for="item in subItems"
          :key="item.name"
          :to="item.to"
          class="mail-subnav-item"
          active-class="active"
        >
          {{ item.label }}
        </router-link>
      </nav>
    </header>
    <div class="mail-verwaltung-body">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const mailBase = computed(() => {
  const raw = route.params.departmentId
  if (typeof raw === 'string' && raw.trim()) {
    return `/${raw}/verwaltung/mail`
  }
  return '/admin-dashboard/verwaltung/mail'
})

const subItems = computed(() => [
  { label: 'Versand', to: `${mailBase.value}/versand`, name: 'versand' },
  { label: 'Einstellungen', to: `${mailBase.value}/einstellungen`, name: 'einstellungen' },
  { label: 'Log', to: `${mailBase.value}/log`, name: 'log' },
])
</script>

<style scoped>
.mail-verwaltung {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.mail-verwaltung-head {
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 12px;
}

.mail-title {
  margin: 0 0 6px 0;
  font-size: 24px;
  font-weight: 600;
  color: #0f172a;
}

.mail-sub {
  margin: 0 0 14px 0;
  font-size: 14px;
  color: #64748b;
  line-height: 1.45;
}

.mail-subnav {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.mail-subnav-item {
  display: inline-flex;
  align-items: center;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  text-decoration: none;
  border: 1px solid transparent;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.mail-subnav-item:hover {
  background: #f1f5f9;
  color: #334155;
}

.mail-subnav-item.active {
  background: #eff6ff;
  color: #2563eb;
  border-color: #bfdbfe;
}

.mail-verwaltung-body {
  min-height: 200px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.12s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
