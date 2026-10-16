<template>
  <EDialog v-model="open" :max-width="560" :title="t('display.profile.title', { name: device?.name ?? '' })" :retain-focus="false">
    <p class="muted">{{ t('display.profile.hint') }}</p>

    <p class="label label--tight mt-3">{{ t('display.profile.resolution') }}</p>
    <div class="profile-grid">
      <ETextField v-model="form.pixel_width" type="number" min="320" :label="t('display.profile.pixelWidth')" hide-details />
      <ETextField v-model="form.pixel_height" type="number" min="240" :label="t('display.profile.pixelHeight')" hide-details />
    </div>
    <p class="muted field-hint">{{ t('display.profile.resolutionHint') }}</p>

    <p class="label label--tight mt-3">{{ t('display.profile.physical') }}</p>
    <div class="profile-grid">
      <ETextField v-model="form.width_cm" type="number" min="5" :label="t('display.profile.widthCm')" hide-details />
      <ETextField v-model="form.height_cm" type="number" min="5" :label="t('display.profile.heightCm')" hide-details />
    </div>

    <div class="profile-grid mt-3">
      <ESelect v-model="form.aspect_ratio" :items="aspectItems" :label="t('display.profile.aspect')" hide-details />
      <ETextField v-model="form.viewing_distance_m" type="number" min="0.3" step="0.5" :label="t('display.profile.distance')" hide-details />
    </div>
    <div class="profile-grid mt-3">
      <ESelect v-model="form.font_profile" :items="fontItems" :label="t('display.profile.font')" hide-details />
      <ESelect v-model="form.variant_id" :items="variantItems" :label="t('display.profile.variant')" hide-details />
    </div>
    <p v-if="derivedAspect" class="muted field-hint">{{ t('display.profile.derivedAspect', { aspect: derivedAspect }) }}</p>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton variant="primary" size="small" :loading="saving" @click="save">{{ t('common.save') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import { updateDisplayDevice, type DisplayDeviceProfile, type DisplayDeviceRow } from '@/api/displayDevice'
import { getLayoutState } from '@/api/displayLayout'

/**
 * Bildschirmprofil eines Geräts. Die Pixelauflösung wird nie aus der physischen Grösse abgeleitet; die Layoutvariante
 * ist „automatisch“ (nach Seitenverhältnis) oder fest gewählt.
 */
const props = defineProps<{ modelValue: boolean; departmentId: string; device: DisplayDeviceRow | null; layoutEnabled: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const open = computed({ get: () => props.modelValue, set: (v: boolean) => emit('update:modelValue', v) })
const saving = ref(false)
const variants = ref<{ id: string; name: string }[]>([])

const form = reactive({
  pixel_width: '' as string | number,
  pixel_height: '' as string | number,
  width_cm: '' as string | number,
  height_cm: '' as string | number,
  aspect_ratio: '' as string,
  viewing_distance_m: '' as string | number,
  font_profile: 'auto' as DisplayDeviceProfile['font_profile'],
  variant_id: '' as string,
})

const ASPECTS = ['16:9', '16:10', '4:3', '3:2', '21:9', '9:16']
const aspectItems = computed(() => [{ value: '', title: t('display.profile.aspectAuto') }, ...ASPECTS.map((a) => ({ value: a, title: a }))])
const fontItems = computed(() => (['auto', 'small', 'normal', 'large', 'xlarge'] as const).map((v) => ({ value: v, title: t(`display.profile.fontProfile.${v}`) })))
const variantItems = computed(() => [{ value: '', title: t('display.profile.variantAuto') }, ...variants.value.map((v) => ({ value: v.id, title: v.name }))])

const derivedAspect = computed(() => {
  const w = Number(form.pixel_width)
  const h = Number(form.pixel_height)
  if (form.aspect_ratio || !w || !h) return ''
  return (w / h).toFixed(2)
})

watch(open, async (isOpen) => {
  if (!isOpen || !props.device) return
  const p = props.device.profile
  form.pixel_width = p?.pixel_width ?? ''
  form.pixel_height = p?.pixel_height ?? ''
  form.width_cm = p?.width_cm ?? ''
  form.height_cm = p?.height_cm ?? ''
  form.aspect_ratio = p?.aspect_ratio ?? ''
  form.viewing_distance_m = p?.viewing_distance_m ?? ''
  form.font_profile = p?.font_profile ?? 'auto'
  form.variant_id = p?.variant_id ?? ''
  variants.value = []
  if (props.layoutEnabled) {
    try {
      const state = await getLayoutState(props.departmentId, props.device.screen_id)
      variants.value = (state.draft?.variants ?? []).map((v) => ({ id: v.id, name: v.name }))
    } catch {
      variants.value = []
    }
  }
})

const num = (v: string | number): number | null => (v === '' || v === null ? null : Number(v))

async function save() {
  if (!props.device) return
  saving.value = true
  try {
    await updateDisplayDevice(props.departmentId, props.device.id, {
      profile: {
        pixel_width: num(form.pixel_width),
        pixel_height: num(form.pixel_height),
        width_cm: num(form.width_cm),
        height_cm: num(form.height_cm),
        aspect_ratio: form.aspect_ratio || null,
        viewing_distance_m: num(form.viewing_distance_m),
        font_profile: form.font_profile,
        variant_id: form.variant_id || null,
      },
    })
    toast.success(t('display.profile.toastSaved'))
    emit('saved')
    open.value = false
  } catch (err) {
    toast.error((err as { response?: { data?: { error?: string } } })?.response?.data?.error || t('display.devices.error'))
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.profile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.field-hint { font-size: 0.8rem; margin: 4px 0 0; }
.muted { color: #6b7280; }
</style>
