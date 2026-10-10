<template>
  <div data-onboarding="profile-departments">
    <h4 class="mb-1 text-[0.82rem] font-bold text-slate-700">{{ t('profile.departments.title') }}</h4>
    <p class="mb-3 text-[0.82rem] text-slate-500">{{ t('profile.departments.hint') }}</p>

    <p v-if="items.length === 0" class="mb-3 text-[0.82rem] text-slate-500" data-testid="no-departments">
      {{ t('profile.departments.empty') }}
    </p>
    <ul v-else class="mb-4 flex flex-col gap-2" data-testid="departments">
      <li
        v-for="item in items"
        :key="item.id"
        class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-slate-200 px-3 py-2 text-[0.85rem]"
        :data-testid="`department-${item.id}`"
      >
        <span class="min-w-0 flex-1 basis-40 font-medium text-slate-800">{{ item.name }}</span>
        <span class="text-[0.78rem] text-slate-500">{{ item.roleLabel }}</span>
        <span v-if="item.isGrossanlass" class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.72rem] font-semibold text-slate-700">
          {{ t('profile.departments.grossanlass') }}
        </span>
        <span v-if="item.isPrimary" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[0.72rem] font-semibold text-emerald-800">
          {{ t('profile.departments.primary') }}
        </span>
        <span v-if="item.isActive" class="rounded-full bg-sky-50 px-2 py-0.5 text-[0.72rem] font-semibold text-sky-800">
          {{ t('profile.departments.active') }}
        </span>
      </li>
    </ul>

    <ProfileMiDataMembershipsAccordion :open="true" />
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentRoleLabelsStore } from '@/stores/departmentRoleLabels'
import ProfileMiDataMembershipsAccordion from '@/components/layout/ProfileMiDataMembershipsAccordion.vue'

const { t } = useI18n()
const authStore = useAuthStore()
const roleLabelsStore = useDepartmentRoleLabelsStore()

/** Read-only list of the user's department memberships; changes happen in the department settings. */
const items = computed(() =>
  authStore.departments.map((dept) => ({
    id: dept.department_id,
    name: dept.department?.name || dept.department_id,
    isPrimary: Boolean(dept.is_primary),
    isGrossanlass: Boolean(dept.department?.is_grossanlass),
    isActive: dept.department_id === authStore.activeDepartmentId,
    roleLabel: roleLabelsStore.labelFor(dept.role, dept.department_id, t, { i18nNamespace: 'adminUsers' }),
  })),
)

watch(
  () => authStore.departments.map((d) => d.department_id).join(','),
  () => {
    for (const dept of authStore.departments) void roleLabelsStore.load(dept.department_id)
  },
  { immediate: true },
)
</script>
