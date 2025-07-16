<template>
  <component
    :is="href ? Link : 'button'"
    :href="href"
    @click="handleClick"
    :class="[
      'w-full flex items-center px-4 py-3 text-sm font-medium text-left transition-colors',
      active
        ? 'bg-blue-50 text-blue-600 border-r-2 border-blue-600'
        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
    ]"
  >
    <component :is="icon" class="w-5 h-5 mr-3" />
    {{ label }}
  </component>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  icon: {
    type: [Object, Function],
    required: true
  },
  label: {
    type: String,
    required: true
  },
  active: {
    type: Boolean,
    default: false
  },
  href: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['click'])

const handleClick = () => {
  emit('click')
}
</script>
