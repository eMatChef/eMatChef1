<template>
  <svg
    class="device-thumb"
    viewBox="0 0 88 64"
    role="img"
    :aria-label="label"
  >
    <g v-if="family === 'brother_ql'">
      <rect class="body" x="22" y="14" width="52" height="38" rx="4" />
      <rect class="panel" x="48" y="18" width="22" height="10" rx="1.5" />
      <circle class="roll" cx="22" cy="33" r="14" />
      <circle class="roll-core" cx="22" cy="33" r="5" />
      <rect class="tape" x="36" y="40" width="46" height="6" rx="1" />
      <rect class="slot" x="68" y="38" width="6" height="10" rx="1" />
    </g>
    <g v-else-if="family === 'tsc_desktop'">
      <rect class="body" x="10" y="18" width="68" height="34" rx="4" />
      <rect class="roll" x="18" y="8" width="28" height="18" rx="9" />
      <rect class="panel" x="52" y="22" width="20" height="10" rx="1.5" />
      <rect class="tape" x="14" y="44" width="62" height="6" rx="1" />
      <rect class="slot" x="70" y="42" width="8" height="10" rx="1" />
    </g>
    <g v-else>
      <rect class="body" x="14" y="10" width="60" height="36" rx="4" />
      <rect class="panel" x="46" y="14" width="22" height="10" rx="1.5" />
      <rect class="tray" x="20" y="40" width="48" height="8" rx="1" />
      <rect class="paper" x="28" y="4" width="32" height="18" rx="1" />
      <rect class="paper" x="32" y="46" width="24" height="14" rx="1" />
    </g>
  </svg>
</template>

<script setup lang="ts">
defineProps<{
  family?: string
  label?: string
}>()
</script>

<style scoped>
.device-thumb {
  display: block;
  width: 100%;
  height: auto;
}
.body,
.tray {
  fill: #cbd5e1;
  stroke: #334155;
  stroke-width: 1.6;
}
.panel {
  fill: #0f172a;
  stroke: none;
}
.roll {
  fill: #94a3b8;
  stroke: #334155;
  stroke-width: 1.6;
}
.roll-core {
  fill: #f8fafc;
  stroke: #334155;
  stroke-width: 1.2;
}
.tape,
.paper {
  fill: #fff;
  stroke: #334155;
  stroke-width: 1.2;
}
.slot {
  fill: #1e293b;
}
</style>
