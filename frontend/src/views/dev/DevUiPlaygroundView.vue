<template>
  <div class="sandbox-view">
    <v-container class="sandbox-view__container">
      <v-alert type="warning" variant="tonal" density="comfortable" class="mb-6">
        {{ t('devSandbox.devOnlyHint') }}
      </v-alert>

      <header class="sandbox-view__header">
        <div>
          <h1 class="sandbox-view__title">{{ t('devSandbox.title') }}</h1>
          <p class="sandbox-view__lead">{{ t('devSandbox.lead') }}</p>
        </div>
        <v-btn variant="outlined" :to="dashboardLink">
          {{ t('devSandbox.backToApp') }}
        </v-btn>
      </header>

      <!-- Breakpoint -->
      <v-card class="mb-6" variant="outlined">
        <v-card-title>{{ t('devSandbox.sections.breakpoints') }}</v-card-title>
        <v-card-text>
          <v-table density="compact">
            <tbody>
              <tr v-for="row in breakpointRows" :key="row.key">
                <td class="font-weight-medium">{{ row.key }}</td>
                <td>
                  <v-chip size="small" :color="row.active ? 'primary' : undefined" variant="tonal">
                    {{ row.active ? t('devSandbox.yes') : t('devSandbox.no') }}
                  </v-chip>
                </td>
              </tr>
              <tr>
                <td class="font-weight-medium">{{ t('devSandbox.windowSize') }}</td>
                <td>{{ windowWidth }} × {{ windowHeight }} px</td>
              </tr>
              <tr v-for="row in safeAreaRows" :key="row.key">
                <td class="font-weight-medium">{{ row.key }}</td>
                <td>{{ row.value }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>

      <!-- Buttons -->
      <v-card class="mb-6" variant="outlined">
        <v-card-title>{{ t('devSandbox.sections.buttons') }}</v-card-title>
        <v-card-text class="d-flex flex-wrap ga-3">
          <v-btn color="primary">{{ t('devSandbox.samples.primary') }}</v-btn>
          <v-btn variant="outlined">{{ t('devSandbox.samples.outlined') }}</v-btn>
          <v-btn variant="text">{{ t('devSandbox.samples.text') }}</v-btn>
          <v-btn color="error" variant="tonal">{{ t('devSandbox.samples.error') }}</v-btn>
          <v-btn color="primary" prepend-icon="mdi-plus">{{ t('devSandbox.samples.withIcon') }}</v-btn>
        </v-card-text>
      </v-card>

      <!-- Form fields (raw V*) -->
      <v-card class="mb-6" variant="outlined">
        <v-card-title>{{ t('devSandbox.sections.fields') }}</v-card-title>
        <v-card-subtitle>{{ t('devSandbox.rawVuetifyNote') }}</v-card-subtitle>
        <v-card-text>
          <v-row dense>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="sampleText"
                :label="t('devSandbox.samples.textField')"
                variant="outlined"
                density="comfortable"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-select
                v-model="sampleSelect"
                :items="sampleSelectItems"
                :label="t('devSandbox.samples.select')"
                variant="outlined"
                density="comfortable"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-checkbox v-model="sampleCheckbox" :label="t('devSandbox.samples.checkbox')" />
            </v-col>
            <v-col cols="12" md="6">
              <v-switch v-model="sampleSwitch" :label="t('devSandbox.samples.switch')" color="primary" />
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Dialog (E*) -->
      <v-card class="mb-6" variant="outlined">
        <v-card-title>{{ t('devSandbox.sections.dialog') }}</v-card-title>
        <v-card-subtitle>{{ t('devSandbox.eDialogNote') }}</v-card-subtitle>
        <v-card-text>
          <EButton @click="dialogOpen = true">
            {{ t('devSandbox.openDialog') }}
          </EButton>
        </v-card-text>
      </v-card>

      <!-- Layout shell (minimal, ohne AppLayout) -->
      <v-card class="mb-6" variant="outlined">
        <v-card-title>{{ t('devSandbox.sections.layout') }}</v-card-title>
        <v-card-subtitle>{{ t('devSandbox.layoutHint') }}</v-card-subtitle>
        <v-card-text>
          <div class="sandbox-layout-demo">
            <v-navigation-drawer
              v-model="demoDrawer"
              class="sandbox-layout-demo__drawer"
              color="#26353b"
              permanent
              rail
              width="200"
              rail-width="56"
            >
              <div class="sandbox-layout-demo__drawer-label">Nav</div>
            </v-navigation-drawer>
            <v-app-bar flat height="48" class="sandbox-layout-demo__bar">
              <v-app-bar-title>{{ t('devSandbox.layoutBarTitle') }}</v-app-bar-title>
            </v-app-bar>
            <v-main class="sandbox-layout-demo__main">
              <p class="text-body-2 mb-0">{{ t('devSandbox.layoutMainText') }}</p>
            </v-main>
          </div>
        </v-card-text>
      </v-card>

      <!-- E* (produktive Wrapper) -->
      <v-card variant="outlined">
        <v-card-title>{{ t('devSandbox.sections.eComponents') }}</v-card-title>
        <v-card-subtitle>{{ t('devSandbox.eComponentsNote') }}</v-card-subtitle>
        <v-card-text>
          <v-row dense>
            <v-col cols="12" md="6">
              <ETextField
                v-model="eSampleText"
                :label="t('devSandbox.samples.textField')"
                :placeholder="t('devSandbox.samples.textField')"
              />
            </v-col>
            <v-col cols="12" md="6">
              <ESelect
                v-model="eSampleSelect"
                :items="sampleSelectItems"
                :label="t('devSandbox.samples.select')"
              />
            </v-col>
            <v-col cols="12">
              <ETextarea
                v-model="eSampleTextarea"
                :label="t('devSandbox.samples.textarea')"
                rows="2"
              />
            </v-col>
            <v-col cols="12" md="6">
              <ECheckbox v-model="eSampleCheckbox" :label="t('devSandbox.samples.checkbox')" />
            </v-col>
            <v-col cols="12" md="6">
              <ESwitch v-model="eSampleSwitch" :label="t('devSandbox.samples.switch')" />
            </v-col>
          </v-row>

          <p class="text-caption text-medium-emphasis mt-4 mb-2">{{ t('devSandbox.eButtonVariants') }}</p>
          <div class="d-flex flex-wrap ga-3 mb-4">
            <EButton>{{ t('devSandbox.samples.primary') }}</EButton>
            <EButton variant="secondary">{{ t('devSandbox.samples.outlined') }}</EButton>
            <EButton variant="text">{{ t('devSandbox.samples.text') }}</EButton>
            <EButton variant="danger">{{ t('devSandbox.samples.error') }}</EButton>
          </div>

          <ECard variant="outlined" class="pa-4">
            <p class="text-body-2 mb-0">{{ t('devSandbox.eCardSample') }}</p>
          </ECard>
        </v-card-text>
      </v-card>
    </v-container>

    <EDialog v-model="dialogOpen" :title="t('devSandbox.dialogTitle')" max-width="480">
      {{ t('devSandbox.dialogBody') }}
      <template #actions>
        <v-spacer />
        <EButton variant="text" @click="dialogOpen = false">{{ t('devSandbox.dialogClose') }}</EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import {
  EButton,
  ECard,
  ECheckbox,
  EDialog,
  ESelect,
  ESwitch,
  ETextField,
  ETextarea,
} from '@/components/form/base'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const display = useDisplay()

const sampleText = ref('')
const sampleSelect = ref<string | null>(null)
const sampleCheckbox = ref(false)
const sampleSwitch = ref(true)
const eSampleText = ref('')
const eSampleSelect = ref<string | null>(null)
const eSampleTextarea = ref('')
const eSampleCheckbox = ref(false)
const eSampleSwitch = ref(true)
const dialogOpen = ref(false)
const demoDrawer = ref(true)
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 0)
const windowHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 0)

