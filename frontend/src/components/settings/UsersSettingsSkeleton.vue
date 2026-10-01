<template>
  <div
    class="users-settings-skeleton"
    :class="{ 'users-settings-skeleton--embedded': embedded }"
    role="status"
    aria-busy="true"
    :aria-label="message || undefined"
  >
    <div class="users-settings-skeleton__accordion users-settings-skeleton__accordion--collapsed">
      <div class="users-settings-skeleton__summary">
        <span class="users-settings-skeleton__line users-settings-skeleton__line--summary" />
      </div>
    </div>

    <div
      v-if="showInvitesSkeleton"
      class="users-settings-skeleton__accordion users-settings-skeleton__accordion--open"
    >
      <div class="users-settings-skeleton__summary">
        <span class="users-settings-skeleton__line users-settings-skeleton__line--summary" />
      </div>
      <div class="users-settings-skeleton__body">
        <div class="users-settings-skeleton__invite">
          <div class="users-settings-skeleton__invite-main">
            <span class="users-settings-skeleton__line users-settings-skeleton__line--email" />
            <span class="users-settings-skeleton__line users-settings-skeleton__line--badge" />
            <span class="users-settings-skeleton__line users-settings-skeleton__line--badge-short" />
          </div>
          <div class="users-settings-skeleton__invite-actions">
            <span class="users-settings-skeleton__btn" />
            <span class="users-settings-skeleton__btn users-settings-skeleton__btn--danger" />
          </div>
        </div>
      </div>
    </div>

    <div
      class="users-settings-skeleton__accordion"
      :class="{ 'users-settings-skeleton__accordion--open': openMembers }"
    >
      <div class="users-settings-skeleton__summary">
        <span class="users-settings-skeleton__line users-settings-skeleton__line--summary" />
      </div>
      <div v-if="openMembers" class="users-settings-skeleton__body">
        <div class="users-settings-skeleton__toolbar">
          <span class="users-settings-skeleton__line users-settings-skeleton__line--count" />
          <span class="users-settings-skeleton__btn users-settings-skeleton__btn--primary" />
        </div>

        <div class="users-settings-skeleton__table-head">
          <span class="users-settings-skeleton__th" />
          <span class="users-settings-skeleton__th" />
          <span class="users-settings-skeleton__th" />
          <span class="users-settings-skeleton__th users-settings-skeleton__th--narrow" />
          <span class="users-settings-skeleton__th users-settings-skeleton__th--actions" />
        </div>

        <div
          v-for="rowIndex in memberRows"
          :key="rowIndex"
          class="users-settings-skeleton__member-row"
        >
          <div class="users-settings-skeleton__member-name">
            <span class="users-settings-skeleton__avatar" />
            <span class="users-settings-skeleton__line users-settings-skeleton__line--name" />
          </div>
          <span class="users-settings-skeleton__line users-settings-skeleton__line--email-cell" />
          <span class="users-settings-skeleton__line users-settings-skeleton__line--role" />
          <span class="users-settings-skeleton__star" />
          <div class="users-settings-skeleton__member-actions">
            <span class="users-settings-skeleton__btn users-settings-skeleton__btn--small" />
            <span class="users-settings-skeleton__icon-btn" />
          </div>
        </div>
      </div>
    </div>

    <p v-if="message" class="users-settings-skeleton__message">{{ message }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    embedded?: boolean
    isGrossanlass?: boolean | null
    openMembers?: boolean
    message?: string
  }>(),
  {
    embedded: false,
    isGrossanlass: null,
    openMembers: true,
    message: '',
  },
)

defineOptions({ name: 'UsersSettingsSkeleton' })

/** Grossanlass-Details + optionale Einladungen — typisches Admin-Modal. */
const showInvitesSkeleton = computed(() => props.embedded)

const memberRows = computed(() => (props.embedded ? 1 : 2))
</script>

