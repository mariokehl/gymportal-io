<template>
  <div
    :class="[
      'flex items-center px-4 py-3 cursor-pointer text-sm transition-colors duration-200',
      active
        ? 'bg-blue-50 text-blue-600 border-r-4 border-blue-600'
        : 'text-gray-600 hover:bg-gray-100'
    ]"
    @click="$emit('click')"
  >
    <div class="mr-3">
      <component :is="icon" class="w-5 h-5" />
    </div>
    <span class="font-medium">{{ label }}</span>
  </div>
</template>

<script setup>
defineProps({
  icon: {
    type: Object,
    required: true
  },
  label: {
    type: String,
    required: true
  },
  active: {
    type: Boolean,
    default: false
  }
})

defineEmits(['click'])
</script>
