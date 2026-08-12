<template>
  <span
    :class="classes"
    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap"
  >
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  // Colour name as delivered by the backend status accessors.
  color: { type: String, default: 'gray' },
})

// Tailwind needs the full class names in the source, so they are listed
// explicitly instead of being interpolated.
const TONES = {
  green: 'bg-green-100 text-green-700',
  gray: 'bg-gray-100 text-gray-700',
  yellow: 'bg-yellow-100 text-yellow-700',
  orange: 'bg-orange-100 text-orange-700',
  red: 'bg-red-100 text-red-700',
  indigo: 'bg-indigo-50 text-indigo-800',
  dark: 'bg-gray-900 text-white',
}

const classes = computed(() => TONES[props.color] ?? TONES.gray)
</script>
