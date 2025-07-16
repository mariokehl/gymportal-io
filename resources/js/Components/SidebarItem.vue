<template>
  <component
    :is="getComponentType"
    :href="getHref"
    @click="handleClick"
    :disabled="disabled"
    :class="[
      'w-full flex items-center px-4 py-3 text-sm font-medium text-left transition-colors',
      {
        'bg-blue-50 text-blue-600 border-r-2 border-blue-600': active && !disabled,
        'text-gray-600 hover:bg-gray-50 hover:text-gray-900': !active && !disabled,
        'text-gray-400 cursor-not-allowed opacity-50': disabled,
      }
    ]"
  >
    <component
      :is="icon"
      :class="[
        'w-5 h-5 mr-3',
        {
          'text-blue-600': active && !disabled,
          'text-gray-600': !active && !disabled,
          'text-gray-300': disabled,
        }
      ]"
    />

    <span class="flex-1">{{ label }}</span>

    <!-- Lock Icon für disabled Items -->
    <svg
      v-if="disabled"
      class="h-4 w-4 text-gray-300 ml-2"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
    >
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
    </svg>
  </component>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
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
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['click'])

const getComponentType = computed(() => {
  if (props.disabled) return 'div'
  if (props.href) return Link
  return 'button'
})

const getHref = computed(() => {
  if (props.disabled) return undefined
  return props.href
})

const handleClick = (event) => {
  if (props.disabled) {
    event.preventDefault()
    return
  }

  emit('click', event)
}
</script>
