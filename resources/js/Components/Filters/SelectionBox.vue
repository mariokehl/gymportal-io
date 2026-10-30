<template>
  <span
    :class="[
      'flex h-4 w-4 items-center justify-center rounded border',
      state === 'none' ? 'border-gray-400 bg-white' : 'border-indigo-600 bg-indigo-600 text-white'
    ]"
    :aria-checked="state === 'some' ? 'mixed' : state === 'all'"
    role="checkbox"
  >
    <Check v-if="state === 'all'" class="h-3 w-3" :stroke-width="3" />
    <Minus v-else-if="state === 'some'" class="h-3 w-3" :stroke-width="3" />
  </span>
</template>

<script setup>
import { Check, Minus } from '@lucide/vue'

defineProps({
  // 'none' | 'some' | 'all'
  state: { type: String, default: 'none' },
})
</script>
