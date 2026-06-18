import { computed, onUnmounted, ref, watch, type Ref } from 'vue'
import {
  getReplenishmentWishes,
  patchReplenishmentWish,
  postFulfillReplenishmentWish,
  postReplenishmentWish,
  type ActivityReplenishmentWish,
  type ReplenishmentWishAvailabilitySnapshot,
} from '@/api/activityReplenishmentWishes'

export function useReplenishmentWishes(options: {
  activityId: Ref<string>
  canManageMaterials: Ref<boolean>
  onFulfilled?: () => void | Promise<void>
}) {
  const wishes = ref<ActivityReplenishmentWish[]>([])
  const loading = ref(false)
  const submitting = ref(false)
  const error = ref<string | null>(null)

  const pendingWishes = computed(() => wishes.value.filter((w) => w.status === 'pending'))
  const myWishes = computed(() => wishes.value)

  async function reload(): Promise<void> {
    if (!options.activityId.value) return
    loading.value = true
    error.value = null
    try {
      wishes.value = await getReplenishmentWishes(options.activityId.value)
    } catch (e) {
      error.value = e instanceof Error ? e.message : String(e)
      wishes.value = []
    } finally {
      loading.value = false
    }
  }

  async function submitWish(body: {
    materialItemId: string
    quantity: number
    notes?: string | null
    availabilitySnapshot?: ReplenishmentWishAvailabilitySnapshot | null
  }): Promise<ActivityReplenishmentWish | null> {
    if (!options.activityId.value) return null
    submitting.value = true
    try {
      const created = await postReplenishmentWish(options.activityId.value, {
        material_item_id: body.materialItemId,
        quantity: body.quantity,
        notes: body.notes ?? null,
        availability_snapshot: body.availabilitySnapshot ?? null,
      })
      wishes.value = [created, ...wishes.value.filter((w) => w.id !== created.id)]
      return created
    } finally {
      submitting.value = false
    }
  }

  async function fulfillWish(wishId: string): Promise<void> {
    if (!options.activityId.value) return
    submitting.value = true
    try {
      const updated = await postFulfillReplenishmentWish(options.activityId.value, wishId)
      wishes.value = wishes.value.map((w) => (w.id === wishId ? updated : w))
      await options.onFulfilled?.()
    } finally {
      submitting.value = false
    }
  }

  async function rejectWish(wishId: string, reason?: string): Promise<void> {
    if (!options.activityId.value) return
    submitting.value = true
    try {
      const updated = await patchReplenishmentWish(options.activityId.value, wishId, {
        action: 'reject',
        reason,
      })
      wishes.value = wishes.value.map((w) => (w.id === wishId ? updated : w))
    } finally {
      submitting.value = false
    }
  }

  async function cancelWish(wishId: string): Promise<void> {
    if (!options.activityId.value) return
    submitting.value = true
    try {
      const updated = await patchReplenishmentWish(options.activityId.value, wishId, {
        action: 'cancel',
      })
      wishes.value = wishes.value.map((w) => (w.id === wishId ? updated : w))
    } finally {
      submitting.value = false
    }
  }

  watch(options.activityId, () => {
    void reload()
  }, { immediate: true })

  return {
    wishes,
    pendingWishes,
    myWishes,
    loading,
    submitting,
    error,
    reload,
    submitWish,
    fulfillWish,
    rejectWish,
    cancelWish,
  }
}
