<template>
  <Teleport to="body">
    <Transition name="pcw-fade">
      <div v-if="store.isOpen" class="pcw-overlay" @click.self="onCancel">
        <div class="pcw-dialog" role="dialog" aria-modal="true" aria-labelledby="pcw-title">
          <div class="pcw-icon pcw-icon--warning" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </div>
          <h3 id="pcw-title" class="pcw-title">{{ t('components.physicalComboWarning.title') }}</h3>
          <p class="pcw-lead">
            {{ t('components.physicalComboWarning.affects') }}
            <strong v-for="(c, i) in store.combos" :key="c.id">
              <template v-if="i > 0">, </template>„{{ c.name }}“
            </strong>
          </p>
          <p class="pcw-text">
            {{ t('components.physicalComboWarning.p1a') }}<strong>{{ t('components.physicalComboWarning.p1strong1') }}</strong
            >{{ t('components.physicalComboWarning.p1b') }}<strong>{{ t('components.physicalComboWarning.p1strong2') }}</strong
            >{{ t('components.physicalComboWarning.p1c') }}
          </p>
          <p class="pcw-hint">
            {{ t('components.physicalComboWarning.hintAdjust') }}
          </p>
          <p v-if="store.combos.length > 1" class="pcw-hint">
            {{ t('components.physicalComboWarning.hintMultiple') }}
          </p>
          <div class="pcw-actions">
            <button type="button" class="pcw-btn pcw-btn--ghost" @click="onCancel">
              {{ t('common.cancel') }}
            </button>
            <button type="button" class="pcw-btn pcw-btn--secondary" @click="onOpenCombo">
              {{ t('components.physicalComboWarning.openCombo') }}
            </button>
            <button type="button" class="pcw-btn pcw-btn--primary" @click="onProceed">
              {{ t('components.physicalComboWarning.proceed') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { usePhysicalComboWarningStore } from '@/stores/physicalComboWarning'

const { t } = useI18n()
const store = usePhysicalComboWarningStore()
const router = useRouter()
const route = useRoute()

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && store.isOpen) {
    store.cancel()
  }
}

watch(
  () => store.isOpen,
  (open) => {
    if (open) document.addEventListener('keydown', onKeydown)
    else document.removeEventListener('keydown', onKeydown)
  }
)

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})

function onCancel() {
  store.cancel()
}

function onProceed() {
  store.proceed()
}

function onOpenCombo() {
  const first = store.combos[0]
  const deptId = String(route.params.departmentId || '').trim()
  if (first && deptId) {
    router.push({ path: `/${deptId}/materials/${first.id}` })
  }
  store.abortAfterOpenCombo()
}
</script>

<style scoped>
.pcw-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(5px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2400;
  padding: 20px;
}

.pcw-dialog {
  background: #fff;
  border-radius: 16px;
  padding: 26px 26px 22px;
  max-width: 440px;
  width: 100%;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.22);
  text-align: center;
}

.pcw-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  margin: 0 auto 14px;
}

.pcw-icon--warning {
  background: #fef3c7;
  color: #b45309;
}

.pcw-title {
  font-size: 1.12rem;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 10px;
  line-height: 1.35;
}

.pcw-lead {
  font-size: 0.95rem;
  color: #334155;
  margin: 0 0 12px;
  line-height: 1.45;
}

.pcw-text {
  font-size: 0.9rem;
  color: #64748b;
  margin: 0 0 8px;
  line-height: 1.5;
}

.pcw-hint {
  font-size: 0.82rem;
  color: #94a3b8;
  margin: 0 0 22px;
  line-height: 1.45;
}

.pcw-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

@media (min-width: 480px) {
  .pcw-actions {
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 10px;
  }
}

.pcw-btn {
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}

.pcw-btn--primary {
  background: linear-gradient(180deg, #16a34a 0%, #15803d 100%);
  color: #fff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.pcw-btn--primary:hover {
  filter: brightness(1.05);
}

.pcw-btn--secondary {
  background: #f1f5f9;
  color: #0f172a;
  border: 1px solid #e2e8f0;
}

.pcw-btn--secondary:hover {
  background: #e2e8f0;
}

.pcw-btn--ghost {
  background: transparent;
  color: #64748b;
}

.pcw-btn--ghost:hover {
  color: #0f172a;
  background: #f8fafc;
}

.pcw-fade-enter-active,
.pcw-fade-leave-active {
  transition: opacity 0.2s ease;
}

.pcw-fade-enter-from,
.pcw-fade-leave-to {
  opacity: 0;
}
</style>
