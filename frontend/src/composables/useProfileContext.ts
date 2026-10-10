import { inject, ref, type InjectionKey } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { profileFallbackPath, profileFromQuery } from '@/utils/profileReturn'

export type ProfileTab = 'basics' | 'security' | 'emails' | 'identities' | 'departments'

/** Tab slug, route name and i18n key suffix of the five profile areas (same order in page and modal). */
export const PROFILE_TABS: readonly { tab: ProfileTab; routeName: string }[] = [
  { tab: 'basics', routeName: 'Profile' },
  { tab: 'security', routeName: 'ProfileSecurity' },
  { tab: 'emails', routeName: 'ProfileEmails' },
  { tab: 'identities', routeName: 'ProfileIdentities' },
  { tab: 'departments', routeName: 'ProfileDepartments' },
]

export function profileTabForRouteName(name: unknown): ProfileTab {
  return PROFILE_TABS.find((t) => t.routeName === name)?.tab ?? 'basics'
}

/** How the profile areas talk to whatever presents them: the routed page or the modal over the current page. */
export interface ProfileContext {
  mode: 'page' | 'modal'
  /** Switch to another profile area. */
  openTab: (tab: ProfileTab) => void
  /** Leave the profile: back to the page it was opened from (page) or close the modal. */
  leave: () => void | Promise<void>
  /** Areas with unsaved input report it, so the modal can confirm before closing. */
  setDirty: (dirty: boolean) => void
}

export const PROFILE_CONTEXT_KEY: InjectionKey<ProfileContext> = Symbol('profileContext')

/** Context of the surrounding container; without one the area behaves like the routed page. */
export function useProfileContext(): ProfileContext {
  const provided = inject(PROFILE_CONTEXT_KEY, null)
  if (provided) return provided
  const router = useRouter()
  const authStore = useAuthStore()
  return {
    mode: 'page',
    openTab: (tab) => {
      const routeName = PROFILE_TABS.find((t) => t.tab === tab)?.routeName ?? 'Profile'
      void router.push({ name: routeName, query: router.currentRoute.value.query })
    },
    leave: async () => {
      await router.push(profileFromQuery(router.currentRoute.value.query) ?? profileFallbackPath(authStore.activeDepartmentId))
    },
    setDirty: () => {},
  }
}

// --- Modal state (shared, so the header menu and the layout host agree) ---------------------------------------
const modalOpen = ref(false)
const modalTab = ref<ProfileTab>('basics')
const modalDirty = ref(false)

export function useProfileModal() {
  return {
    isOpen: modalOpen,
    tab: modalTab,
    dirty: modalDirty,
    open(tab: ProfileTab = 'basics') {
      modalTab.value = tab
      modalDirty.value = false
      modalOpen.value = true
    },
    close() {
      modalOpen.value = false
      modalDirty.value = false
    },
  }
}
