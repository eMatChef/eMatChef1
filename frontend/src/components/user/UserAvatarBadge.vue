<template>
  <span
    ref="rootRef"
    class="user-avatar-badge"
    :class="[
      `user-avatar-badge--${variant}`,
      `user-avatar-badge--${size}`,
      { 'user-avatar-badge--leader': showLeaderStar && variant === 'badge' },
    ]"
    @mouseenter="showFloatingTooltip"
    @mouseleave="hideFloatingTooltip"
    @focusin="showFloatingTooltip"
    @focusout="hideFloatingTooltip"
  >
    <span class="user-avatar-badge__avatar" :style="avatarStyle">
      <span v-if="showLeaderStar && variant === 'badge'" class="user-avatar-badge__star" aria-hidden="true">★</span>
      {{ initials }}
    </span>
    <Teleport to="body">
      <span
        v-if="floatingVisible && showTooltip && tooltip"
        class="user-avatar-badge__tooltip user-avatar-badge__tooltip--floating"
        role="tooltip"
        :style="floatingStyle"
      >
        <span v-if="tooltip.line1" class="user-avatar-badge__tooltip-line">
          <span class="user-avatar-badge__tooltip-label">{{ tooltip.line1.label }}:</span>
          {{ tooltip.line1.value }}
        </span>
        <span v-if="tooltip.line2" class="user-avatar-badge__tooltip-line user-avatar-badge__tooltip-line--nickname">
          <span class="user-avatar-badge__tooltip-label">{{ tooltip.line2.label }}:</span>
          {{ tooltip.line2.value }}
        </span>
      </span>
    </Teleport>
  </span>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getMemberHoverTooltip,
  getUserAvatarInitials,
  getUserAvatarStyle,
  type UserAvatarFields,
} from '@/utils/userAvatar'

export type UserAvatarVariant = 'badge' | 'profile'
export type UserAvatarSize = 'sm' | 'md' | 'lg'

const props = withDefaults(
  defineProps<{
    user: UserAvatarFields
    /** badge = abgerundetes Quadrat + Rahmen; profile = rund */
    variant?: UserAvatarVariant
    size?: UserAvatarSize
    showLeaderStar?: boolean
    showTooltip?: boolean
  }>(),
  {
    variant: 'badge',
    size: 'sm',
    showLeaderStar: false,
    showTooltip: true,
  }
)

const { t } = useI18n()

const rootRef = ref<HTMLElement | null>(null)
const floatingVisible = ref(false)
const floatingStyle = ref<Record<string, string>>({})

const avatarStyle = computed(() => getUserAvatarStyle(props.user))
const initials = computed(() => getUserAvatarInitials(props.user))

const tooltip = computed(() => {
  if (!props.showTooltip) return null
  return getMemberHoverTooltip(props.user, {
    name: t('common.userAvatar.hoverName'),
    nickname: t('common.userAvatar.hoverNickname'),
  })
})

let repositionHandler: (() => void) | null = null

function updateFloatingPosition() {
  const el = rootRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  floatingStyle.value = {
    position: 'fixed',
    left: `${rect.left + rect.width / 2}px`,
    top: `${rect.top - 8}px`,
    transform: 'translate(-50%, -100%)',
    zIndex: '10000',
  }
}

function showFloatingTooltip() {
  if (!props.showTooltip || !tooltip.value) return
  updateFloatingPosition()
  floatingVisible.value = true
  repositionHandler = () => updateFloatingPosition()
  window.addEventListener('scroll', repositionHandler, true)
  window.addEventListener('resize', repositionHandler)
}

function hideFloatingTooltip() {
  floatingVisible.value = false
  if (repositionHandler) {
    window.removeEventListener('scroll', repositionHandler, true)
    window.removeEventListener('resize', repositionHandler)
    repositionHandler = null
  }
}

onBeforeUnmount(() => hideFloatingTooltip())
</script>

<style src="@/styles/components/user-avatar-badge.css"></style>
