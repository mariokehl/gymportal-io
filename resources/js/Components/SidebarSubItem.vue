<template>
  <!-- The label lines up with the parent's label: row padding (8) + icon
       column (18) + gap (10). -->
  <Link
    :href="href"
    :aria-current="current ? 'page' : undefined"
    :class="[
      'flex items-center rounded-md h-8 pointer-coarse:h-11 pl-9 pr-2 text-compact font-medium transition-colors',
      current
        ? 'bg-brand-subtle text-brand-ink'
        : 'text-ink-secondary hover:bg-fill hover:text-ink'
    ]"
  >
    {{ label }}
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  label: {
    type: String,
    required: true
  },
  href: {
    type: String,
    required: true
  },
  current: {
    type: Boolean,
    default: false
  }
})
</script>
