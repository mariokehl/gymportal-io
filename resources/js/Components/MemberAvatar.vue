<template>
  <div class="relative inline-flex">
    <!-- Avatar with initials; white text needs ≥4.5:1, hence orange-700 / brand -->
    <div
      :class="[
        'rounded-full text-white flex items-center justify-center font-semibold',
        isGuest ? 'bg-guest-solid' : 'bg-brand',
        sizeClasses
      ]"
    >
      {{ initials }}
    </div>

    <!-- Verifiziert-Haken -->
    <span
      v-if="ageVerified"
      :class="[
        'absolute flex items-center justify-center bg-info-solid rounded-full border-2 border-white',
        checkmarkSizeClasses
      ]"
      :title="verifiedTooltip"
    >
      <Check :class="checkIconClasses" />
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Check } from '@lucide/vue'

const props = defineProps({
  initials: {
    type: String,
    required: true
  },
  ageVerified: {
    type: Boolean,
    default: false
  },
  verifiedAt: {
    type: String,
    default: null
  },
  isGuest: {
    type: Boolean,
    default: false
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value)
  }
})

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'w-8 h-8 text-sm',
    md: 'w-10 h-10 text-sm',
    lg: 'w-12 h-12 text-base',
    xl: 'w-16 h-16 text-xl'
  }
  return sizes[props.size]
})

const checkmarkSizeClasses = computed(() => {
  const sizes = {
    sm: '-bottom-0.5 -right-0.5 w-3.5 h-3.5',
    md: '-bottom-0.5 -right-0.5 w-4 h-4',
    lg: '-bottom-1 -right-1 w-5 h-5',
    xl: '-bottom-1 -right-1 w-6 h-6'
  }
  return sizes[props.size]
})

const checkIconClasses = computed(() => {
  const sizes = {
    sm: 'w-2 h-2 text-white',
    md: 'w-2.5 h-2.5 text-white',
    lg: 'w-3 h-3 text-white',
    xl: 'w-3.5 h-3.5 text-white'
  }
  return sizes[props.size]
})

const verifiedTooltip = computed(() => {
  if (!props.ageVerified) return ''
  if (props.verifiedAt) {
    const date = new Date(props.verifiedAt)
    return `Alter verifiziert am ${date.toLocaleDateString('de-DE')}`
  }
  return 'Alter verifiziert'
})
</script>
