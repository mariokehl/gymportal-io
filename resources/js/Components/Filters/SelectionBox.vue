<!-- Purely visual: the wrapping button carries role="checkbox", the checked
     state and the accessible name. -->
<template>
  <span
    :class="[
      'flex h-4 w-4 items-center justify-center rounded border',
      state === 'none' ? 'border-line-strong bg-surface' : 'border-brand bg-brand text-white'
    ]"
    aria-hidden="true"
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
