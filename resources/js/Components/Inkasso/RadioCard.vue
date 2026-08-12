<template>
  <div
    :class="selected ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-white'"
    class="flex gap-3 px-4 py-3.5 border rounded-lg cursor-pointer transition-colors"
    @click="$emit('select')"
  >
    <span
      :class="selected ? 'border-indigo-600' : 'border-gray-300'"
      class="w-[18px] h-[18px] rounded-full border-2 flex-none mt-0.5 flex items-center justify-center"
    >
      <span v-if="selected" class="w-2 h-2 rounded-full bg-indigo-600" />
    </span>
    <div>
      <div class="text-sm font-semibold text-gray-900">{{ title }}</div>
      <div class="text-sm text-gray-500 mt-0.5">{{ description }}</div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  selected: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: '' },
})

defineEmits(['select'])
</script>