<style scoped>
.users-settings-skeleton {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.users-settings-skeleton--embedded {
  padding: 2px 0 4px;
}

.users-settings-skeleton__accordion {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fafafa;
  overflow: hidden;
}

.users-settings-skeleton__summary {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  min-height: 42px;
}

.users-settings-skeleton__body {
  padding: 12px 14px 14px;
  background: #fff;
  border-top: 1px solid #e5e7eb;
}

.users-settings-skeleton__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.users-settings-skeleton__invite {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fafafa;
}

.users-settings-skeleton__invite-main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.users-settings-skeleton__invite-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.users-settings-skeleton__table-head {
  display: grid;
  grid-template-columns: minmax(140px, 1.4fr) minmax(120px, 1.2fr) minmax(100px, 1fr) 52px 88px;
  gap: 8px;
  padding: 8px 10px;
  background: #f8fafc;
  border-radius: 8px 8px 0 0;
  border: 1px solid #e5e7eb;
  border-bottom: none;
}

.users-settings-skeleton__member-row {
  display: grid;
  grid-template-columns: minmax(140px, 1.4fr) minmax(120px, 1.2fr) minmax(100px, 1fr) 52px 88px;
  gap: 8px;
  align-items: center;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-top: none;
  background: #fff;
}

.users-settings-skeleton__member-row:last-child {
  border-radius: 0 0 8px 8px;
}

.users-settings-skeleton__member-name {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.users-settings-skeleton__member-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

.users-settings-skeleton__line,
.users-settings-skeleton__th,
.users-settings-skeleton__btn,
.users-settings-skeleton__avatar,
.users-settings-skeleton__star,
.users-settings-skeleton__icon-btn {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: users-settings-skeleton-shimmer 1.2s ease-in-out infinite;
  border-radius: 6px;
}

.users-settings-skeleton__line--summary {
  height: 14px;
  width: 38%;
  max-width: 220px;
}

.users-settings-skeleton__line--count {
  height: 16px;
  width: 72px;
}

.users-settings-skeleton__line--email {
  height: 14px;
  width: 160px;
}

.users-settings-skeleton__line--badge {
  height: 22px;
  width: 200px;
  border-radius: 999px;
}

.users-settings-skeleton__line--badge-short {
  height: 14px;
  width: 72px;
}

.users-settings-skeleton__line--name {
  height: 14px;
  width: 80px;
  flex: 1;
  max-width: 120px;
}

.users-settings-skeleton__line--email-cell {
  height: 14px;
  width: 90%;
  max-width: 180px;
}

.users-settings-skeleton__line--role {
  height: 24px;
  width: 100px;
  border-radius: 999px;
}

.users-settings-skeleton__th {
  height: 10px;
  width: 70%;
}

.users-settings-skeleton__th--narrow {
  width: 50%;
  justify-self: center;
}

.users-settings-skeleton__th--actions {
  width: 60%;
  justify-self: end;
}

.users-settings-skeleton__btn {
  height: 32px;
  width: 120px;
  border-radius: 8px;
}

.users-settings-skeleton__btn--primary {
  width: 148px;
  height: 34px;
}

.users-settings-skeleton__btn--small {
  width: 64px;
  height: 30px;
}

.users-settings-skeleton__btn--danger {
  width: 72px;
}

.users-settings-skeleton__avatar {
  width: 36px;
  height: 36px;
  border-radius: 999px;
  flex-shrink: 0;
}

.users-settings-skeleton__star {
  width: 18px;
  height: 18px;
  border-radius: 4px;
  justify-self: center;
}

.users-settings-skeleton__icon-btn {
  width: 30px;
  height: 30px;
  border-radius: 6px;
}

.users-settings-skeleton__message {
  margin: 4px 0 0;
  text-align: center;
  font-size: 0.85rem;
  color: #94a3b8;
}

@keyframes users-settings-skeleton-shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

@media (max-width: 720px) {
  .users-settings-skeleton__table-head,
  .users-settings-skeleton__member-row {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .users-settings-skeleton__table-head {
    display: none;
  }
}
</style>
