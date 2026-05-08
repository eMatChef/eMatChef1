import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Löst im TopHeader ein erneutes Laden der Benachrichtigungen aus (Glocken-Badge).
 */
export const useHeaderNotificationsStore = defineStore('headerNotifications', () => {
  const refreshNonce = ref(0)

  function requestRefresh() {
    refreshNonce.value++
  }

  return { refreshNonce, requestRefresh }
})