const sampleSelectItems = computed(() => [
  { title: t('devSandbox.samples.optionA'), value: 'a' },
  { title: t('devSandbox.samples.optionB'), value: 'b' },
])

const dashboardLink = computed(() => {
  const id =
    (route.params.departmentId as string) ||
    authStore.activeDepartmentId ||
    authStore.departments.find((d) => d.is_primary)?.department_id ||
    authStore.departments[0]?.department_id
  return id ? `/${id}` : '/dashboard'
})

const breakpointRows = computed(() => [
  { key: 'xs', active: display.xs.value },
  { key: 'sm', active: display.sm.value },
  { key: 'smAndUp', active: display.smAndUp.value },
  { key: 'md', active: display.md.value },
  { key: 'mdAndUp', active: display.mdAndUp.value },
  { key: 'lg', active: display.lg.value },
  { key: 'lgAndUp', active: display.lgAndUp.value },
  { key: 'xl', active: display.xl.value },
  { key: 'mobile', active: display.mobile.value },
])

const safeAreaInsets = ref({ top: '0px', right: '0px', bottom: '0px', left: '0px' })

const safeAreaRows = computed(() => [
  { key: '--emc-safe-top', value: safeAreaInsets.value.top },
  { key: '--emc-safe-bottom', value: safeAreaInsets.value.bottom },
  { key: '--emc-safe-left', value: safeAreaInsets.value.left },
  { key: '--emc-safe-right', value: safeAreaInsets.value.right },
])

function readSafeAreaInsets() {
  const root = getComputedStyle(document.documentElement)
  safeAreaInsets.value = {
    top: root.getPropertyValue('--emc-safe-top').trim() || '0px',
    right: root.getPropertyValue('--emc-safe-right').trim() || '0px',
    bottom: root.getPropertyValue('--emc-safe-bottom').trim() || '0px',
    left: root.getPropertyValue('--emc-safe-left').trim() || '0px',
  }
}

function updateWindowSize() {
  windowWidth.value = window.innerWidth
  windowHeight.value = window.innerHeight
}

onMounted(() => {
  window.addEventListener('resize', updateWindowSize)
  updateWindowSize()
  readSafeAreaInsets()
})

onUnmounted(() => {
  window.removeEventListener('resize', updateWindowSize)
})
</script>

<style scoped>
.sandbox-view {
  padding-bottom: 48px;
}

.sandbox-view__container {
  max-width: 960px;
}

.sandbox-view__header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 24px;
}

.sandbox-view__title {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 700;
  color: #1a1a2e;
}

.sandbox-view__lead {
  margin: 8px 0 0;
  color: #6b7280;
  max-width: 42rem;
}

.sandbox-layout-demo {
  position: relative;
  height: 220px;
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  overflow: hidden;
  background: #fff;
}

.sandbox-layout-demo__drawer {
  position: absolute !important;
  height: 100% !important;
}

.sandbox-layout-demo__drawer-label {
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.75rem;
  font-weight: 600;
  text-align: center;
  padding-top: 12px;
}

.sandbox-layout-demo__bar {
  position: absolute !important;
  top: 0;
  right: 0;
  left: 56px;
  width: auto !important;
}

.sandbox-layout-demo__main {
  position: absolute;
  top: 48px;
  right: 0;
  bottom: 0;
  left: 56px;
  padding: 16px;
  overflow: auto;
  background: #f8fafc;
}
</style>
